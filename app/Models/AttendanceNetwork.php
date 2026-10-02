<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AttendanceNetwork extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'ip_range',
        'ddns_hostname',
        'ddns_enabled',
        'last_resolved_ip',
        'last_resolved_at',
        'resolution_status',
        'resolution_error',
        'enabled',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'ddns_enabled' => 'boolean',
            'last_resolved_at' => 'datetime',
        ];
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get effective DDNS hostname (from database field or environment fallback based on network name).
     */
    public function getEffectiveHostnameAttribute(): ?string
    {
        if (!empty($this->ddns_hostname)) {
            return trim($this->ddns_hostname);
        }

        // Environment fallbacks by network name convention
        $nameLower = strtolower($this->name ?? '');
        if (str_contains($nameLower, 'wifi a') || str_contains($nameLower, 'wi-fi a') || str_contains($nameLower, 'office a')) {
            $envHost = config('attendance.office_wifi_a_hostname');
            if (!empty($envHost)) {
                return trim($envHost);
            }
        }

        if (str_contains($nameLower, 'wifi b') || str_contains($nameLower, 'wi-fi b') || str_contains($nameLower, 'office b')) {
            $envHost = config('attendance.office_wifi_b_hostname');
            if (!empty($envHost)) {
                return trim($envHost);
            }
        }

        return null;
    }

    /**
     * Resolve DDNS hostname to its current public IP address with caching and safe error handling.
     */
    public function resolveDdns(bool $forceRefresh = false): ?string
    {
        $hostname = $this->effective_hostname;

        if (!$this->ddns_enabled || empty($hostname)) {
            if ($this->resolution_status !== 'not_configured') {
                $this->update([
                    'resolution_status' => 'not_configured',
                    'resolution_error' => null,
                ]);
            }
            return null;
        }

        $cacheKey = "ddns_resolution_network_" . $this->id;
        $ttl = (int) config('attendance.ddns_cache_ttl', 180);

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, $ttl, function () use ($hostname) {
            return $this->performDnsLookup($hostname);
        });
    }

    /**
     * Perform actual DNS lookup safely.
     */
    public function performDnsLookup(string $hostname): ?string
    {
        // Sanitize and validate hostname format
        $hostname = trim($hostname);

        if (empty($hostname) || filter_var($hostname, FILTER_VALIDATE_IP)) {
            // If it's already an IP address, return it directly
            if (filter_var($hostname, FILTER_VALIDATE_IP)) {
                $this->update([
                    'last_resolved_ip' => $hostname,
                    'last_resolved_at' => now(),
                    'resolution_status' => 'configured_and_resolving',
                    'resolution_error' => null,
                ]);
                return $hostname;
            }

            $this->update([
                'resolution_status' => 'resolution_failed',
                'resolution_error' => 'Invalid hostname format.',
            ]);
            return null;
        }

        try {
            // Attempt DNS lookup using gethostbynamel (returns array of IPv4 addresses or false)
            $ips = @gethostbynamel($hostname);

            if ($ips !== false && !empty($ips) && filter_var($ips[0], FILTER_VALIDATE_IP)) {
                $resolvedIp = trim($ips[0]);

                $this->update([
                    'last_resolved_ip' => $resolvedIp,
                    'last_resolved_at' => now(),
                    'resolution_status' => 'configured_and_resolving',
                    'resolution_error' => null,
                ]);

                Log::info("DDNS Resolved successfully for network [{$this->name}]: {$hostname} -> {$resolvedIp}");
                return $resolvedIp;
            }

            // Check if dns_get_record supports IPv6 (AAAA) or IPv4 (A)
            $records = @dns_get_record($hostname, DNS_A + DNS_AAAA);
            if (!empty($records)) {
                foreach ($records as $record) {
                    $candidateIp = $record['ip'] ?? ($record['ipv6'] ?? null);
                    if ($candidateIp && filter_var($candidateIp, FILTER_VALIDATE_IP)) {
                        $resolvedIp = trim($candidateIp);

                        $this->update([
                            'last_resolved_ip' => $resolvedIp,
                            'last_resolved_at' => now(),
                            'resolution_status' => 'configured_and_resolving',
                            'resolution_error' => null,
                        ]);

                        Log::info("DDNS Resolved successfully for network [{$this->name}]: {$hostname} -> {$resolvedIp}");
                        return $resolvedIp;
                    }
                }
            }

            // If gethostbyname returns something different than original string and valid IP
            $singleIp = @gethostbyname($hostname);
            if ($singleIp !== $hostname && filter_var($singleIp, FILTER_VALIDATE_IP)) {
                $resolvedIp = trim($singleIp);

                $this->update([
                    'last_resolved_ip' => $resolvedIp,
                    'last_resolved_at' => now(),
                    'resolution_status' => 'configured_and_resolving',
                    'resolution_error' => null,
                ]);

                Log::info("DDNS Resolved successfully for network [{$this->name}]: {$hostname} -> {$resolvedIp}");
                return $resolvedIp;
            }

            // Lookup failed
            $this->update([
                'resolution_status' => 'resolution_failed',
                'resolution_error' => "Unable to resolve DDNS hostname: {$hostname}",
            ]);

            Log::warning("DDNS resolution failed for network [{$this->name}] hostname [{$hostname}]");
            return null;
        } catch (\Throwable $e) {
            $this->update([
                'resolution_status' => 'resolution_failed',
                'resolution_error' => 'DNS lookup exception: ' . $e->getMessage(),
            ]);

            Log::warning("DDNS exception for network [{$this->name}] hostname [{$hostname}]: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if a given IP address matches this network's IP range/CIDR/DDNS.
     */
    public function matchesIp(string $ip): bool
    {
        if (!$this->enabled) {
            return false;
        }

        // 1. Check static IP ranges / CIDRs
        if (!empty($this->ip_range)) {
            $ranges = array_map('trim', explode(',', $this->ip_range));

            foreach ($ranges as $range) {
                if ($range === '' || $range === '*') {
                    return true;
                }

                // Direct IP or CIDR match
                if (self::ipMatchesCidr($ip, $range)) {
                    return true;
                }

                // Inline Hostname match (backward compatibility)
                if (!filter_var($range, FILTER_VALIDATE_IP) && !str_contains($range, '/')) {
                    $resolved = @gethostbyname($range);
                    if ($resolved !== $range && filter_var($resolved, FILTER_VALIDATE_IP)) {
                        if (self::ipMatchesCidr($ip, $resolved)) {
                            return true;
                        }
                    }
                }
            }
        }

        // 2. Check DDNS resolution if DDNS is enabled
        if ($this->ddns_enabled) {
            $ddnsIp = $this->resolveDdns();
            if ($ddnsIp && self::ipMatchesCidr($ip, $ddnsIp)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Compare IP against CIDR or exact IP safely.
     */
    public static function ipMatchesCidr(string $ip, string $cidr): bool
    {
        $ip = trim($ip);
        $cidr = trim($cidr);

        if ($ip === $cidr) {
            return true;
        }

        if (!str_contains($cidr, '/')) {
            return $ip === $cidr;
        }

        [$subnet, $mask] = explode('/', $cidr, 2);

        // IPv4 subnet matching
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $maskInt = (int) $mask;

            if ($maskInt < 0 || $maskInt > 32) {
                return false;
            }

            $bitmask = -1 << (32 - $maskInt);
            return ($ipLong & $bitmask) === ($subnetLong & $bitmask);
        }

        // IPv6 subnet matching
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $ipBin = inet_pton($ip);
            $subnetBin = inet_pton($subnet);
            $maskInt = (int) $mask;

            if ($ipBin === false || $subnetBin === false || $maskInt < 0 || $maskInt > 128) {
                return false;
            }

            $bytes = (int) ($maskInt / 8);
            $bits = $maskInt % 8;

            if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
                return false;
            }

            if ($bits > 0) {
                $ipByte = ord($ipBin[$bytes]);
                $subnetByte = ord($subnetBin[$bytes]);
                $maskByte = (0xFF << (8 - $bits)) & 0xFF;

                if (($ipByte & $maskByte) !== ($subnetByte & $maskByte)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }
}
