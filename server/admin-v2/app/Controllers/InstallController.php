<?php
/**
 * One-time installer + upgrade runner. On a fresh database it applies migrations and
 * seeds the permission set, default roles, catalogue and the first Super Admin. Seeding
 * the Super Admin only ever happens when NO admin exists, so this route is safe to leave
 * reachable; upgrades (re-running migrations) require a signed-in Super Admin.
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
use App\Database\Migrator;
use App\Database\Seeder;

final class InstallController extends Controller
{
    private function installed(): bool
    {
        return Database::tableExists('admin_users')
            && (int) (Database::scalar('SELECT COUNT(*) FROM ' . Database::table('admin_users')) ?? 0) > 0;
    }

    /**
     * Migrator/Seeder live under database/ (App\Database namespace) but the web
     * autoloader only maps App\ -> app/, so they must be required explicitly here.
     * Their CLI self-run blocks are guarded by PHP_SAPI === 'cli', so requiring them
     * from the web only defines the classes.
     */
    private function loadDbTools(): void
    {
        $base = dirname(__DIR__, 2) . '/database';
        require_once $base . '/migrate.php';
        require_once $base . '/seed.php';
    }

    public function show(Request $request): void
    {
        Session::start();
        if ($this->installed() && !Auth::isSuperAdmin()) {
            $this->redirect('/login');
        }
        // Read and clear one-time values BEFORE rendering (Response::html exits).
        $generated = Session::get('_install_pw');
        $log = Session::get('_install_log', []);
        Session::forget('_install_pw');
        Session::forget('_install_log');
        Response::html(View::render('install/index', [
            'installed' => $this->installed(),
            'flashes'   => Flash::take(),
            'generated' => $generated,
            'log'       => $log,
            'pageTitle' => 'Install',
        ], null));
    }

    public function run(Request $request): void
    {
        Session::start();
        // Once installed, only a Super Admin may re-run (upgrade). Fresh installs are open.
        if ($this->installed() && !Auth::isSuperAdmin()) {
            $this->redirect('/login');
        }
        Csrf::check($request);
        $this->loadDbTools();

        $log = [];
        $mig = Migrator::run();
        if ($mig['error']) {
            Flash::error('Migration failed: ' . $mig['error']);
            $this->redirect('/install');
        }
        $log[] = 'Migrations applied: ' . (implode(', ', $mig['applied']) ?: 'already up to date');

        $seed = Seeder::run();
        if (!$seed['ok']) {
            Flash::error('Seeding failed: ' . $seed['error']);
            $this->redirect('/install');
        }
        foreach ($seed['messages'] as $m) {
            $log[] = $m;
        }
        Session::set('_install_log', $log);
        if ($seed['generatedPassword']) {
            Session::set('_install_pw', $seed['generatedPassword']);
        }
        Flash::success('Install complete. Review the log below.');
        $this->redirect('/install');
    }

    public function migrate(Request $request): void
    {
        $this->requireAuth();
        \App\Core\Rbac::requireSuperAdmin();
        Csrf::check($request);
        $this->loadDbTools();
        $mig = Migrator::run();
        if ($mig['error']) {
            Flash::error('Migration failed: ' . $mig['error']);
        } else {
            Flash::success('Migrations applied: ' . (implode(', ', $mig['applied']) ?: 'already up to date'));
        }
        $this->redirect('/settings');
    }
}
