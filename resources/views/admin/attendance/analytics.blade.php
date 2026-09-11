@extends('layouts.admin')

@section('title', 'Staff Attendance Analytics - Admin Console')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900">Staff Attendance Analytics & Trends</h1>
            <p class="text-xs text-slate-500">Track employee punctuality, early/late patterns, and multi-period late counts (Weekly, Monthly, Yearly, All-Time)</p>
        </div>

        <a href="{{ route('admin.attendance.index') }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
            &larr; Back to Dashboard Overview
        </a>
    </div>

    <!-- Company Punctuality Metric Cards -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Avg Punctuality Rate</span>
            <span class="text-2xl font-black text-indigo-600 mt-1 block">{{ $avgPunctualityRate }}%</span>
            <span class="text-[11px] text-slate-500 mt-0.5 block">Across all active staff</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Late This Week</span>
            <span class="text-2xl font-black text-amber-600 mt-1 block">{{ $totalLateThisWeek }}</span>
            <span class="text-[11px] text-slate-500 mt-0.5 block">Current week total</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Late This Month</span>
            <span class="text-2xl font-black text-amber-600 mt-1 block">{{ $totalLateThisMonth }}</span>
            <span class="text-[11px] text-slate-500 mt-0.5 block">Current month total</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Late This Year</span>
            <span class="text-2xl font-black text-rose-600 mt-1 block">{{ $totalLateThisYear }}</span>
            <span class="text-[11px] text-slate-500 mt-0.5 block">Current year total</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Late All-Time</span>
            <span class="text-2xl font-black text-slate-800 mt-1 block">{{ $totalLateAllTime }}</span>
            <span class="text-[11px] text-slate-500 mt-0.5 block">All-time historical total</span>
        </div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
        <form method="GET" action="{{ route('admin.attendance.analytics') }}" class="flex flex-wrap items-center gap-3">
            <div class="flex-1 min-w-[200px]">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search staff member by name or email..." class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500">
            </div>

            <select name="role" onchange="this.form.submit()" class="px-3 py-2 border border-slate-300 rounded-lg text-xs font-bold text-slate-700">
                <option value="">All Roles</option>
                <option value="user" {{ $roleFilter === 'user' ? 'selected' : '' }}>User / Staff</option>
                <option value="admin" {{ $roleFilter === 'admin' ? 'selected' : '' }}>Admin</option>
                <option value="manager" {{ $roleFilter === 'manager' ? 'selected' : '' }}>Manager</option>
                <option value="executive" {{ $roleFilter === 'executive' ? 'selected' : '' }}>Executive</option>
                <option value="super_admin" {{ $roleFilter === 'super_admin' ? 'selected' : '' }}>Super Admin</option>
            </select>

            <button type="submit" class="bg-[#0f172a] hover:bg-slate-800 text-white font-bold text-xs py-2 px-4 rounded-lg shadow">Filter Analytics</button>
            @if($search || $roleFilter)
                <a href="{{ route('admin.attendance.analytics') }}" class="text-xs font-bold text-rose-600 hover:underline">Clear Filters</a>
            @endif
        </form>
    </div>

    <!-- Staff Punctuality & Late Count Matrix -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between bg-slate-50">
            <h3 class="font-extrabold text-sm text-slate-800">Staff Punctuality & Late Counts Matrix</h3>
            <span class="text-xs text-slate-500 font-semibold">{{ $staffAnalytics->count() }} staff member(s)</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-700">
                <thead class="bg-slate-100 uppercase tracking-wider font-extrabold text-[10px] text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="p-3.5">Staff Member</th>
                        <th class="p-3.5 text-center">Late (Week)</th>
                        <th class="p-3.5 text-center">Late (Month)</th>
                        <th class="p-3.5 text-center">Late (Year)</th>
                        <th class="p-3.5 text-center">Late (All-Time)</th>
                        <th class="p-3.5 text-center">Days Present</th>
                        <th class="p-3.5 text-center">Punctuality Rate</th>
                        <th class="p-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($staffAnalytics as $item)
                        @php
                            $u = $item['user'];
                            $pRate = $item['punctuality_rate'];
                            $pColor = $pRate >= 90 ? 'bg-emerald-100 text-emerald-800' : ($pRate >= 75 ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800');
                        @endphp
                        <tr class="hover:bg-slate-50 transition">
                            <td class="p-3.5">
                                <div class="font-bold text-slate-900">{{ $u->name }}</div>
                                <div class="text-[11px] text-slate-400">{{ $u->email }} &bull; <span class="uppercase">{{ $u->role }}</span></div>
                            </td>
                            <td class="p-3.5 text-center font-extrabold">
                                @if($item['late_this_week'] > 0)
                                    <span class="px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-800 font-black">{{ $item['late_this_week'] }}</span>
                                @else
                                    <span class="text-slate-400">0</span>
                                @endif
                            </td>
                            <td class="p-3.5 text-center font-extrabold">
                                @if($item['late_this_month'] > 0)
                                    <span class="px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-800 font-black">{{ $item['late_this_month'] }}</span>
                                @else
                                    <span class="text-slate-400">0</span>
                                @endif
                            </td>
                            <td class="p-3.5 text-center font-extrabold">
                                @if($item['late_this_year'] > 0)
                                    <span class="px-2 py-0.5 rounded text-xs bg-rose-100 text-rose-800 font-black">{{ $item['late_this_year'] }}</span>
                                @else
                                    <span class="text-slate-400">0</span>
                                @endif
                            </td>
                            <td class="p-3.5 text-center font-extrabold">
                                @if($item['late_all_time'] > 0)
                                    <span class="px-2 py-0.5 rounded text-xs bg-slate-200 text-slate-800 font-black">{{ $item['late_all_time'] }}</span>
                                @else
                                    <span class="text-slate-400">0</span>
                                @endif
                            </td>
                            <td class="p-3.5 text-center font-bold text-slate-700">
                                {{ $item['present_all_time'] }}
                            </td>
                            <td class="p-3.5 text-center">
                                <span class="px-2.5 py-1 rounded-full text-xs font-black {{ $pColor }}">
                                    {{ $pRate }}%
                                </span>
                            </td>
                            <td class="p-3.5 text-right">
                                <a href="{{ route('admin.attendance.user-history', $u) }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 border border-indigo-200 px-2.5 py-1 rounded-lg hover:bg-indigo-50 transition">
                                    View Full History
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-8 text-center text-slate-400 text-xs">No staff members found matching criteria.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
