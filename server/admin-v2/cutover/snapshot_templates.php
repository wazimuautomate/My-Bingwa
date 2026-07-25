<?php
/**
 * OPT-IN cutover bridge: current published templates in the legacy get_templates.php
 * TemplateSet shape. Returns null on failure. See docs/MIGRATION_CUTOVER.md §6.
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
        return null;
    }
    $t = $snap['templates'] ?? ['version' => 1, 'delivery' => [], 'lowBalance' => []];
    $strip = fn($x) => [
        'id' => $x['id'], 'senderId' => $x['senderId'], 'category' => $x['category'],
        'pattern' => $x['pattern'], 'description' => $x['description'],
    ];
    return [
        'version' => $t['version'],
        'delivery' => array_map($strip, $t['delivery'] ?? []),
        'lowBalance' => array_map($strip, $t['lowBalance'] ?? []),
    ];
} catch (\Throwable $e) {
    return null;
}
