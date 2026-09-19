<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceAuditLog;
use App\Models\AttendanceCredential;
use App\Models\AttendanceNetwork;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSetting;
use App\Services\PayrollService;
use App\Services\WebAuthnService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    protected WebAuthnService $webAuthnService;
    protected PayrollService $payrollService;

    public function __construct(WebAuthnService $webAuthnService, PayrollService $payrollService)
    {
        $this->webAuthnService = $webAuthnService;
        $this->payrollService  = $payrollService;
    }

    /**
     * Return the configured timezone (from DB settings), defaulting to UTC.
     */
    protected static function appTz(): string
    {
        return AttendanceSetting::get('timezone', config('app.timezone', 'Africa/Lagos'));
    }

    /**
     * Return Carbon::now() in the configured attendance timezone.
     */
    protected static function nowTz(): Carbon
    {
        return Carbon::now(self::appTz());
    }

    /**
     * Return Carbon::today() in the configured attendance timezone.
     */
    protected static function todayTz(): Carbon
    {
        return Carbon::today(self::appTz());
    }

    /**
     * Proxy-aware real client IP resolution (handles Railway / Cloudflare).
     */
    public static function resolveClientIp(Request $request): string
    {
        $xForwardedFor = $request->header('x-forwarded-for');
        if (!empty($xForwardedFor)) {
            $ips = array_map('trim', explode(',', $xForwardedFor));
            if (!empty($ips[0]) && filter_var($ips[0], FILTER_VALIDATE_IP)) {
                return $ips[0];
            }
        }

        $xRealIp = $request->header('x-real-ip');
        if (!empty($xRealIp) && filter_var(trim($xRealIp), FILTER_VALIDATE_IP)) {
            return trim($xRealIp);
        }

        return $request->ip() ?: '127.0.0.1';
    }

    public function dashboard(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $tz   = self::appTz();
        $today = self::todayTz();

        $record = AttendanceRecord::where('user_id', $user->id)
            ->whereDate('attendance_date', $today->toDateString())
            ->first();

        $hasRegisteredDevice = $user->hasActiveAttendanceCredential();
        $activeCredential    = $user->activeAttendanceCredential;

        // Network verification
        $clientIp       = self::resolveClientIp($request);
        $enabledNetworks = AttendanceNetwork::where('enabled', true)->get();

        $isNetworkVerified = false;
        $matchedNetwork    = null;

        if ($enabledNetworks->isEmpty()) {
            $isNetworkVerified = true;
        } else {
            foreach ($enabledNetworks as $network) {
                if ($network->matchesIp($clientIp)) {
                    $isNetworkVerified = true;
                    $matchedNetwork    = $network;
                    break;
                }
            }
        }

        if ($record) {
            $this->syncRecordStatusWithTimezone($record);
            $record->refresh();
        }

        $attendanceEnabled = AttendanceSetting::get('attendance_enabled', true);
        $profile           = $user->staffProfile;
        $expectedArrival   = $profile ? $profile->getExpectedResumptionTime($today, AttendanceSetting::get('expected_arrival_time', '08:00')) : AttendanceSetting::get('expected_arrival_time', '08:00');
        $checkInStart      = AttendanceSetting::get('check_in_start_time', '07:00');
        $checkInClosing    = AttendanceSetting::get('check_in_closing_time', '12:00');

        $currentPayPeriod  = \App\Services\PayrollService::currentPayPeriod();
        $payrollSummary    = $this->payrollService->calculateMonthlyBalance($user, $currentPayPeriod);

        return response()
            ->view('attendance.dashboard', compact(
                'user',
                'today',
                'tz',
                'record',
                'hasRegisteredDevice',
                'activeCredential',
                'isNetworkVerified',
                'matchedNetwork',
                'clientIp',
                'attendanceEnabled',
                'expectedArrival',
                'checkInStart',
                'checkInClosing',
                'payrollSummary'
            ))
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    protected function syncRecordStatusWithTimezone(?AttendanceRecord $record): void
    {
        if (!$record || !$record->check_in_at) {
            return;
        }

        $tz = self::appTz();
        $dateStr = $record->attendance_date->toDateString();
        $user = $record->user;
        $profile = $user?->staffProfile;

        $expectedTimeStr = $profile ? $profile->getExpectedResumptionTime($record->attendance_date, AttendanceSetting::get('expected_arrival_time', '08:00')) : AttendanceSetting::get('expected_arrival_time', '08:00');
        $lateThreshold   = $profile ? $profile->grace_period_minutes : (int) AttendanceSetting::get('late_threshold_minutes', 15);

        // Convert check_in_at to local WAT timezone
        $checkInLocal = Carbon::parse($record->check_in_at)->setTimezone($tz);

        // Calculate expected arrival deadline in WAT
        $expectedArrival = Carbon::createFromFormat('Y-m-d H:i', $dateStr . ' ' . $expectedTimeStr, $tz);
        $lateDeadline = (clone $expectedArrival)->addMinutes($lateThreshold);

        $isOffDay = $profile ? $profile->isOffDay($record->attendance_date) : false;

        $expectedStatus = ($isOffDay) ? 'present' : ($checkInLocal->greaterThan($lateDeadline) ? 'late' : 'present');

        if ($record->status !== $expectedStatus) {
            $record->update(['status' => $expectedStatus]);
        }
    }

    public function getRegisterOptions()
    {
        $user = Auth::user();
        $options = $this->webAuthnService->generateRegistrationOptions($user);
        return response()->json($options);
    }

    public function registerDevice(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'credential_id' => ['required', 'string'],
            'public_key'    => ['nullable', 'string'],
            'device_name'   => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $credential = $this->webAuthnService->registerCredential($user, $request->all());

            return response()->json([
                'success'    => true,
                'message'    => 'Device registration submitted successfully. Please wait for an administrator to approve your device passkey.',
                'credential' => $credential,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function getAssertionOptions()
    {
        $user    = Auth::user();
        $options = $this->webAuthnService->generateAssertionOptions($user);
        return response()->json($options);
    }

    public function logClientError(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'action' => ['nullable', 'string', 'max:80'],
            'stage' => ['nullable', 'string', 'max:80'],
            'name' => ['nullable', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:500'],
            'host' => ['nullable', 'string', 'max:255'],
            'rp_id' => ['nullable', 'string', 'max:255'],
            'user_agent' => ['nullable', 'string', 'max:500'],
        ]);

        AttendanceAuditLog::logEvent(
            eventType: 'failed_device_verification',
            actor: $user,
            affectedUser: $user,
            newValues: $validated,
            reason: 'Client-side WebAuthn failure during '
                . ($validated['action'] ?? 'attendance action')
                . ' at '
                . ($validated['stage'] ?? 'unknown stage')
                . ': '
                . ($validated['name'] ?? 'Error')
                . ' - '
                . ($validated['message'] ?? 'No browser message')
        );

        return response()->json(['success' => true]);
    }

    public function checkIn(Request $request)
    {
        /** @var \App\Models\User $user */
        $user  = Auth::user();
        $tz    = self::appTz();
        $today = self::todayTz();

        // 1. Attendance module enabled?
        if (!AttendanceSetting::get('attendance_enabled', true)) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance module is currently disabled by administrator.',
            ], 422);
        }

        // 2. Already checked in today?
        $existingRecord = AttendanceRecord::where('user_id', $user->id)
            ->whereDate('attendance_date', $today->toDateString())
            ->first();

        if ($existingRecord && $existingRecord->check_in_at) {
            return response()->json([
                'success' => false,
                'message' => 'You have already checked in for today ('
                    . $existingRecord->check_in_at->setTimezone($tz)->format('g:i A') . ').',
            ], 422);
        }

        // 3. Network verification
        $clientIp        = self::resolveClientIp($request);
        $enabledNetworks = AttendanceNetwork::where('enabled', true)->get();
        $isNetworkVerified = false;

        if ($enabledNetworks->isEmpty()) {
            $isNetworkVerified = true;
        } else {
            foreach ($enabledNetworks as $network) {
                if ($network->matchesIp($clientIp)) {
                    $isNetworkVerified = true;
                    break;
                }
            }
        }

        if (!$isNetworkVerified) {
            AttendanceAuditLog::logEvent(
                eventType:    'failed_network_verification',
                actor:        $user,
                affectedUser: $user,
                reason:       'Check-in attempt from unapproved network IP: ' . $clientIp
            );

            return response()->json([
                'success' => false,
                'message' => 'Check-in failed. Request must originate from an approved office network (Your IP: ' . $clientIp . ').',
            ], 403);
        }

        // 4. WebAuthn credential verification
        $credentialId  = $request->input('credential_id');
        $clientDataJson = $request->input('client_data_json');

        try {
            $credential = $this->webAuthnService->verifyAssertion($user, [
                'credential_id'   => $credentialId,
                'client_data_json' => $clientDataJson,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        // 5. Evaluate late status using staff profile shift/resumption & off-days
        $now             = self::nowTz();
        $profile         = $user->staffProfile;
        $expectedTimeStr = $profile ? $profile->getExpectedResumptionTime($today, AttendanceSetting::get('expected_arrival_time', '08:00')) : AttendanceSetting::get('expected_arrival_time', '08:00');
        $lateThreshold   = $profile ? $profile->grace_period_minutes : (int) AttendanceSetting::get('late_threshold_minutes', 15);

        // Build expected arrival for today in the correct timezone
        $expectedArrival = Carbon::createFromFormat('Y-m-d H:i', $today->toDateString() . ' ' . $expectedTimeStr, $tz);
        $lateDeadline    = (clone $expectedArrival)->addMinutes($lateThreshold);

        $isOffDay = $profile ? $profile->isOffDay($today) : false;
        $status   = ($isOffDay) ? 'present' : ($now->greaterThan($lateDeadline) ? 'late' : 'present');

        // 6. Record attendance
        $record = AttendanceRecord::updateOrCreate(
            ['user_id' => $user->id, 'attendance_date' => $today->toDateString()],
            [
                'check_in_at'             => $now,
                'status'                  => $status,
                'check_in_method'         => 'webauthn',
                'check_in_network_verified' => true,
                'check_in_ip'             => $clientIp,
                'check_in_credential_id'  => $credential->id,
            ]
        );

        // 7. Apply lateness penalty if late and not an off-day
        if ($status === 'late') {
            $this->payrollService->evaluateAndApplyLatenessPenalty($record, $user);
        }

        AttendanceAuditLog::logEvent(
            eventType:    'check_in',
            actor:        $user,
            affectedUser: $user,
            record:       $record,
            newValues:    ['check_in_at' => $now->toIso8601String(), 'status' => $status],
            reason:       'Check-in via WebAuthn passkey'
        );

        return response()->json([
            'success'       => true,
            'message'       => 'Check-in successful',
            'check_in_time' => $now->format('g:i A'),
            'status'        => $status,
            'record'        => $record,
        ]);
    }

    public function checkOut(Request $request)
    {
        /** @var \App\Models\User $user */
        $user  = Auth::user();
        $tz    = self::appTz();
        $today = self::todayTz();

        $record = AttendanceRecord::where('user_id', $user->id)
            ->whereDate('attendance_date', $today->toDateString())
            ->first();

        if (!$record || !$record->check_in_at) {
            return response()->json([
                'success' => false,
                'message' => 'You must check in first before checking out.',
            ], 422);
        }

        if ($record->check_out_at) {
            return response()->json([
                'success' => false,
                'message' => 'You have already checked out for today at '
                    . $record->check_out_at->setTimezone($tz)->format('g:i A') . '.',
            ], 422);
        }

        // Network verification
        $clientIp        = self::resolveClientIp($request);
        $enabledNetworks = AttendanceNetwork::where('enabled', true)->get();
        $isNetworkVerified = false;

        if ($enabledNetworks->isEmpty()) {
            $isNetworkVerified = true;
        } else {
            foreach ($enabledNetworks as $network) {
                if ($network->matchesIp($clientIp)) {
                    $isNetworkVerified = true;
                    break;
                }
            }
        }

        if (!$isNetworkVerified) {
            AttendanceAuditLog::logEvent(
                eventType:    'failed_network_verification',
                actor:        $user,
                affectedUser: $user,
                reason:       'Check-out attempt from unapproved network IP: ' . $clientIp
            );

            return response()->json([
                'success' => false,
                'message' => 'Check-out failed. Request must originate from an approved office network (Your IP: ' . $clientIp . ').',
            ], 403);
        }

        // WebAuthn verification
        $credentialId  = $request->input('credential_id');
        $clientDataJson = $request->input('client_data_json');

        try {
            $credential = $this->webAuthnService->verifyAssertion($user, [
                'credential_id'    => $credentialId,
                'client_data_json' => $clientDataJson,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $now = self::nowTz();
        $record->update([
            'check_out_at'              => $now,
            'check_out_network_verified' => true,
            'check_out_ip'              => $clientIp,
            'check_out_credential_id'   => $credential->id,
        ]);

        AttendanceAuditLog::logEvent(
            eventType:    'check_out',
            actor:        $user,
            affectedUser: $user,
            record:       $record,
            newValues:    ['check_out_at' => $now->toIso8601String()],
            reason:       'Check-out via WebAuthn passkey'
        );

        return response()->json([
            'success'        => true,
            'message'        => 'Check-out successful',
            'check_out_time' => $now->format('g:i A'),
            'record'         => $record,
        ]);
    }

    public function history()
    {
        $user    = Auth::user();
        $records = AttendanceRecord::where('user_id', $user->id)
            ->orderBy('attendance_date', 'desc')
            ->paginate(15);

        foreach ($records as $r) {
            $this->syncRecordStatusWithTimezone($r);
        }

        return response()
            ->view('attendance.history', compact('user', 'records'))
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
