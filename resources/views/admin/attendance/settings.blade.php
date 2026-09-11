@extends('layouts.admin')

@section('title', 'Attendance Rules & Settings - Admin Console')

@section('content')
<div class="space-y-6 max-w-3xl">

    <div>
        <h1 class="text-2xl font-extrabold text-slate-900">Attendance Rules & Settings</h1>
        <p class="text-xs text-slate-500">Configure global shift timings, late thresholds, timezone, and working days</p>
    </div>

    <form method="POST" action="{{ route('admin.attendance.settings.update') }}" class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm space-y-6">
        @csrf

        <!-- Global Enable Switch -->
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
            <div>
                <h3 class="font-extrabold text-sm text-slate-900">Enable Attendance Module</h3>
                <p class="text-xs text-slate-500">Allow staff members to check in and out from the office network</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" name="attendance_enabled" value="1" {{ $settings['attendance_enabled'] ? 'checked' : '' }} class="sr-only peer">
                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
            </label>
        </div>

        <!-- Shift Timings Grid -->
        <div class="space-y-4">
            <h3 class="font-extrabold text-sm text-slate-900 border-b border-slate-100 pb-2">Office Timings & Thresholds</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Check-in Start Time</label>
                    <input type="time" name="check_in_start_time" value="{{ $settings['check_in_start_time'] }}" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-bold">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Expected Arrival Time</label>
                    <input type="time" name="expected_arrival_time" value="{{ $settings['expected_arrival_time'] }}" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-bold">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Late Threshold (Minutes)</label>
                    <input type="number" name="late_threshold_minutes" value="{{ $settings['late_threshold_minutes'] }}" required min="0" max="180" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-bold">
                    <p class="text-[10px] text-slate-400 mt-0.5">Grace period in minutes after expected arrival before marked Late.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Check-in Closing Time</label>
                    <input type="time" name="check_in_closing_time" value="{{ $settings['check_in_closing_time'] }}" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-bold">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Check-out Availability Time</label>
                    <input type="time" name="check_out_availability_time" value="{{ $settings['check_out_availability_time'] }}" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-bold">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">System Timezone</label>
                    <select name="timezone" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-bold text-slate-800">
                        <option value="UTC" {{ $settings['timezone'] === 'UTC' ? 'selected' : '' }}>UTC</option>
                        <option value="Africa/Lagos" {{ $settings['timezone'] === 'Africa/Lagos' ? 'selected' : '' }}>Africa/Lagos (WAT)</option>
                        <option value="Europe/London" {{ $settings['timezone'] === 'Europe/London' ? 'selected' : '' }}>Europe/London (GMT/BST)</option>
                        <option value="America/New_York" {{ $settings['timezone'] === 'America/New_York' ? 'selected' : '' }}>America/New_York (EST)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Working Days Selection -->
        <div class="space-y-3 pt-2 border-t border-slate-100">
            <h3 class="font-extrabold text-sm text-slate-900">Working Days</h3>
            <div class="flex flex-wrap gap-4">
                @php
                    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                    $activeDays = is_array($settings['working_days']) ? $settings['working_days'] : [];
                @endphp
                @foreach($days as $d)
                    <label class="inline-flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer">
                        <input type="checkbox" name="working_days[]" value="{{ $d }}" {{ in_array($d, $activeDays) ? 'checked' : '' }} class="rounded border-slate-300 text-sky-600">
                        <span>{{ $d }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="pt-4 border-t border-slate-100 flex justify-end">
            <button type="submit" class="bg-[#0A1428] hover:bg-slate-800 text-white font-bold text-xs py-2.5 px-6 rounded-lg shadow">
                Save Attendance Rules
            </button>
        </div>
    </form>
</div>
@endsection
