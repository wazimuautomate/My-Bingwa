<?php
/**
 * Global helper functions. Loaded once by the autoloader. Kept tiny and dependency
 * free. `e()` is the HTML-escape used by every view.
 */

use App\Core\Response;

if (!function_exists('e')) {
    /** HTML-escape for safe output. */
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    /** Application URL honouring the base path (e.g. /admin/offers). */
    function url(string $path = '/'): string
    {
        return Response::url($path);
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return Response::url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('icon')) {
    /** Inline SVG icon from the single admin icon family. */
    function icon(string $name, int $size = 20, string $class = ''): string
    {
        return \App\Support\Icons::svg($name, $size, $class);
    }
}

if (!function_exists('can')) {
    /** True if the current admin holds a permission (view-layer convenience). */
    function can(string $permission): bool
    {
        return \App\Core\Rbac::can($permission);
    }
}

if (!function_exists('old')) {
    /** Repopulate a form field after a validation failure. */
    function old(string $key, $default = '')
    {
        $old = \App\Core\Session::get('_old', []);
        return $old[$key] ?? $default;
    }
}

if (!function_exists('str_mask_phone')) {
    /**
     * Mask a Kenyan MSISDN for display: keep the country/network head and last 3.
     * 254712345678 -> 2547*****678 ; 0712345678 -> 0712***678.
     */
    function str_mask_phone(?string $msisdn): string
    {
        $digits = preg_replace('/\D/', '', (string) $msisdn);
        if ($digits === '' || strlen($digits) < 6) {
            return $digits === '' ? '—' : str_repeat('*', strlen($digits));
        }
        $head = substr($digits, 0, 4);
        $tail = substr($digits, -3);
        return $head . str_repeat('*', max(3, strlen($digits) - 7)) . $tail;
    }
}

if (!function_exists('str_mask_receipt')) {
    /** Mask an M-Pesa receipt: keep first 3 + last 2, star the middle. */
    function str_mask_receipt(?string $receipt): string
    {
        $r = trim((string) $receipt);
        if ($r === '') {
            return '—';
        }
        if (strlen($r) <= 5) {
            return substr($r, 0, 1) . str_repeat('*', strlen($r) - 1);
        }
        return substr($r, 0, 3) . str_repeat('*', strlen($r) - 5) . substr($r, -2);
    }
}

if (!function_exists('nairobi_now')) {
    /** Current time in Africa/Nairobi as a DateTimeImmutable. */
    function nairobi_now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('Africa/Nairobi'));
    }
}

if (!function_exists('fmt_nairobi')) {
    /** Format a UTC/db datetime string for Africa/Nairobi display. */
    function fmt_nairobi(?string $utc, string $format = 'd M Y, H:i'): string
    {
        if (!$utc) {
            return '—';
        }
        try {
            $dt = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
            return $dt->setTimezone(new DateTimeZone('Africa/Nairobi'))->format($format);
        } catch (Throwable $e) {
            return (string) $utc;
        }
    }
}

if (!function_exists('ksh')) {
    function ksh($amount): string
    {
        return 'KSh ' . number_format((float) $amount, 0);
    }
}

if (!function_exists('array_get')) {
    function array_get(array $arr, string $key, $default = null)
    {
        return array_key_exists($key, $arr) ? $arr[$key] : $default;
    }
}
