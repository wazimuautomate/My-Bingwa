<?php
/**
 * TEMPORARY standalone diagnostic — no framework, no secrets printed. Visit
 * https://your-domain/admin/diag.php to see why the app 500s, then DELETE this file.
 *
 * If even THIS page returns 500, the cause is the .htaccess (rename admin/.htaccess and
 * retry). If it renders, the report below points at PHP version / extensions / config / DB.
 */
header('Content-Type: text/plain; charset=utf-8');

echo "My Bingwa Admin — diagnostics\n";
echo str_repeat('=', 44) . "\n";

echo "PHP version : " . PHP_VERSION . "  "
   . (version_compare(PHP_VERSION, '8.0.0', '>=') ? 'OK' : '>>> TOO OLD — set PHP 8.1/8.2 in cPanel MultiPHP Manager') . "\n";
echo "SAPI        : " . PHP_SAPI . "\n\n";

echo "Required extensions:\n";
foreach (['pdo_mysql', 'openssl', 'gd', 'mbstring', 'json', 'curl'] as $ext) {
    echo "  " . str_pad($ext, 12) . (extension_loaded($ext) ? 'yes' : '>>> MISSING') . "\n";
}
echo "\n";

$cfgPath = __DIR__ . '/config/config.php';
echo "config/config.php exists: " . (is_file($cfgPath) ? 'yes' : '>>> NO (create it)') . "\n";
if (is_file($cfgPath)) {
    $cfg = @include $cfgPath;
    if (!is_array($cfg)) {
        echo ">>> config.php did NOT return an array — likely a PHP syntax error from editing.\n";
    } else {
        $db = $cfg['db'] ?? [];
        $name = (string) ($db['name'] ?? '');
        $keySet = !empty($cfg['app_key']) && $cfg['app_key'] !== 'CHANGE_ME_TO_A_LONG_RANDOM_STRING';
        echo "app_key set   : " . ($keySet ? 'yes' : '>>> NO') . "\n";
        echo "db.name set   : " . ($name !== '' && $name !== 'PUT_DB_NAME' ? 'yes (' . $name . ')' : '>>> NO — still the placeholder') . "\n";
        echo "db.user set   : " . (!empty($db['user']) && $db['user'] !== 'PUT_DB_USER' ? 'yes' : '>>> NO — still the placeholder') . "\n";
        if ($name !== '' && $name !== 'PUT_DB_NAME') {
            try {
                $pdo = new PDO(
                    'mysql:host=' . ($db['host'] ?? 'localhost') . ';dbname=' . $name . ';charset=utf8mb4',
                    (string) ($db['user'] ?? ''), (string) ($db['pass'] ?? ''),
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                echo "DB connection : OK\n";
            } catch (Throwable $e) {
                echo "DB connection : >>> FAILED — " . $e->getMessage() . "\n";
            }
        }
    }
}

echo "\nmod_rewrite  : " . (function_exists('apache_get_modules')
    ? (in_array('mod_rewrite', apache_get_modules(), true) ? 'yes' : 'NO') : 'unknown (LiteSpeed/CGI)') . "\n";

echo "\nIf you can read this, PHP works. Fix any '>>>' lines above.\n";
echo "When done, DELETE this diag.php file.\n";
