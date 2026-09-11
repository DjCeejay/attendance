@extends('layouts.admin')

@section('title', $user->name . ' - Device & Attendance History')

@section('content')
<div class="space-y-6">

    <!-- Top Navigation & Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900">{{ $user->name }}</h1>
            <p class="text-xs text-slate-500">{{ $user->email }} &bull; Role: {{ strtoupper($user->role) }}</p>
        </div>
        <a href="{{ route('admin.attendance.index') }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
            &larr; Back to Dashboard Overview
        </a>
    </div>

    <!-- Punctuality & Late Statistics Cards (Week, Month, Year, All-Time) -->
    @if(isset($lateStats))
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Late This Week</span>
            <span class="text-2xl font-black text-amber-600 mt-1 block">{{ $lateStats['week'] }}</span>
            <span class="text-[11px] text-slate-500 mt-0.5 block">Current week</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Late This Month</span>
            <span class="text-2xl font-black text-amber-600 mt-1 block">{{ $lateStats['month'] }}</span>
            <span class="text-[11px] text-slate-500 mt-0.5 block">Current calendar month</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Late This Year</span>
            <span class="text-2xl font-black text-rose-600 mt-1 block">{{ $lateStats['year'] }}</span>
            <span class="text-[11px] text-slate-500 mt-0.5 block">Current year</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Late All-Time</span>
            <span class="text-2xl font-black text-slate-800 mt-1 block">{{ $lateStats['all_time'] }}</span>
            <span class="text-[11px] text-slate-500 mt-0.5 block">Total historical late count</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Punctuality Rate</span>
            <span class="text-2xl font-black text-indigo-600 mt-1 block">{{ $lateStats['punctuality_rate'] }}%</span>
            <span class="text-[11px] text-slate-500 mt-0.5 block">{{ $lateStats['total_present'] }} days present</span>
        </div>
    </div>
    @endif

    <!-- Registered Devices & Passkey Management -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <h3 class="font-extrabold text-base text-slate-900">Registered Attendance Passkey Devices</h3>
                <p class="text-xs text-slate-500">Cryptographic WebAuthn credentials stored for anti-cheating verification</p>
            </div>

            <form method="POST" action="{{ route('admin.attendance.allow-replacement', $user) }}" onsubmit="return confirm('Invalidate existing active device credentials to allow staff member to register a new device?');">
                @csrf
                <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-extrabold text-xs py-2 px-3.5 rounded-lg shadow">
                    Authorize Device Replacement
                </button>
            </form>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse($credentials as $cred)
                <div class="py-3 flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-sm text-slate-900">{{ $cred->device_name ?: 'WebAuthn Security Device' }}</span>
                            @if($cred->is_active)
                                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-emerald-100 text-emerald-800">ACTIVE DEVICE</span>
                            @else
                                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-slate-100 text-slate-600">DEACTIVATED</span>
                            @endif
                        </div>
                        <div class="text-xs text-slate-500 mt-1 space-x-3">
                            <span>Registered: <strong>{{ $cred->registered_at ? $cred->registered_at->format('M j, Y g:i A') : 'N/A' }}</strong></span>
                            <span>Last Used: <strong>{{ $cred->last_used_at ? $cred->last_used_at->format('M j, Y g:i A') : 'Never' }}</strong></span>
                        </div>
                    </div>

                    @if($cred->is_active)
                        <form method="POST" action="{{ route('admin.attendance.deactivate-credential', $cred) }}" onsubmit="return confirm('Deactivate this passkey device credential?');">
                            @csrf
                            <button type="submit" class="text-xs font-bold text-rose-600 hover:text-rose-800 border border-rose-200 px-3 py-1.5 rounded-lg hover:bg-rose-50 transition">
                                Deactivate Device
                            </button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="py-4 text-center text-xs text-slate-500">
                    No registered attendance devices found for this staff member.
                </div>
            @endforelse
        </div>
    </div>

    <!-- Personal Attendance History -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 font-extrabold text-sm text-slate-900">
            Attendance Record History
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-700">
                <thead class="bg-slate-100 uppercase tracking-wider font-extrabold text-[10px] text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="p-3.5">Date</th>
                        <th class="p-3.5">Check In</th>
                        <th class="p-3.5">Check Out</th>
                        <th class="p-3.5">Verification Method</th>
                        <th class="p-3.5">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($records as $rec)
                        <tr class="hover:bg-slate-50">
                            <td class="p-3.5 font-bold text-slate-900">{{ $rec->attendance_date->format('D, M j, Y') }}</td>
                            <td class="p-3.5 font-bold text-slate-800">{{ $rec->check_in_at ? $rec->check_in_at->format('g:i A') : '--' }}</td>
                            <td class="p-3.5 font-bold text-slate-800">{{ $rec->check_out_at ? $rec->check_out_at->format('g:i A') : '--' }}</td>
                            <td class="p-3.5">
                                @if($rec->check_in_network_verified)
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Office IP Verified</span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600">{{ $rec->check_in_method ?: 'Direct' }}</span>
                                @endif
                            </td>
                            <td class="p-3.5">
                                @if($rec->isLate())
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-amber-100 text-amber-800">Late</span>
                                @elseif($rec->status === 'present')
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-emerald-100 text-emerald-800">Present</span>
                                @else
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-slate-100 text-slate-700 uppercase">{{ $rec->status }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-6 text-center text-slate-400 text-xs">No records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-slate-100">
            {{ $records->links() }}
        </div>
    </div>

    <!-- Security Audit Logs for Staff Member -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
        <h3 class="font-extrabold text-base text-slate-900 border-b border-slate-100 pb-3">Security & Device Audit Trail</h3>

        <div class="space-y-3">
            @forelse($auditLogs as $log)
                <div class="p-3 bg-slate-50 rounded-lg border border-slate-200/80 text-xs flex items-start justify-between">
                    <div>
                        <div class="font-bold text-slate-900 uppercase tracking-wider text-[10px]">
                            Event: <span class="text-indigo-700">{{ str_replace('_', ' ', $log->event_type) }}</span>
                        </div>
                        <div class="text-slate-600 mt-0.5">
                            Reason: {{ $log->reason ?: 'System security log' }}
                        </div>
                        <div class="text-[10px] text-slate-400 mt-1">
                            Actor: {{ $log->actor ? $log->actor->name : 'System' }} &bull; IP: {{ $log->ip_address ?: 'N/A' }}
                        </div>
                    </div>

                    <div class="text-[10px] font-bold text-slate-400 whitespace-nowrap">
                        {{ $log->created_at->format('M j, g:i A') }}
                    </div>
                </div>
            @empty
                <div class="text-center text-xs text-slate-400 py-3">No audit log entries.</div>
            @endforelse
        </div>
    </div>

</div>
@endsection
