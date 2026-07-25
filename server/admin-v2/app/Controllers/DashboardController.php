<?php
/**
 * Dashboard: a small, honest overview — active offers, scheduled notifications,
 * revenue and successful payments (last 30 days), when the app data was last
 * published, and the latest payments. No charts, telemetry or draft panels.
 */

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Repositories\PaymentRepository;
use App\Support\Csv;

final class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $this->guard('dashboard.view');

        $tz = new \DateTimeZone('Africa/Nairobi');
        $to = (new \DateTimeImmutable('now', $tz))->format('Y-m-d');
        $from = (new \DateTimeImmutable('-30 day', $tz))->format('Y-m-d');

        $this->view('dashboard/index', [
            'activeNav'  => 'dashboard',
            'pageTitle'  => 'Dashboard',
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
            'latestPayments' => PaymentRepository::latest(6),
        ]);
    }

    private function greeting(): string
    {
        $h = (int) (new \DateTimeImmutable('now', new \DateTimeZone('Africa/Nairobi')))->format('G');
        if ($h < 12) return 'Good morning';
        if ($h < 17) return 'Good afternoon';
        return 'Good evening';
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
