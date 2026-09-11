@extends('layouts.app')

@section('title', 'Staff Registration - Staff Attendance')

@section('content')
<div class="bg-white rounded-2xl shadow-xl border border-slate-200/80 p-6 md:p-8 mt-6">
    <div class="text-center mb-6">
        <div class="w-14 h-14 bg-[#0f172a] rounded-2xl mx-auto flex items-center justify-center text-white font-black text-xl shadow-lg mb-3">
            SA
        </div>
        <h2 class="text-2xl font-extrabold text-slate-900">Staff Account Registration</h2>
        <p class="text-xs text-slate-500 mt-1">Fill in your details below to register a new staff account</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <div>
            <label for="name" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Full Name</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required autofocus placeholder="e.g. John Doe" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm outline-none transition">
        </div>

        <div>
            <label for="email" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Email Address</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required placeholder="your.name@company.com" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm outline-none transition">
        </div>

        <div>
            <label for="role" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Staff Organization / Role</label>
            <select name="role" id="role" required class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm outline-none transition font-bold text-slate-800">
                <option value="afc_staff" {{ old('role') === 'afc_staff' ? 'selected' : '' }}>AFC Staff</option>
                <option value="artsci_staff" {{ old('role') === 'artsci_staff' ? 'selected' : '' }}>ARTSCI Staff</option>
            </select>
        </div>

        <div>
            <label for="password" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Password</label>
            <input type="password" name="password" id="password" required minlength="8" placeholder="At least 8 characters" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm outline-none transition">
        </div>

        <div>
            <label for="password_confirmation" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Confirm Password</label>
            <input type="password" name="password_confirmation" id="password_confirmation" required minlength="8" placeholder="Re-enter password" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm outline-none transition">
        </div>

        <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-xs text-amber-900 font-semibold flex items-start gap-2">
            <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>Notice: New staff registrations require administrator approval before you can sign in.</span>
        </div>

        <button type="submit" class="w-full bg-[#0f172a] hover:bg-slate-800 text-white font-extrabold text-sm py-3.5 px-4 rounded-xl shadow-md transition btn-touch mt-2">
            Register Account
        </button>

        <div class="text-center pt-2 border-t border-slate-100">
            <p class="text-xs text-slate-600">
                Already have an account? 
                <a href="{{ route('login') }}" class="font-extrabold text-indigo-600 hover:text-indigo-800">Sign In here</a>
            </p>
        </div>
    </form>
</div>
@endsection
