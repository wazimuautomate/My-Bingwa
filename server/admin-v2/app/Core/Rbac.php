<?php
/**
 * Role-based access control, enforced ON THE SERVER for every request. Hiding a
 * sidebar item is never authorisation — controllers call Rbac::require('perm').
 *
 * A Super Admin implicitly holds every permission. Other admins hold the union of the
 * permissions granted to their assigned roles.
 */

namespace App\Core;

final class Rbac
{
    private static ?array $cache = null;

    /** All permission keys the current user holds. */
    public static function permissions(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $user = Auth::user();
        if (!$user) {
            return self::$cache = [];
        }
        if ((int) $user['is_super_admin'] === 1) {
            return self::$cache = ['*'];
        }
        $rows = Database::fetchAll(
            'SELECT DISTINCT p.perm_key
               FROM ' . Database::table('admin_user_roles') . ' ur
               JOIN ' . Database::table('role_permissions') . ' rp ON rp.role_id = ur.role_id
               JOIN ' . Database::table('permissions') . ' p ON p.id = rp.permission_id
              WHERE ur.admin_user_id = ?',
            [(int) $user['id']]
        );
        return self::$cache = array_column($rows, 'perm_key');
    }

    public static function can(string $permission): bool
    {
        $perms = self::permissions();
        return in_array('*', $perms, true) || in_array($permission, $perms, true);
    }

    public static function canAny(array $permissions): bool
    {
        foreach ($permissions as $p) {
            if (self::can($p)) {
                return true;
            }
        }
        return false;
    }

    /** Abort with 403 unless the current user holds the permission. */
    public static function require(string $permission): void
    {
        if (!self::can($permission)) {
            self::deny($permission);
        }
    }

    public static function requireSuperAdmin(): void
    {
        if (!Auth::isSuperAdmin()) {
            self::deny('super-admin');
        }
    }

    private static function deny(string $permission): void
    {
        $html = View::render('errors/forbidden', ['permission' => $permission], null);
        Response::html($html, 403);
    }

    public static function invalidate(): void
    {
        self::$cache = null;
    }
}
