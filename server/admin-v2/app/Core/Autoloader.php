<?php
/**
 * Minimal PSR-4 autoloader so the whole admin runs on plain cPanel PHP with no
 * `composer install` step. Maps the `App\` namespace to the `app/` directory.
 *
 * Usage (front controller):  require app/Core/Autoloader.php; Autoloader::register();
 */

namespace App\Core;

final class Autoloader
{
    private static string $baseDir = '';

    public static function register(string $appDir): void
    {
        self::$baseDir = rtrim($appDir, '/\\');
        spl_autoload_register([self::class, 'load']);
        require self::$baseDir . '/Support/helpers.php';
    }

    private static function load(string $class): void
    {
        $prefix = 'App\\';
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }
        $relative = substr($class, strlen($prefix));
        $path = self::$baseDir . '/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
}
