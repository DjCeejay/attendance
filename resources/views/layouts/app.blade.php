<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="/manifest.json">
    <title>@yield('title', 'Staff Attendance Portal')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --brand-dark: #0f172a;
            --brand-ink: #0f172a;
            --brand-soft: #f8fafc;
            --brand-border: #e2e8f0;
            --brand-accent: #4f46e5;
        }
        body {
            font-family: 'Manrope', system-ui, -apple-system, sans-serif;
            background: #f8fafc;
            color: #0f172a;
        }
        .btn-touch {
            min-height: 56px;
            touch-action: manipulation;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col justify-between antialiased selection:bg-indigo-500 selection:text-white">

    <header class="bg-[#0f172a] text-white shadow-md sticky top-0 z-40">
        <div class="max-w-md mx-auto px-4 py-3 flex items-center justify-between">
            <a href="{{ route('attendance.dashboard') }}" class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center font-black text-white text-sm shadow">
                    SA
                </div>
                <div>
                    <span class="font-extrabold text-base tracking-wide block leading-none">Staff Attendance</span>
                    <span class="text-[10px] text-indigo-300 tracking-wider uppercase font-semibold">Verification Portal</span>
                </div>
            </a>
            @auth
            <div class="flex items-center gap-3">
                <a href="{{ route('attendance.history') }}" class="text-xs font-semibold text-slate-300 hover:text-white transition">History</a>
                @if(auth()->user()->isAdmin())
                <a href="{{ route('admin.attendance.index') }}" class="text-xs font-bold bg-indigo-600 hover:bg-indigo-500 px-2.5 py-1 rounded text-white transition">Admin</a>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="text-xs font-semibold text-slate-300 hover:text-white transition">Logout</button>
                </form>
            </div>
            @endauth
        </div>
    </header>

    <main class="flex-1 max-w-md mx-auto w-full px-4 py-6">
        @if(session('success'))
            <div class="mb-4 p-3.5 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-semibold rounded-xl flex items-center gap-2 shadow-sm">
                <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error') || $errors->any())
            <div class="mb-4 p-3.5 bg-rose-50 border border-rose-200 text-rose-800 text-sm font-semibold rounded-xl flex items-center gap-2 shadow-sm">
                <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>
                    @if(session('error')) <div>{{ session('error') }}</div> @endif
                    @foreach($errors->all() as $err) <div>{{ $err }}</div> @endforeach
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="bg-white border-t border-slate-200 py-3 text-center text-xs text-slate-500">
        <div class="max-w-md mx-auto px-4 flex items-center justify-between">
            <span>&copy; {{ date('Y') }} Staff Attendance Portal</span>
            <span class="text-[11px] font-semibold text-slate-400">v2.0</span>
        </div>
    </footer>

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js').catch(err => console.log('SW reg error:', err));
            });
        }
    </script>
    @stack('scripts')
</body>
</html>
