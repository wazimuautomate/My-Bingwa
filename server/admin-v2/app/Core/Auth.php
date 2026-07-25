<?php
/**
 * Session-based admin authentication with:
 *  - password_hash/password_verify (bcrypt by default, upgradeable),
 *  - login throttling + temporary lockout,
 *  - TOTP two-factor (pending state between password and code),
 *  - session id rotation after login and after a privilege change,
 *  - short-lived re-authentication gate for sensitive actions.
 *
 * The signed-in user id lives in the session; the full user record is loaded per
 * request from the database so role/permission changes take effect immediately.
 */

namespace App\Core;

use Throwable;

final class Auth
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_MINUTES = 15;
    private const REAUTH_WINDOW_SECONDS = 600; // 10 minutes

    private static ?array $cachedUser = null;

    /* ---------------------------------------------------------------- login */

    /**
     * Attempt a username/password login.
     * Returns: 'ok' (fully signed in), 'totp' (password ok, 2FA code needed),
     *          'locked', or 'invalid'.
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

        if ((int) $user['totp_enabled'] === 1) {
            Session::set('_2fa_pending', (int) $user['id']);
            Session::set('_2fa_time', time());
            return 'totp';
        }

        self::completeLogin($user, $ip);
        return 'ok';
    }

    /** Complete a pending 2FA login with a TOTP or recovery code. */
    public static function completeTotp(string $code, string $ip): bool
    {
        $uid = (int) Session::get('_2fa_pending', 0);
        if ($uid === 0 || (time() - (int) Session::get('_2fa_time', 0)) > 300) {
            return false;
        }
        $user = self::findById($uid);
        if (!$user) {
            return false;
        }
        $secret = Crypto::decrypt((string) $user['totp_secret']);
        $verified = $secret !== '' && Totp::verify($secret, $code);

        if (!$verified) {
            $verified = self::consumeRecoveryCode($user, $code);
        }
        if (!$verified) {
            self::recordAttempt((string) $user['email'], $ip, false);
            return false;
        }

        Session::forget('_2fa_pending');
        Session::forget('_2fa_time');
        self::completeLogin($user, $ip);
        return true;
    }

    private static function completeLogin(array $user, string $ip): void
    {
        Session::regenerate();
        Session::set('_uid', (int) $user['id']);
        Session::set('_login_at', time());
        Session::set('_reauth_at', time());
        self::recordAttempt((string) $user['email'], $ip, true);
        Database::run(
            'UPDATE ' . Database::table('admin_users') . ' SET last_login_at = UTC_TIMESTAMP() WHERE id = ?',
            [$user['id']]
        );
        self::trackSession($ip);
    }

    public static function logout(): void
    {
        self::untrackSession();
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

    /* -------------------------------------------------------- re-authenticate */

    public static function reauthFresh(): bool
    {
        return (time() - (int) Session::get('_reauth_at', 0)) <= self::REAUTH_WINDOW_SECONDS;
    }

    /** Confirm the current user's password (and TOTP if enabled) for a sensitive action. */
    public static function reauthenticate(string $password, string $totp = ''): bool
    {
        $user = self::user();
        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            return false;
        }
        if ((int) $user['totp_enabled'] === 1) {
            $secret = Crypto::decrypt((string) $user['totp_secret']);
            if ($secret === '' || !Totp::verify($secret, $totp)) {
                return false;
            }
        }
        Session::set('_reauth_at', time());
        return true;
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

    /* ------------------------------------------------------- recovery codes */

    private static function consumeRecoveryCode(array $user, string $code): bool
    {
        $code = strtolower(trim($code));
        $stored = json_decode((string) ($user['recovery_codes'] ?? '[]'), true) ?: [];
        foreach ($stored as $i => $hash) {
            if (password_verify($code, (string) $hash)) {
                unset($stored[$i]);
                Database::run(
                    'UPDATE ' . Database::table('admin_users') . ' SET recovery_codes = ? WHERE id = ?',
                    [json_encode(array_values($stored)), $user['id']]
                );
                return true;
            }
        }
        return false;
    }

    /* --------------------------------------------------------- session track */

    private static function trackSession(string $ip): void
    {
        try {
            Database::run(
                'INSERT INTO ' . Database::table('admin_sessions') . '
                    (admin_user_id, session_id, ip, user_agent, created_at, last_seen_at)
                 VALUES (?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE ip = VALUES(ip), user_agent = VALUES(user_agent), last_seen_at = UTC_TIMESTAMP()',
                [self::id(), session_id(), substr($ip, 0, 45), substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]
            );
        } catch (Throwable $e) {
        }
    }

    public static function touchSession(string $ip): void
    {
        if (!self::check()) {
            return;
        }
        try {
            Database::run(
                'UPDATE ' . Database::table('admin_sessions') . ' SET last_seen_at = UTC_TIMESTAMP(), ip = ? WHERE session_id = ?',
                [substr($ip, 0, 45), session_id()]
            );
        } catch (Throwable $e) {
        }
    }

    private static function untrackSession(): void
    {
        try {
            Database::run(
                'DELETE FROM ' . Database::table('admin_sessions') . ' WHERE session_id = ?',
                [session_id()]
            );
        } catch (Throwable $e) {
        }
    }
}
