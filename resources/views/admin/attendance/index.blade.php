@extends('layouts.admin')

@section('title', 'Attendance Dashboard - Admin Console')

@section('content')
<div class="space-y-6">

    <!-- Header & Date Controls -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900">Attendance Overview</h1>
            <p class="text-xs text-slate-500">Monitor daily office attendance, pending staff approvals, and device passkeys</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.attendance.analytics') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs py-2 px-3.5 rounded-xl shadow flex items-center gap-1.5">
                <svg class="w-4 h-4 text-indigo-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <span>View Staff Analytics</span>
            </a>

            <form method="GET" action="{{ route('admin.attendance.index') }}" class="flex items-center gap-2">
                <input type="date" name="date" value="{{ $selectedDate }}" onchange="this.form.submit()" class="px-3 py-2 rounded-xl border border-slate-300 text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                @if($selectedUser) <input type="hidden" name="user_id" value="{{ $selectedUser }}"> @endif
                @if($selectedStatus) <input type="hidden" name="status" value="{{ $selectedStatus }}"> @endif
            </form>
        </div>
    </div>

    <!-- Pending Staff Account Approvals Queue -->
    @if(isset($pendingUsers) && $pendingUsers->isNotEmpty())
    <div class="bg-amber-50 border-2 border-amber-300 p-5 rounded-2xl shadow-md space-y-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-amber-500 animate-ping"></span>
                <h3 class="font-extrabold text-sm text-amber-900">Pending Staff Account Approvals ({{ $pendingUsers->count() }})</h3>
            </div>
            <span class="text-xs font-bold text-amber-800">Requires Admin Approval</span>
        </div>

        <div class="divide-y divide-amber-200 bg-white rounded-xl border border-amber-200 overflow-hidden">
            @foreach($pendingUsers as $pu)
                <div class="p-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-amber-50/50">
                    <div>
                        <div class="font-extrabold text-sm text-slate-900">{{ $pu->name }}</div>
                        <div class="text-xs text-slate-500">{{ $pu->email }} &bull; Registered: {{ $pu->created_at->diffForHumans() }}</div>
                    </div>

                    <div class="flex items-center gap-2">
                        <form method="POST" action="{{ route('admin.attendance.users.approve', $pu) }}">
                            @csrf
                            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs py-1.5 px-3 rounded-lg shadow transition">
                                Approve Account
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.attendance.users.reject', $pu) }}" onsubmit="return confirm('Reject this account application?');">
                            @csrf
                            <button type="submit" class="bg-slate-200 hover:bg-rose-100 text-rose-700 font-extrabold text-xs py-1.5 px-3 rounded-lg transition">
                                Reject
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Pending Device Passkey Approvals Queue -->
    @if(isset($pendingCredentials) && $pendingCredentials->isNotEmpty())
    <div class="bg-sky-50 border-2 border-sky-300 p-5 rounded-2xl shadow-md space-y-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-sky-500 animate-ping"></span>
                <h3 class="font-extrabold text-sm text-sky-900">Pending Device Passkey Registrations ({{ $pendingCredentials->count() }})</h3>
            </div>
            <span class="text-xs font-bold text-sky-800">Requires Admin Approval</span>
        </div>

        <div class="divide-y divide-sky-200 bg-white rounded-xl border border-sky-200 overflow-hidden">
            @foreach($pendingCredentials as $pc)
                <div class="p-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-sky-50/50">
                    <div>
                        <div class="font-extrabold text-sm text-slate-900">{{ $pc->user->name }}</div>
                        <div class="text-xs text-slate-500">Device: <strong>{{ $pc->device_name ?: 'WebAuthn Device' }}</strong> &bull; Submitted: {{ $pc->registered_at ? $pc->registered_at->diffForHumans() : 'Recently' }}</div>
                    </div>

                    <div class="flex items-center gap-2">
                        <form method="POST" action="{{ route('admin.attendance.credentials.approve', $pc) }}">
                            @csrf
                            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs py-1.5 px-3 rounded-lg shadow transition">
                                Approve Device
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.attendance.credentials.reject', $pc) }}" onsubmit="return confirm('Reject this device registration?');">
                            @csrf
                            <button type="submit" class="bg-slate-200 hover:bg-rose-100 text-rose-700 font-extrabold text-xs py-1.5 px-3 rounded-lg transition">
                                Reject Device
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Overview Metric Cards -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Present Today</span>
            <span class="text-2xl font-black text-slate-900 mt-1 block">{{ $presentCount }}</span>
            <span class="text-[11px] text-slate-500 mt-0.5 block">of {{ $totalStaffCount }} total staff</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Currently Present</span>
            <span class="text-2xl font-black text-emerald-600 mt-1 block">{{ $currentlyPresentCount }}</span>
            <span class="text-[11px] text-slate-500 mt-0.5 block">Checked in & not out</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Late Arrival</span>
            <span class="text-2xl font-black text-amber-600 mt-1 block">{{ $lateCount }}</span>
            <span class="text-[11px] text-slate-500 mt-0.5 block">Exceeded threshold</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Checked Out</span>
            <span class="text-2xl font-black text-slate-700 mt-1 block">{{ $checkedOutCount }}</span>
            <span class="text-[11px] text-slate-500 mt-0.5 block">Completed shift</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Not Checked In</span>
            <span class="text-2xl font-black text-rose-600 mt-1 block">{{ $notCheckedInCount }}</span>
            <span class="text-[11px] text-slate-500 mt-0.5 block">Absent or pending</span>
        </div>
    </div>

    <!-- Filters & Actions Bar -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <form method="GET" action="{{ route('admin.attendance.index') }}" class="flex flex-wrap items-center gap-3">
            <input type="hidden" name="date" value="{{ $selectedDate }}">

            <select name="user_id" onchange="this.form.submit()" class="px-3 py-2 rounded-lg border border-slate-300 text-xs font-bold text-slate-700">
                <option value="">All Staff Members</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ $selectedUser == $u->id ? 'selected' : '' }}>{{ $u->name }} ({{ $u->role }})</option>
                @endforeach
            </select>

            <select name="status" onchange="this.form.submit()" class="px-3 py-2 rounded-lg border border-slate-300 text-xs font-bold text-slate-700">
                <option value="">All Statuses</option>
                <option value="present" {{ $selectedStatus === 'present' ? 'selected' : '' }}>Present</option>
                <option value="late" {{ $selectedStatus === 'late' ? 'selected' : '' }}>Late</option>
                <option value="half_day" {{ $selectedStatus === 'half_day' ? 'selected' : '' }}>Half Day</option>
                <option value="absent" {{ $selectedStatus === 'absent' ? 'selected' : '' }}>Absent</option>
                <option value="manual_entry" {{ $selectedStatus === 'manual_entry' ? 'selected' : '' }}>Manual Entry</option>
            </select>

            @if($selectedUser || $selectedStatus)
                <a href="{{ route('admin.attendance.index', ['date' => $selectedDate]) }}" class="text-xs text-rose-600 font-bold hover:underline">Clear Filters</a>
            @endif
        </form>

        <button type="button" onclick="document.getElementById('manual-modal').classList.remove('hidden')" class="bg-[#0f172a] hover:bg-slate-800 text-white font-bold text-xs py-2 px-3.5 rounded-lg shadow flex items-center gap-2">
            <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>Add Manual Entry</span>
        </button>
    </div>

    <!-- Attendance Records Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between bg-slate-50">
            <h3 class="font-extrabold text-sm text-slate-800">Attendance Log for {{ $date->format('F j, Y') }}</h3>
            <span class="text-xs text-slate-500 font-semibold">{{ $records->total() }} record(s)</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-700">
                <thead class="bg-slate-100 uppercase tracking-wider font-extrabold text-[10px] text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="p-3.5">Staff Member</th>
                        <th class="p-3.5">Check In</th>
                        <th class="p-3.5">Check Out</th>
                        <th class="p-3.5">Network & Device</th>
                        <th class="p-3.5">Status</th>
                        <th class="p-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($records as $rec)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="p-3.5">
                                <div class="font-bold text-slate-900">{{ $rec->user->name }}</div>
                                <div class="text-[11px] text-slate-400">{{ $rec->user->email }} &bull; {{ $rec->user->role }}</div>
                            </td>
                            <td class="p-3.5">
                                <div class="font-bold text-slate-800">
                                    {{ $rec->check_in_at ? $rec->check_in_at->format('g:i A') : '--:--' }}
                                </div>
                                <div class="text-[10px] text-slate-400">IP: {{ $rec->check_in_ip ?: 'N/A' }}</div>
                            </td>
                            <td class="p-3.5">
                                <div class="font-bold text-slate-800">
                                    {{ $rec->check_out_at ? $rec->check_out_at->format('g:i A') : '--:--' }}
                                </div>
                                <div class="text-[10px] text-slate-400">IP: {{ $rec->check_out_ip ?: 'N/A' }}</div>
                            </td>
                            <td class="p-3.5 space-y-1">
                                <div>
                                    @if($rec->check_in_network_verified)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Verified Office IP</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600">Manual / Direct</span>
                                    @endif
                                </div>
                                <div class="text-[10px] text-slate-500 truncate max-w-[160px]">
                                    {{ $rec->checkInCredential ? $rec->checkInCredential->device_name : 'No Credential' }}
                                </div>
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
                            <td class="p-3.5 text-right space-x-2">
                                <a href="{{ route('admin.attendance.user-history', $rec->user) }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">History</a>
                                <button type="button" onclick="openCorrectionModal({{ $rec->id }}, '{{ $rec->user->name }}', '{{ $rec->status }}', '{{ $rec->check_in_at ? $rec->check_in_at->format('Y-m-d\TH:i') : '' }}', '{{ $rec->check_out_at ? $rec->check_out_at->format('Y-m-d\TH:i') : '' }}', '{{ addslashes($rec->notes ?? '') }}')" class="text-xs font-bold text-slate-600 hover:text-slate-900 border border-slate-300 px-2 py-1 rounded">Correct</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-400 text-xs">No attendance records for this date.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-slate-100">
            {{ $records->links() }}
        </div>
    </div>
</div>

<!-- Manual Correction Modal -->
<div id="correction-modal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4 border border-slate-200">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="font-extrabold text-base text-slate-900">Manual Correction</h3>
            <button type="button" onclick="document.getElementById('correction-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-lg font-bold">&times;</button>
        </div>

        <form id="correction-form" method="POST" action="" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Staff Member</label>
                <input type="text" id="corr-user-name" disabled class="w-full px-3 py-2 bg-slate-100 border border-slate-300 rounded-lg text-xs font-bold text-slate-700">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Status</label>
                <select name="status" id="corr-status" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-bold text-slate-800">
                    <option value="present">Present</option>
                    <option value="late">Late</option>
                    <option value="half_day">Half Day</option>
                    <option value="absent">Absent</option>
                    <option value="manual_entry">Manual Entry</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Check In Time</label>
                    <input type="datetime-local" name="check_in_at" id="corr-check-in" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-bold">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Check Out Time</label>
                    <input type="datetime-local" name="check_out_at" id="corr-check-out" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-bold">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-rose-700 mb-1">Audit Reason (Required)</label>
                <textarea name="reason" required placeholder="Explain why this manual change is being made..." class="w-full px-3 py-2 border border-rose-300 rounded-lg text-xs font-medium focus:ring-2 focus:ring-rose-500"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('correction-modal').classList.add('hidden')" class="px-4 py-2 text-xs font-bold text-slate-600 hover:text-slate-800">Cancel</button>
                <button type="submit" class="bg-[#0f172a] hover:bg-slate-800 text-white font-bold text-xs py-2 px-4 rounded-lg shadow">Save & Audit</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Manual Entry Modal -->
<div id="manual-modal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4 border border-slate-200">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="font-extrabold text-base text-slate-900">Add Manual Attendance Entry</h3>
            <button type="button" onclick="document.getElementById('manual-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-lg font-bold">&times;</button>
        </div>

        <form method="POST" action="{{ route('admin.attendance.manual-record') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Select Staff Member</label>
                <select name="user_id" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-bold text-slate-800">
                    <option value="">-- Choose Employee --</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Attendance Date</label>
                <input type="date" name="attendance_date" value="{{ $selectedDate }}" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-bold">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Status</label>
                <select name="status" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-bold">
                    <option value="present">Present</option>
                    <option value="late">Late</option>
                    <option value="half_day">Half Day</option>
                    <option value="absent">Absent</option>
                    <option value="manual_entry">Manual Entry</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Check In Time</label>
                    <input type="datetime-local" name="check_in_at" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-bold">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Check Out Time</label>
                    <input type="datetime-local" name="check_out_at" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-bold">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-rose-700 mb-1">Audit Reason (Required)</label>
                <textarea name="reason" required placeholder="Explain why this entry is being manually added..." class="w-full px-3 py-2 border border-rose-300 rounded-lg text-xs font-medium focus:ring-2 focus:ring-rose-500"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('manual-modal').classList.add('hidden')" class="px-4 py-2 text-xs font-bold text-slate-600 hover:text-slate-800">Cancel</button>
                <button type="submit" class="bg-[#0f172a] hover:bg-slate-800 text-white font-bold text-xs py-2 px-4 rounded-lg shadow">Create Entry</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function openCorrectionModal(id, name, status, checkIn, checkOut, notes) {
        document.getElementById('correction-form').action = "/admin/attendance/correct/" + id;
        document.getElementById('corr-user-name').value = name;
        document.getElementById('corr-status').value = status;
        document.getElementById('corr-check-in').value = checkIn;
        document.getElementById('corr-check-out').value = checkOut;
        document.getElementById('correction-modal').classList.remove('hidden');
    }
</script>
@endpush
