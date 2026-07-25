<?php
/**
 * Remote app configuration: maintenance mode, sync interval (clamped to a safe range),
 * feature flags, quiet hours, notification caps, personalisation weights (bounded) and
 * emergency disable lists. Configuration controls KNOWN app behaviour only — no remote
 * code, no server-driven UI. Changes are drafts until published.
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
        $c = $this->load();
        $this->view('app_config/index', [
            'activeNav' => 'config', 'pageTitle' => 'App configuration',
            'config' => $c,
            'flags' => json_decode($c['feature_flags_json'] ?? '{}', true) ?: [],
            'weights' => json_decode($c['personalisation_json'] ?? '{}', true) ?: [],
            'emergency' => json_decode($c['emergency_disable_json'] ?? '{}', true) ?: [],
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

        $flags = [
            'sms_parsing' => $request->post('flag_sms_parsing') ? true : false,
            'billboards'  => $request->post('flag_billboards') ? true : false,
        ];
        $weights = $this->boundedWeights($request);
        $emergency = [
            'offers'    => $this->splitList((string) $request->post('disable_offers', '')),
            'campaigns' => $this->splitList((string) $request->post('disable_campaigns', '')),
            'routes'    => $this->splitList((string) $request->post('disable_routes', '')),
        ];

        Database::run(
            'UPDATE ' . Database::table('app_config') . ' SET
                maintenance_mode=?, maintenance_title=?, maintenance_message=?, maintenance_allow_help=?,
                sync_interval_minutes=?, snapshot_cache_hours=?, offline_config_valid_hours=?,
                quiet_hours_start=?, quiet_hours_end=?, campaign_daily_cap=?,
                feature_flags_json=?, personalisation_json=?, emergency_disable_json=?,
                row_version = row_version + 1, updated_at = UTC_TIMESTAMP(), updated_by = ? WHERE id = 1',
            [
                $request->post('maintenance_mode') ? 1 : 0,
                trim((string) $request->post('maintenance_title', '')),
                trim((string) $request->post('maintenance_message', '')),
                $request->post('maintenance_allow_help') ? 1 : 0,
                $sync,
                max(1, (int) $request->post('snapshot_cache_hours', 168)),
                max(1, (int) $request->post('offline_config_valid_hours', 168)),
                $this->cleanTime((string) $request->post('quiet_hours_start', '21:00')),
                $this->cleanTime((string) $request->post('quiet_hours_end', '07:00')),
                max(0, (int) $request->post('campaign_daily_cap', 2)),
                json_encode($flags), json_encode($weights), json_encode($emergency),
                Auth::user()['name'] ?? 'system',
            ]
        );
        Audit::log([
            'action' => 'config.update', 'entity_type' => 'app_config', 'entity_id' => 1,
            'before' => $before, 'after' => $this->load(),
        ]);
        Flash::success('App configuration saved as a draft change. Publish to apply.');
        $this->redirect('/app-config');
    }

    private function boundedWeights(Request $request): array
    {
        $clamp = fn($v, $lo, $hi) => max($lo, min($hi, (float) $v));
        return [
            'frequency_weight' => $clamp($request->post('w_frequency', 1.0), 0, 2),
            'value_weight'     => $clamp($request->post('w_value', 0.6), 0, 2),
            'validity_weight'  => $clamp($request->post('w_validity', 0.4), 0, 2),
            'diversity_floor'  => $clamp($request->post('w_diversity', 0.2), 0, 1),
            'max_step_up'      => $clamp($request->post('w_stepup', 3.0), 1, 5),
            'top_pool'         => (int) $clamp($request->post('w_pool', 5), 3, 10),
        ];
    }

    private function splitList(string $raw): array
    {
        $parts = array_filter(array_map('trim', explode(',', $raw)));
        return array_values(array_map(fn($x) => substr($x, 0, 64), $parts));
    }

    private function cleanTime(string $t): string
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t) ? $t : '21:00';
    }
}
