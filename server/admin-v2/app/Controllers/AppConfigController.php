<?php
/**
 * Remote app configuration — kept deliberately small: maintenance mode + message and the
 * background sync interval (clamped to a safe range).
 * Changes are drafts until published.
 */

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Request;

final class AppConfigController extends Controller
{
    private const SYNC_MIN = 60;
    private const SYNC_MAX = 1440;

    private function load(): array
    {
        return Database::fetch('SELECT * FROM ' . Database::table('app_config') . ' WHERE id = 1') ?: [];
    }

    public function index(Request $request): void
    {
        $this->guard('config.edit');
        $this->view('app_config/index', [
            'activeNav' => 'config', 'pageTitle' => 'App configuration',
            'config' => $this->load(),
            'syncMin' => self::SYNC_MIN, 'syncMax' => self::SYNC_MAX,
        ]);
    }

    public function save(Request $request): void
    {
        Csrf::check($request);
        $this->guard('config.edit');
        $before = $this->load();

        $sync = (int) $request->post('sync_interval_minutes', 360);
        $sync = max(self::SYNC_MIN, min(self::SYNC_MAX, $sync));

        Database::run(
            'UPDATE ' . Database::table('app_config') . ' SET
                maintenance_mode=?, maintenance_message=?, sync_interval_minutes=?,
                row_version = row_version + 1, updated_at = UTC_TIMESTAMP(), updated_by = ? WHERE id = 1',
            [
                $request->post('maintenance_mode') ? 1 : 0,
                trim((string) $request->post('maintenance_message', '')),
                $sync,
                Auth::user()['name'] ?? 'system',
            ]
        );
        Audit::log([
            'action' => 'config.update', 'entity_type' => 'app_config', 'entity_id' => 1,
            'before' => $before, 'after' => $this->load(),
        ]);
        Flash::success('App configuration saved. Publish changes to apply them in the app.');
        $this->redirect('/app-config');
    }
}
