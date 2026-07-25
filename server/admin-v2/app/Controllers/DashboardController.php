<?php
/**
 * Dashboard: answers "is the app configured, is it syncing, what is selling, what needs
 * attention?" Every metric is real or explicitly marked "Not available yet" — no
 * invented numbers.
 */

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Repositories\PaymentRepository;
use App\Services\PublishingService;
use App\Services\TemplateMatcher;
use App\Support\Csv;

final class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $this->guard('dashboard.view');

        $period = (int) $request->get('period', 30);
        if (!in_array($period, [7, 30, 90], true)) {
            $period = 30;
        }
        $tz = new \DateTimeZone('Africa/Nairobi');
        $to = (new \DateTimeImmutable('now', $tz))->format('Y-m-d');
        $from = (new \DateTimeImmutable("-{$period} day", $tz))->format('Y-m-d');

        $data = [
            'activeNav'  => 'dashboard',
            'pageTitle'  => 'Dashboard',
            'period'     => $period,
            'greeting'   => $this->greeting(),
            'firstName'  => explode(' ', trim((string) (Auth::user()['name'] ?? 'there')))[0],
            'paymentsAvailable' => PaymentRepository::available(),
            'stats' => [
                'activeOffers' => (int) (Database::scalar("SELECT COUNT(*) FROM " . Database::table('offers') . " WHERE status='active'") ?? 0),
                'scheduledNotifications' => (int) (Database::scalar(
                    "SELECT COUNT(*) FROM " . Database::table('notification_campaigns') . " WHERE status='scheduled' AND (scheduled_at IS NULL OR scheduled_at >= UTC_TIMESTAMP())"
                ) ?? 0),
                'revenue' => PaymentRepository::revenueBetween($from, $to),
                'confirmed' => PaymentRepository::confirmedCountBetween($from, $to),
            ],
            'revenueSeries' => PaymentRepository::dailyRevenue(min(30, max(7, $period))),
            'categories'    => PaymentRepository::categoryBreakdown($period),
            'latestPayments'=> PaymentRepository::latest(6),
            'upcoming'      => Database::fetchAll(
                "SELECT * FROM " . Database::table('notification_campaigns') . "
                  WHERE status='scheduled' AND scheduled_at >= UTC_TIMESTAMP() ORDER BY scheduled_at ASC LIMIT 5"
            ),
            'pending'       => array_slice(PublishingService::pendingChanges(), 0, 8),
            'recentReleases'=> PublishingService::releases(5),
            'sync'          => $this->syncHealth(),
            'warnings'      => $this->warnings(),
            'recentAudit'   => Database::fetchAll(
                "SELECT * FROM " . Database::table('audit_logs') . " ORDER BY created_at DESC LIMIT 6"
            ),
        ];
        $this->view('dashboard/index', $data);
    }

    private function greeting(): string
    {
        $h = (int) (new \DateTimeImmutable('now', new \DateTimeZone('Africa/Nairobi')))->format('G');
        if ($h < 12) return 'Good morning';
        if ($h < 17) return 'Good afternoon';
        return 'Good evening';
    }

    private function syncHealth(): array
    {
        $current = (int) (PublishingService::currentRelease()['version'] ?? 0);
        $hasTelemetry = Database::tableExists('anonymous_app_versions')
            && (int) (Database::scalar("SELECT COUNT(*) FROM " . Database::table('anonymous_app_versions')) ?? 0) > 0;

        $onCurrent = 0;
        $onOlder = 0;
        if ($hasTelemetry) {
            $onCurrent = (int) (Database::scalar(
                "SELECT COUNT(*) FROM " . Database::table('anonymous_app_versions') . " WHERE config_version = ?",
                [$current]
            ) ?? 0);
            $onOlder = (int) (Database::scalar(
                "SELECT COUNT(*) FROM " . Database::table('anonymous_app_versions') . " WHERE config_version < ?",
                [$current]
            ) ?? 0);
        }

        $lastSuccess = Database::scalar(
            "SELECT MAX(created_at) FROM " . Database::table('sync_events') . " WHERE event_type IN ('manifest','snapshot','not_modified')"
        );
        $recentTotal = (int) (Database::scalar(
            "SELECT COUNT(*) FROM " . Database::table('sync_events') . " WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)"
        ) ?? 0);
        $recentErrors = (int) (Database::scalar(
            "SELECT COUNT(*) FROM " . Database::table('sync_events') . " WHERE event_type='error' AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)"
        ) ?? 0);

        return [
            'current'       => $current,
            'hasTelemetry'  => $hasTelemetry,
            'onCurrent'     => $onCurrent,
            'onOlder'       => $onOlder,
            'lastSuccess'   => $lastSuccess,
            'failedRate'    => $recentTotal > 0 ? round(($recentErrors / $recentTotal) * 100, 1) : null,
            'signed'        => (bool) (PublishingService::currentRelease()['signature'] ?? false),
        ];
    }

    private function warnings(): array
    {
        $w = [];
        $support = Database::fetch("SELECT till_number, paybill_number FROM " . Database::table('support_config') . " WHERE id=1") ?: [];
        if (($support['till_number'] ?? '') === '' && ($support['paybill_number'] ?? '') === '') {
            $w[] = ['level' => 'error', 'text' => 'No Till or Paybill number is set. Offline purchase is disabled in the app.', 'link' => '/support'];
        }
        $expired = (int) (Database::scalar(
            "SELECT COUNT(*) FROM " . Database::table('billboards') . " WHERE status='active' AND ends_at IS NOT NULL AND ends_at < UTC_TIMESTAMP()"
        ) ?? 0);
        if ($expired > 0) {
            $w[] = ['level' => 'warning', 'text' => "{$expired} active billboard(s) are past their end date.", 'link' => '/billboards'];
        }
        $badTemplates = 0;
        foreach (Database::fetchAll("SELECT pattern, case_sensitive FROM " . Database::table('message_templates') . " WHERE status='active'") as $t) {
            if (!TemplateMatcher::validatePattern((string) $t['pattern'], (bool) $t['case_sensitive'])['ok']) {
                $badTemplates++;
            }
        }
        if ($badTemplates > 0) {
            $w[] = ['level' => 'error', 'text' => "{$badTemplates} active message template(s) have an invalid pattern.", 'link' => '/message-templates'];
        }
        $v = Database::fetch("SELECT * FROM " . Database::table('app_versions') . " WHERE status='active' ORDER BY latest_version_code DESC LIMIT 1");
        if ($v) {
            if ((int) $v['min_supported_version_code'] > (int) $v['latest_version_code']) {
                $w[] = ['level' => 'error', 'text' => 'Forced-update misconfiguration: minimum supported version is higher than the latest version.', 'link' => '/versions'];
            }
            if ((int) $v['mandatory'] === 1 && ($v['play_store_url'] ?? '') === '' && ($v['apk_url'] ?? '') === '') {
                $w[] = ['level' => 'error', 'text' => 'A forced update is set with no Play Store or APK destination.', 'link' => '/versions'];
            }
        }
        return $w;
    }

    public function exportCsv(Request $request): void
    {
        $this->guard('dashboard.view');
        $rows = PaymentRepository::latest(500);
        Csv::stream('mybingwa-payments-summary.csv',
            ['date', 'offer', 'amount', 'status'],
            array_map(fn($r) => [
                fmt_nairobi($r['created_at'], 'Y-m-d H:i'),
                $r['offer_id'], $r['amount'],
                PaymentRepository::displayState($r['status'])['label'],
            ], $rows)
        );
    }
}
