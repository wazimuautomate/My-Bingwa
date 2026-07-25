<?php
/**
 * App version / update rules. Guards against lockout: minimum supported cannot exceed
 * latest, a forced update needs a valid destination, latest cannot be a downgrade of the
 * active rule, and rollout stays 0–100. Forced-update changes require Super Admin +
 * re-authentication. Changes are drafts until published.
 */

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Validator;

final class VersionsController extends Controller
{
    private function table(): string { return Database::table('app_versions'); }

    public function index(Request $request): void
    {
        $this->guard('releases.manage');
        $this->view('versions/index', [
            'activeNav' => 'versions', 'pageTitle' => 'Updates & versions',
            'versions' => Database::fetchAll('SELECT * FROM ' . $this->table() . ' ORDER BY latest_version_code DESC'),
        ]);
    }

    public function create(Request $request): void
    {
        $this->guard('releases.manage');
        $this->view('versions/form', ['activeNav' => 'versions', 'pageTitle' => 'Add release rule', 'version' => null, 'isNew' => true]);
    }

    public function edit(Request $request, string $id): void
    {
        $this->guard('releases.manage');
        $row = Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE id = ?', [(int) $id]);
        if (!$row) { Flash::error('Version rule not found.'); $this->redirect('/versions'); }
        $this->view('versions/form', ['activeNav' => 'versions', 'pageTitle' => 'Edit release rule', 'version' => $row, 'isNew' => false]);
    }

    public function save(Request $request): void
    {
        Csrf::check($request);
        $this->guard('releases.manage');
        $isNew = $request->post('is_new') === '1';
        $id = (int) $request->post('id', 0);

        $input = [
            'latest_version_code' => (int) $request->post('latest_version_code', 0),
            'latest_version_name' => trim((string) $request->post('latest_version_name', '')),
            'min_supported_version_code' => (int) $request->post('min_supported_version_code', 1),
            'mandatory' => $request->post('mandatory') ? 1 : 0,
            'play_store_url' => trim((string) $request->post('play_store_url', '')),
            'apk_url' => trim((string) $request->post('apk_url', '')),
            'apk_sha256' => trim((string) $request->post('apk_sha256', '')),
            'rollout_percent' => (int) $request->post('rollout_percent', 100),
            'release_notes' => trim((string) $request->post('release_notes', '')),
            'status' => $request->post('active') ? 'active' : 'inactive',
        ];

        $v = Validator::make($input);
        $v->validate([
            'latest_version_code' => 'required|int|min:1',
            'latest_version_name' => 'required|max:24',
            'min_supported_version_code' => 'required|int|min:1',
            'play_store_url' => 'max:200',
            'apk_url' => 'max:200',
        ]);
        if ($input['min_supported_version_code'] > $input['latest_version_code']) {
            $v->add('min_supported_version_code', 'Minimum supported cannot exceed the latest version.');
        }
        if ($input['mandatory'] && $input['play_store_url'] === '' && $input['apk_url'] === '') {
            $v->add('mandatory', 'A forced update needs a Play Store or APK destination.');
        }
        if ($input['rollout_percent'] < 0 || $input['rollout_percent'] > 100) {
            $v->add('rollout_percent', 'Rollout must be 0–100.');
        }
        // No downgrade of the active latest.
        $activeLatest = (int) (Database::scalar('SELECT MAX(latest_version_code) FROM ' . $this->table() . " WHERE status='active'" . ($isNew ? '' : ' AND id <> ?'), $isNew ? [] : [$id]) ?? 0);
        if ($input['status'] === 'active' && $activeLatest > 0 && $input['latest_version_code'] < $activeLatest) {
            $v->add('latest_version_code', "Cannot publish a downgrade below the current active latest ({$activeLatest}).");
        }
        if ($v->fails()) {
            Flash::error('Fix: ' . implode(' ', array_values($v->firstErrors())));
            $this->redirect($isNew ? '/versions/new' : '/versions/' . $id . '/edit');
        }

        // Forced update → Super Admin + re-authentication.
        if ($input['mandatory']) {
            if (!Auth::isSuperAdmin()) { Flash::error('Only a Super Admin can set a forced update.'); $this->redirect('/versions'); }
            if (!Auth::reauthenticate((string) $request->post('reauth_password', ''), (string) $request->post('reauth_totp', ''))) {
                Flash::error('Re-authentication failed. Forced-update rule not saved.'); $this->redirect('/versions');
            }
        }

        $actor = Auth::user()['name'] ?? 'system';
        // Only one active rule at a time.
        if ($input['status'] === 'active') {
            Database::run('UPDATE ' . $this->table() . " SET status='inactive' WHERE status='active'" . ($isNew ? '' : ' AND id <> ?'), $isNew ? [] : [$id]);
        }
        $before = $isNew ? null : Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE id = ?', [$id]);
        if ($isNew) {
            Database::run(
                'INSERT INTO ' . $this->table() . '
                    (latest_version_code, latest_version_name, min_supported_version_code, mandatory,
                     play_store_url, apk_url, apk_sha256, rollout_percent, release_notes, status,
                     row_version, created_at, updated_at, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), ?)',
                array_merge(array_values($input), [$actor])
            );
            $id = (int) Database::pdo()->lastInsertId();
        } else {
            Database::run(
                'UPDATE ' . $this->table() . ' SET
                    latest_version_code=?, latest_version_name=?, min_supported_version_code=?, mandatory=?,
                    play_store_url=?, apk_url=?, apk_sha256=?, rollout_percent=?, release_notes=?, status=?,
                    row_version = row_version + 1, updated_at = UTC_TIMESTAMP(), updated_by = ? WHERE id = ?',
                array_merge(array_values($input), [$actor, $id])
            );
        }
        Audit::log([
            'action' => $input['mandatory'] ? 'version.forced_update' : ($isNew ? 'version.create' : 'version.update'),
            'entity_type' => 'app_version', 'entity_id' => $id,
            'before' => $before, 'after' => Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE id = ?', [$id]),
            'reason' => $input['mandatory'] ? 'Forced update' : null,
        ]);
        Flash::success('Version rule saved as a draft change. Publish to apply.');
        $this->redirect('/versions');
    }

    public function activate(Request $request, string $id): void
    {
        Csrf::check($request);
        $this->guard('releases.manage');
        $row = Database::fetch('SELECT * FROM ' . $this->table() . ' WHERE id = ?', [(int) $id]);
        if (!$row) { Flash::error('Version rule not found.'); $this->redirect('/versions'); }
        // Activating a forced-update rule is the same privileged outcome as saving one:
        // require Super Admin + re-authentication (matches save()).
        if ((int) $row['mandatory'] === 1) {
            if (!Auth::isSuperAdmin()) { Flash::error('Only a Super Admin can activate a forced update.'); $this->redirect('/versions'); }
            if (!Auth::reauthenticate((string) $request->post('reauth_password', ''), (string) $request->post('reauth_totp', ''))) {
                Flash::error('Re-authentication failed. Forced-update rule not activated.'); $this->redirect('/versions');
            }
        }
        Database::run('UPDATE ' . $this->table() . " SET status='inactive' WHERE status='active'");
        Database::run('UPDATE ' . $this->table() . " SET status='active', updated_at=UTC_TIMESTAMP() WHERE id = ?", [(int) $id]);
        Audit::log(['action' => 'version.activate', 'entity_type' => 'app_version', 'entity_id' => (int) $id]);
        Flash::success('Version rule activated (draft). Publish to apply.');
        $this->redirect('/versions');
    }
}
