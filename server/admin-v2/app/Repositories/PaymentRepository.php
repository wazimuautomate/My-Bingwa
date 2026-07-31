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

    /**
     * Bulk-delete payment records by id — the same deliberate, audited write as delete(),
     * for clearing many rows at once. The caller casts, de-duplicates and caps the ids and
     * records them in the audit trail. No-ops (returns 0) when the table is absent or no ids
     * are given. Returns the number of rows actually removed.
     *
     * @param int[] $ids
     */
    public static function deleteMany(array $ids): int
    {
        if (!self::available() || $ids === []) {
            return 0;
        }
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        return Database::run(
            "DELETE FROM payments WHERE id IN ({$placeholders})",
            array_values($ids)
        )->rowCount();
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

    /* ============================================================== analytics
     *
     * Offer performance reporting. Everything below answers "what actually sold",
     * which is the only kind of analysis this panel does: it reads the seller's own
     * payment records. It never profiles a customer, and no per-customer behaviour
     * is derived, stored or shown.
     *
     * A note on time, because it decides whether "today" is right: the payment
     * endpoints write created_at with MySQL NOW(), which is the DATABASE server's
     * local clock — on shared hosting that may be UTC or EAT, and we cannot assume.
     * So the offset is measured once per request and every Africa/Nairobi day window
     * is converted into that clock before it reaches SQL. No CONVERT_TZ, which needs
     * timezone tables many cPanel MySQL installs do not have.
     */

    /** A purchase counts as revenue only in this state. */
    public const SUCCESS_STATE = 'PAYMENT_CONFIRMED';

    /**
     * SQL fragment splitting a row into 'self' or 'other'. Buy-for-myself leaves the
     * recipient blank (or equal to the payer); buy-for-another carries a real, different
     * bundle recipient. See mybingwa-api/stk.php.
     */
    private const BUYER_KIND = "CASE WHEN recipient IS NULL OR recipient = '' OR recipient = payer
                                     THEN 'self' ELSE 'other' END";

    private static ?int $dbOffset = null;

    /** Seconds the database clock is ahead of UTC. Measured once, never assumed. */
    private static function dbOffsetSeconds(): int
    {
        if (self::$dbOffset !== null) {
            return self::$dbOffset;
        }
        try {
            $row = Database::fetch('SELECT NOW() AS local_now, UTC_TIMESTAMP() AS utc_now');
            $local = new \DateTimeImmutable((string) $row['local_now'], new \DateTimeZone('UTC'));
            $utc   = new \DateTimeImmutable((string) $row['utc_now'], new \DateTimeZone('UTC'));
            // Round to the nearest minute: the two SELECT values can differ by a second.
            $delta = (int) round(($local->getTimestamp() - $utc->getTimestamp()) / 60) * 60;
            return self::$dbOffset = $delta;
        } catch (\Throwable $e) {
            return self::$dbOffset = 0; // assume UTC rather than fail a dashboard
        }
    }

    /** Render an instant as a datetime string in the database's own clock. */
    private static function dbTime(\DateTimeImmutable $moment): string
    {
        return $moment->modify(self::dbOffsetSeconds() . ' seconds')
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
    }

    /**
     * Format a payments timestamp for display in Africa/Nairobi.
     *
     * Do NOT use the global fmt_nairobi() on a payments row: that helper assumes the
     * value is UTC, but payments.created_at is written with MySQL NOW(), i.e. the
     * database server's own clock. On a host whose MySQL runs on EAT that would show
     * every payment three hours late. This converts using the measured offset.
     */
    public static function nairobiTime(?string $dbDatetime, string $format = 'd M Y, H:i'): string
    {
        $value = trim((string) $dbDatetime);
        if ($value === '' || strpos($value, '0000-00-00') === 0) {
            return '—';
        }
        try {
            return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))
                ->modify((-self::dbOffsetSeconds()) . ' seconds')
                ->setTimezone(new \DateTimeZone('Africa/Nairobi'))
                ->format($format);
        } catch (\Throwable $e) {
            return $value;
        }
    }

    /**
     * The [start, end] of an Africa/Nairobi day, expressed in the database clock.
     *
     * @param string|null $day 'Y-m-d' in Nairobi terms; null means today.
     * @return array{0:string,1:string}
     */
    public static function dayWindow(?string $day = null): array
    {
        $tz = new \DateTimeZone('Africa/Nairobi');
        $start = $day === null
            ? (new \DateTimeImmutable('today', $tz))
            : (new \DateTimeImmutable($day . ' 00:00:00', $tz));
        return [self::dbTime($start), self::dbTime($start->modify('+1 day'))];
    }

    /** The window covering the last $days Nairobi days, today included. */
    public static function daysWindow(int $days): array
    {
        $tz = new \DateTimeZone('Africa/Nairobi');
        $start = (new \DateTimeImmutable('today', $tz))->modify('-' . max(0, $days - 1) . ' day');
        return [self::dbTime($start), self::dbTime((new \DateTimeImmutable('today', $tz))->modify('+1 day'))];
    }

    /**
     * Money in, today and for all time, with the number of sales behind each figure.
     * @return array{todayRevenue:int, todaySales:int, totalRevenue:int, totalSales:int, averageSale:int}
     */
    public static function revenueSummary(): array
    {
        $empty = ['todayRevenue' => 0, 'todaySales' => 0, 'totalRevenue' => 0, 'totalSales' => 0, 'averageSale' => 0];
        if (!self::available()) {
            return $empty;
        }
        [$dayFrom, $dayTo] = self::dayWindow();
        $today = Database::fetch(
            'SELECT COALESCE(SUM(amount),0) AS revenue, COUNT(*) AS sales FROM payments
              WHERE status = ? AND created_at >= ? AND created_at < ?',
            [self::SUCCESS_STATE, $dayFrom, $dayTo]
        ) ?: [];
        $all = Database::fetch(
            'SELECT COALESCE(SUM(amount),0) AS revenue, COUNT(*) AS sales FROM payments WHERE status = ?',
            [self::SUCCESS_STATE]
        ) ?: [];
        $totalRevenue = (int) ($all['revenue'] ?? 0);
        $totalSales = (int) ($all['sales'] ?? 0);
        return [
            'todayRevenue' => (int) ($today['revenue'] ?? 0),
            'todaySales'   => (int) ($today['sales'] ?? 0),
            'totalRevenue' => $totalRevenue,
            'totalSales'   => $totalSales,
            'averageSale'  => $totalSales > 0 ? (int) round($totalRevenue / $totalSales) : 0,
        ];
    }

    /**
     * How many sales each category made, and what they earned, inside a window.
     * Categories come from the offer catalogue; a payment whose offer no longer exists
     * is counted under OTHER rather than silently dropped.
     *
     * @return array<string, array{sales:int, revenue:int}>
     */
    public static function categoryPerformance(?string $from = null, ?string $to = null): array
    {
        $out = [];
        foreach (['DATA', 'SMS', 'MINUTES', 'SPECIAL', 'OTHER'] as $key) {
            $out[$key] = ['sales' => 0, 'revenue' => 0];
        }
        if (!self::available()) {
            return $out;
        }
        if ($from === null || $to === null) {
            [$from, $to] = self::dayWindow();
        }
        $offers = Database::table('offers');
        $rows = Database::fetchAll(
            "SELECT COALESCE(o.category, 'OTHER') AS cat, COUNT(*) AS sales, COALESCE(SUM(p.amount),0) AS revenue
               FROM payments p
               LEFT JOIN {$offers} o ON o.offer_id = p.offer_id
              WHERE p.status = ? AND p.created_at >= ? AND p.created_at < ?
              GROUP BY cat",
            [self::SUCCESS_STATE, $from, $to]
        );
        foreach ($rows as $r) {
            $cat = strtoupper((string) $r['cat']);
            if (!isset($out[$cat])) {
                $cat = 'OTHER';
            }
            $out[$cat]['sales']   += (int) $r['sales'];
            $out[$cat]['revenue'] += (int) $r['revenue'];
        }
        return $out;
    }

    /**
     * Buying trend: are people buying for themselves or for someone else?
     * @return array{self:array{sales:int,revenue:int}, other:array{sales:int,revenue:int}, total:int, selfShare:int}
     */
    public static function buyerTrend(?string $from = null, ?string $to = null): array
    {
        $out = ['self' => ['sales' => 0, 'revenue' => 0], 'other' => ['sales' => 0, 'revenue' => 0], 'total' => 0, 'selfShare' => 0];
        if (!self::available()) {
            return $out;
        }
        $where = 'WHERE status = ?';
        $params = [self::SUCCESS_STATE];
        if ($from !== null && $to !== null) {
            $where .= ' AND created_at >= ? AND created_at < ?';
            $params[] = $from;
            $params[] = $to;
        }
        $rows = Database::fetchAll(
            'SELECT ' . self::BUYER_KIND . " AS kind, COUNT(*) AS sales, COALESCE(SUM(amount),0) AS revenue
               FROM payments {$where} GROUP BY kind",
            $params
        );
        foreach ($rows as $r) {
            $kind = (string) $r['kind'];
            if (isset($out[$kind])) {
                $out[$kind] = ['sales' => (int) $r['sales'], 'revenue' => (int) $r['revenue']];
            }
        }
        $out['total'] = $out['self']['sales'] + $out['other']['sales'];
        $out['selfShare'] = $out['total'] > 0 ? (int) round($out['self']['sales'] * 100 / $out['total']) : 0;
        return $out;
    }

    /**
     * Best and worst performing offers over a window: how often each sold, what it
     * earned, and how many attempts never completed. This is the actual "bundle
     * performance" answer.
     *
     * @return array<int, array{offer_id:string, name:string, category:string, price:int,
     *                          sales:int, revenue:int, attempts:int, conversion:int}>
     */
    public static function offerPerformance(?string $from = null, ?string $to = null, int $limit = 100): array
    {
        if (!self::available()) {
            return [];
        }
        // Two placeholders for the SUM(CASE ...) columns come FIRST, then the window.
        $params = [self::SUCCESS_STATE, self::SUCCESS_STATE];
        $where = '';
        if ($from !== null && $to !== null) {
            $where = 'WHERE p.created_at >= ? AND p.created_at < ?';
            $params[] = $from;
            $params[] = $to;
        }
        $offers = Database::table('offers');
        $rows = Database::fetchAll(
            "SELECT p.offer_id,
                    COALESCE(o.name, '')      AS name,
                    COALESCE(o.category, '')  AS category,
                    COALESCE(o.price, 0)      AS price,
                    SUM(CASE WHEN p.status = ? THEN 1 ELSE 0 END)          AS sales,
                    COALESCE(SUM(CASE WHEN p.status = ? THEN p.amount ELSE 0 END), 0) AS revenue,
                    COUNT(*) AS attempts
               FROM payments p
               LEFT JOIN {$offers} o ON o.offer_id = p.offer_id
               {$where}
              GROUP BY p.offer_id, o.name, o.category, o.price
              ORDER BY revenue DESC, sales DESC
              LIMIT " . (int) $limit,
            $params
        );
        $out = [];
        foreach ($rows as $r) {
            $attempts = (int) $r['attempts'];
            $sales = (int) $r['sales'];
            $out[] = [
                'offer_id'   => (string) $r['offer_id'],
                'name'       => (string) $r['name'],
                'category'   => (string) $r['category'],
                'price'      => (int) $r['price'],
                'sales'      => $sales,
                'revenue'    => (int) $r['revenue'],
                'attempts'   => $attempts,
                'conversion' => $attempts > 0 ? (int) round($sales * 100 / $attempts) : 0,
            ];
        }
        return $out;
    }

    /**
     * How many payments ended in each state over a window — the honest view of how
     * many attempts actually completed.
     *
     * @return array{states:array<string,int>, total:int, confirmed:int, successRate:int}
     */
    public static function statusBreakdown(?string $from = null, ?string $to = null): array
    {
        if (!self::available()) {
            return ['states' => [], 'total' => 0, 'confirmed' => 0, 'successRate' => 0];
        }
        $where = '';
        $params = [];
        if ($from !== null && $to !== null) {
            $where = 'WHERE created_at >= ? AND created_at < ?';
            $params = [$from, $to];
        }
        $rows = Database::fetchAll(
            "SELECT status, COUNT(*) AS c FROM payments {$where} GROUP BY status ORDER BY c DESC",
            $params
        );
        $states = [];
        $total = 0;
        foreach ($rows as $r) {
            $states[(string) $r['status']] = (int) $r['c'];
            $total += (int) $r['c'];
        }
        $confirmed = $states[self::SUCCESS_STATE] ?? 0;
        return [
            'states' => $states,
            'total' => $total,
            'confirmed' => $confirmed,
            'successRate' => $total > 0 ? (int) round($confirmed * 100 / $total) : 0,
        ];
    }

    /**
     * Confirmed sales and revenue per Nairobi day, for a trend line.
     * @return array{labels:string[], sales:int[], revenue:int[]}
     */
    public static function dailySeries(int $days = 14): array
    {
        $tz = new \DateTimeZone('Africa/Nairobi');
        $labels = [];
        $sales = [];
        $revenue = [];
        $slot = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = (new \DateTimeImmutable('today', $tz))->modify("-{$i} day");
            $labels[] = $d->format('j M');
            $sales[] = 0;
            $revenue[] = 0;
            $slot[$d->format('Y-m-d')] = count($sales) - 1;
        }
        if (!self::available()) {
            return ['labels' => $labels, 'sales' => $sales, 'revenue' => $revenue];
        }
        // Bucket in PHP using the measured database offset, so this works on hosts
        // without MySQL timezone tables.
        [$from, $to] = self::daysWindow($days);
        $rows = Database::fetchAll(
            'SELECT created_at, amount FROM payments
              WHERE status = ? AND created_at >= ? AND created_at < ?',
            [self::SUCCESS_STATE, $from, $to]
        );
        $shift = -self::dbOffsetSeconds();
        foreach ($rows as $r) {
            try {
                $at = (new \DateTimeImmutable((string) $r['created_at'], new \DateTimeZone('UTC')))
                    ->modify($shift . ' seconds')
                    ->setTimezone($tz);
            } catch (\Throwable $e) {
                continue;
            }
            $key = $at->format('Y-m-d');
            if (isset($slot[$key])) {
                $sales[$slot[$key]]++;
                $revenue[$slot[$key]] += (int) $r['amount'];
            }
        }
        return ['labels' => $labels, 'sales' => $sales, 'revenue' => $revenue];
    }
}
