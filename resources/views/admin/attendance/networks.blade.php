@extends('layouts.admin')

@section('title', 'Office Network & Dynamic DNS Configuration - Admin Console')

@section('content')
<div class="space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900">Approved Office Networks & DDNS</h1>
            <p class="text-xs text-slate-500">Configure public IP / CIDR ranges and Dynamic DNS (DDNS) hostnames for automatic office Wi-Fi verification</p>
        </div>

        <button type="button" onclick="document.getElementById('add-network-modal').classList.remove('hidden')" class="bg-[#0f172a] hover:bg-slate-800 text-white font-bold text-xs py-2 px-4 rounded-lg shadow flex items-center gap-2">
            <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>Add Office Network / DDNS</span>
        </button>
    </div>

    <!-- Automatic Public IP Updater & Current IP Status -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Server Background Auto-Updater Card -->
        <div class="bg-slate-900 text-white p-4 rounded-2xl shadow-md flex flex-col justify-between border border-slate-800">
            <div>
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full {{ ($autoIpStatus === 'updated' || $autoIpStatus === 'unchanged') ? 'bg-emerald-400' : 'bg-amber-400' }} animate-pulse"></span>
                        <h3 class="font-extrabold text-xs text-slate-300 uppercase tracking-wider">Office Server Public IP (Auto-Updater)</h3>
                    </div>
                    <span class="text-[10px] uppercase font-black px-2 py-0.5 rounded {{ $autoIpStatus === 'updated' ? 'bg-emerald-500/20 text-emerald-300' : ($autoIpStatus === 'unchanged' ? 'bg-indigo-500/20 text-indigo-300' : 'bg-slate-700 text-slate-300') }}">
                        {{ $autoIpStatus === 'updated' ? 'Updated' : ($autoIpStatus === 'unchanged' ? 'Verified Active' : 'Scheduled (5m)') }}
                    </span>
                </div>
                <div class="flex items-baseline gap-2 mb-1">
                    <span class="font-mono font-black text-lg text-emerald-400">{{ $autoIpCurrent ?: 'Detecting...' }}</span>
                    @if($autoIpProvider)
                        <span class="text-[11px] text-slate-400">via {{ $autoIpProvider }}</span>
                    @endif
                </div>
                <p class="text-[11px] text-slate-400">
                    Runs every 5 minutes on the Windows server. Updates the approved office network automatically when the router's public IP changes.
                    @if($autoIpCheckedAt)
                        <span class="block mt-0.5 text-slate-500 text-[10px]">Last checked: {{ \Carbon\Carbon::parse($autoIpCheckedAt)->format('M j, Y g:i A') }} ({{ \Carbon\Carbon::parse($autoIpCheckedAt)->diffForHumans() }})</span>
                    @endif
                </p>
                @if($autoIpError)
                    <p class="text-[10px] text-rose-400 mt-1 font-mono">Notice: {{ $autoIpError }}</p>
                @endif
            </div>

            <div class="mt-3 pt-3 border-t border-slate-800 flex items-center justify-between">
                <span class="text-[10px] text-slate-500">Scheduler: <strong>Active (5-min cycle)</strong></span>
                <form method="POST" action="{{ route('admin.attendance.networks.auto-update-ip') }}" class="inline">
                    @csrf
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 active:scale-95 text-white font-bold text-[11px] py-1.5 px-3 rounded-lg shadow transition flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        <span>Check & Update Now</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Admin Connected Client IP Banner -->
        <div class="bg-indigo-900 text-white p-4 rounded-2xl shadow-md flex flex-col justify-between border border-indigo-800">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-400"></span>
                    <h3 class="font-extrabold text-xs text-indigo-100 uppercase tracking-wider">Your Device's Public IP (Admin Client)</h3>
                </div>
                <div class="flex items-baseline gap-2 mb-1">
                    <span class="font-mono font-black text-lg text-white">{{ $currentIp }}</span>
                    <span class="text-[10px] text-indigo-300">Resolved via Cloudflare/Proxy</span>
                </div>
                <p class="text-[11px] text-indigo-200">
                    This is the IP address Cloudflare sees for your current browser session. If you are currently in the office, this should match the office server's detected IP.
                </p>
            </div>

            <div class="mt-3 pt-3 border-t border-indigo-800/60 flex items-center justify-between">
                <span class="text-[10px] text-indigo-300">Quick Manual Sync</span>
                <form method="POST" action="{{ route('admin.attendance.networks.sync-ip') }}" onsubmit="return confirm('Update Office Network to your current client IP ({{ $currentIp }}/32)?');">
                    @csrf
                    <button type="submit" class="bg-emerald-500 hover:bg-emerald-600 active:scale-95 text-slate-950 font-black text-[11px] py-1.5 px-3 rounded-lg shadow transition flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-slate-950" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                        <span>Sync My IP ({{ $currentIp }})</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Networks List -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 font-extrabold text-sm text-slate-900 flex justify-between items-center">
            <span>Configured Office Networks & DDNS Hosts</span>
            <span class="text-xs font-normal text-slate-400">Independent Wi-Fi A & B Validation</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-700">
                <thead class="bg-slate-100 uppercase tracking-wider font-extrabold text-[10px] text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="p-3.5">Network Name</th>
                        <th class="p-3.5">DDNS Hostname</th>
                        <th class="p-3.5">DDNS Status & Resolved IP</th>
                        <th class="p-3.5">Static IP Range / CIDR</th>
                        <th class="p-3.5">Network Status</th>
                        <th class="p-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($networks as $net)
                        <tr class="hover:bg-slate-50">
                            <td class="p-3.5">
                                <div class="font-bold text-slate-900">{{ $net->name }}</div>
                                @if($net->description)
                                    <div class="text-[11px] text-slate-400">{{ $net->description }}</div>
                                @endif
                            </td>

                            <!-- DDNS Hostname -->
                            <td class="p-3.5">
                                @if($net->effective_hostname)
                                    <div class="font-mono text-xs font-bold text-indigo-700">{{ $net->effective_hostname }}</div>
                                    @if(!$net->ddns_hostname)
                                        <span class="text-[10px] text-slate-400 italic">(via .env fallback)</span>
                                    @endif
                                @else
                                    <span class="text-slate-400 italic text-[11px]">Not set</span>
                                @endif
                            </td>

                            <!-- DDNS Status & Resolved IP -->
                            <td class="p-3.5">
                                @if(!$net->ddns_enabled)
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600">DDNS Disabled</span>
                                @elseif($net->resolution_status === 'configured_and_resolving')
                                    <div class="flex items-center gap-1.5 mb-0.5">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-emerald-100 text-emerald-800">
                                            Configured & Resolving
                                        </span>
                                    </div>
                                    <div class="font-mono text-xs font-black text-slate-900">
                                        {{ $net->last_resolved_ip }}
                                    </div>
                                    @if($net->last_resolved_at)
                                        <div class="text-[10px] text-slate-400">
                                            Checked: {{ $net->last_resolved_at->format('M j, g:i A') }}
                                        </div>
                                    @endif
                                @elseif($net->resolution_status === 'resolution_failed')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-rose-100 text-rose-800" title="{{ $net->resolution_error }}">
                                        Resolution Failed
                                    </span>
                                    @if($net->resolution_error)
                                        <div class="text-[10px] text-rose-600 max-w-xs truncate" title="{{ $net->resolution_error }}">
                                            {{ $net->resolution_error }}
                                        </div>
                                    @endif
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600">
                                        Not Configured
                                    </span>
                                @endif
                            </td>

                            <!-- Static IP Range -->
                            <td class="p-3.5 font-mono text-xs font-bold text-slate-700">
                                {{ $net->ip_range ?: 'None' }}
                            </td>

                            <!-- Network Enabled Status -->
                            <td class="p-3.5">
                                @if($net->enabled)
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-emerald-100 text-emerald-800">ENABLED</span>
                                @else
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-slate-100 text-slate-600">DISABLED</span>
                                @endif
                            </td>

                            <!-- Actions -->
                            <td class="p-3.5 text-right space-x-1 whitespace-nowrap">
                                @if($net->ddns_enabled)
                                    <form method="POST" action="{{ route('admin.attendance.networks.refresh-ddns', $net) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs font-bold text-emerald-600 hover:text-emerald-800 border border-emerald-200 px-2 py-1 rounded shadow-sm hover:bg-emerald-50">
                                            Refresh DNS
                                        </button>
                                    </form>
                                @endif

                                <button
                                    type="button"
                                    data-id="{{ $net->id }}"
                                    data-name="{{ $net->name }}"
                                    data-ip-range="{{ $net->ip_range ?? '' }}"
                                    data-ddns-host="{{ $net->ddns_hostname ?? '' }}"
                                    data-ddns-enabled="{{ (int) $net->ddns_enabled }}"
                                    data-description="{{ $net->description ?? '' }}"
                                    data-enabled="{{ (int) $net->enabled }}"
                                    onclick="openEditNetworkModal(this)"
                                    class="text-xs font-bold text-indigo-600 hover:text-indigo-800 border border-indigo-200 px-2.5 py-1 rounded shadow-sm"
                                >
                                    Edit
                                </button>

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
                            <td colspan="6" class="p-8 text-center text-slate-400 text-xs">No office networks configured. All IP addresses allowed by default until a network is added.</td>
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
            <h3 class="font-extrabold text-base text-slate-900">Add Office Network / DDNS</h3>
            <button type="button" onclick="document.getElementById('add-network-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-lg font-bold">&times;</button>
        </div>

        <form method="POST" action="{{ route('admin.attendance.networks.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Network Label / Name</label>
                <input type="text" name="name" required placeholder="e.g. Office Wi-Fi A (Tenda) or Office Wi-Fi B (Airtel)" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-bold">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Dynamic DNS (DDNS) Hostname</label>
                <input type="text" name="ddns_hostname" placeholder="e.g. office-a.ddns.net or office-b.synology.me" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono font-bold">
                <p class="text-[10px] text-slate-400 mt-1">Configured router DDNS domain name for automatic IP tracking.</p>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="ddns_enabled" value="1" id="add-ddns-enabled-chk" checked class="rounded border-slate-300 text-indigo-600">
                <label for="add-ddns-enabled-chk" class="text-xs font-bold text-slate-700">Enable Dynamic DNS (DDNS) Validation</label>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Static IP Range / CIDR Notation (Optional)</label>
                <input type="text" name="ip_range" placeholder="e.g. 197.210.0.0/16 or 102.89.23.45/32" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono font-bold">
                <p class="text-[10px] text-slate-400 mt-1">Optional fallback static IP or CIDR subnet.</p>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Description (Optional)</label>
                <textarea name="description" placeholder="e.g. Tenda TX2 Pro connected to ZTE 5G router" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-medium"></textarea>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="enabled" value="1" id="enabled-chk" checked class="rounded border-slate-300 text-indigo-600">
                <label for="enabled-chk" class="text-xs font-bold text-slate-700">Enable Network immediately</label>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('add-network-modal').classList.add('hidden')" class="px-4 py-2 text-xs font-bold text-slate-600 hover:text-slate-800">Cancel</button>
                <button type="submit" class="bg-[#0f172a] hover:bg-slate-800 text-white font-bold text-xs py-2 px-4 rounded-lg shadow">Save Network</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Network Modal -->
<div id="edit-network-modal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4 border border-slate-200">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="font-extrabold text-base text-slate-900">Edit Office Network & DDNS</h3>
            <button type="button" onclick="document.getElementById('edit-network-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-lg font-bold">&times;</button>
        </div>

        <form id="edit-network-form" method="POST" action="" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Network Label / Name</label>
                <input type="text" name="name" id="edit-net-name" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-bold">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Dynamic DNS (DDNS) Hostname</label>
                <input type="text" name="ddns_hostname" id="edit-net-ddns-host" placeholder="e.g. office-a.ddns.net" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono font-bold">
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="ddns_enabled" value="1" id="edit-net-ddns-enabled" class="rounded border-slate-300 text-indigo-600">
                <label for="edit-net-ddns-enabled" class="text-xs font-bold text-slate-700">Enable Dynamic DNS (DDNS) Validation</label>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Static IP Range / CIDR Notation</label>
                <input type="text" name="ip_range" id="edit-net-ip" placeholder="e.g. 192.168.1.0/24 or 127.0.0.1/32" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono font-bold">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Description (Optional)</label>
                <textarea name="description" id="edit-net-desc" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-medium"></textarea>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="enabled" value="1" id="edit-net-enabled" class="rounded border-slate-300 text-indigo-600">
                <label for="edit-net-enabled" class="text-xs font-bold text-slate-700">Network Enabled</label>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('edit-network-modal').classList.add('hidden')" class="px-4 py-2 text-xs font-bold text-slate-600 hover:text-slate-800">Cancel</button>
                <button type="submit" class="bg-[#0f172a] hover:bg-slate-800 text-white font-bold text-xs py-2 px-4 rounded-lg shadow">Update Network</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function openEditNetworkModal(button) {
        const id = button.dataset.id;
        const name = button.dataset.name || '';
        const ipRange = button.dataset.ipRange || '';
        const ddnsHost = button.dataset.ddnsHost || '';
        const ddnsEnabled = button.dataset.ddnsEnabled === '1';
        const description = button.dataset.description || '';
        const enabled = button.dataset.enabled === '1';

        document.getElementById('edit-network-form').action = "/admin/attendance/networks/" + id;
        document.getElementById('edit-net-name').value = name;
        document.getElementById('edit-net-ip').value = ipRange;
        document.getElementById('edit-net-ddns-host').value = ddnsHost;
        document.getElementById('edit-net-ddns-enabled').checked = ddnsEnabled;
        document.getElementById('edit-net-desc').value = description;
        document.getElementById('edit-net-enabled').checked = enabled;
        document.getElementById('edit-network-modal').classList.remove('hidden');
    }
</script>
@endpush
