@extends('layouts.app')

@section('title', 'My Payroll & Deductions')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-black text-slate-900 tracking-tight">Payroll & Salary Statement</h1>
            <p class="text-xs text-slate-500 font-medium">Monthly base salary, lateness deductions, and net balance</p>
        </div>
        <div>
            <form method="GET" action="{{ route('staff.payroll.index') }}" class="flex items-center gap-2">
                <label for="pay_period" class="text-xs font-bold text-slate-600">Pay Period:</label>
                <input type="month" id="pay_period" name="pay_period" value="{{ $selectedPeriod }}" onchange="this.form.submit()" class="text-xs font-bold bg-slate-50 border border-slate-300 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500">
            </form>
        </div>
    </div>

    <!-- Salary Balance Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Base Salary -->
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200/80">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Base Monthly Salary</div>
            <div class="text-2xl font-black text-slate-900">₦{{ number_format($balance['base_salary'], 2) }}</div>
            <div class="text-[11px] text-slate-400 font-medium mt-1">Configured monthly base</div>
        </div>

        <!-- Total Deductions -->
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200/80">
            <div class="text-xs font-bold text-rose-500 uppercase tracking-wider mb-1">Deductions ({{ $selectedPeriod }})</div>
            <div class="text-2xl font-black text-rose-600">-₦{{ number_format($balance['total_deductions'], 2) }}</div>
            <div class="text-[11px] text-rose-500/80 font-medium mt-1">
                {{ $balance['late_count'] }} late check-in penalty @ ₦500
                @if($balance['total_waived'] > 0)
                    &bull; <span class="text-emerald-600">₦{{ number_format($balance['total_waived']) }} Waived</span>
                @endif
            </div>
        </div>

        <!-- Net Payable Salary -->
        <div class="bg-gradient-to-br from-indigo-900 to-slate-900 text-white p-5 rounded-2xl shadow-sm">
            <div class="text-xs font-bold text-indigo-300 uppercase tracking-wider mb-1">Net Balance Payable</div>
            <div class="text-2xl font-black text-emerald-400">₦{{ number_format($balance['net_salary'], 2) }}</div>
            <div class="text-[11px] text-indigo-200/80 font-medium mt-1">Estimated payout for {{ $selectedPeriod }}</div>
        </div>
    </div>

    <!-- Itemized Deductions List -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900">Deduction History ({{ $selectedPeriod }})</h2>
            <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-600">{{ count($balance['deductions']) }} Records</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 font-bold uppercase tracking-wider border-b border-slate-100">
                        <th class="p-3.5">Date</th>
                        <th class="p-3.5">Reason</th>
                        <th class="p-3.5">Amount</th>
                        <th class="p-3.5">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                    @forelse($balance['deductions'] as $d)
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="p-3.5 font-bold text-slate-900">
                                {{ $d->created_at->format('M j, Y - g:i A') }}
                            </td>
                            <td class="p-3.5">
                                {{ $d->reason ?: 'Lateness penalty' }}
                            </td>
                            <td class="p-3.5 font-black {{ $d->isWaived() ? 'text-slate-400 line-through' : 'text-rose-600' }}">
                                ₦{{ number_format($d->amount, 2) }}
                            </td>
                            <td class="p-3.5">
                                @if($d->isWaived())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800" title="Waived: {{ $d->waiver_reason }}">
                                        Waived by Admin
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800">
                                        Deducted
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-8 text-center text-slate-400 text-xs">
                                No salary deductions recorded for {{ $selectedPeriod }}. Great job punctually resuming work! 🎉
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Historical Payroll Archive -->
    @if($archives->count() > 0)
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5 space-y-3">
            <h3 class="text-xs font-bold uppercase text-slate-400 tracking-wider">Payroll History Archives</h3>
            <div class="divide-y divide-slate-100">
                @foreach($archives as $archive)
                    <div class="py-2.5 flex items-center justify-between text-xs">
                        <div>
                            <span class="font-bold text-slate-900">{{ $archive->pay_period }}</span>
                            <span class="text-slate-400 text-[11px] ml-2">Reset on {{ $archive->reset_at ? $archive->reset_at->format('M j, Y') : 'N/A' }}</span>
                        </div>
                        <div class="font-bold text-slate-800">
                            Base: ₦{{ number_format($archive->base_salary) }} &bull; Deductions: <span class="text-rose-600">-₦{{ number_format($archive->total_deductions) }}</span> &bull; Net: <span class="text-emerald-700">₦{{ number_format($archive->net_salary) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
