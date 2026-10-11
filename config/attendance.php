<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Attendance Network Verification Mode
    |--------------------------------------------------------------------------
    |
    | Defines how attendance check-in requests are verified against office networks:
    | - 'hybrid': Allows either local office LAN subnets OR public IP/DDNS match.
    |   (Ideal for seamless migration between Railway and local Office Server).
    | - 'local_lan': Requires request to originate from an authorized office LAN subnet.
    |   (Used when running on the Windows Office Server without public IP dependency).
    | - 'public_ip': Legacy mode verifying against public IP allowlist & DDNS.
    |
    */
    'verification_mode' => env('ATTENDANCE_VERIFICATION_MODE', 'hybrid'),

    /*
    |--------------------------------------------------------------------------
    | Authorized Office LAN Subnets
    |--------------------------------------------------------------------------
    |
    | Private IP subnets for Office Wi-Fi A (Tenda TX2 Pro / ZTE 5G) and
    | Office Wi-Fi B (Airtel Router). Requests originating from these local
    | subnets are verified when running on the Windows Office Server.
    |
    */
    'office_lan_subnets' => array_values(array_filter(array_map(
        static fn (string $subnet): string => trim($subnet),
        preg_split('/[\s,]+/', (string) env('OFFICE_LAN_SUBNETS', '192.168.0.0/24,192.168.1.0/24,192.168.8.0/24'))
    ), static fn (string $subnet): bool => $subnet !== '')),

    /*
    |--------------------------------------------------------------------------
    | Allow Loopback / Localhost Verification
    |--------------------------------------------------------------------------
    |
    | When enabled (e.g. during local testing/administration), requests from
    | 127.0.0.1 or ::1 are accepted as valid local server connections.
    |
    */
    'allow_loopback' => (bool) env('ATTENDANCE_ALLOW_LOOPBACK', false),

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
    | Configuration for Laravel trusted proxies (Railway, Cloudflare, local reverse proxy).
    | On Railway: use '*' or configured proxy CIDRs.
    | On Windows local server behind Caddy/IIS: '127.0.0.1,::1'.
    |
    */
    'trusted_proxies' => array_values(array_filter(array_map(
        static fn (string $proxy): string => trim($proxy),
        preg_split('/[\s,]+/', (string) env('TRUSTED_PROXIES', '127.0.0.1,::1'))
    ), static fn (string $proxy): bool => $proxy !== '' && $proxy !== '*')),
];
