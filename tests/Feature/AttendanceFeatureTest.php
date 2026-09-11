<?php

namespace Tests\Feature;

use App\Models\AttendanceAuditLog;
use App\Models\AttendanceCredential;
use App\Models\AttendanceNetwork;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AttendanceFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function createUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'John Staff',
            'email' => 'staff_' . Str::random(6) . '@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user',
            'status' => 'approved',
        ], $overrides));
    }

    protected function createAdmin(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Admin Manager',
            'email' => 'admin_' . Str::random(6) . '@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'status' => 'approved',
        ], $overrides));
    }

    protected function createApprovedNetwork(string $ipRange = '127.0.0.1/32'): AttendanceNetwork
    {
        return AttendanceNetwork::create([
            'name' => 'Office HQ Wi-Fi',
            'ip_range' => $ipRange,
            'enabled' => true,
        ]);
    }

    protected function registerCredentialForUser(User $user, string $credentialId = 'cred_john_123'): AttendanceCredential
    {
        return AttendanceCredential::create([
            'user_id' => $user->id,
            'credential_id' => $credentialId,
            'public_key' => 'pubkey_sample_data',
            'device_name' => "John's Phone",
            'is_active' => true,
            'registered_at' => now(),
        ]);
    }

    /** 1. Staff can view attendance dashboard */
    public function test_staff_can_view_attendance_dashboard(): void
    {
        $staff = $this->createUser();

        $response = $this->actingAs($staff)->get(route('attendance.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Staff Attendance');
        $response->assertSee('John');
    }

    /** 2. Staff can register an attendance credential */
    public function test_staff_can_register_an_attendance_credential(): void
    {
        $staff = $this->createUser();

        $response = $this->actingAs($staff)->postJson(route('attendance.register-device'), [
            'credential_id' => 'cred_abc_123',
            'public_key' => 'pubkey_xyz',
            'device_name' => "Staff Mobile Phone",
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('attendance_credentials', [
            'user_id' => $staff->id,
            'credential_id' => 'cred_abc_123',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('attendance_audit_logs', [
            'event_type' => 'device_registration',
            'affected_user_id' => $staff->id,
        ]);
    }

    /** 3. Staff can check in with valid credentials and approved network */
    public function test_staff_can_check_in_with_valid_credentials_and_approved_network(): void
    {
        $staff = $this->createUser();
        $this->createApprovedNetwork('127.0.0.1/32');
        $cred = $this->registerCredentialForUser($staff, 'cred_john_passkey');

        $response = $this->actingAs($staff)
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson(route('attendance.check-in'), [
                'credential_id' => 'cred_john_passkey',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $staff->id,
            'check_in_credential_id' => $cred->id,
            'check_in_network_verified' => true,
        ]);
    }

    /** 4. Staff can check out with valid credentials and approved network */
    public function test_staff_can_check_out_with_valid_credentials_and_approved_network(): void
    {
        $staff = $this->createUser();
        $this->createApprovedNetwork('127.0.0.1/32');
        $cred = $this->registerCredentialForUser($staff, 'cred_john_passkey');

        // Initial check-in
        AttendanceRecord::create([
            'user_id' => $staff->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'check_in_at' => now()->subHours(4),
            'status' => 'present',
            'check_in_method' => 'webauthn',
            'check_in_credential_id' => $cred->id,
        ]);

        $response = $this->actingAs($staff)
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson(route('attendance.check-out'), [
                'credential_id' => 'cred_john_passkey',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $record = AttendanceRecord::where('user_id', $staff->id)->whereDate('attendance_date', Carbon::today())->first();
        $this->assertNotNull($record->check_out_at);
        $this->assertTrue($record->check_out_network_verified);
    }

    /** 5. Duplicate check-in is rejected */
    public function test_duplicate_check_in_is_rejected(): void
    {
        $staff = $this->createUser();
        $this->createApprovedNetwork('127.0.0.1/32');
        $cred = $this->registerCredentialForUser($staff, 'cred_john_passkey');

        // Already checked in today
        AttendanceRecord::create([
            'user_id' => $staff->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'check_in_at' => now()->subHour(),
            'status' => 'present',
            'check_in_credential_id' => $cred->id,
        ]);

        $response = $this->actingAs($staff)
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson(route('attendance.check-in'), [
                'credential_id' => 'cred_john_passkey',
            ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    /** 6. Check-in from an unapproved network is rejected */
    public function test_check_in_from_an_unapproved_network_is_rejected(): void
    {
        $staff = $this->createUser();
        $this->createApprovedNetwork('192.168.10.0/24');
        $this->registerCredentialForUser($staff, 'cred_john_passkey');

        $response = $this->actingAs($staff)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])
            ->postJson(route('attendance.check-in'), [
                'credential_id' => 'cred_john_passkey',
            ]);

        $response->assertStatus(403);
        $response->assertJson(['success' => false]);
    }

    /** 7. Check-in with another employee's login but an unregistered credential is rejected */
    public function test_check_in_with_another_employees_login_but_unregistered_credential_is_rejected(): void
    {
        $john = $this->createUser(['name' => 'John']);
        $peter = $this->createUser(['name' => 'Peter']);

        $this->createApprovedNetwork('127.0.0.1/32');
        $this->registerCredentialForUser($john, 'johns_phone_cred');

        $response = $this->actingAs($john)
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson(route('attendance.check-in'), [
                'credential_id' => 'peters_phone_cred',
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Attendance verification failed. This device is not registered for this account.',
        ]);
    }

    /** 8. Staff cannot view another staff member's attendance */
    public function test_staff_cannot_view_another_staff_members_attendance(): void
    {
        $john = $this->createUser(['name' => 'John']);
        $peter = $this->createUser(['name' => 'Peter']);

        $response = $this->actingAs($john)->get(route('admin.attendance.user-history', $peter));

        $response->assertStatus(403);
    }

    /** 9. Unauthorized users cannot manage attendance */
    public function test_unauthorized_users_cannot_manage_attendance(): void
    {
        $staff = $this->createUser();

        $response = $this->actingAs($staff)->get(route('admin.attendance.index'));

        $response->assertStatus(403);
    }

    /** 10. Admin can manually correct attendance */
    public function test_admin_can_manually_correct_attendance(): void
    {
        $admin = $this->createAdmin();
        $staff = $this->createUser();

        $record = AttendanceRecord::create([
            'user_id' => $staff->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'check_in_at' => now()->subHours(2),
            'status' => 'late',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.attendance.correct', $record), [
            'status' => 'present',
            'reason' => 'Staff member was on authorized official duty outside office',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $record->refresh();
        $this->assertEquals('present', $record->status);
    }

    /** 11. Manual corrections are audited */
    public function test_manual_corrections_are_audited(): void
    {
        $admin = $this->createAdmin();
        $staff = $this->createUser();

        $record = AttendanceRecord::create([
            'user_id' => $staff->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'check_in_at' => now(),
            'status' => 'late',
        ]);

        $this->actingAs($admin)->post(route('admin.attendance.correct', $record), [
            'status' => 'present',
            'reason' => 'Approved HR waiver for transportation delay',
        ]);

        $this->assertDatabaseHas('attendance_audit_logs', [
            'event_type' => 'manual_correction',
            'actor_id' => $admin->id,
            'affected_user_id' => $staff->id,
            'attendance_record_id' => $record->id,
        ]);
    }

    /** 12. Deactivated credentials cannot authenticate attendance */
    public function test_deactivated_credentials_cannot_authenticate_attendance(): void
    {
        $staff = $this->createUser();
        $this->createApprovedNetwork('127.0.0.1/32');

        $cred = $this->registerCredentialForUser($staff, 'cred_deactivated_123');
        $cred->update(['is_active' => false, 'deactivated_at' => now()]);

        $response = $this->actingAs($staff)
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson(route('attendance.check-in'), [
                'credential_id' => 'cred_deactivated_123',
            ]);

        $response->assertStatus(422);
    }

    /** 13. Device replacement invalidates old credential */
    public function test_device_replacement_invalidates_old_credential(): void
    {
        $admin = $this->createAdmin();
        $staff = $this->createUser();

        $oldCred = $this->registerCredentialForUser($staff, 'old_cred_123');

        $response = $this->actingAs($admin)->post(route('admin.attendance.allow-replacement', $staff));
        $response->assertRedirect();

        $oldCred->refresh();
        $this->assertFalse($oldCred->is_active);

        $regResponse = $this->actingAs($staff)->postJson(route('attendance.register-device'), [
            'credential_id' => 'new_cred_456',
            'device_name' => 'New Replacement Phone',
        ]);

        $regResponse->assertStatus(200);
        $this->assertDatabaseHas('attendance_credentials', [
            'user_id' => $staff->id,
            'credential_id' => 'new_cred_456',
            'is_active' => true,
        ]);
    }

    /** 14. Admin can view staff analytics with weekly, monthly, yearly, and all-time late counts */
    public function test_admin_can_view_staff_analytics_with_late_counts(): void
    {
        $admin = $this->createAdmin();
        $staff = $this->createUser(['name' => 'Alice Late Staff']);

        // Create late records across week, month, year, and all-time
        AttendanceRecord::create([
            'user_id' => $staff->id,
            'attendance_date' => Carbon::now()->startOfWeek()->toDateString(),
            'check_in_at' => Carbon::now()->startOfWeek()->setTime(10, 0),
            'status' => 'late',
        ]);

        AttendanceRecord::create([
            'user_id' => $staff->id,
            'attendance_date' => Carbon::now()->subYear()->toDateString(),
            'check_in_at' => Carbon::now()->subYear()->setTime(10, 30),
            'status' => 'late',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.attendance.analytics'));

        $response->assertStatus(200);
        $response->assertSee('Staff Attendance Analytics');
        $response->assertSee('Alice Late Staff');
    }
}
