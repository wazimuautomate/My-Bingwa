<?php
/**
 * Shared admin helpers: session, login guard, DB, CSRF, escaping.
 * The admin panel manages offers, payment/support settings and notification
 * templates. Credentials live in config.php (admin_user / admin_pass), which the
 * web cannot download.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function admin_config(): array
{
    return require __DIR__ . '/../config.php';
}

function admin_db(): PDO
{
    return require __DIR__ . '/../db.php';
}

/** Redirect to the login page unless the admin is signed in. */
function require_login(): void
{
    if (empty($_SESSION['admin_ok'])) {
        header('Location: login.php');
        exit;
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function check_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Bad request (CSRF).');
    }
}

/** HTML-escape. */
function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/** One-line flash message via the session. */
function set_flash(string $msg): void
{
    $_SESSION['flash'] = $msg;
}

function take_flash(): ?string
{
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}
