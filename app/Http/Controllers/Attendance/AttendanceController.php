<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceAuditLog;
use App\Models\AttendanceCredential;
use App\Models\AttendanceNetwork;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSetting;
use App\Models\User;
use App\Services\WebAuthnService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    protected WebAuthnService $webAuthnService;

    public function __construct(WebAuthnService $webAuthnService)
    {
        $this->webAuthnService = $webAuthnService;
    }

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
        /** @var User $user */
        $user = Auth::user();
        $today = Carbon::today();

        $record = AttendanceRecord::where('user_id', $user->id)
            ->whereDate('attendance_date', $today)
            ->first();

        $hasRegisteredDevice = $user->hasActiveAttendanceCredential();
        $activeCredential = $user->activeAttendanceCredential;

        // Check office network using proxy-aware IP resolution
        $clientIp = self::resolveClientIp($request);
        $enabledNetworks = AttendanceNetwork::where('enabled', true)->get();

        $isNetworkVerified = false;
        $matchedNetwork = null;

        if ($enabledNetworks->isEmpty()) {
            // Default fallback if no network explicitly defined
            $isNetworkVerified = true;
        } else {
            foreach ($enabledNetworks as $network) {
                if ($network->matchesIp($clientIp)) {
                    $isNetworkVerified = true;
                    $matchedNetwork = $network;
                    break;
                }
            }
        }

        $attendanceEnabled = AttendanceSetting::get('attendance_enabled', true);
        $expectedArrival = AttendanceSetting::get('expected_arrival_time', '09:00');
        $checkInStart = AttendanceSetting::get('check_in_start_time', '07:00');
        $checkInClosing = AttendanceSetting::get('check_in_closing_time', '12:00');

        return view('attendance.dashboard', compact(
            'user',
            'today',
            'record',
            'hasRegisteredDevice',
            'activeCredential',
            'isNetworkVerified',
            'matchedNetwork',
            'clientIp',
            'attendanceEnabled',
            'expectedArrival',
            'checkInStart',
            'checkInClosing'
        ));
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
            'public_key' => ['nullable', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $credential = $this->webAuthnService->registerCredential($user, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Device registration submitted successfully. Please wait for an administrator to approve your device passkey.',
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
        $user = Auth::user();
        $options = $this->webAuthnService->generateAssertionOptions($user);
        return response()->json($options);
    }

    public function checkIn(Request $request)
    {
        $user = Auth::user();
        $today = Carbon::today();

        // 1. Check if attendance module is globally enabled
        if (!AttendanceSetting::get('attendance_enabled', true)) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance module is currently disabled by administrator.',
            ], 422);
        }

        // 2. Check if already checked in today
        $existingRecord = AttendanceRecord::where('user_id', $user->id)
            ->whereDate('attendance_date', $today)
            ->first();

        if ($existingRecord && $existingRecord->check_in_at) {
            return response()->json([
                'success' => false,
                'message' => 'You have already checked in for today (' . $existingRecord->check_in_at->format('g:i A') . ').',
            ], 422);
        }

        // 3. Network Verification
        $clientIp = self::resolveClientIp($request);
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
                eventType: 'failed_network_verification',
                actor: $user,
                affectedUser: $user,
                reason: 'Check-in attempt from unapproved network IP: ' . $clientIp
            );

            return response()->json([
                'success' => false,
                'message' => 'Check-in failed. Request must originate from an approved office network (Your IP: ' . $clientIp . ').',
            ], 403);
        }

        // 4. WebAuthn Credential Verification
        $credentialId = $request->input('credential_id');
        $clientDataJson = $request->input('client_data_json');

        try {
            $credential = $this->webAuthnService->verifyAssertion($user, [
                'credential_id' => $credentialId,
                'client_data_json' => $clientDataJson,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        // 5. Evaluate Late Status
        $now = Carbon::now();
        $expectedTimeStr = AttendanceSetting::get('expected_arrival_time', '09:00');
        $lateThreshold = (int) AttendanceSetting::get('late_threshold_minutes', 15);

        $expectedArrival = Carbon::createFromFormat('H:i', $expectedTimeStr)->setDateFrom($now);
        $lateDeadline = (clone $expectedArrival)->addMinutes($lateThreshold);

        $status = $now->greaterThan($lateDeadline) ? 'late' : 'present';

        // 6. Record attendance
        $record = AttendanceRecord::updateOrCreate(
            ['user_id' => $user->id, 'attendance_date' => $today->toDateString()],
            [
                'check_in_at' => $now,
                'status' => $status,
                'check_in_method' => 'webauthn',
                'check_in_network_verified' => true,
                'check_in_ip' => $clientIp,
                'check_in_credential_id' => $credential->id,
            ]
        );

        AttendanceAuditLog::logEvent(
            eventType: 'check_in',
            actor: $user,
            affectedUser: $user,
            record: $record,
            newValues: ['check_in_at' => $now->toIso8601String(), 'status' => $status],
            reason: 'Check-in via WebAuthn passkey'
        );

        return response()->json([
            'success' => true,
            'message' => 'Check-in successful',
            'check_in_time' => $now->format('g:i A'),
            'status' => $status,
            'record' => $record,
        ]);
    }

    public function checkOut(Request $request)
    {
        $user = Auth::user();
        $today = Carbon::today();

        $record = AttendanceRecord::where('user_id', $user->id)
            ->whereDate('attendance_date', $today)
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
                'message' => 'You have already checked out for today at ' . $record->check_out_at->format('g:i A') . '.',
            ], 422);
        }

        // Network Verification
        $clientIp = self::resolveClientIp($request);
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
                eventType: 'failed_network_verification',
                actor: $user,
                affectedUser: $user,
                reason: 'Check-out attempt from unapproved network IP: ' . $clientIp
            );

            return response()->json([
                'success' => false,
                'message' => 'Check-out failed. Request must originate from an approved office network (Your IP: ' . $clientIp . ').',
            ], 403);
        }

        // WebAuthn Credential Verification
        $credentialId = $request->input('credential_id');
        $clientDataJson = $request->input('client_data_json');

        try {
            $credential = $this->webAuthnService->verifyAssertion($user, [
                'credential_id' => $credentialId,
                'client_data_json' => $clientDataJson,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $now = Carbon::now();
        $record->update([
            'check_out_at' => $now,
            'check_out_network_verified' => true,
            'check_out_ip' => $clientIp,
            'check_out_credential_id' => $credential->id,
        ]);

        AttendanceAuditLog::logEvent(
            eventType: 'check_out',
            actor: $user,
            affectedUser: $user,
            record: $record,
            newValues: ['check_out_at' => $now->toIso8601String()],
            reason: 'Check-out via WebAuthn passkey'
        );

        return response()->json([
            'success' => true,
            'message' => 'Check-out successful',
            'check_out_time' => $now->format('g:i A'),
            'record' => $record,
        ]);
    }

    public function history()
    {
        $user = Auth::user();
        $records = AttendanceRecord::where('user_id', $user->id)
            ->orderBy('attendance_date', 'desc')
            ->paginate(15);

        return view('attendance.history', compact('user', 'records'));
    }
}
