<?php
/**
 * One-shot flash messages rendered as toasts on the next page load.
 * Levels: success | error | warning | info.
 */

namespace App\Core;

final class Flash
{
    public static function add(string $level, string $message): void
    {
        $_SESSION['_flash'][] = ['level' => $level, 'message' => $message];
    }

    public static function success(string $m): void { self::add('success', $m); }
    public static function error(string $m): void   { self::add('error', $m); }
    public static function warning(string $m): void { self::add('warning', $m); }
    public static function info(string $m): void    { self::add('info', $m); }

    /** Return and clear queued messages. */
    public static function take(): array
    {
        $items = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $items;
    }

    /** Keep the submitted form values for one redirect (repopulate on error). */
    public static function keepOld(array $data): void
    {
        unset($data['_csrf'], $data['action'], $data['password'], $data['new_password'], $data['totp_code']);
        $_SESSION['_old'] = $data;
    }

    public static function clearOld(): void
    {
        unset($_SESSION['_old']);
    }
}
