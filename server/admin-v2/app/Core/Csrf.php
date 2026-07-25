<?php
/**
 * CSRF protection for every state-changing web request. A per-session token echoed in
 * a hidden field and verified with a constant-time compare.
 */

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function check(Request $request): void
    {
        $sent = (string) $request->input('_csrf', $request->header('X-CSRF-Token'));
        if (!hash_equals($_SESSION['_csrf'] ?? '', $sent)) {
            Response::html('<!doctype html><meta charset="utf-8"><div style="font-family:system-ui;padding:40px">'
                . 'Your session expired or the request could not be verified. Please go back and try again.'
                . '</div>', 419);
        }
    }
}
