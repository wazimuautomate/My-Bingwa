<?php
/**
 * Response helpers: HTML, JSON, redirect, and the security headers every admin page
 * sends. In production, error detail is never leaked.
 */

namespace App\Core;

final class Response
{
    public static function securityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        // Self-hosted assets + Google Fonts only; no inline scripts are used.
        $csp = "default-src 'self'; "
             . "img-src 'self' data:; "
             . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
             . "font-src 'self' https://fonts.gstatic.com; "
             . "script-src 'self'; "
             . "connect-src 'self'; "
             . "frame-ancestors 'none'; base-uri 'self'; form-action 'self'";
        header('Content-Security-Policy: ' . $csp);
        if (Session::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    public static function html(string $body, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: text/html; charset=utf-8');
        echo $body;
        exit;
    }

    public static function json(array $data, int $code = 200, array $headers = []): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($headers as $k => $v) {
            header($k . ': ' . $v);
        }
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function redirect(string $to): void
    {
        header('Location: ' . self::url($to));
        exit;
    }

    /** Build an application URL from a path like "/offers" honouring the base path. */
    public static function url(string $path): string
    {
        if (preg_match('#^https?://#', $path)) {
            return $path;
        }
        $base = Request::basePath();
        return $base . '/' . ltrim($path, '/');
    }

    public static function notFound(string $message = 'Not found'): void
    {
        self::html('<!doctype html><meta charset="utf-8"><title>404</title>'
            . '<div style="font-family:system-ui;padding:40px">404 — ' . htmlspecialchars($message) . '</div>', 404);
    }
}
