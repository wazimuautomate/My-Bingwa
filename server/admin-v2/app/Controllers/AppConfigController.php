<?php
/**
 * Remote app configuration — maintenance mode + message, the background sync interval
 * (clamped to a safe range), the offer categories the app shows as tabs, and the feature
 * flags that turn app capabilities on and off.
 *
 * Categories and flags used to be compiled into the APK. They are configuration now, so a
 * tab can be renamed or a capability retired without an app release. All of it is a draft
 * until published.
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

    private function categories(): array
    {
        return Database::fetchAll(
            'SELECT * FROM ' . Database::table('offer_categories') . ' ORDER BY sort_order, category_key'
        );
    }

    private function flags(): array
    {
        return Database::fetchAll(
            'SELECT * FROM ' . Database::table('feature_flags') . ' ORDER BY sort_order, flag_key'
        );
    }

    public function index(Request $request): void
    {
        $this->guard('config.edit');
        $this->view('app_config/index', [
            'activeNav' => 'config', 'pageTitle' => 'App configuration',
            'config' => $this->load(),
            'categories' => $this->categories(),
            'flags' => $this->flags(),
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

    /* ------------------------------------------------------------- categories */

    /**
     * Save the offer category tabs. Existing categories are edited in place and new ones
     * appended — a key is never rewritten, because offers reference it. A category that is
     * still used by an offer cannot be disabled without a warning.
     */
    public function saveCategories(Request $request): void
    {
        Csrf::check($request);
        $this->guard('config.edit');
        $before = $this->categories();

        $keys   = (array) $request->post('category_key', []);
        $labels = (array) $request->post('category_label', []);
        $descs  = (array) $request->post('category_description', []);
        $orders = (array) $request->post('category_sort', []);
        $enabled = array_flip(array_map('strval', (array) $request->post('category_enabled', [])));

        $t = Database::table('offer_categories');
        $seen = [];
        $errors = [];
        foreach ($keys as $i => $rawKey) {
            $key = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '', (string) $rawKey));
            $label = trim((string) ($labels[$i] ?? ''));
            if ($key === '' && $label === '') {
                continue; // an untouched blank row
            }
            if ($key === '') {
                $errors[] = 'A category needs a key (letters, numbers and underscore only).';
                continue;
            }
            if ($label === '') {
                $errors[] = "Category {$key} needs a label.";
                continue;
            }
            if (isset($seen[$key])) {
                $errors[] = "Category {$key} is listed twice.";
                continue;
            }
            $seen[$key] = true;
            $isOn = isset($enabled[(string) $i]) ? 1 : 0;
            Database::run(
                "INSERT INTO {$t} (category_key, label, description, accent, sort_order, enabled, is_system, created_at, updated_at)
                 VALUES (?, ?, ?, '', ?, ?, 0, UTC_TIMESTAMP(), UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE label = VALUES(label), description = VALUES(description),
                                         sort_order = VALUES(sort_order), enabled = VALUES(enabled),
                                         updated_at = UTC_TIMESTAMP()",
                [$key, mb_substr($label, 0, 40), mb_substr(trim((string) ($descs[$i] ?? '')), 0, 160), (int) ($orders[$i] ?? 100), $isOn]
            );
        }

        if ($errors !== []) {
            Flash::error(implode(' ', array_slice($errors, 0, 3)));
            $this->redirect('/app-config');
        }

        // Warn — never block — when a disabled category still has live offers behind it.
        $orphaned = Database::fetchAll(
            'SELECT o.category, COUNT(*) AS c FROM ' . Database::table('offers') . " o
               LEFT JOIN {$t} c ON c.category_key = o.category
              WHERE o.status = 'active' AND (c.category_key IS NULL OR c.enabled = 0)
              GROUP BY o.category"
        );
        Audit::log([
            'action' => 'config.categories', 'entity_type' => 'offer_categories', 'entity_id' => 'all',
            'before' => ['categories' => $before], 'after' => ['categories' => $this->categories()],
        ]);
        if ($orphaned !== []) {
            $names = implode(', ', array_column($orphaned, 'category'));
            Flash::warning("Categories saved, but active offers still use: {$names}. Those offers will not appear under any tab.");
        } else {
            Flash::success('Categories saved. Publish to apply.');
        }
        $this->redirect('/app-config');
    }

    /* ---------------------------------------------------------- feature flags */

    /** Turn app capabilities on or off. Flags themselves are seeded; only the switch moves. */
    public function saveFlags(Request $request): void
    {
        Csrf::check($request);
        $this->guard('config.edit');
        $before = $this->flags();

        $on = array_flip(array_map('strval', (array) $request->post('flags', [])));
        $t = Database::table('feature_flags');
        foreach ($before as $flag) {
            $want = isset($on[$flag['flag_key']]) ? 1 : 0;
            if ((int) $flag['enabled'] === $want) {
                continue; // no write, so nothing pretends to have changed
            }
            Database::run(
                "UPDATE {$t} SET enabled = ?, updated_at = UTC_TIMESTAMP() WHERE flag_key = ?",
                [$want, $flag['flag_key']]
            );
        }
        Audit::log([
            'action' => 'config.flags', 'entity_type' => 'feature_flags', 'entity_id' => 'all',
            'before' => ['flags' => $before], 'after' => ['flags' => $this->flags()],
        ]);
        Flash::success('Feature flags saved. Publish to apply.');
        $this->redirect('/app-config');
    }
}
