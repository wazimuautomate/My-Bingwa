<?php
/**
 * Preview & publish — a real, standalone page (no modal, no dummy data).
 *
 * It renders the CURRENT working snapshot exactly as the app will receive it —
 * the same array produced by PublishingService::buildWorkingSnapshot() — alongside
 * the pending draft-vs-published diff, real counts and any validation results. The
 * publish button on the page posts to the existing /publish/execute action, so what
 * the operator sees here is precisely what gets published to the app.
 */

namespace App\Controllers;

use App\Core\Rbac;
use App\Core\Request;
use App\Services\PublishingService;

final class PreviewController extends Controller
{
    public function index(Request $request): void
    {
        $this->requireAuth();
        // Viewing is allowed for anyone who can publish, roll back or edit offers; the
        // publish action itself is still gated by publish.execute in the view + controller.
        if (!Rbac::canAny(['publish.execute', 'rollback.execute', 'offers.view'])) {
            Rbac::require('publish.execute');
        }

        $snapshot = PublishingService::buildWorkingSnapshot();
        $check = PublishingService::validate($snapshot);
        $current = PublishingService::currentRelease();

        $this->view('preview/index', [
            'activeNav' => 'preview',
            'pageTitle' => 'Preview & publish',
            'snapshot'  => $snapshot,
            'pending'   => PublishingService::pendingChanges(),
            'errors'    => $check['errors'],
            'warnings'  => $check['warnings'],
            'current'   => $current,
            'nextVersion' => (int) ($current['version'] ?? 0) + 1,
        ]);
    }
}
