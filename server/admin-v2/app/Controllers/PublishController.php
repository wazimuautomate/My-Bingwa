<?php
/**
 * Draft review, publish (immutable signed release) and rollback (a new later version).
 * Rollback requires a reason, the rollback.execute permission and re-authentication.
 */

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Rbac;
use App\Core\Request;
use App\Core\Database;
use App\Services\PublishingService;

final class PublishController extends Controller
{
    public function review(Request $request): void
    {
        $this->requireAuth();
        if (!Rbac::canAny(['publish.execute', 'rollback.execute', 'offers.view'])) {
            Rbac::require('publish.execute');
        }
        $snapshot = PublishingService::buildWorkingSnapshot();
        $check = PublishingService::validate($snapshot);
        $this->view('publish/review', [
            'activeNav' => 'publish', 'pageTitle' => 'Review & publish',
            'pending' => PublishingService::pendingChanges(),
            'errors' => $check['errors'], 'warnings' => $check['warnings'],
            'current' => PublishingService::currentRelease(),
            'snapshot' => $snapshot,
        ]);
    }

    public function execute(Request $request): void
    {
        Csrf::check($request);
        $this->guard('publish.execute');
        $notes = trim((string) $request->post('notes', ''));

        $result = PublishingService::publish($notes);
        if (!$result['ok']) {
            Flash::error('Publish blocked: ' . implode(' ', $result['errors']));
            $this->redirect('/publish');
        }
        foreach ($result['warnings'] as $wmsg) {
            Flash::warning($wmsg);
        }
        Flash::success('Published configuration v' . $result['version'] . '. The app will pick it up on its next background sync.');
        $this->redirect('/releases/' . $result['version']);
    }

    public function releases(Request $request): void
    {
        $this->requireAuth();
        if (!Rbac::canAny(['publish.execute', 'rollback.execute', 'audit.view'])) {
            Rbac::require('publish.execute');
        }
        $this->view('publish/releases', [
            'activeNav' => 'releases', 'pageTitle' => 'Release history',
            'releases' => PublishingService::releases(100),
        ]);
    }

    public function show(Request $request, string $version): void
    {
        $this->requireAuth();
        if (!Rbac::canAny(['publish.execute', 'rollback.execute', 'audit.view'])) {
            Rbac::require('publish.execute');
        }
        $release = PublishingService::release((int) $version);
        if (!$release) {
            Flash::error('Release not found.');
            $this->redirect('/releases');
        }
        $items = Database::fetchAll(
            'SELECT * FROM ' . Database::table('configuration_release_items') . ' WHERE version = ? ORDER BY entity_type, change_type',
            [(int) $version]
        );
        $this->view('publish/show', [
            'activeNav' => 'releases', 'pageTitle' => 'Release v' . (int) $version,
            'release' => $release, 'items' => $items,
            'snapshot' => json_decode($release['snapshot_json'], true) ?: [],
            'isCurrent' => (int) $version === (int) (PublishingService::currentRelease()['version'] ?? 0),
        ]);
    }

    public function rollback(Request $request, string $version): void
    {
        Csrf::check($request);
        $this->guard('rollback.execute');

        $reason = trim((string) $request->post('reason', ''));
        if ($reason === '') {
            Flash::error('A reason is required to roll back.');
            $this->redirect('/releases/' . (int) $version);
        }
        // Re-authenticate for this sensitive action.
        $password = (string) $request->post('reauth_password', '');
        if (!Auth::reauthenticate($password, (string) $request->post('reauth_totp', ''))) {
            Flash::error('Re-authentication failed. Rollback was not performed.');
            $this->redirect('/releases/' . (int) $version);
        }

        if (!PublishingService::restoreWorkingFrom((int) $version)) {
            Flash::error('Could not load that release for rollback.');
            $this->redirect('/releases/' . (int) $version);
        }
        $result = PublishingService::publish('Rollback to v' . (int) $version . ': ' . $reason, (int) $version);
        if (!$result['ok']) {
            Flash::error('Rollback publish failed: ' . implode(' ', $result['errors']));
            $this->redirect('/releases/' . (int) $version);
        }
        Flash::success('Rolled back. Published as new configuration v' . $result['version'] . '.');
        $this->redirect('/releases/' . $result['version']);
    }
}
