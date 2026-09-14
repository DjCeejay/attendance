<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceNetwork extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'ip_range',
        'enabled',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if a given IP address matches this network's IP range/CIDR/Hostname.
     */
    public function matchesIp(string $ip): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $ranges = array_map('trim', explode(',', $this->ip_range));

        foreach ($ranges as $range) {
            if ($range === '' || $range === '*') {
                return true;
            }

            // Direct IP or CIDR match
            if (self::ipMatchesCidr($ip, $range)) {
                return true;
            }

            // Hostname / Dynamic DNS (DDNS) resolution match (e.g. office.ddns.net)
            if (!filter_var($range, FILTER_VALIDATE_IP) && !str_contains($range, '/')) {
                $resolvedIp = gethostbyname($range);
                if ($resolvedIp !== $range && filter_var($resolvedIp, FILTER_VALIDATE_IP)) {
                    if (self::ipMatchesCidr($ip, $resolvedIp)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

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
