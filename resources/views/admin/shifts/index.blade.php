@extends('layouts.admin')

@section('title', 'Shift Templates')

@section('content')
<div class="space-y-6 max-w-5xl mx-auto">
    <!-- Header -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-black text-slate-900 tracking-tight">Shift Template Management</h1>
            <p class="text-xs text-slate-500 font-medium">Define shift schedules for ACF & ARTSCI staff (e.g., Shift 1: 07:00 AM, Shift 2: 01:00 PM)</p>
        </div>
        <div>
            <button type="button" onclick="openCreateShiftModal()" class="px-4 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition shadow-sm">
                + Create New Shift
            </button>
        </div>
    </div>

    <!-- Shifts Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 font-bold uppercase tracking-wider border-b border-slate-100">
                        <th class="p-3.5">Shift Name</th>
                        <th class="p-3.5">Department</th>
                        <th class="p-3.5">Resumption Time</th>
                        <th class="p-3.5">Closing Time</th>
                        <th class="p-3.5">Assigned Staff</th>
                        <th class="p-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                    @forelse($shifts as $sh)
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="p-3.5 font-bold text-slate-900">
                                {{ $sh->name }}
                                @if($sh->description)
                                    <div class="text-[11px] text-slate-400 font-normal">{{ $sh->description }}</div>
                                @endif
                            </td>
                            <td class="p-3.5">
                                <span class="uppercase font-bold text-[10px] px-2 py-0.5 rounded bg-slate-100 text-slate-700">
                                    {{ $sh->department }}
                                </span>
                            </td>
                            <td class="p-3.5 font-bold text-emerald-700">
                                {{ $sh->formatted_resumption }}
                            </td>
                            <td class="p-3.5 text-slate-600">
                                {{ $sh->formatted_closing ?: 'Flexible' }}
                            </td>
                            <td class="p-3.5">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                    {{ $sh->staff_profiles_count }} Staff Assigned
                                </span>
                            </td>
                            <td class="p-3.5 text-right">
                                <button type="button" onclick="openEditShiftModal({{ $sh->id }}, '{{ addslashes($sh->name) }}', '{{ \Carbon\Carbon::parse($sh->resumption_time)->format('H:i') }}', '{{ $sh->closing_time ? \Carbon\Carbon::parse($sh->closing_time)->format('H:i') : '' }}', '{{ $sh->department }}', '{{ addslashes($sh->description ?? '') }}')" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 border border-indigo-200 px-2.5 py-1 rounded-lg hover:bg-indigo-50 transition">
                                    Edit Shift
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-400 text-xs">
                                No shift templates defined. Click "+ Create New Shift" above to add your first shift (e.g. Shift 1: 07:00 AM).
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Create / Edit Shift -->
<div id="shift-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 space-y-4">
        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
            <h3 class="text-sm font-bold text-slate-900" id="shift-modal-title">Create New Shift</h3>
            <button type="button" onclick="closeShiftModal()" class="text-slate-400 hover:text-slate-600">&times;</button>
        </div>

        <form id="shift-form" method="POST" action="{{ route('admin.shifts.store') }}" class="space-y-4 text-xs">
            @csrf
            <input type="hidden" name="_method" id="shift_method" value="POST">

            <div>
                <label class="block font-bold text-slate-700 mb-1">Shift Name (e.g., Morning Shift / Shift 1)</label>
                <input type="text" name="name" id="shift_name" required placeholder="e.g. Shift 1" class="w-full px-3 py-2 border border-slate-300 rounded-lg font-bold">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Resumption Time</label>
                    <input type="time" name="resumption_time" id="shift_resumption" required class="w-full px-3 py-2 border border-slate-300 rounded-lg font-bold">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Closing Time (Optional)</label>
                    <input type="time" name="closing_time" id="shift_closing" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                </div>
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Department Scope</label>
                <select name="department" id="shift_department" required class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    <option value="acf">ACF Staff</option>
                    <option value="artsci">ARTSCI Staff</option>
                    <option value="all">All Departments</option>
                </select>
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Description (Optional notes)</label>
                <textarea name="description" id="shift_description" placeholder="Notes for staff assignment..." class="w-full px-3 py-2 border border-slate-300 rounded-lg"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeShiftModal()" class="px-4 py-2 font-bold text-slate-600 hover:text-slate-800">Cancel</button>
                <button type="submit" class="px-4 py-2 font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg">Save Shift</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openCreateShiftModal() {
        document.getElementById('shift-modal-title').innerText = 'Create New Shift';
        document.getElementById('shift-form').action = "{{ route('admin.shifts.store') }}";
        document.getElementById('shift_method').value = 'POST';
        document.getElementById('shift_name').value = '';
        document.getElementById('shift_resumption').value = '08:00';
        document.getElementById('shift_closing').value = '';
        document.getElementById('shift_department').value = 'acf';
        document.getElementById('shift_description').value = '';
        document.getElementById('shift-modal').classList.remove('hidden');
    }

    function openEditShiftModal(id, name, resumption, closing, dept, desc) {
        document.getElementById('shift-modal-title').innerText = 'Edit Shift: ' + name;
        document.getElementById('shift-form').action = '/admin/shifts/' + id;
        document.getElementById('shift_method').value = 'PUT';
        document.getElementById('shift_name').value = name;
        document.getElementById('shift_resumption').value = resumption;
        document.getElementById('shift_closing').value = closing || '';
        document.getElementById('shift_department').value = dept;
        document.getElementById('shift_description').value = desc || '';
        document.getElementById('shift-modal').classList.remove('hidden');
    }

    function closeShiftModal() {
        document.getElementById('shift-modal').classList.add('hidden');
    }
</script>
@endsection
