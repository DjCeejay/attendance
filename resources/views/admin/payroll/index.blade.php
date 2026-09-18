@extends('layouts.admin')

@section('title', 'Payroll & Lateness Management')

@section('content')
<div class="space-y-6">
    <!-- Top Header -->
    <div class="bg-white p-4 sm:p-6 rounded-2xl shadow-sm border border-slate-200/80 flex flex-col gap-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-lg sm:text-xl font-black text-slate-900 tracking-tight truncate">Payroll & Staff Salary Management</h1>
                <p class="text-xs text-slate-500 font-medium mt-0.5">Manage base salaries, shift schedules, lateness penalties, and execute monthly payroll reset</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <form method="GET" action="{{ route('admin.payroll.index') }}" class="flex items-center gap-2">
                    <label for="pay_period" class="text-xs font-bold text-slate-600">Period:</label>
                    <input type="month" id="pay_period" name="pay_period" value="{{ $selectedPeriod }}" onchange="this.form.submit()" class="text-xs font-bold bg-slate-50 border border-slate-300 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500">
                </form>
                <button type="button" onclick="openResetModal()" class="px-3 py-2 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-xl transition shadow-sm whitespace-nowrap">
                    Monthly Payroll Reset
                </button>
            </div>
        </div>
    </div>

    <!-- Overview Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200/80 min-w-0 overflow-hidden">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1 truncate">Total Base Payroll ({{ $selectedPeriod }})</div>
            <div class="stat-number font-black text-slate-900 font-mono tracking-tight truncate">₦{{ number_format($grandBaseSalary, 2) }}</div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200/80 min-w-0 overflow-hidden">
            <div class="text-xs font-bold text-rose-500 uppercase tracking-wider mb-1 truncate">Total Lateness Deductions</div>
            <div class="stat-number font-black text-rose-600 font-mono tracking-tight truncate">-₦{{ number_format($grandTotalDeductions, 2) }}</div>
        </div>
        <div class="bg-gradient-to-br from-indigo-900 to-slate-900 text-white p-5 rounded-2xl shadow-sm min-w-0 overflow-hidden">
            <div class="text-xs font-bold text-indigo-300 uppercase tracking-wider mb-1 truncate">Total Net Payable</div>
            <div class="stat-number font-black text-emerald-400 font-mono tracking-tight truncate">₦{{ number_format($grandNetSalary, 2) }}</div>
        </div>
    </div>

    <!-- Staff Salaries & Shift Configuration Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900">Staff Salaries & Shift Assignments</h2>
            <a href="{{ route('admin.shifts.index') }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">Manage Shifts &rarr;</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 font-bold uppercase tracking-wider border-b border-slate-100">
                        <th class="p-3.5">Staff Member</th>
                        <th class="p-3.5">Dept</th>
                        <th class="p-3.5">Base Salary (₦)</th>
                        <th class="p-3.5">Assigned Shift</th>
                        <th class="p-3.5">Off-Days</th>
                        <th class="p-3.5">Deductions</th>
                        <th class="p-3.5">Net Payable</th>
                        <th class="p-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                    @forelse($staffPayrollData as $item)
                        @php
                            $st = $item['user'];
                            $sum = $item['summary'];
                            $prof = $st->staffProfile;
                            $offDaysArray = $prof?->off_days ?: [];
                            $dayNames = [0 => 'Sun', 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat'];
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="p-3.5">
                                <div class="font-bold text-slate-900">{{ $st->name }}</div>
                                <div class="text-[11px] text-slate-400">{{ $st->email }}</div>
                            </td>
                            <td class="p-3.5">
                                <span class="uppercase font-extrabold text-[10px] px-2 py-0.5 rounded {{ $st->isArtsciStaff() ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' }}">
                                    {{ $st->role_label }}
                                </span>
                            </td>
                            <td class="p-3.5 font-bold text-slate-900">
                                ₦{{ number_format($sum['base_salary'], 2) }}
                            </td>
                            <td class="p-3.5">
                                @if($prof?->shift)
                                    <span class="font-bold text-slate-800">{{ $prof->shift->name }}</span>
                                    <div class="text-[10px] text-slate-400">{{ $prof->shift->formatted_resumption }}</div>
                                @elseif($prof?->custom_resumption_time)
                                    <span class="font-bold text-slate-800">Custom: {{ \Carbon\Carbon::parse($prof->custom_resumption_time)->format('g:i A') }}</span>
                                @else
                                    <span class="text-slate-400">Default (08:00 AM)</span>
                                @endif
                            </td>
                            <td class="p-3.5">
                                @if(empty($offDaysArray))
                                    <span class="text-slate-400 text-[11px]">None</span>
                                @else
                                    <div class="flex gap-1">
                                        @foreach($offDaysArray as $dNum)
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                                {{ $dayNames[$dNum] ?? $dNum }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="p-3.5 font-bold text-rose-600">
                                -₦{{ number_format($sum['total_deductions'], 2) }}
                                <div class="text-[10px] text-slate-400 font-normal">{{ $sum['late_count'] }} late</div>
                            </td>
                            <td class="p-3.5 font-black text-emerald-700">
                                ₦{{ number_format($sum['net_salary'], 2) }}
                            </td>
                            <td class="p-3.5 text-right">
                                <button type="button" onclick="openStaffEditModal({{ $st->id }}, '{{ addslashes($st->name) }}', {{ $prof?->base_salary ?: 0 }}, '{{ $prof?->shift_id ?: '' }}', '{{ $prof?->custom_resumption_time ? \Carbon\Carbon::parse($prof->custom_resumption_time)->format('H:i') : '' }}', {{ json_encode($offDaysArray) }}, {{ $prof?->grace_period_minutes ?: 15 }})" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 border border-indigo-200 px-2.5 py-1 rounded-lg hover:bg-indigo-50 transition">
                                    Edit Salary & Shift
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-8 text-center text-slate-400 text-xs">No active staff members found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Lateness Deductions & Waivers Log -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900">Lateness Penalties Log ({{ $selectedPeriod }})</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 font-bold uppercase tracking-wider border-b border-slate-100">
                        <th class="p-3.5">Staff Member</th>
                        <th class="p-3.5">Date & Time</th>
                        <th class="p-3.5">Reason</th>
                        <th class="p-3.5">Amount</th>
                        <th class="p-3.5">Status</th>
                        <th class="p-3.5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                    @forelse($recentDeductions as $ded)
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="p-3.5">
                                <div class="font-bold text-slate-900">{{ $ded->user->name }}</div>
                                <div class="text-[11px] text-slate-400">{{ $ded->user->email }}</div>
                            </td>
                            <td class="p-3.5">
                                {{ $ded->created_at->format('M j, Y - g:i A') }}
                            </td>
                            <td class="p-3.5">
                                {{ $ded->reason ?: 'Lateness penalty' }}
                            </td>
                            <td class="p-3.5 font-bold {{ $ded->isWaived() ? 'line-through text-slate-400' : 'text-rose-600' }}">
                                ₦{{ number_format($ded->amount, 2) }}
                            </td>
                            <td class="p-3.5">
                                @if($ded->isWaived())
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800" title="Reason: {{ $ded->waiver_reason }}">
                                        Waived by {{ $ded->waivedBy?->name ?: 'Admin' }}
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800">
                                        Active Penalty
                                    </span>
                                @endif
                            </td>
                            <td class="p-3.5 text-right">
                                @if(!$ded->isWaived())
                                    <button type="button" onclick="openWaiveModal({{ $ded->id }}, '{{ addslashes($ded->user->name) }}', {{ $ded->amount }})" class="text-xs font-bold text-emerald-600 hover:text-emerald-800 border border-emerald-300 px-2.5 py-1 rounded-lg hover:bg-emerald-50 transition">
                                        Waive Penalty
                                    </button>
                                @else
                                    <span class="text-slate-400 text-[11px] italic">Waived</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-400 text-xs">No lateness deductions logged for {{ $selectedPeriod }}.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100">
            {{ $recentDeductions->appends(request()->query())->links() }}
        </div>
    </div>
</div>

<!-- Modal: Edit Staff Salary & Shift Profile -->
<div id="staff-edit-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 space-y-4">
        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
            <h3 class="text-sm font-bold text-slate-900" id="edit-modal-title">Edit Staff Profile</h3>
            <button type="button" onclick="closeStaffEditModal()" class="text-slate-400 hover:text-slate-600">&times;</button>
        </div>

        <form id="staff-edit-form" method="POST" action="" class="space-y-4 text-xs">
            @csrf
            <div>
                <label class="block font-bold text-slate-700 mb-1">Base Monthly Salary (₦)</label>
                <input type="number" step="500" name="base_salary" id="modal_base_salary" required class="w-full px-3 py-2 border border-slate-300 rounded-lg font-bold">
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Assigned Shift</label>
                <select name="shift_id" id="modal_shift_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    <option value="">-- Custom / Default Resumption --</option>
                    @foreach($shifts as $sh)
                        <option value="{{ $sh->id }}">{{ $sh->name }} ({{ $sh->formatted_resumption }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Custom Resumption Time (Optional override)</label>
                <input type="time" name="custom_resumption_time" id="modal_custom_resumption" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Grace Period (Minutes)</label>
                <input type="number" name="grace_period_minutes" id="modal_grace_period" required class="w-full px-3 py-2 border border-slate-300 rounded-lg">
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Assigned Off-Days (Check off-days)</label>
                <div class="grid grid-cols-4 gap-2 pt-1">
                    @php $days = [0 => 'Sun', 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat']; @endphp
                    @foreach($days as $val => $label)
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" name="off_days[]" value="{{ $val }}" id="off_day_{{ $val }}" class="rounded text-indigo-600">
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeStaffEditModal()" class="px-4 py-2 font-bold text-slate-600 hover:text-slate-800">Cancel</button>
                <button type="submit" class="px-4 py-2 font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Waive Lateness Penalty -->
<div id="waive-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 space-y-4">
        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
            <h3 class="text-sm font-bold text-slate-900">Waive Lateness Penalty</h3>
            <button type="button" onclick="closeWaiveModal()" class="text-slate-400 hover:text-slate-600">&times;</button>
        </div>

        <form id="waive-form" method="POST" action="" class="space-y-4 text-xs">
            @csrf
            <p class="text-slate-600">You are waiving the ₦<span id="waive-amount">500</span> lateness penalty for <strong id="waive-staff-name">Staff</strong>.</p>
            <div>
                <label class="block font-bold text-slate-700 mb-1">Reason for Waiver (Required audit note)</label>
                <textarea name="waiver_reason" required placeholder="e.g., Official assignment / Approved leave / Vehicle breakdown" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500"></textarea>
            </div>
            <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeWaiveModal()" class="px-4 py-2 font-bold text-slate-600 hover:text-slate-800">Cancel</button>
                <button type="submit" class="px-4 py-2 font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg">Confirm Waiver</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Monthly Payroll Reset -->
<div id="reset-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 space-y-4">
        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
            <h3 class="text-sm font-bold text-rose-700">Monthly Payroll Reset Confirmation</h3>
            <button type="button" onclick="closeResetModal()" class="text-slate-400 hover:text-slate-600">&times;</button>
        </div>

        <form method="POST" action="{{ route('admin.payroll.reset') }}" class="space-y-4 text-xs">
            @csrf
            <div class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-rose-900 leading-relaxed">
                <strong>Warning:</strong> Executing Monthly Reset will snapshot and archive all active deductions for period <strong>{{ $selectedPeriod }}</strong> into payroll history archives.
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Pay Period to Archive & Reset</label>
                <input type="month" name="pay_period" value="{{ $selectedPeriod }}" required class="w-full px-3 py-2 border border-slate-300 rounded-lg font-bold">
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeResetModal()" class="px-4 py-2 font-bold text-slate-600 hover:text-slate-800">Cancel</button>
                <button type="submit" class="px-4 py-2 font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-lg">Execute Reset & Archive</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openStaffEditModal(userId, name, baseSalary, shiftId, customTime, offDays, gracePeriod) {
        document.getElementById('edit-modal-title').innerText = 'Edit Profile: ' + name;
        document.getElementById('staff-edit-form').action = '/admin/payroll/staff/' + userId;
        document.getElementById('modal_base_salary').value = baseSalary;
        document.getElementById('modal_shift_id').value = shiftId || '';
        document.getElementById('modal_custom_resumption').value = customTime || '';
        document.getElementById('modal_grace_period').value = gracePeriod || 15;

        for (let i = 0; i <= 6; i++) {
            const chk = document.getElementById('off_day_' + i);
            if (chk) chk.checked = offDays.includes(i) || offDays.includes(String(i));
        }

        document.getElementById('staff-edit-modal').classList.remove('hidden');
    }

    function closeStaffEditModal() {
        document.getElementById('staff-edit-modal').classList.add('hidden');
    }

    function openWaiveModal(deductionId, staffName, amount) {
        document.getElementById('waive-form').action = '/admin/payroll/deductions/' + deductionId + '/waive';
        document.getElementById('waive-staff-name').innerText = staffName;
        document.getElementById('waive-amount').innerText = amount;
        document.getElementById('waive-modal').classList.remove('hidden');
    }

    function closeWaiveModal() {
        document.getElementById('waive-modal').classList.add('hidden');
    }

    function openResetModal() {
        document.getElementById('reset-modal').classList.remove('hidden');
    }

    function closeResetModal() {
        document.getElementById('reset-modal').classList.add('hidden');
    }
</script>
@endsection
