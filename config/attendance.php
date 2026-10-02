<?php

return [

    /*
    |--------------------------------------------------------------------------
    | DDNS Cache TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | How long resolved DDNS IP addresses are cached before re-querying DNS.
    | Default is 180 seconds (3 minutes) to ensure fast attendance response
    | times while capturing IP changes quickly.
    |
    */
    'ddns_cache_ttl' => (int) env('DDNS_CACHE_TTL', 180),

    /*
    |--------------------------------------------------------------------------
    | Default DDNS Hostnames for Office Wi-Fi Networks
    |--------------------------------------------------------------------------
    |
    | Optional environment fallbacks for Office Wi-Fi A (Tenda TX2 Pro / ZTE 5G)
    | and Office Wi-Fi B (Airtel Router). These can also be configured directly
    | via the Admin Attendance Networks management dashboard.
    |
    */
    'office_wifi_a_hostname' => env('OFFICE_WIFI_A_DDNS_HOSTNAME', null),
    'office_wifi_b_hostname' => env('OFFICE_WIFI_B_DDNS_HOSTNAME', null),

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxy Header Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Laravel trusted proxies (Railway, Cloudflare, etc.).
    | Use an explicit allowlist such as 10.0.0.0/8,172.16.0.0/12,192.168.0.0/16
    | or the exact upstream proxy IPs. Never trust '*' in production.
    |
    */
    'trusted_proxies' => array_values(array_filter(array_map(
        static fn (string $proxy): string => trim($proxy),
        preg_split('/[\s,]+/', (string) env('TRUSTED_PROXIES', '127.0.0.1,::1'))
    ), static fn (string $proxy): bool => $proxy !== '' && $proxy !== '*')),
];
