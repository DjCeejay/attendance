@extends('layouts.app')

@section('title', 'Sign In - ARTSCI Attendance')

@section('content')
<div class="bg-white rounded-2xl shadow-xl border border-slate-200/80 p-6 md:p-8 mt-6">
    <div class="text-center mb-6">
        <div class="w-14 h-14 bg-[#0A1428] rounded-2xl mx-auto flex items-center justify-center text-white font-black text-xl shadow-lg mb-3">
            AT
        </div>
        <h2 class="text-2xl font-extrabold text-slate-900">Sign In to Attendance</h2>
        <p class="text-xs text-slate-500 mt-1">Use your ARTSCI staff account credentials</p>
    </div>

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Email Address</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 text-sm outline-none transition">
        </div>

        <div>
            <label for="password" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Password</label>
            <input type="password" name="password" id="password" required class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 text-sm outline-none transition">
        </div>

        <div class="flex items-center justify-between text-xs text-slate-600">
            <label class="flex items-center gap-2 cursor-pointer select-none">
                <input type="checkbox" name="remember" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                <span>Remember me</span>
            </label>
        </div>

        <button type="submit" class="w-full bg-[#0A1428] hover:bg-slate-800 text-white font-extrabold text-sm py-3.5 px-4 rounded-xl shadow-md transition btn-touch mt-2">
            Sign In
        </button>
    </form>
</div>
@endsection
