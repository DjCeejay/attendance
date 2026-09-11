@extends('layouts.admin')

@section('title', 'Office Network Configuration - ARTSCI Admin')

@section('content')
<div class="space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900">Approved Office Networks</h1>
            <p class="text-xs text-slate-500">Configure public IP / CIDR ranges for server-side office network verification</p>
        </div>

        <button type="button" onclick="document.getElementById('add-network-modal').classList.remove('hidden')" class="bg-[#0A1428] hover:bg-slate-800 text-white font-bold text-xs py-2 px-4 rounded-lg shadow flex items-center gap-2">
            <svg class="w-4 h-4 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>Add Office Network CIDR</span>
        </button>
    </div>

    <!-- Networks List -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 font-extrabold text-sm text-slate-900">
            Active & Configured Networks
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-700">
                <thead class="bg-slate-100 uppercase tracking-wider font-extrabold text-[10px] text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="p-3.5">Network Name</th>
                        <th class="p-3.5">Allowed IP Range / CIDR</th>
                        <th class="p-3.5">Description</th>
                        <th class="p-3.5">Status</th>
                        <th class="p-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($networks as $net)
                        <tr class="hover:bg-slate-50">
                            <td class="p-3.5 font-bold text-slate-900">{{ $net->name }}</td>
                            <td class="p-3.5 font-mono text-xs font-bold text-sky-700">{{ $net->ip_range }}</td>
                            <td class="p-3.5 text-slate-500">{{ $net->description ?: 'No description' }}</td>
                            <td class="p-3.5">
                                @if($net->enabled)
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-emerald-100 text-emerald-800">ENABLED</span>
                                @else
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-slate-100 text-slate-600">DISABLED</span>
                                @endif
                            </td>
                            <td class="p-3.5 text-right space-x-2">
                                <form method="POST" action="{{ route('admin.attendance.networks.toggle', $net) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-xs font-bold {{ $net->enabled ? 'text-amber-600 hover:text-amber-800' : 'text-emerald-600 hover:text-emerald-800' }}">
                                        {{ $net->enabled ? 'Disable' : 'Enable' }}
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('admin.attendance.networks.destroy', $net) }}" onsubmit="return confirm('Delete this network configuration?');" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-bold text-rose-600 hover:text-rose-800">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-8 text-center text-slate-400 text-xs">No office networks configured. All IP addresses allowed by default until a network is added.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Network Modal -->
<div id="add-network-modal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4 border border-slate-200">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="font-extrabold text-base text-slate-900">Add Office Network CIDR</h3>
            <button type="button" onclick="document.getElementById('add-network-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-lg font-bold">&times;</button>
        </div>

        <form method="POST" action="{{ route('admin.attendance.networks.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Network Label / Name</label>
                <input type="text" name="name" required placeholder="e.g. ARTSCI Main HQ Wi-Fi" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-bold">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">IP Range / CIDR Notation</label>
                <input type="text" name="ip_range" required placeholder="e.g. 192.168.1.0/24 or 203.0.113.45/32 or 127.0.0.1/32" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono font-bold">
                <p class="text-[10px] text-slate-400 mt-1">Accepts IPv4/IPv6 CIDR ranges, comma-separated lists, or exact IP addresses.</p>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Description (Optional)</label>
                <textarea name="description" placeholder="Brief description of location or access point..." class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-medium"></textarea>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="enabled" value="1" id="enabled-chk" checked class="rounded border-slate-300 text-sky-600">
                <label for="enabled-chk" class="text-xs font-bold text-slate-700">Enable immediately</label>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('add-network-modal').classList.add('hidden')" class="px-4 py-2 text-xs font-bold text-slate-600 hover:text-slate-800">Cancel</button>
                <button type="submit" class="bg-[#0A1428] hover:bg-slate-800 text-white font-bold text-xs py-2 px-4 rounded-lg shadow">Save Network Range</button>
            </div>
        </form>
    </div>
</div>
@endsection
