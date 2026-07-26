<?php
/**
 * Access to the existing (legacy) `payments` table that the payment endpoints own.
 * Admin V2 reports on payments and, for reconciliation, exposes the full customer
 * identifiers (payer, recipient, M-Pesa receipt) unmasked to holders of the payments
 * permission. The only write it performs is deleting a payment record — a deliberate,
 * audited capability. If the table does not exist yet (admin installed before any
 * payment), every read method degrades to empty/zero so the UI shows "Not available
 * yet" rather than erroring.
 */

namespace App\Repositories;

use App\Core\Database;

final class PaymentRepository
{
    public static function available(): bool
    {
        $row = Database::fetch(
            "SELECT COUNT(*) AS c FROM information_schema.tables
              WHERE table_schema = DATABASE() AND table_name = 'payments'"
        );
        return (int) ($row['c'] ?? 0) > 0;
    }

    /** Map raw payment status to an admin display state + css class. */
    public static function displayState(string $status): array
    {
        switch (strtoupper($status)) {
            case 'PAYMENT_CONFIRMED': return ['label' => 'Payment received', 'class' => 'confirmed'];
            case 'PAYMENT_REQUESTED': return ['label' => 'Requested',        'class' => 'requested'];
            case 'CANCELLED':         return ['label' => 'Cancelled',        'class' => 'cancelled'];
            case 'TIMED_OUT':         return ['label' => 'Timed out',        'class' => 'expired'];
            case 'PAYMENT_FAILED':    return ['label' => 'Failed',           'class' => 'failed'];
            default:                  return ['label' => $status ?: 'Unknown', 'class' => 'muted'];
        }
    }

    /**
     * Paged, filtered list. Filters: from, to (Y-m-d), state, q (offer/receipt), min, max.
     * @return array{rows:array, total:int}
     */
    public static function search(array $f, int $page = 1, int $perPage = 25): array
    {
        if (!self::available()) {
            return ['rows' => [], 'total' => 0];
        }
        [$where, $params] = self::buildWhere($f);
        $total = (int) (Database::scalar("SELECT COUNT(*) FROM payments {$where}", $params) ?? 0);
        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::fetchAll(
            "SELECT * FROM payments {$where} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['rows' => $rows, 'total' => $total];
    }

    public static function find(int $id): ?array
    {
        if (!self::available()) {
            return null;
        }
        return Database::fetch('SELECT * FROM payments WHERE id = ? LIMIT 1', [$id]);
    }

    /**
     * Delete a payment record from the legacy `payments` table. This is a deliberate,
     * audited write — Admin V2 otherwise only reads payments. No-ops when the table is
     * absent; the caller confirms the row exists before calling.
     */
    public static function delete(int $id): void
    {
        if (!self::available()) {
            return;
        }
        Database::run('DELETE FROM payments WHERE id = ?', [$id]);
    }

    private static function buildWhere(array $f): array
    {
        $clauses = [];
        $params = [];
        if (!empty($f['from'])) { $clauses[] = 'created_at >= ?'; $params[] = $f['from'] . ' 00:00:00'; }
        if (!empty($f['to']))   { $clauses[] = 'created_at <= ?'; $params[] = $f['to'] . ' 23:59:59'; }
        if (!empty($f['state'])){ $clauses[] = 'status = ?';     $params[] = $f['state']; }
        if (isset($f['min']) && $f['min'] !== '') { $clauses[] = 'amount >= ?'; $params[] = (int) $f['min']; }
        if (isset($f['max']) && $f['max'] !== '') { $clauses[] = 'amount <= ?'; $params[] = (int) $f['max']; }
        if (!empty($f['q'])) {
            $clauses[] = '(offer_id LIKE ? OR mpesa_receipt LIKE ?)';
            $params[] = '%' . $f['q'] . '%';
            $params[] = '%' . $f['q'] . '%';
        }
        return [$clauses ? ('WHERE ' . implode(' AND ', $clauses)) : '', $params];
    }

    /* --------------------------------------------------------- aggregates */

    public static function revenueBetween(string $fromDate, string $toDate): int
    {
        if (!self::available()) {
            return 0;
        }
        return (int) (Database::scalar(
            "SELECT COALESCE(SUM(amount),0) FROM payments
              WHERE status = 'PAYMENT_CONFIRMED' AND created_at BETWEEN ? AND ?",
            [$fromDate . ' 00:00:00', $toDate . ' 23:59:59']
        ) ?? 0);
    }

    public static function confirmedCountBetween(string $fromDate, string $toDate): int
    {
        if (!self::available()) {
            return 0;
        }
        return (int) (Database::scalar(
            "SELECT COUNT(*) FROM payments
              WHERE status = 'PAYMENT_CONFIRMED' AND created_at BETWEEN ? AND ?",
            [$fromDate . ' 00:00:00', $toDate . ' 23:59:59']
        ) ?? 0);
    }

    /** Daily confirmed revenue series for a chart. @return array{labels:string[], values:int[]} */
    public static function dailyRevenue(int $days = 14): array
    {
        $labels = [];
        $values = [];
        $index = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = (new \DateTimeImmutable("-{$i} day", new \DateTimeZone('Africa/Nairobi')));
            $key = $d->format('Y-m-d');
            $labels[] = $d->format('j M');
            $values[] = 0;
            $index[$key] = count($values) - 1;
        }
        if (self::available()) {
            $from = (new \DateTimeImmutable("-{$days} day", new \DateTimeZone('Africa/Nairobi')))->format('Y-m-d');
            $rows = Database::fetchAll(
                "SELECT DATE(CONVERT_TZ(created_at,'+00:00','+03:00')) AS d, SUM(amount) AS total
                   FROM payments
                  WHERE status = 'PAYMENT_CONFIRMED' AND created_at >= ?
                  GROUP BY d",
                [$from . ' 00:00:00']
            );
            foreach ($rows as $r) {
                // CONVERT_TZ may return null if MySQL tz tables are absent; fall back to raw date.
                $key = $r['d'] ?? null;
                if ($key !== null && isset($index[$key])) {
                    $values[$index[$key]] = (int) $r['total'];
                }
            }
        }
        return ['labels' => $labels, 'values' => $values];
    }

    /** Confirmed purchases grouped by offer category over the last N days. */
    public static function categoryBreakdown(int $days = 30): array
    {
        $out = ['DATA' => 0, 'SMS' => 0, 'MINUTES' => 0, 'SPECIAL' => 0];
        if (!self::available()) {
            return $out;
        }
        $from = (new \DateTimeImmutable("-{$days} day", new \DateTimeZone('Africa/Nairobi')))->format('Y-m-d');
        $offers = Database::table('offers');
        $rows = Database::fetchAll(
            "SELECT o.category AS cat, COUNT(*) AS c
               FROM payments p JOIN {$offers} o ON o.offer_id = p.offer_id
              WHERE p.status = 'PAYMENT_CONFIRMED' AND p.created_at >= ?
              GROUP BY o.category",
            [$from . ' 00:00:00']
        );
        foreach ($rows as $r) {
            $cat = strtoupper((string) $r['cat']);
            if (isset($out[$cat])) {
                $out[$cat] = (int) $r['c'];
            }
        }
        return $out;
    }

    public static function latest(int $limit = 6): array
    {
        if (!self::available()) {
            return [];
        }
        return Database::fetchAll("SELECT * FROM payments ORDER BY created_at DESC LIMIT " . (int) $limit);
    }
}
