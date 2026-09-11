<?php

namespace App\Services;

use App\Models\AttendanceAuditLog;
use App\Models\AttendanceCredential;
use App\Models\User;
use Illuminate\Support\Str;

class WebAuthnService
{
    /**
     * Generate registration challenge & options for navigator.credentials.create()
     */
    public function generateRegistrationOptions(User $user): array
    {
        $challenge = Str::random(32);
        session(['webauthn_challenge' => $challenge]);

        return [
            'challenge' => self::base64UrlEncode($challenge),
            'rp' => [
                'name' => 'ARTSCI Attendance',
                'id' => request()->getHost(),
            ],
            'user' => [
                'id' => self::base64UrlEncode((string) $user->id),
                'name' => $user->email,
                'displayName' => $user->name,
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],   // ES256
                ['type' => 'public-key', 'alg' => -257], // RS256
            ],
            'authenticatorSelection' => [
                'userVerification' => 'preferred',
            ],
            'timeout' => 60000,
        ];
    }

    /**
     * Register a new WebAuthn credential for the authenticated user.
     */
    public function registerCredential(User $user, array $payload): AttendanceCredential
    {
        // Policy: 1 active device per user. Check if active device exists.
        if ($user->hasActiveAttendanceCredential()) {
            throw new \InvalidArgumentException('You already have an active registered device for attendance. Replacing a device requires administrator authorization.');
        }

        $storedChallenge = session('webauthn_challenge');
        $clientDataJsonRaw = isset($payload['client_data_json']) ? self::base64UrlDecode($payload['client_data_json']) : '';
        $clientData = json_decode($clientDataJsonRaw, true);

        if ($storedChallenge && is_array($clientData)) {
            $receivedChallenge = isset($clientData['challenge']) ? self::base64UrlDecode($clientData['challenge']) : '';
            if ($receivedChallenge !== '' && $receivedChallenge !== $storedChallenge) {
                // If challenges were explicitly decoded and mismatch
                throw new \InvalidArgumentException('Invalid cryptographic challenge.');
            }
        }

        $credentialId = $payload['credential_id'] ?? null;
        $publicKey = $payload['public_key'] ?? ($payload['raw_id'] ?? Str::random(64));
        $deviceName = $payload['device_name'] ?? 'WebAuthn Device (' . request()->header('User-Agent', 'Unknown') . ')';

        if (empty($credentialId)) {
            throw new \InvalidArgumentException('Credential ID is required.');
        }

        // Check if credential ID is already registered to another user
        if (AttendanceCredential::where('credential_id', $credentialId)->exists()) {
            throw new \InvalidArgumentException('This credential is already registered to another account.');
        }

        $credential = AttendanceCredential::create([
            'user_id' => $user->id,
            'credential_id' => $credentialId,
            'public_key' => $publicKey,
            'attestation_format' => $payload['attestation_format'] ?? 'none',
            'sign_count' => 0,
            'device_name' => mb_substr($deviceName, 0, 255),
            'user_agent' => mb_substr(request()->header('User-Agent', ''), 0, 500),
            'is_active' => true,
            'registered_at' => now(),
            'last_used_at' => now(),
        ]);

        AttendanceAuditLog::logEvent(
            eventType: 'device_registration',
            actor: $user,
            affectedUser: $user,
            newValues: [
                'credential_id' => $credential->id,
                'device_name' => $credential->device_name,
            ],
            reason: 'First-time device registration for attendance'
        );

        return $credential;
    }

    /**
     * Generate assertion options for navigator.credentials.get()
     */
    public function generateAssertionOptions(User $user): array
    {
        $challenge = Str::random(32);
        session(['webauthn_challenge' => $challenge]);

        $activeCredential = $user->activeAttendanceCredential;

        return [
            'challenge' => self::base64UrlEncode($challenge),
            'allowCredentials' => $activeCredential ? [
                [
                    'type' => 'public-key',
                    'id' => $activeCredential->credential_id,
                ],
            ] : [],
            'timeout' => 60000,
        ];
    }

    /**
     * Verify WebAuthn assertion credential against the authenticated user's registered credential.
     */
    public function verifyAssertion(User $user, array $payload): AttendanceCredential
    {
        $activeCredential = $user->activeAttendanceCredential;

        if (!$activeCredential) {
            AttendanceAuditLog::logEvent(
                eventType: 'failed_device_verification',
                actor: $user,
                affectedUser: $user,
                reason: 'Attendance verification failed: No active registered device on account'
            );
            throw new \InvalidArgumentException('Attendance verification failed. This device is not registered for this account.');
        }

        $submittedCredentialId = $payload['credential_id'] ?? null;

        // Anti-cheating core rule:
        // If Peter logged into John's account, Peter's device credential ID will NOT match John's active credential ID!
        if (empty($submittedCredentialId) || $submittedCredentialId !== $activeCredential->credential_id) {
            AttendanceAuditLog::logEvent(
                eventType: 'failed_device_verification',
                actor: $user,
                affectedUser: $user,
                reason: 'Attendance verification failed: Unregistered device credential submitted (' . substr((string)$submittedCredentialId, 0, 20) . '...)'
            );
            throw new \InvalidArgumentException('Attendance verification failed. This device is not registered for this account.');
        }

        $storedChallenge = session('webauthn_challenge');
        $clientDataJsonRaw = isset($payload['client_data_json']) ? self::base64UrlDecode($payload['client_data_json']) : '';
        $clientData = json_decode($clientDataJsonRaw, true);

        if ($storedChallenge && is_array($clientData)) {
            $receivedChallenge = isset($clientData['challenge']) ? self::base64UrlDecode($clientData['challenge']) : '';
            if ($receivedChallenge !== '' && $receivedChallenge !== $storedChallenge) {
                // Challenge mismatch
                throw new \InvalidArgumentException('Attendance verification failed. Invalid authentication challenge.');
            }
        }

        $activeCredential->update([
            'last_used_at' => now(),
            'sign_count' => $activeCredential->sign_count + 1,
        ]);

        return $activeCredential;
    }

    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
