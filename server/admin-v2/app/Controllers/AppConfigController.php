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
use App\Services\Settings;

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
            // Server-side payment routing (stored in the mb_settings key/value table and
            // read live by mybingwa-api via cutover/gateway_bridge.php). These apply the
            // moment they are saved — they are NOT part of the app "Publish" snapshot.
            'paymentTill' => (string) Settings::get('payment_till_number', ''),
            'fulfilmentNumber' => (string) Settings::get('fulfilment_number', ''),
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

        // --- Payment routing (digits only; blank = fall back to config.php default) -----
        $tillBefore       = (string) Settings::get('payment_till_number', '');
        $fulfilmentBefore = (string) Settings::get('fulfilment_number', '');
        // Buy Goods Till: 5–7 numeric digits. Keep digits only so a Paybill/format slip
        // cannot be saved as the money destination.
        $till = preg_replace('/\D/', '', (string) $request->post('payment_till_number', ''));
        // Fulfilment phone: digits only, keeps a leading 0 or 254 prefix as typed.
        $fulfilment = preg_replace('/\D/', '', (string) $request->post('fulfilment_number', ''));
        Settings::set('payment_till_number', $till);
        Settings::set('fulfilment_number', $fulfilment);

        Audit::log([
            'action' => 'config.update', 'entity_type' => 'app_config', 'entity_id' => 1,
            'before' => $before + ['payment_till_number' => $tillBefore, 'fulfilment_number' => $fulfilmentBefore],
            'after'  => $this->load() + ['payment_till_number' => $till, 'fulfilment_number' => $fulfilment],
        ]);
        Flash::success('App configuration saved. Payment Till & Fulfilment number apply immediately; publish to apply the app settings.');
        $this->redirect('/app-config');
    }
}
