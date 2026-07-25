<?php
/**
 * Session-based admin authentication with:
 *  - password_hash/password_verify (bcrypt by default, upgradeable),
 *  - login throttling + temporary lockout,
 *  - session id rotation after login.
 *
 * The signed-in user id lives in the session; the full user record is loaded per
 * request from the database so an access change takes effect immediately.
 */

namespace App\Core;

use Throwable;

final class Auth
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_MINUTES = 15;

    private static ?array $cachedUser = null;

    /* ---------------------------------------------------------------- login */

    /**
     * Attempt a username/password login.
     * Returns: 'ok' (signed in), 'locked', or 'invalid'.
     */
    public static function attempt(string $email, string $password, string $ip): string
    {
        $email = strtolower(trim($email));
        $user = Database::fetch(
            'SELECT * FROM ' . Database::table('admin_users') . ' WHERE email = ? LIMIT 1',
            [$email]
        );

        if ($user && self::isLocked($user)) {
            self::recordAttempt($email, $ip, false);
            return 'locked';
        }

        if (!$user || (int) $user['status'] !== 1 || !password_verify($password, (string) $user['password_hash'])) {
            self::recordAttempt($email, $ip, false);
            if ($user) {
                self::bumpFailure($user);
            }
            return 'invalid';
        }

        // Password correct → reset failures.
        Database::run(
            'UPDATE ' . Database::table('admin_users') . ' SET failed_attempts = 0, locked_until = NULL WHERE id = ?',
            [$user['id']]
        );

        // Rehash if the algorithm/cost changed.
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            Database::run(
                'UPDATE ' . Database::table('admin_users') . ' SET password_hash = ? WHERE id = ?',
                [password_hash($password, PASSWORD_DEFAULT), $user['id']]
            );
        }

        self::completeLogin($user, $ip);
        return 'ok';
    }

    private static function completeLogin(array $user, string $ip): void
    {
        Session::regenerate();
        Session::set('_uid', (int) $user['id']);
        Session::set('_login_at', time());
        self::recordAttempt((string) $user['email'], $ip, true);
        Database::run(
            'UPDATE ' . Database::table('admin_users') . ' SET last_login_at = UTC_TIMESTAMP() WHERE id = ?',
            [$user['id']]
        );
    }

    public static function logout(): void
    {
        Session::destroy();
        self::$cachedUser = null;
    }

    /* ------------------------------------------------------------- identity */

    public static function check(): bool
    {
        return (int) Session::get('_uid', 0) > 0;
    }

    public static function id(): int
    {
        return (int) Session::get('_uid', 0);
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        if (self::$cachedUser !== null) {
            return self::$cachedUser;
        }
        self::$cachedUser = self::findById(self::id());
        if (self::$cachedUser === null || (int) self::$cachedUser['status'] !== 1) {
            self::logout();
            return null;
        }
        return self::$cachedUser;
    }

    public static function isSuperAdmin(): bool
    {
        $u = self::user();
        return $u !== null && (int) $u['is_super_admin'] === 1;
    }

    private static function findById(int $id): ?array
    {
        return Database::fetch(
            'SELECT * FROM ' . Database::table('admin_users') . ' WHERE id = ? LIMIT 1',
            [$id]
        );
    }

    /* ----------------------------------------------------------- throttling */

    private static function isLocked(array $user): bool
    {
        $until = $user['locked_until'] ?? null;
        if (!$until) {
            return false;
        }
        try {
            return (new \DateTimeImmutable($until, new \DateTimeZone('UTC'))) > new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        } catch (Throwable $e) {
            return false;
        }
    }

    private static function bumpFailure(array $user): void
    {
        $attempts = (int) $user['failed_attempts'] + 1;
        if ($attempts >= self::MAX_ATTEMPTS) {
            Database::run(
                'UPDATE ' . Database::table('admin_users') . '
                    SET failed_attempts = ?, locked_until = DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? MINUTE) WHERE id = ?',
                [$attempts, self::LOCK_MINUTES, $user['id']]
            );
        } else {
            Database::run(
                'UPDATE ' . Database::table('admin_users') . ' SET failed_attempts = ? WHERE id = ?',
                [$attempts, $user['id']]
            );
        }
    }

    private static function recordAttempt(string $email, string $ip, bool $success): void
    {
        try {
            Database::run(
                'INSERT INTO ' . Database::table('login_attempts') . ' (email, ip, success, created_at)
                 VALUES (?, ?, ?, UTC_TIMESTAMP())',
                [substr($email, 0, 190), substr($ip, 0, 45), $success ? 1 : 0]
            );
        } catch (Throwable $e) {
            // Never block login on the audit/throttle table being briefly unavailable.
        }
    }
}
