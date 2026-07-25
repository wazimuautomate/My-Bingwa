<?php
/**
 * OPT-IN payment-routing bridge.
 *
 * server/mybingwa-api/config.php does `@include` on this file (as a SIBLING folder) and
 * overlays any NON-EMPTY value it returns onto the payment config. That lets the owner set
 * the two payment-routing values from the audited Admin V2 UI instead of hand-editing
 * config.php:
 *
 *   party_b          <- Admin V2 → App configuration → "Payment Till number"
 *                       (the Buy Goods TILL that collects BUY-FOR-MYSELF money).
 *   fulfilment_phone <- Admin V2 → App configuration → "Fulfilment number"
 *                       (the phone that receives the BUY-FOR-ANOTHER notification SMS).
 *
 * It NEVER returns business_shortcode / paybill_shortcode / passkeys, so it can only move
 * WHERE self-money lands and WHO is notified — it cannot silently repoint auth/paybill.
 *
 * Fails safe: returns [] on ANY problem (admin not installed, DB down, settings table
 * absent, value blank). config.php then keeps its own fallback defaults. This file must
 * NEVER emit output or fatal — a payment request includes it on every call.
 */

try {
    $adminRoot = dirname(__DIR__); // .../admin-v2

    // Config::load() hard-exits(500) if the file is missing, which would kill the payment
    // request. So resolve the path the SAME way Config does and bail BEFORE loading if it
    // is not present.
    $envPath    = getenv('MYBINGWA_ADMIN_CONFIG') ?: '';
    $configPath = ($envPath !== '' && is_file($envPath)) ? $envPath : ($adminRoot . '/config/config.php');
    if (!is_file($configPath)) {
        return [];
    }

    if (!class_exists('App\\Core\\Autoloader')) {
        require $adminRoot . '/app/Core/Autoloader.php';
        \App\Core\Autoloader::register($adminRoot . '/app');
    }
    if (!\App\Core\Config::get('db.name')) {
        \App\Core\Config::load($adminRoot . '/config/config.php');
        \App\Core\Database::boot();
    }

    $out = [];

    $till = trim((string) \App\Services\Settings::get('payment_till_number', ''));
    if ($till !== '') {
        // Buy-for-myself money settles to this Till (STK PartyB on the Buy Goods route).
        $out['party_b'] = $till;
    }

    $fulfilment = trim((string) \App\Services\Settings::get('fulfilment_number', ''));
    if ($fulfilment !== '') {
        // Buy-for-another confirmation SMS is delivered here.
        $out['fulfilment_phone'] = $fulfilment;
    }

    return $out;
} catch (\Throwable $e) {
    return [];
}
