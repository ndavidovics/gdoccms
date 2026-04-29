<?php

return [
    'join_token_secret' => env('JOIN_TOKEN_SECRET', 'change-me-in-production'),
    'join_token_ttl_seconds' => (int) env('JOIN_TOKEN_TTL_SECONDS', 300),
    'heartbeat_timeout_seconds' => (int) env('HEARTBEAT_TIMEOUT_SECONDS', 45),
    'heartbeat_interval_seconds' => (int) env('HEARTBEAT_INTERVAL_SECONDS', 10),
    'contact_hash_pepper' => env('CONTACT_HASH_PEPPER', ''),
];
