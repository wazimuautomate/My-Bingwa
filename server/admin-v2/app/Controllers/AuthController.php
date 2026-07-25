<?php
/**
 * Login and sign out. Login is throttled in Auth; all posts are CSRF-checked.
 * There is no 2FA and no email/SMS reset: a Super Admin resets the partner
 * Admin's password from Settings, so /forgot is just guidance.
 */

namespace App\Controllers;

use App\Core\Auth;
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
        if ($result === 'locked') {
            Flash::error('Too many attempts. This account is temporarily locked. Try again shortly.');
        } else {
            Flash::error('Wrong email or password.');
        }
        $this->redirect('/login');
    }

    public function showForgot(Request $request): void
    {
        Response::html(View::render('auth/forgot', [
            'flashes' => Flash::take(),
            'pageTitle' => 'Account recovery',
        ], null));
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
