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

    protected function registerCredentialForUser(User $user, string $credentialId = 'cred_john_123', string $approvalStatus = 'approved'): AttendanceCredential
    {
        return AttendanceCredential::create([
            'user_id' => $user->id,
            'credential_id' => $credentialId,
            'public_key' => 'pubkey_sample_data',
            'device_name' => "John's Phone",
            'is_active' => true,
            'approval_status' => $approvalStatus,
            'registered_at' => now(),
        ]);
    }

    /** 1. Staff can self-register at /register with pending status */
    public function test_staff_can_self_register_with_pending_status(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'New Staff User',
            'email' => 'newstaff@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('success');

        $user = User::where('email', 'newstaff@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('pending', $user->status);

        // Attempt login before approval
        $loginResponse = $this->post(route('login'), [
            'email' => 'newstaff@example.com',
            'password' => 'password123',
        ]);

        $loginResponse->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /** 2. Admin can approve a pending staff account */
    public function test_admin_can_approve_pending_staff_account(): void
    {
        $admin = $this->createAdmin();
        $pendingUser = $this->createUser(['status' => 'pending']);

        $response = $this->actingAs($admin)->post(route('admin.attendance.users.approve', $pendingUser));
        $response->assertRedirect();

        $pendingUser->refresh();
        $this->assertEquals('approved', $pendingUser->status);

        // Staff can now log in
        $loginResponse = $this->post(route('login'), [
            'email' => $pendingUser->email,
            'password' => 'password123',
        ]);

        $this->assertAuthenticatedAs($pendingUser);
    }

    /** 3. Device passkey registration starts in pending approval status */
    public function test_device_registration_starts_in_pending_approval_status(): void
    {
        $staff = $this->createUser();

        $response = $this->actingAs($staff)->postJson(route('attendance.register-device'), [
            'credential_id' => 'cred_pending_123',
            'public_key' => 'pubkey_xyz',
            'device_name' => "Staff Mobile Phone",
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('attendance_credentials', [
            'user_id' => $staff->id,
            'credential_id' => 'cred_pending_123',
            'approval_status' => 'pending',
        ]);
    }

    /** 4. Admin can approve pending device registration */
    public function test_admin_can_approve_pending_device_registration(): void
    {
        $admin = $this->createAdmin();
        $staff = $this->createUser();
        $this->createApprovedNetwork('127.0.0.1/32');

        $pendingCred = $this->registerCredentialForUser($staff, 'cred_unapproved_123', 'pending');

        // Check-in should fail while pending approval
        $checkInResponse = $this->actingAs($staff)
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson(route('attendance.check-in'), [
                'credential_id' => 'cred_unapproved_123',
            ]);

        $checkInResponse->assertStatus(422);

        // Admin approves device
        $approveResponse = $this->actingAs($admin)->post(route('admin.attendance.credentials.approve', $pendingCred));
        $approveResponse->assertRedirect();

        $pendingCred->refresh();
        $this->assertEquals('approved', $pendingCred->approval_status);

        // Staff can now check in successfully
        $checkInSuccess = $this->actingAs($staff->fresh())
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson(route('attendance.check-in'), [
                'credential_id' => 'cred_unapproved_123',
            ]);

        $checkInSuccess->assertStatus(200);
        $checkInSuccess->assertJson(['success' => true]);
    }

    /** 5. Staff can view attendance dashboard */
    public function test_staff_can_view_attendance_dashboard(): void
    {
        $staff = $this->createUser();

        $response = $this->actingAs($staff)->get(route('attendance.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Staff Attendance');
        $response->assertSee('John');
    }

    /** 6. Staff can check in with valid credentials and approved network */
    public function test_staff_can_check_in_with_valid_credentials_and_approved_network(): void
    {
        $staff = $this->createUser();
        $this->createApprovedNetwork('127.0.0.1/32');
        $cred = $this->registerCredentialForUser($staff, 'cred_john_passkey', 'approved');

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

    /** 7. Duplicate check-in is rejected */
    public function test_duplicate_check_in_is_rejected(): void
    {
        $staff = $this->createUser();
        $this->createApprovedNetwork('127.0.0.1/32');
        $cred = $this->registerCredentialForUser($staff, 'cred_john_passkey', 'approved');

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

    /** 8. Check-in from an unapproved network is rejected */
    public function test_check_in_from_an_unapproved_network_is_rejected(): void
    {
        $staff = $this->createUser();
        $this->createApprovedNetwork('192.168.10.0/24');
        $this->registerCredentialForUser($staff, 'cred_john_passkey', 'approved');

        $response = $this->actingAs($staff)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])
            ->postJson(route('attendance.check-in'), [
                'credential_id' => 'cred_john_passkey',
            ]);

        $response->assertStatus(403);
        $response->assertJson(['success' => false]);
    }

    /** 9. Admin can manually correct attendance */
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

    /** 10. Admin can view staff analytics */
    public function test_admin_can_view_staff_analytics(): void
    {
        $admin = $this->createAdmin();
        $staff = $this->createUser(['name' => 'Alice Late Staff']);

        $response = $this->actingAs($admin)->get(route('admin.attendance.analytics'));

        $response->assertStatus(200);
        $response->assertSee('Staff Attendance Analytics');
        $response->assertSee('Alice Late Staff');
    }
}
