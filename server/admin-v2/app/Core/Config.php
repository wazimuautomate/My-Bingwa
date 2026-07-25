<?php
/**
 * Loads the deployment config (secrets, DB credentials, signing key paths) from a
 * PHP array file that is NEVER committed. Access with dot notation: Config::get('db.host').
 *
 * The config file location is resolved in this order so secrets can live OUTSIDE the
 * web root on cPanel when the operator chooses:
 *   1. $MYBINGWA_ADMIN_CONFIG environment variable (absolute path), else
 *   2. config/config.php next to the application (blocked from download by .htaccess).
 */

namespace App\Core;

final class Config
{
    private static array $data = [];
    private static bool $loaded = false;

    public static function load(string $defaultPath): void
    {
        $envPath = getenv('MYBINGWA_ADMIN_CONFIG') ?: '';
        $path = ($envPath !== '' && is_file($envPath)) ? $envPath : $defaultPath;
        if (!is_file($path)) {
            http_response_code(500);
            header('Content-Type: text/plain');
            exit("Admin is not configured yet. Copy config/config.sample.php to config/config.php and fill it in.");
        }
        $data = require $path;
        self::$data = is_array($data) ? $data : [];
        self::$loaded = true;
    }

    public static function get(string $key, $default = null)
    {
        if (!self::$loaded) {
            return $default;
        }
        $node = self::$data;
        foreach (explode('.', $key) as $seg) {
            if (is_array($node) && array_key_exists($seg, $node)) {
                $node = $node[$seg];
            } else {
                return $default;
            }
        }
        return $node;
    }

    public static function all(): array
    {
        return self::$data;
    }

    public static function isProduction(): bool
    {
        return strtolower((string) self::get('environment', 'production')) === 'production';
    }
}
