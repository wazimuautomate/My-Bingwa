<?php
/**
 * Tiny key/value store on the mb_settings table for operational flags such as the
 * last publish time and the current sync-hint version.
 */

namespace App\Services;

use App\Core\Database;

final class Settings
{
    public static function get(string $key, $default = null)
    {
        $row = Database::fetch('SELECT svalue FROM ' . Database::table('settings') . ' WHERE skey = ?', [$key]);
        return $row ? $row['svalue'] : $default;
    }

    public static function set(string $key, string $value): void
    {
        Database::run(
            'INSERT INTO ' . Database::table('settings') . ' (skey, svalue, updated_at)
             VALUES (?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE svalue = VALUES(svalue), updated_at = UTC_TIMESTAMP()',
            [$key, $value]
        );
    }

    public static function all(): array
    {
        $out = [];
        foreach (Database::fetchAll('SELECT skey, svalue FROM ' . Database::table('settings')) as $r) {
            $out[$r['skey']] = $r['svalue'];
        }
        return $out;
    }
}
