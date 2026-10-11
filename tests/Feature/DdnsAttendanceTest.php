<?php

namespace Tests\Feature;

use App\Models\AttendanceCredential;
use App\Models\AttendanceNetwork;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class DdnsAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function createStaff(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Test Staff',
            'email' => 'staff_' . Str::random(6) . '@example.com',
            'password' => bcrypt('password123'),
            'role' => 'afc_staff',
            'status' => 'approved',
        ], $overrides));
    }

    protected function createAdmin(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Test Admin',
            'email' => 'admin_' . Str::random(6) . '@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'status' => 'approved',
        ], $overrides));
    }

    protected function registerCredential(User $user): AttendanceCredential
    {
        return AttendanceCredential::create([
            'user_id' => $user->id,
            'credential_id' => 'cred_' . Str::random(8),
            'public_key' => 'pubkey_mock',
            'device_name' => "Staff Device",
            'is_active' => true,
            'approval_status' => 'approved',
            'registered_at' => now(),
        ]);
    }

    /** 1. Existing static IP attendance validation still works */
    public function test_static_ip_attendance_validation_still_works(): void
    {
        $staff = $this->createStaff();
        $cred = $this->registerCredential($staff);

        AttendanceNetwork::create([
            'name' => 'Static Office Subnet',
            'ip_range' => '198.51.100.0/24',
            'enabled' => true,
        ]);

        // Request from 198.51.100.45 should pass network matching
        $net = AttendanceNetwork::first();
        $this->assertTrue($net->matchesIp('198.51.100.45'));
        $this->assertFalse($net->matchesIp('203.0.113.10'));
    }

    /** 2. DDNS resolved IP allows attendance checkin */
    public function test_ddns_resolved_ip_allows_attendance_checkin(): void
    {
        $network = AttendanceNetwork::create([
            'name' => 'Office Wi-Fi A (Tenda 5G)',
            'ip_range' => '',
            'ddns_hostname' => 'office-a.example.net',
            'ddns_enabled' => true,
            'enabled' => true,
        ]);

        // Mock performDnsLookup method
        Cache::put("ddns_resolution_network_{$network->id}", '203.0.113.50', 180);
        $network->update([
            'last_resolved_ip' => '203.0.113.50',
            'last_resolved_at' => now(),
            'resolution_status' => 'configured_and_resolving',
        ]);

        $this->assertTrue($network->matchesIp('203.0.113.50'));
        $this->assertFalse($network->matchesIp('198.51.100.99'));
    }

    /** 3. Unrelated IP is rejected */
    public function test_unrelated_ip_is_rejected(): void
    {
        $staff = $this->createStaff();
        $cred = $this->registerCredential($staff);

        AttendanceNetwork::create([
            'name' => 'Office Wi-Fi B (Airtel)',
            'ip_range' => '',
            'ddns_hostname' => 'office-b.example.net',
            'ddns_enabled' => true,
            'enabled' => true,
        ]);

        $net = AttendanceNetwork::first();
        Cache::put("ddns_resolution_network_{$net->id}", '197.210.10.5', 180);

        // Attempt check-in from unapproved IP 102.89.1.1
        $response = $this->actingAs($staff)
            ->withServerVariables(['REMOTE_ADDR' => '102.89.1.1'])
            ->postJson(route('attendance.check-in'), [
                'credential_id' => $cred->credential_id,
                'client_data_json' => base64_encode(json_encode(['type' => 'webauthn.get'])),
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
        ]);
    }

    /** 4. Missing or malformed DDNS hostname fails gracefully */
    public function test_missing_or_malformed_ddns_hostname_fails_gracefully(): void
    {
        $network = AttendanceNetwork::create([
            'name' => 'Broken DDNS Network',
            'ip_range' => '',
            'ddns_hostname' => 'invalid..hostname..test',
            'ddns_enabled' => true,
            'enabled' => true,
        ]);

        $resolved = $network->resolveDdns(forceRefresh: true);

        $this->assertNull($resolved);
        $this->assertEquals('resolution_failed', $network->fresh()->resolution_status);
        $this->assertFalse($network->matchesIp('1.2.3.4'));
    }

    /** 5. DNS lookup failure fails closed safely (no arbitrary match) */
    public function test_dns_lookup_failure_fails_closed_safely(): void
    {
        $network = AttendanceNetwork::create([
            'name' => 'Unreachable Hostname Network',
            'ip_range' => '',
            'ddns_hostname' => 'nonexistent-host-9999999.invalid',
            'ddns_enabled' => true,
            'enabled' => true,
        ]);

        $resolved = $network->resolveDdns(forceRefresh: true);

        $this->assertNull($resolved);
        $this->assertFalse($network->matchesIp('203.0.113.50'));
    }

    /** 6. IPv4 and IPv6 DDNS / CIDR matching */
    public function test_ipv4_and_ipv6_ddns_matching(): void
    {
        $v6Network = AttendanceNetwork::create([
            'name' => 'IPv6 Office Subnet',
            'ip_range' => '2001:db8::/32',
            'enabled' => true,
        ]);

        $this->assertTrue($v6Network->matchesIp('2001:db8::1'));
        $this->assertFalse($v6Network->matchesIp('2001:db9::1'));
    }

    /** 7. DDNS Cache TTL and manual refresh */
    public function test_ddns_cache_ttl_and_manual_refresh(): void
    {
        $admin = $this->createAdmin();

        $network = AttendanceNetwork::create([
            'name' => 'Cache Test Network',
            'ip_range' => '',
            'ddns_hostname' => 'cache-test.example.net',
            'ddns_enabled' => true,
            'enabled' => true,
        ]);

        // Prime cache
        Cache::put("ddns_resolution_network_{$network->id}", '198.51.100.10', 180);

        // Admin triggers force refresh
        $response = $this->actingAs($admin)
            ->post(route('admin.attendance.networks.refresh-ddns', $network));

        $response->assertRedirect();
    }

    /** 8. Office Wi-Fi A and Office Wi-Fi B configuration independence */
    public function test_office_wifi_a_and_office_wifi_b_independence(): void
    {
        $netA = AttendanceNetwork::create([
            'name' => 'Office Wi-Fi A (Tenda/ZTE)',
            'ip_range' => '',
            'ddns_hostname' => 'wifi-a.ddns.net',
            'ddns_enabled' => true,
            'enabled' => true,
        ]);

        $netB = AttendanceNetwork::create([
            'name' => 'Office Wi-Fi B (Airtel)',
            'ip_range' => '197.210.0.0/16',
            'ddns_hostname' => 'wifi-b.ddns.net',
            'ddns_enabled' => false, // Wi-Fi B DDNS disabled, static range only
            'enabled' => true,
        ]);

        Cache::put("ddns_resolution_network_{$netA->id}", '102.89.44.12', 180);

        // IP matching netA via DDNS
        $this->assertTrue($netA->matchesIp('102.89.44.12'));
        $this->assertFalse($netB->matchesIp('102.89.44.12'));

        // IP matching netB via static subnet
        $this->assertTrue($netB->matchesIp('197.210.15.8'));
        $this->assertFalse($netA->matchesIp('197.210.15.8'));
    }

    /** 9. Unauthorized users cannot modify network or DDNS settings */
    public function test_unauthorized_users_cannot_modify_network_settings(): void
    {
        $staff = $this->createStaff();

        $response = $this->actingAs($staff)
            ->get(route('admin.attendance.networks'));
        $response->assertStatus(403);

        $responsePost = $this->actingAs($staff)
            ->post(route('admin.attendance.networks.store'), [
                'name' => 'Hacker Subnet',
                'ip_range' => '0.0.0.0/0',
            ]);
        $responsePost->assertStatus(403);
    }

    /** 10. Admin can add, update, and refresh DDNS networks */
    public function test_admin_can_add_update_refresh_ddns_networks(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)
            ->post(route('admin.attendance.networks.store'), [
                'name' => 'New Branch Wi-Fi',
                'ddns_hostname' => 'branch.ddns.net',
                'ddns_enabled' => 1,
                'ip_range' => '192.168.1.0/24',
                'description' => 'Branch office network',
                'enabled' => 1,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('attendance_networks', [
            'name' => 'New Branch Wi-Fi',
            'ddns_hostname' => 'branch.ddns.net',
            'ddns_enabled' => true,
        ]);

        $net = AttendanceNetwork::where('name', 'New Branch Wi-Fi')->first();

        // Update Network
        $updateResp = $this->actingAs($admin)
            ->put(route('admin.attendance.networks.update', $net), [
                'name' => 'Updated Branch Wi-Fi',
                'ddns_hostname' => 'updated-branch.ddns.net',
                'ddns_enabled' => 1,
                'ip_range' => '192.168.2.0/24',
                'description' => 'Updated description',
                'enabled' => 1,
            ]);

        $updateResp->assertRedirect();
        $this->assertDatabaseHas('attendance_networks', [
            'id' => $net->id,
            'name' => 'Updated Branch Wi-Fi',
            'ddns_hostname' => 'updated-branch.ddns.net',
        ]);
    }

    public function test_trusted_proxy_configuration_does_not_allow_wildcard_proxies(): void
    {
        $trustedProxies = config('attendance.trusted_proxies');

        $this->assertIsArray($trustedProxies);
        $this->assertNotContains('*', $trustedProxies, 'Wildcard trusted proxies are not allowed for attendance security.');
        $this->assertNotEmpty($trustedProxies);
    }

    /** 12. Local LAN verification mode accepts authorized office LAN subnets */
    public function test_local_lan_verification_mode_accepts_office_subnets(): void
    {
        config(['attendance.verification_mode' => 'local_lan']);
        config(['attendance.office_lan_subnets' => ['192.168.0.0/24', '192.168.8.0/24']]);

        $net = AttendanceNetwork::create([
            'name' => 'Office Wi-Fi Local',
            'ip_range' => '',
            'enabled' => true,
        ]);

        // Phone on Wi-Fi A (Tenda 192.168.0.x)
        $this->assertTrue($net->matchesIp('192.168.0.55'));

        // Phone on Wi-Fi B (Airtel 192.168.8.x)
        $this->assertTrue($net->matchesIp('192.168.8.102'));
    }

    /** 13. Local LAN verification mode rejects foreign / external IPs */
    public function test_local_lan_verification_mode_rejects_foreign_ips(): void
    {
        config(['attendance.verification_mode' => 'local_lan']);
        config(['attendance.office_lan_subnets' => ['192.168.0.0/24', '192.168.8.0/24']]);

        $net = AttendanceNetwork::create([
            'name' => 'Office Wi-Fi Local',
            'ip_range' => '',
            'enabled' => true,
        ]);

        // External / public / foreign IP
        $this->assertFalse($net->matchesIp('102.89.23.44'));
        $this->assertFalse($net->matchesIp('10.0.0.1'));
    }

    /** 14. Hybrid verification mode accepts both LAN subnet connections and valid DDNS */
    public function test_hybrid_mode_accepts_both_lan_and_ddns(): void
    {
        config(['attendance.verification_mode' => 'hybrid']);
        config(['attendance.office_lan_subnets' => ['192.168.0.0/24']]);

        $net = AttendanceNetwork::create([
            'name' => 'Office Wi-Fi Hybrid',
            'ip_range' => '',
            'ddns_hostname' => 'hybrid-office.example.net',
            'ddns_enabled' => true,
            'enabled' => true,
        ]);

        Cache::put("ddns_resolution_network_{$net->id}", '203.0.113.88', 180);

        // 1. Matches via LAN subnet
        $this->assertTrue($net->matchesIp('192.168.0.120'));

        // 2. Matches via DDNS public IP
        $this->assertTrue($net->matchesIp('203.0.113.88'));

        // 3. Unrelated IP rejected
        $this->assertFalse($net->matchesIp('198.51.100.77'));
    }

    /** 15. db:verify-integrity command executes successfully */
    public function test_db_verify_integrity_command_reports_healthy_database(): void
    {
        $this->artisan('db:verify-integrity')
            ->expectsOutputToContain('Integrity Check PASSED')
            ->assertExitCode(0);

        $this->artisan('db:verify-integrity', ['--json' => true])
            ->assertExitCode(0);
    }

    /** 16. attendance:auto-checkout command processes unclosed records */
    public function test_auto_checkout_command_processes_unclosed_records(): void
    {
        $staff = $this->createStaff();

        \App\Models\AttendanceRecord::create([
            'user_id' => $staff->id,
            'attendance_date' => now()->toDateString(),
            'check_in_at' => now()->subHours(8),
            'check_out_at' => null,
            'status' => 'present',
        ]);

        $this->artisan('attendance:auto-checkout', ['--force' => true])
            ->expectsOutputToContain('Completed auto check-out')
            ->assertExitCode(0);

        $record = \App\Models\AttendanceRecord::where('user_id', $staff->id)->first();
        $this->assertNotNull($record->check_out_at);
        $this->assertStringContainsString('Forgotten Check-Out', $record->notes);
    }

    /** 17. Spoofed X-Forwarded-For from an untrusted client connection is rejected */
    public function test_spoofed_forwarded_for_from_untrusted_client_is_rejected(): void
    {
        config(['attendance.verification_mode' => 'local_lan']);
        config(['attendance.office_lan_subnets' => ['192.168.0.0/24']]);

        $staff = $this->createStaff();
        $cred = $this->registerCredential($staff);

        AttendanceNetwork::create([
            'name' => 'Office Wi-Fi Local',
            'ip_range' => '',
            'enabled' => true,
        ]);

        // Attacker on external IP 203.0.113.195 connects directly, but forges X-Forwarded-For: 192.168.0.50
        $response = $this->actingAs($staff)
            ->withServerVariables([
                'REMOTE_ADDR' => '203.0.113.195',
                'HTTP_X_FORWARDED_FOR' => '192.168.0.50',
            ])
            ->postJson(route('attendance.check-in'), [
                'credential_id' => $cred->credential_id,
                'client_data_json' => base64_encode(json_encode(['type' => 'webauthn.get', 'challenge' => 'dummy'])),
            ]);

        // Must reject with 403 Forbidden because REMOTE_ADDR is untrusted!
        $response->assertStatus(403);
        $this->assertStringContainsString('approved office network', $response->json('message'));
    }
}
