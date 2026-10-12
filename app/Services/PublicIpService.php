<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PublicIpService
{
    /**
     * Default list of reliable, HTTPS-capable public IP detection services.
     */
    protected array $providers = [
        'api.ipify.org' => 'https://api.ipify.org',
        'icanhazip.com' => 'https://icanhazip.com',
        'checkip.amazonaws.com' => 'https://checkip.amazonaws.com',
        'ifconfig.me' => 'https://ifconfig.me/ip',
        'ipinfo.io' => 'https://ipinfo.io/ip',
    ];

    /**
     * Detect current public IP with automatic fallback across providers.
     */
    public function detectPublicIp(): ?string
    {
        $result = $this->detectPublicIpWithDetails();
        return $result['ip'] ?? null;
    }

    /**
     * Detect public IP and return full diagnostic details.
     *
     * @return array{success: bool, ip: ?string, provider: ?string, latency_ms: int, error: ?string}
     */
    public function detectPublicIpWithDetails(): array
    {
        $customUrl = config('attendance.public_ip_service_url');
        $providerList = $this->providers;

        if (!empty($customUrl)) {
            $providerList = ['custom_endpoint' => $customUrl] + $providerList;
        }

        $timeoutSeconds = (int) config('attendance.public_ip_detection_timeout', 5);
        $errors = [];

        foreach ($providerList as $name => $url) {
            $start = microtime(true);
            try {
                $response = Http::timeout($timeoutSeconds)
                    ->withHeaders(['User-Agent' => 'ARTSCI-Attendance-IPUpdater/1.0'])
                    ->get($url);

                if ($response->successful()) {
                    $rawIp = trim($response->body());

                    // Validate that the returned value is a valid public IP
                    if ($this->isValidPublicIp($rawIp)) {
                        $latencyMs = (int) round((microtime(true) - $start) * 1000);
                        return [
                            'success' => true,
                            'ip' => $rawIp,
                            'provider' => $name,
                            'latency_ms' => $latencyMs,
                            'error' => null,
                        ];
                    }

                    $errors[] = "Provider [{$name}] returned invalid IP format: '{$rawIp}'";
                } else {
                    $errors[] = "Provider [{$name}] responded with HTTP status {$response->status()}";
                }
            } catch (\Throwable $e) {
                $errors[] = "Provider [{$name}] failed: " . $e->getMessage();
            }
        }

        Log::warning('Public IP detection failed across all providers: ' . implode(' | ', $errors));

        return [
            'success' => false,
            'ip' => null,
            'provider' => null,
            'latency_ms' => 0,
            'error' => implode('; ', $errors),
        ];
    }

    /**
     * Validate that the string is a valid IPv4 or IPv6 address and not private/reserved.
     */
    public function isValidPublicIp(string $ip): bool
    {
        $ip = trim($ip);

        // Filter valid IP format
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        // Must not be loopback or private range
        if (in_array($ip, ['127.0.0.1', '::1', '0.0.0.0'], true)) {
            return false;
        }

        // Validate public range (reject RFC 1918 private subnets 10.x, 172.16-31.x, 192.168.x)
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
}
