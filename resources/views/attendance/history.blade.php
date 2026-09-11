@extends('layouts.app')

@section('title', 'Attendance History - ARTSCI')

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-extrabold text-slate-900">Attendance History</h2>
            <p class="text-xs text-slate-500">Your personal check-in & check-out records</p>
        </div>
        <a href="{{ route('attendance.dashboard') }}" class="text-xs font-bold text-sky-600 hover:text-sky-800">
            &larr; Dashboard
        </a>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-md overflow-hidden">
        @if($history->isEmpty())
            <div class="p-8 text-center text-slate-500 text-xs">
                No attendance records found.
            </div>
        @else
            <div class="divide-y divide-slate-100">
                @foreach($history as $rec)
                    <div class="p-4 flex items-center justify-between hover:bg-slate-50 transition">
                        <div>
                            <div class="text-sm font-extrabold text-slate-900">
                                {{ $rec->attendance_date->format('D, M j, Y') }}
                            </div>
                            <div class="text-xs text-slate-500 mt-1 flex items-center gap-3">
                                <span>In: <strong>{{ $rec->check_in_at ? $rec->check_in_at->format('g:i A') : '--' }}</strong></span>
                                <span>Out: <strong>{{ $rec->check_out_at ? $rec->check_out_at->format('g:i A') : '--' }}</strong></span>
                            </div>
                        </div>

                        <div>
                            @if($rec->isLate())
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-amber-100 text-amber-800">
                                    Late
                                </span>
                            @elseif($rec->status === 'present')
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-emerald-100 text-emerald-800">
                                    Present
                                </span>
                            @else
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-slate-100 text-slate-700 uppercase">
                                    {{ $rec->status }}
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="p-4 border-t border-slate-100">
                {{ $history->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
