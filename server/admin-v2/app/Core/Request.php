<?php
/**
 * Read-only wrapper over the incoming HTTP request. Keeps controllers free of raw
 * superglobal access and centralises path/method/JSON parsing and client IP.
 */

namespace App\Core;

final class Request
{
    private ?array $json = null;

    public function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    /** Path relative to the application base (front controller), without query string. */
    public function path(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $uri = parse_url($uri, PHP_URL_PATH) ?: '/';
        $base = self::basePath();
        if ($base !== '' && strpos($uri, $base) === 0) {
            $uri = substr($uri, strlen($base));
        }
        $uri = '/' . ltrim($uri, '/');
        return $uri === '' ? '/' : (rtrim($uri, '/') ?: '/');
    }

    /** Directory the front controller lives in, e.g. "/admin". Used to build links. */
    public static function basePath(): string
    {
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
        return $dir === '/' ? '' : $dir;
    }

    public function get(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    public function post(string $key, $default = null)
    {
        return $_POST[$key] ?? $default;
    }

    public function input(string $key, $default = null)
    {
        if (array_key_exists($key, $_POST)) {
            return $_POST[$key];
        }
        $json = $this->jsonBody();
        return $json[$key] ?? ($_GET[$key] ?? $default);
    }

    public function all(): array
    {
        return array_merge($_GET, $_POST, $this->jsonBody());
    }

    public function jsonBody(): array
    {
        if ($this->json !== null) {
            return $this->json;
        }
        $ct = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
        if (stripos($ct, 'application/json') !== false) {
            $raw = file_get_contents('php://input') ?: '';
            $decoded = json_decode($raw, true);
            $this->json = is_array($decoded) ? $decoded : [];
        } else {
            $this->json = [];
        }
        return $this->json;
    }

    public function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return (string) ($_SERVER[$key] ?? '');
    }

    public function ip(): string
    {
        // Only trust a forwarded header if the operator explicitly names one they control.
        $trusted = (string) Config::get('security.trusted_proxy_header', '');
        if ($trusted !== '' && !empty($_SERVER[$trusted])) {
            $parts = explode(',', (string) $_SERVER[$trusted]);
            return trim($parts[0]);
        }
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    public function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }
}
