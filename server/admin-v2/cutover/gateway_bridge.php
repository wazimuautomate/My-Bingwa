<?php
/**
 * OPT-IN bridge: lets the LEGACY payment API (server/mybingwa-api) read the payment
 * gateway settings managed from the Admin V2 dashboard (mb_gateway_config).
 *
 * It returns an array of resolved values (SMS key decrypted) or [] on ANY failure — so
 * a misconfigured admin can NEVER break live payments. Only non-empty values should be
 * overlaid by the caller.
 *
 * To enable (documented in docs/MIGRATION_CUTOVER.md), append to the legacy config.php,
 * just before `return $config;`:
 *
 *   $gw = @include __DIR__ . '/../admin-v2/cutover/gateway_bridge.php';
 *   if (is_array($gw)) {
 *       foreach (['transaction_type','business_shortcode','party_b','paybill_shortcode',
 *                 'callback_url','fulfilment_phone','business_name','sms_api_url',
 *                 'sms_sender_id','sms_api_key','daraja_env'] as $k) {
 *           if (isset($gw[$k]) && $gw[$k] !== '') { $config[$k] = $gw[$k]; }
 *       }
 *   }
 *
 * Adjust the relative path if the admin lives elsewhere.
 */

try {
    $adminRoot = dirname(__DIR__); // server/admin-v2
    if (!class_exists('App\\Core\\Autoloader')) {
        require $adminRoot . '/app/Core/Autoloader.php';
        \App\Core\Autoloader::register($adminRoot . '/app');
    }
    if (!\App\Core\Config::get('db.name')) {
        \App\Core\Config::load($adminRoot . '/config/config.php');
        \App\Core\Database::boot();
    }
    return \App\Services\GatewayService::resolved();
} catch (\Throwable $e) {
    return [];
}
