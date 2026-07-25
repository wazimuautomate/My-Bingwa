<?php
/**
 * Settings: profile, password, TOTP two-factor (with one-time recovery codes), active
 * sessions, administrators and roles/permissions (RBAC). Administrator/role management
 * requires admins.manage; a Super Admin flag can only be granted by a Super Admin.
 */

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Crypto;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Rbac;
use App\Core\Request;
use App\Core\Session;
use App\Core\Totp;
use App\Core\Validator;

final class SettingsController extends Controller
{
    private function usersTable(): string { return Database::table('admin_users'); }

    /* -------------------------------------------------------------- profile */

    public function index(Request $request): void
    {
        $this->requireAuth();
        $user = Auth::user();
        $sessions = Database::fetchAll(
            'SELECT * FROM ' . Database::table('admin_sessions') . ' WHERE admin_user_id = ? ORDER BY last_seen_at DESC',
            [(int) $user['id']]
        );
        $this->view('settings/index', [
            'activeNav' => 'settings', 'pageTitle' => 'Settings',
            'user' => $user, 'sessions' => $sessions, 'currentSession' => session_id(),
            'env' => Config::isProduction() ? 'Production' : 'Staging',
            'phpVersion' => PHP_VERSION,
            'dbOk' => $this->dbOk(),
            'signingConfigured' => \App\Core\Signer::isConfigured(),
        ]);
    }

    public function saveProfile(Request $request): void
    {
        Csrf::check($request);
        $this->requireAuth();
        $user = Auth::user();
        $name = trim((string) $request->post('name', ''));
        $email = strtolower(trim((string) $request->post('email', '')));
        $v = Validator::make(['name' => $name, 'email' => $email]);
        $v->validate(['name' => 'required|max:120', 'email' => 'required|email|max:190']);
        $dupe = Database::fetch('SELECT id FROM ' . $this->usersTable() . ' WHERE email = ? AND id <> ?', [$email, (int) $user['id']]);
        if ($dupe) { $v->add('email', 'That email is already in use.'); }
        if ($v->fails()) { Flash::error(implode(' ', array_values($v->firstErrors()))); $this->redirect('/settings'); }
        Database::run('UPDATE ' . $this->usersTable() . ' SET name=?, email=?, updated_at=UTC_TIMESTAMP() WHERE id=?', [$name, $email, (int) $user['id']]);
        Audit::log(['action' => 'profile.update', 'entity_type' => 'admin_user', 'entity_id' => (int) $user['id']]);
        Flash::success('Profile updated.');
        $this->redirect('/settings');
    }

    public function savePassword(Request $request): void
    {
        Csrf::check($request);
        $this->requireAuth();
        $user = Auth::user();
        $current = (string) $request->post('current_password', '');
        $new = (string) $request->post('new_password', '');
        if (!password_verify($current, (string) $user['password_hash'])) {
            Flash::error('Current password is incorrect.'); $this->redirect('/settings');
        }
        if (strlen($new) < 10) {
            Flash::error('New password must be at least 10 characters.'); $this->redirect('/settings');
        }
        Database::run('UPDATE ' . $this->usersTable() . ' SET password_hash=?, updated_at=UTC_TIMESTAMP() WHERE id=?', [password_hash($new, PASSWORD_DEFAULT), (int) $user['id']]);
        Session::regenerate(); // rotate after a credential change
        Audit::log(['action' => 'password.change', 'entity_type' => 'admin_user', 'entity_id' => (int) $user['id']]);
        Flash::success('Password changed.');
        $this->redirect('/settings');
    }

    /* -------------------------------------------------------------- 2FA */

    public function twoFactor(Request $request): void
    {
        $this->requireAuth();
        $user = Auth::user();
        $pendingSecret = null;
        if ((int) $user['totp_enabled'] !== 1) {
            $pendingSecret = Session::get('_totp_pending');
            if (!$pendingSecret) {
                $pendingSecret = Totp::generateSecret();
                Session::set('_totp_pending', $pendingSecret);
            }
        }
        $this->view('settings/twofa', [
            'activeNav' => 'settings', 'pageTitle' => 'Two-factor',
            'user' => $user, 'pendingSecret' => $pendingSecret,
            'provisioningUri' => $pendingSecret ? Totp::provisioningUri($pendingSecret, (string) $user['email'], 'My Bingwa Admin') : '',
            'recoveryCodes' => Session::get('_recovery_codes_once'),
        ]);
        Session::forget('_recovery_codes_once');
    }

    public function enable2fa(Request $request): void
    {
        Csrf::check($request);
        $this->requireAuth();
        $user = Auth::user();
        $secret = (string) Session::get('_totp_pending', '');
        $code = (string) $request->post('totp_code', '');
        if ($secret === '' || !Totp::verify($secret, $code)) {
            Flash::error('That code did not verify. Scan the key again and enter the current code.');
            $this->redirect('/settings/2fa');
        }
        $codes = Totp::recoveryCodes(8);
        $hashed = array_map(fn($c) => password_hash(strtolower($c), PASSWORD_DEFAULT), $codes);
        Database::run(
            'UPDATE ' . $this->usersTable() . ' SET totp_enabled=1, totp_secret=?, recovery_codes=?, updated_at=UTC_TIMESTAMP() WHERE id=?',
            [Crypto::encrypt($secret), json_encode($hashed), (int) $user['id']]
        );
        Session::forget('_totp_pending');
        Session::set('_recovery_codes_once', $codes);
        Audit::log(['action' => 'twofa.enable', 'entity_type' => 'admin_user', 'entity_id' => (int) $user['id']]);
        Flash::success('Two-factor enabled. Save your recovery codes now — they are shown once.');
        $this->redirect('/settings/2fa');
    }

    public function disable2fa(Request $request): void
    {
        Csrf::check($request);
        $this->requireAuth();
        $user = Auth::user();
        if (!password_verify((string) $request->post('password', ''), (string) $user['password_hash'])) {
            Flash::error('Password incorrect. Two-factor was not disabled.'); $this->redirect('/settings/2fa');
        }
        Database::run('UPDATE ' . $this->usersTable() . ' SET totp_enabled=0, totp_secret=\'\', recovery_codes=NULL, updated_at=UTC_TIMESTAMP() WHERE id=?', [(int) $user['id']]);
        Audit::log(['action' => 'twofa.disable', 'entity_type' => 'admin_user', 'entity_id' => (int) $user['id']]);
        Flash::warning('Two-factor disabled.');
        $this->redirect('/settings/2fa');
    }

    public function revokeSession(Request $request): void
    {
        Csrf::check($request);
        $this->requireAuth();
        $sid = (string) $request->post('session_id', '');
        Database::run('DELETE FROM ' . Database::table('admin_sessions') . ' WHERE session_id = ? AND admin_user_id = ?', [$sid, Auth::id()]);
        Flash::success('Session revoked. That device must sign in again on its next request.');
        $this->redirect('/settings');
    }

    /* -------------------------------------------------------------- admins */

    public function admins(Request $request): void
    {
        $this->guard('admins.manage');
        $this->view('settings/admins', [
            'activeNav' => 'settings', 'pageTitle' => 'Administrators',
            'admins' => Database::fetchAll('SELECT * FROM ' . $this->usersTable() . ' ORDER BY is_super_admin DESC, name'),
            'roles' => Database::fetchAll('SELECT * FROM ' . Database::table('roles') . ' ORDER BY name'),
            'assignments' => $this->roleAssignments(),
        ]);
    }

    public function saveAdmin(Request $request): void
    {
        Csrf::check($request);
        $this->guard('admins.manage');
        $id = (int) $request->post('id', 0);
        $isNew = $id === 0;
        $name = trim((string) $request->post('name', ''));
        $email = strtolower(trim((string) $request->post('email', '')));
        $password = (string) $request->post('password', '');
        $roleIds = array_map('intval', (array) $request->post('roles', []));
        $wantSuper = $request->post('is_super_admin') ? 1 : 0;

        $v = Validator::make(['name' => $name, 'email' => $email]);
        $v->validate(['name' => 'required|max:120', 'email' => 'required|email|max:190']);
        if ($isNew && strlen($password) < 10) { $v->add('password', 'Set an initial password (min 10 chars).'); }
        $dupe = Database::fetch('SELECT id FROM ' . $this->usersTable() . ' WHERE email = ? AND id <> ?', [$email, $id]);
        if ($dupe) { $v->add('email', 'Email already in use.'); }
        // Only a Super Admin can grant Super Admin.
        if ($wantSuper && !Auth::isSuperAdmin()) { $v->add('is_super_admin', 'Only a Super Admin can grant Super Admin.'); $wantSuper = 0; }
        if ($v->fails()) { Flash::error(implode(' ', array_values($v->firstErrors()))); $this->redirect('/settings/admins'); }

        if ($isNew) {
            Database::run(
                'INSERT INTO ' . $this->usersTable() . ' (name, email, password_hash, is_super_admin, status, totp_enabled, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 1, 0, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
                [$name, $email, password_hash($password, PASSWORD_DEFAULT), $wantSuper]
            );
            $id = (int) Database::pdo()->lastInsertId();
        } else {
            if ($password !== '' && strlen($password) >= 10) {
                Database::run('UPDATE ' . $this->usersTable() . ' SET password_hash=? WHERE id=?', [password_hash($password, PASSWORD_DEFAULT), $id]);
            }
            // Prevent removing the last Super Admin.
            if (!$wantSuper && $this->isLastSuperAdmin($id)) {
                Flash::error('Cannot remove the last Super Admin.'); $this->redirect('/settings/admins');
            }
            Database::run('UPDATE ' . $this->usersTable() . ' SET name=?, email=?, is_super_admin=?, updated_at=UTC_TIMESTAMP() WHERE id=?', [$name, $email, $wantSuper, $id]);
        }
        // Reset role assignments.
        Database::run('DELETE FROM ' . Database::table('admin_user_roles') . ' WHERE admin_user_id = ?', [$id]);
        foreach ($roleIds as $rid) {
            Database::run('INSERT IGNORE INTO ' . Database::table('admin_user_roles') . ' (admin_user_id, role_id) VALUES (?, ?)', [$id, $rid]);
        }
        Audit::log(['action' => $isNew ? 'admin.create' : 'admin.update', 'entity_type' => 'admin_user', 'entity_id' => $id, 'after' => ['name' => $name, 'email' => $email, 'super' => $wantSuper]]);
        Flash::success('Administrator saved.');
        $this->redirect('/settings/admins');
    }

    public function disableAdmin(Request $request, string $id): void
    {
        Csrf::check($request);
        $this->guard('admins.manage');
        $id = (int) $id;
        if ($id === Auth::id()) { Flash::error('You cannot disable your own account.'); $this->redirect('/settings/admins'); }
        if ($this->isLastSuperAdmin($id)) { Flash::error('Cannot disable the last Super Admin.'); $this->redirect('/settings/admins'); }
        Database::run('UPDATE ' . $this->usersTable() . ' SET status=0, updated_at=UTC_TIMESTAMP() WHERE id=?', [$id]);
        Database::run('DELETE FROM ' . Database::table('admin_sessions') . ' WHERE admin_user_id = ?', [$id]);
        Audit::log(['action' => 'admin.disable', 'entity_type' => 'admin_user', 'entity_id' => $id]);
        Flash::success('Administrator disabled and signed out.');
        $this->redirect('/settings/admins');
    }

    /* -------------------------------------------------------------- roles */

    public function roles(Request $request): void
    {
        $this->guard('admins.manage');
        $this->view('settings/roles', [
            'activeNav' => 'settings', 'pageTitle' => 'Roles & permissions',
            'roles' => Database::fetchAll('SELECT * FROM ' . Database::table('roles') . ' ORDER BY name'),
            'permissions' => Database::fetchAll('SELECT * FROM ' . Database::table('permissions') . ' ORDER BY perm_group, perm_key'),
            'rolePerms' => $this->rolePermissionMap(),
        ]);
    }

    public function saveRole(Request $request): void
    {
        Csrf::check($request);
        $this->guard('admins.manage');
        $roleId = (int) $request->post('role_id', 0);
        $permIds = array_map('intval', (array) $request->post('permissions', []));
        $role = Database::fetch('SELECT * FROM ' . Database::table('roles') . ' WHERE id = ?', [$roleId]);
        if (!$role) { Flash::error('Role not found.'); $this->redirect('/settings/roles'); }
        Database::run('DELETE FROM ' . Database::table('role_permissions') . ' WHERE role_id = ?', [$roleId]);
        foreach ($permIds as $pid) {
            Database::run('INSERT IGNORE INTO ' . Database::table('role_permissions') . ' (role_id, permission_id) VALUES (?, ?)', [$roleId, $pid]);
        }
        Rbac::invalidate();
        Audit::log(['action' => 'role.update', 'entity_type' => 'role', 'entity_id' => $roleId, 'after' => ['permissions' => count($permIds)]]);
        Flash::success('Role permissions updated.');
        $this->redirect('/settings/roles');
    }

    /* -------------------------------------------------------------- helpers */

    private function isLastSuperAdmin(int $id): bool
    {
        $supers = (int) (Database::scalar('SELECT COUNT(*) FROM ' . $this->usersTable() . ' WHERE is_super_admin = 1 AND status = 1') ?? 0);
        $isSuper = (int) (Database::scalar('SELECT is_super_admin FROM ' . $this->usersTable() . ' WHERE id = ?', [$id]) ?? 0) === 1;
        return $isSuper && $supers <= 1;
    }

    private function roleAssignments(): array
    {
        $rows = Database::fetchAll('SELECT admin_user_id, role_id FROM ' . Database::table('admin_user_roles'));
        $map = [];
        foreach ($rows as $r) { $map[(int) $r['admin_user_id']][] = (int) $r['role_id']; }
        return $map;
    }

    private function rolePermissionMap(): array
    {
        $rows = Database::fetchAll('SELECT role_id, permission_id FROM ' . Database::table('role_permissions'));
        $map = [];
        foreach ($rows as $r) { $map[(int) $r['role_id']][(int) $r['permission_id']] = true; }
        return $map;
    }

    private function dbOk(): bool
    {
        try { Database::scalar('SELECT 1'); return true; } catch (\Throwable $e) { return false; }
    }
}
