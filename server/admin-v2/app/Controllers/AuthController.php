<?php
/**
 * Login, TOTP two-factor, sign out and recovery-code account recovery.
 * All state-changing posts are CSRF-checked; login is throttled in Auth.
 */

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Crypto;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

final class AuthController extends Controller
{
    public function showLogin(Request $request): void
    {
        if (Auth::check()) {
            $this->redirect('/');
        }
        // If nothing is installed yet, guide the operator to the installer.
        if (!Database::tableExists('admin_users')) {
            $this->redirect('/install');
        }
        Response::html(View::render('auth/login', [
            'flashes' => Flash::take(),
            'pageTitle' => 'Sign in',
        ], null));
    }

    public function login(Request $request): void
    {
        Csrf::check($request);
        $email = (string) $request->post('email', '');
        $password = (string) $request->post('password', '');
        $result = Auth::attempt($email, $password, $request->ip());

        if ($result === 'ok') {
            $intended = Session::get('_intended', '/');
            Session::forget('_intended');
            $this->redirect(is_string($intended) ? $intended : '/');
        }
        if ($result === 'totp') {
            $this->redirect('/2fa');
        }
        if ($result === 'locked') {
            Flash::error('Too many attempts. This account is temporarily locked. Try again shortly.');
        } else {
            Flash::error('Wrong email or password.');
        }
        $this->redirect('/login');
    }

    public function show2fa(Request $request): void
    {
        if (Auth::check()) {
            $this->redirect('/');
        }
        if ((int) Session::get('_2fa_pending', 0) === 0) {
            $this->redirect('/login');
        }
        Response::html(View::render('auth/twofa', [
            'flashes' => Flash::take(),
            'pageTitle' => 'Two-factor',
        ], null));
    }

    public function verify2fa(Request $request): void
    {
        Csrf::check($request);
        $code = (string) $request->post('totp_code', '');
        if (Auth::completeTotp($code, $request->ip())) {
            $intended = Session::get('_intended', '/');
            Session::forget('_intended');
            $this->redirect(is_string($intended) ? $intended : '/');
        }
        Flash::error('That code was not valid. Enter the current 6-digit code or a recovery code.');
        $this->redirect('/2fa');
    }

    public function showForgot(Request $request): void
    {
        Response::html(View::render('auth/forgot', [
            'flashes' => Flash::take(),
            'pageTitle' => 'Account recovery',
        ], null));
    }

    /**
     * Recovery-code reset: an admin who saved their 2FA recovery codes can set a new
     * password without email/SMS infrastructure. Consumes one recovery code.
     */
    public function forgot(Request $request): void
    {
        Csrf::check($request);
        $email = strtolower(trim((string) $request->post('email', '')));
        $code = strtolower(trim((string) $request->post('recovery_code', '')));
        $new = (string) $request->post('new_password', '');

        if (strlen($new) < 10) {
            Flash::error('Choose a new password of at least 10 characters.');
            $this->redirect('/forgot');
        }
        $user = Database::fetch(
            'SELECT * FROM ' . Database::table('admin_users') . ' WHERE email = ? LIMIT 1',
            [$email]
        );
        $codes = $user ? (json_decode((string) ($user['recovery_codes'] ?? '[]'), true) ?: []) : [];
        $matchedIndex = null;
        foreach ($codes as $i => $hash) {
            if (password_verify($code, (string) $hash)) {
                $matchedIndex = $i;
                break;
            }
        }
        // Uniform failure message; never reveal whether the email exists.
        if ($user === null || $matchedIndex === null) {
            Flash::error('Recovery failed. Check the email and recovery code.');
            $this->redirect('/forgot');
        }
        unset($codes[$matchedIndex]);
        Database::run(
            'UPDATE ' . Database::table('admin_users') . '
                SET password_hash = ?, recovery_codes = ?, failed_attempts = 0, locked_until = NULL, updated_at = UTC_TIMESTAMP()
              WHERE id = ?',
            [password_hash($new, PASSWORD_DEFAULT), json_encode(array_values($codes)), $user['id']]
        );
        \App\Core\Audit::log([
            'action' => 'admin.password_recovered',
            'entity_type' => 'admin_user',
            'entity_id' => $user['id'],
            'success' => true,
        ]);
        Flash::success('Password reset. You can sign in with your new password.');
        $this->redirect('/login');
    }

    public function logout(Request $request): void
    {
        if ($request->method() === 'POST') {
            Csrf::check($request);
        }
        Auth::logout();
        Flash::info('You have been signed out.');
        $this->redirect('/login');
    }
}
