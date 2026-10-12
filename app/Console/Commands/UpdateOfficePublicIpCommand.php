<?php

namespace App\Console\Commands;

use App\Models\AttendanceAuditLog;
use App\Models\AttendanceNetwork;
use App\Models\AttendanceSetting;
use App\Services\PublicIpService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateOfficePublicIpCommand extends Command
{
    protected $signature = 'attendance:update-office-ip 
                            {--force : Force database update even if IP appears identical}
                            {--network= : Specific AttendanceNetwork ID to update}
                            {--dry-run : Only detect IP without updating database}';

    protected $description = 'Automatically detect current office public IP and update the approved attendance network';

    public function handle(PublicIpService $ipService): int
    {
        $this->line('[' . now()->toIso8601String() . '] Checking current office public IP address...');

        $details = $ipService->detectPublicIpWithDetails();

        if (!$details['success'] || empty($details['ip'])) {
            $errorMsg = $details['error'] ?? 'Unknown network detection error';
            $this->error("Failed to detect office public IP: {$errorMsg}");

            AttendanceSetting::set('office_ip_update_status', 'failed');
            AttendanceSetting::set('office_ip_last_error', $errorMsg);
            AttendanceSetting::set('office_ip_last_checked_at', now()->toIso8601String());

            return Command::FAILURE;
        }

        $detectedIp = $details['ip'];
        $provider = $details['provider'];
        $latency = $details['latency_ms'];
        $newIpRange = $detectedIp . '/32';

        $this->info("Detected Public IP: {$detectedIp} (Provider: {$provider}, Latency: {$latency}ms)");

        if ($this->option('dry-run')) {
            $this->comment("[DRY RUN] Would set approved network IP range to: {$newIpRange}");
            return Command::SUCCESS;
        }

        // Locate designated office network
        $network = $this->resolveTargetNetwork();

        if (!$network) {
            $this->error('No suitable AttendanceNetwork found to update.');
            return Command::FAILURE;
        }

        $oldIpRange = trim($network->ip_range ?? '');
        $isSameIp = ($oldIpRange === $newIpRange || $oldIpRange === $detectedIp);

        // Update settings status
        AttendanceSetting::set('office_ip_current', $detectedIp);
        AttendanceSetting::set('office_ip_provider', $provider);
        AttendanceSetting::set('office_ip_last_checked_at', now()->toIso8601String());
        AttendanceSetting::set('office_ip_network_id', (string) $network->id);

        if ($isSameIp && !$this->option('force')) {
            // Touch last resolved timestamps without cluttering audit log
            $network->update([
                'last_resolved_ip' => $detectedIp,
                'last_resolved_at' => now(),
                'resolution_status' => 'active',
                'resolution_error' => null,
            ]);

            AttendanceSetting::set('office_ip_update_status', 'unchanged');
            $this->info("Approved office IP is unchanged: {$detectedIp} (Network: [{$network->id}] {$network->name})");
            return Command::SUCCESS;
        }

        // IP changed or forced update
        $network->update([
            'ip_range' => $newIpRange,
            'enabled' => true,
            'last_resolved_ip' => $detectedIp,
            'last_resolved_at' => now(),
            'resolution_status' => 'active',
            'resolution_error' => null,
            'description' => "Auto-updated office public IP on " . Carbon::now()->format('M j, Y g:i A') . " via {$provider}",
        ]);

        AttendanceSetting::set('office_ip_update_status', 'updated');
        AttendanceSetting::set('office_ip_last_changed_at', now()->toIso8601String());
        AttendanceSetting::set('office_ip_last_error', null);

        // Record audit trail event
        AttendanceAuditLog::logEvent(
            eventType: 'office_ip_auto_update',
            actor: null,
            affectedUser: null,
            newValues: [
                'network_id' => $network->id,
                'network_name' => $network->name,
                'previous_ip_range' => $oldIpRange,
                'new_ip_range' => $newIpRange,
                'provider' => $provider,
            ],
            reason: "Office public IP automatically updated from [{$oldIpRange}] to [{$newIpRange}] via {$provider}"
        );

        Log::info("Office Attendance IP automatically updated: [{$oldIpRange}] -> [{$newIpRange}] for network [{$network->name}]");
        $this->info("Successfully updated Network [{$network->id}] '{$network->name}' to {$newIpRange}");

        return Command::SUCCESS;
    }

    /**
     * Resolve the target AttendanceNetwork record to update.
     */
    protected function resolveTargetNetwork(): ?AttendanceNetwork
    {
        // 1. Explicit option passed
        if ($networkId = $this->option('network')) {
            return AttendanceNetwork::find($networkId);
        }

        // 2. Configured network setting in database
        $configuredId = AttendanceSetting::get('office_ip_network_id');
        if (!empty($configuredId)) {
            $network = AttendanceNetwork::find($configuredId);
            if ($network) {
                return $network;
            }
        }

        // 3. Search for existing network by name keywords
        $network = AttendanceNetwork::where(function ($query) {
            $query->where('name', 'like', '%office%')
                ->orWhere('name', 'like', '%hq%')
                ->orWhere('name', 'like', '%main%')
                ->orWhere('name', 'like', '%wi-fi%')
                ->orWhere('name', 'like', '%wifi%');
        })->first();

        if ($network) {
            return $network;
        }

        // 4. First enabled network
        $network = AttendanceNetwork::where('enabled', true)->first();
        if ($network) {
            return $network;
        }

        // 5. Create default network if none exists
        return AttendanceNetwork::create([
            'name' => 'HQ Main Office Wi-Fi',
            'ip_range' => '127.0.0.1/32',
            'enabled' => true,
            'description' => 'Primary office network for automatic public IP attendance verification',
        ]);
    }
}
