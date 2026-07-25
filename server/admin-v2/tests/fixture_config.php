<?php
// Test-only configuration. No real secrets. Used by the pure-logic test harness so
// Crypto/Signer/Config have deterministic inputs without touching a real database.
return [
    'environment' => 'staging',
    'app_key' => 'test-app-key-0123456789-abcdefghijklmnop',
    'db' => ['host' => 'localhost', 'name' => 'test', 'user' => 'test', 'pass' => 'test', 'prefix' => 'mb_'],
    'signing' => ['algorithm' => 'RS256', 'private_key_path' => '', 'public_key_path' => ''],
    'security' => ['trusted_proxy_header' => '', 'sync_api_key' => ''],
    'sync' => ['rate_limit_per_minute' => 60, 'min_client_version_code' => 1],
];
