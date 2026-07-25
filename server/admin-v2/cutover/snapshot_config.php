<?php
/**
 * OPT-IN cutover bridge: current published support/config in the legacy get_config.php
 * shape. Returns [] on failure. See docs/MIGRATION_CUTOVER.md §6.
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
    $snap = \App\Services\PublishingService::currentSnapshot();
    if (!$snap) {
        return [];
    }
    $s = $snap['support'] ?? [];
    return [
        'tillNumber' => $s['tillNumber'] ?? '',
        'paybillNumber' => $s['paybillNumber'] ?? '',
        'supportNumber' => $s['supportNumber'] ?? '',
        'supportWhatsapp' => $s['supportWhatsapp'] ?? '',
        'updatedAt' => $snap['publishedAt'] ?? null,
    ];
} catch (\Throwable $e) {
    return [];
}
