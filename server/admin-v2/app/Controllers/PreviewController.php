<?php
/**
 * Preview — the release-oriented view of what is about to go out.
 *
 * It answers three questions and nothing else: which version is live, which version the
 * draft will become, and exactly which values changed. Unchanged items are not listed,
 * because they are not changing: the diff is a field-by-field VALUE comparison of the
 * working snapshot against the last published snapshot (see ChangeDetector), never a
 * timestamp or row_version check. Saving an item without editing it therefore leaves this
 * page empty.
 *
 * /preview/diff?module=offers renders the same page focused on one module.
 */

namespace App\Controllers;

use App\Core\Rbac;
use App\Core\Request;
use App\Services\ChangeDetector;
use App\Services\PublishingService;
use App\Services\ResourceVersions;

final class PreviewController extends Controller
{
    public function index(Request $request): void
    {
        $this->render('');
    }

    /** A focused view of ONE module, reusing the same markup. */
    public function diff(Request $request): void
    {
        $module = (string) $request->get('module', '');
        if (!isset(ChangeDetector::MODULES[$module])) {
            $module = '';
        }
        $this->render($module);
    }

    private function render(string $moduleFilter): void
    {
        $this->requireAuth();
        // Viewing is allowed for anyone who can publish, roll back or edit offers; the
        // publish action itself is still gated by publish.execute in the view + controller.
        if (!Rbac::canAny(['publish.execute', 'rollback.execute', 'offers.view'])) {
            Rbac::require('publish.execute');
        }

        $snapshot = PublishingService::buildWorkingSnapshot();
        $check    = PublishingService::validate($snapshot);
        $summary  = PublishingService::publishSummary();

        $groups = $summary['byModule'];
        if ($moduleFilter !== '') {
            $groups = isset($groups[$moduleFilter]) ? [$moduleFilter => $groups[$moduleFilter]] : [];
        }

        $this->view('preview/index', [
            'activeNav'    => 'preview',
            'pageTitle'    => 'Preview & publish',
            'snapshot'     => $snapshot,
            'summary'      => $summary,
            'groups'       => $groups,
            'moduleFilter' => $moduleFilter,
            'resources'    => self::resourceStrip($snapshot, $summary),
            'firstPublish' => self::firstPublishNotice($snapshot, $summary),
            'errors'       => $check['errors'],
            'warnings'     => $check['warnings'],
        ]);
    }

    /**
     * "I changed nothing, so why are there changes?"
     *
     * After a server upgrade the working snapshot carries whole sections the last published
     * release never had — categories, feature flags. Those are real, publishable
     * differences, not phantom ones, but to an operator who has edited nothing they look
     * like a fault. Detect that case so the page can say plainly what is happening and that
     * one publish clears it.
     *
     * @return array{isUpgrade:bool, modules:array<int,array{key:string,label:string,count:int}>, count:int}
     */
    private static function firstPublishNotice(array $snapshot, array $summary): array
    {
        $published = PublishingService::currentSnapshot();
        $modules = [];
        $count = 0;
        if (is_array($published)) {
            foreach ($summary['byModule'] as $key => $group) {
                // A section the live release simply does not contain yet.
                if (!array_key_exists($key, $published)) {
                    $modules[] = [
                        'key'   => $key,
                        'label' => ChangeDetector::moduleLabel($key),
                        'count' => (int) ($group['count'] ?? 0),
                    ];
                    $count += (int) ($group['count'] ?? 0);
                }
            }
        }
        return ['isUpgrade' => $modules !== [], 'modules' => $modules, 'count' => $count];
    }

    /**
     * Per-resource version strip: what each resource is on now and, when it has pending
     * changes, what it will become. "Will change" is taken from the field-level diff, not
     * from a re-hash, so it can never disagree with the list of changes shown below it.
     *
     * @return array<int,array{key:string,label:string,version:int,count:int,pending:int,willBe:?int}>
     */
    private static function resourceStrip(array $snapshot, array $summary): array
    {
        $current = $summary['resourceVersions'];
        $byModule = $summary['byModule'];
        $out = [];
        foreach (ResourceVersions::keys() as $key) {
            $pending = (int) ($byModule[$key]['count'] ?? 0);
            $out[] = [
                'key'     => $key,
                'label'   => ResourceVersions::label($key),
                'version' => (int) ($current[$key]['version'] ?? 0),
                'count'   => ResourceVersions::countOf($key, $snapshot[$key] ?? null),
                'pending' => $pending,
                'willBe'  => $pending > 0 ? (int) $summary['draftVersion'] : null,
            ];
        }
        return $out;
    }
}
