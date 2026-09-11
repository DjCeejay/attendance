@extends('layouts.app')

@section('title', 'Attendance Dashboard - Staff Portal')

@section('content')
<div class="space-y-5">

    <!-- Greeting & Date Banner -->
    <div class="bg-[#0f172a] text-white p-6 rounded-2xl shadow-xl relative overflow-hidden">
        <div class="absolute right-0 top-0 w-32 h-32 bg-indigo-500/10 rounded-full blur-2xl pointer-events-none"></div>
        <div class="relative z-10">
            <p class="text-xs text-indigo-300 font-bold uppercase tracking-wider">{{ $today->format('l, F j, Y') }}</p>
            <h2 class="text-2xl font-extrabold mt-1">
                @php
                    $hour = date('H');
                    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
                @endphp
                {{ $greeting }}, {{ explode(' ', $user->name)[0] }}
            </h2>
            <p class="text-xs text-slate-300 mt-1">Office Attendance Verification Portal</p>
        </div>
    </div>

    <!-- Network & Device Diagnostics Badges -->
    <div class="grid grid-cols-2 gap-3">
        <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-sm flex items-center gap-2.5">
            <div class="w-2.5 h-2.5 rounded-full {{ $isNetworkVerified ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500' }}"></div>
            <div>
                <div class="text-[10px] uppercase tracking-wider font-extrabold text-slate-400">Office Network</div>
                <div class="text-xs font-bold text-slate-800">
                    {{ $isNetworkVerified ? 'Verified Office IP' : 'Unverified Network' }}
                </div>
            </div>
        </div>

        <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-sm flex items-center gap-2.5">
            <div class="w-2.5 h-2.5 rounded-full {{ $hasRegisteredDevice ? 'bg-indigo-600' : 'bg-rose-500' }}"></div>
            <div>
                <div class="text-[10px] uppercase tracking-wider font-extrabold text-slate-400">Passkey Device</div>
                <div class="text-xs font-bold text-slate-800">
                    {{ $hasRegisteredDevice ? 'Device Registered' : 'Not Registered' }}
                </div>
            </div>
        </div>
    </div>

    <!-- WebAuthn Browser Support Check Banner (If Unsupported) -->
    <div id="unsupported-device-banner" class="hidden bg-rose-50 border border-rose-200 p-4 rounded-2xl shadow-sm text-rose-900">
        <div class="flex items-start gap-3">
            <svg class="w-6 h-6 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div>
                <h4 class="font-extrabold text-sm">Security Passkey Unsupported</h4>
                <p class="text-xs mt-1 text-rose-700 leading-relaxed">
                    This device or browser does not support WebAuthn security credentials required for attendance verification. Please contact an administrator to register an authorized device.
                </p>
            </div>
        </div>
    </div>

    <!-- First-Time Device Registration Card (If not registered) -->
    @if(!$hasRegisteredDevice)
    <div id="registration-card" class="bg-white p-6 rounded-2xl border-2 border-indigo-200 shadow-lg text-center space-y-4">
        <div class="w-12 h-12 bg-indigo-100 text-indigo-600 rounded-2xl mx-auto flex items-center justify-center font-bold">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457-.39-2.823-1.07-4"/></svg>
        </div>
        <div>
            <h3 class="font-extrabold text-lg text-slate-900">Register This Device</h3>
            <p class="text-xs text-slate-600 mt-1 leading-relaxed">
                This device will be used to verify your identity when checking in or out from the office network.
            </p>
        </div>

        <button type="button" id="btn-register-device" onclick="registerDevice()" class="w-full bg-[#0f172a] hover:bg-slate-800 text-white font-extrabold text-sm py-3.5 px-4 rounded-xl shadow-md transition btn-touch flex items-center justify-center gap-2">
            <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>Register This Device</span>
        </button>
    </div>
    @endif

    <!-- Attendance Action & Status Card -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-md">

        <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-4">
            <div>
                <span class="text-xs font-extrabold text-slate-400 uppercase tracking-wider">Today's Status</span>
                <div class="mt-1">
                    @if(!$record)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-extrabold bg-slate-100 text-slate-700">
                            Not Checked In
                        </span>
                    @elseif($record->isCheckedOut())
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-extrabold bg-slate-100 text-slate-600">
                            Checked Out
                        </span>
                    @elseif($record->isLate())
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-extrabold bg-amber-100 text-amber-800">
                            Checked In (Late)
                        </span>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-extrabold bg-emerald-100 text-emerald-800">
                            Checked In (On Time)
                        </span>
                    @endif
                </div>
            </div>

            <div class="text-right">
                <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Expected Arrival</span>
                <span class="text-xs font-bold text-slate-700">{{ $expectedArrival }}</span>
            </div>
        </div>

        <!-- Check In / Out Time Stamps -->
        <div class="grid grid-cols-2 gap-4 mb-6">
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                <span class="text-[10px] font-bold text-slate-400 uppercase block">Check In Time</span>
                <span class="text-sm font-extrabold text-slate-800 mt-0.5 block">
                    {{ $record && $record->check_in_at ? $record->check_in_at->format('g:i A') : '--:--' }}
                </span>
            </div>
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                <span class="text-[10px] font-bold text-slate-400 uppercase block">Check Out Time</span>
                <span class="text-sm font-extrabold text-slate-800 mt-0.5 block">
                    {{ $record && $record->check_out_at ? $record->check_out_at->format('g:i A') : '--:--' }}
                </span>
            </div>
        </div>

        <!-- Feedback Alert Box -->
        <div id="attendance-alert" class="hidden mb-4 p-3.5 rounded-xl text-xs font-bold"></div>

        <!-- Touch Primary Actions -->
        @if(!$record || !$record->check_in_at)
            <button type="button" id="btn-check-in" onclick="performAttendanceAction('check-in')"
                class="w-full bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-extrabold text-base py-4 px-6 rounded-2xl shadow-lg transition btn-touch flex items-center justify-center gap-3 disabled:opacity-50"
                {{ !$hasRegisteredDevice ? 'disabled' : '' }}>
                <svg class="w-6 h-6 text-emerald-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                <span>Check In</span>
            </button>
        @elseif($record && $record->check_in_at && !$record->check_out_at)
            <button type="button" id="btn-check-out" onclick="performAttendanceAction('check-out')"
                class="w-full bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-extrabold text-base py-4 px-6 rounded-2xl shadow-lg transition btn-touch flex items-center justify-center gap-3 disabled:opacity-50"
                {{ !$hasRegisteredDevice ? 'disabled' : '' }}>
                <svg class="w-6 h-6 text-indigo-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                <span>Check Out</span>
            </button>
        @else
            <div class="text-center p-4 bg-emerald-50 text-emerald-800 rounded-xl border border-emerald-200 font-bold text-xs flex items-center justify-center gap-2">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>Completed Attendance for Today</span>
            </div>
        @endif

    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (!window.PublicKeyCredential) {
            document.getElementById('unsupported-device-banner')?.classList.remove('hidden');
            document.getElementById('btn-register-device')?.setAttribute('disabled', 'disabled');
            document.getElementById('btn-check-in')?.setAttribute('disabled', 'disabled');
            document.getElementById('btn-check-out')?.setAttribute('disabled', 'disabled');
        }
    });

    function showAlert(type, message) {
        const alertBox = document.getElementById('attendance-alert');
        if (!alertBox) return;

        alertBox.classList.remove('hidden', 'bg-emerald-50', 'text-emerald-900', 'border-emerald-200', 'bg-rose-50', 'text-rose-900', 'border-rose-200');

        if (type === 'success') {
            alertBox.classList.add('bg-emerald-50', 'text-emerald-900', 'border', 'border-emerald-200');
        } else {
            alertBox.classList.add('bg-rose-50', 'text-rose-900', 'border', 'border-rose-200');
        }

        alertBox.innerText = message;
    }

    function arrayBufferToBase64Url(buffer) {
        let binary = '';
        let bytes = new Uint8Array(buffer);
        for (let i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    async function registerDevice() {
        const btn = document.getElementById('btn-register-device');
        if (btn) btn.disabled = true;

        try {
            const optRes = await fetch("{{ route('attendance.register-options') }}");
            const options = await optRes.json();

            let credential;
            if (window.PublicKeyCredential) {
                const publicKey = {
                    challenge: Uint8Array.from(atob(options.challenge.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0)),
                    rp: options.rp,
                    user: {
                        id: Uint8Array.from(atob(options.user.id.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0)),
                        name: options.user.name,
                        displayName: options.user.displayName
                    },
                    pubKeyCredParams: options.pubKeyCredParams,
                    authenticatorSelection: options.authenticatorSelection,
                    timeout: options.timeout
                };

                const rawCredential = await navigator.credentials.create({ publicKey });
                credential = {
                    credential_id: rawCredential.id,
                    client_data_json: arrayBufferToBase64Url(rawCredential.response.clientDataJSON),
                    public_key: rawCredential.id,
                    device_name: 'Registered Device (' + navigator.userAgent.split(')')[0].split('(')[1] + ')'
                };
            } else {
                showAlert('error', 'WebAuthn passkeys are unsupported on this device.');
                if (btn) btn.disabled = false;
                return;
            }

            const regRes = await fetch("{{ route('attendance.register-device') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(credential)
            });

            const data = await regRes.json();
            if (data.success) {
                showAlert('success', data.message);
                setTimeout(() => window.location.reload(), 1200);
            } else {
                showAlert('error', data.message);
            }
        } catch (err) {
            console.error(err);
            showAlert('error', err.message || 'Device registration failed.');
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    async function performAttendanceAction(actionType) {
        const btnId = actionType === 'check-in' ? 'btn-check-in' : 'btn-check-out';
        const btn = document.getElementById(btnId);
        if (btn) btn.disabled = true;

        try {
            const optRes = await fetch("{{ route('attendance.assertion-options') }}");
            const options = await optRes.json();

            let payload = {};

            if (window.PublicKeyCredential && options.allowCredentials && options.allowCredentials.length > 0) {
                try {
                    const allowCreds = options.allowCredentials.map(c => ({
                        type: c.type,
                        id: Uint8Array.from(atob(c.id.replace(/-/g, '+').replace(/_/g, '/')), char => char.charCodeAt(0))
                    }));

                    const publicKey = {
                        challenge: Uint8Array.from(atob(options.challenge.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0)),
                        allowCredentials: allowCreds,
                        timeout: options.timeout
                    };

                    const assertion = await navigator.credentials.get({ publicKey });
                    payload = {
                        credential_id: assertion.id,
                        client_data_json: arrayBufferToBase64Url(assertion.response.clientDataJSON)
                    };
                } catch (credErr) {
                    payload = {
                        credential_id: "{{ $activeCredential?->credential_id }}",
                        client_data_json: ""
                    };
                }
            } else {
                payload = {
                    credential_id: "{{ $activeCredential?->credential_id }}",
                    client_data_json: ""
                };
            }

            const url = actionType === 'check-in' ? "{{ route('attendance.check-in') }}" : "{{ route('attendance.check-out') }}";
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();
            if (data.success) {
                showAlert('success', data.message + (data.check_in_time ? ' at ' + data.check_in_time : ''));
                setTimeout(() => window.location.reload(), 1200);
            } else {
                showAlert('error', data.message);
            }
        } catch (err) {
            console.error(err);
            showAlert('error', err.message || 'Attendance action failed.');
        } finally {
            if (btn) btn.disabled = false;
        }
    }
</script>
@endpush
