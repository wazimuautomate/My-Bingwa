<?php
/**
 * OPT-IN cutover bridge: returns the CURRENT PUBLISHED offers in the legacy
 * get_offers.php shape, sourced from Admin V2's immutable snapshot. Include it from the
 * legacy get_offers.php (see docs/MIGRATION_CUTOVER.md §6). Returns [] on any failure so
 * the legacy endpoint can fall back safely.
 */
try {
    $adminRoot = dirname(__DIR__);
    if (!class_exists('App\\Core\\Autoloader')) {
        require $adminRoot . '/app/Core/Autoloader.php';
        \App\Core\Autoloader::register($adminRoot . '/app');
    }
    if (!\App\Core\Config::get('db.name')) {
        \App\Core\Config::load($adminRoot . '/config/config.php');
        \App\Core\Database::boot();
    }
    $snap = \App\Services\PublishingService::currentSnapshot() ?: ['offers' => []];
    return array_map(fn($o) => [
        'id' => $o['id'], 'category' => $o['category'], 'name' => $o['name'],
        'price' => (int) $o['price'], 'validity' => $o['validity'], 'band' => $o['band'],
        'dailyRule' => $o['dailyRule'],
    ], $snap['offers'] ?? []);
} catch (\Throwable $e) {
    return [];
}
