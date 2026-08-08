<?php
/**
 * The customer register — the seller's own copy of who is using the app.
 *
 * Rows are written by `mybingwa-api/register_user.php` when a phone finishes
 * onboarding, and are only ever read (or deleted) here. There is deliberately
 * nothing to edit: the customer owns their details on their phone.
 *
 * Timestamps are written with MySQL NOW(), i.e. the DATABASE server's clock, which
 * on shared hosting may be UTC or EAT. Every "today" window below is therefore
 * expressed in that measured clock — the same approach PaymentRepository uses, and
 * for the same reason: assuming would silently move "today" by three hours.
 */

namespace App\Repositories;

use App\Core\Database;

final class CustomerRepository
{
    public static function available(): bool
    {
        $table = Database::table('customers');
        $row = Database::fetch(
            'SELECT COUNT(*) AS c FROM information_schema.tables
              WHERE table_schema = DATABASE() AND table_name = ?',
            [$table]
        );
        return (int) ($row['c'] ?? 0) > 0;
    }

    /**
     * Filtered, paged list. Filters: q (name or number), from, to (Nairobi Y-m-d).
     * @return array{rows:array, total:int}
     */
    public static function search(array $f, int $page = 1, int $perPage = 50): array
    {
        if (!self::available()) {
            return ['rows' => [], 'total' => 0];
        }
        [$where, $params] = self::buildWhere($f);
        $table = Database::table('customers');
        $total = (int) (Database::scalar("SELECT COUNT(*) FROM {$table} {$where}", $params) ?? 0);
        $perPage = max(1, min(500, $perPage));
        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::fetchAll(
            "SELECT * FROM {$table} {$where} ORDER BY created_at DESC, id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /** Every matching row, unpaged — for the CSV export. */
    public static function all(array $f): array
    {
        if (!self::available()) {
            return [];
        }
        [$where, $params] = self::buildWhere($f);
        $table = Database::table('customers');
        return Database::fetchAll(
            "SELECT * FROM {$table} {$where} ORDER BY created_at DESC, id DESC",
            $params
        );
    }

    public static function find(int $id): ?array
    {
        if (!self::available()) {
            return null;
        }
        return Database::fetch('SELECT * FROM ' . Database::table('customers') . ' WHERE id = ? LIMIT 1', [$id]);
    }

    /**
     * The two figures the owner actually wants: how many customers there are, and
     * how many joined today (Africa/Nairobi). "This week" is included because a
     * single day is a noisy number to judge growth by.
     *
     * @return array{total:int, today:int, week:int, available:bool}
     */
    public static function summary(): array
    {
        if (!self::available()) {
            return ['total' => 0, 'today' => 0, 'week' => 0, 'available' => false];
        }
        $table = Database::table('customers');
        [$dayFrom, $dayTo] = PaymentRepository::dayWindow();
        [$weekFrom, $weekTo] = PaymentRepository::daysWindow(7);

        return [
            'total' => (int) (Database::scalar("SELECT COUNT(*) FROM {$table}") ?? 0),
            'today' => (int) (Database::scalar(
                "SELECT COUNT(*) FROM {$table} WHERE created_at >= ? AND created_at < ?",
                [$dayFrom, $dayTo]
            ) ?? 0),
            'week' => (int) (Database::scalar(
                "SELECT COUNT(*) FROM {$table} WHERE created_at >= ? AND created_at < ?",
                [$weekFrom, $weekTo]
            ) ?? 0),
            'available' => true,
        ];
    }

    /** Remove one customer from the register. The app on their phone is untouched. */
    public static function delete(int $id): void
    {
        if (!self::available()) {
            return;
        }
        Database::run('DELETE FROM ' . Database::table('customers') . ' WHERE id = ?', [$id]);
    }

    /**
     * Bulk delete by id, for the "select all → remove" action. The caller casts,
     * de-duplicates and caps the ids and writes the audit entry.
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
            'DELETE FROM ' . Database::table('customers') . " WHERE id IN ({$placeholders})",
            array_values($ids)
        )->rowCount();
    }

    /** 254712345678 → "0712 345 678", the way the number is read back in Kenya. */
    public static function displayNumber(string $msisdn): string
    {
        $digits = preg_replace('/\D/', '', $msisdn);
        $tail = substr($digits, -9);
        if (strlen($tail) !== 9) {
            return $msisdn;
        }
        $local = '0' . $tail;
        return substr($local, 0, 4) . ' ' . substr($local, 4, 3) . ' ' . substr($local, 7);
    }

    private static function buildWhere(array $f): array
    {
        $clauses = [];
        $params = [];
        if (!empty($f['q'])) {
            $clauses[] = '(name LIKE ? OR msisdn LIKE ?)';
            // Search the number by its digits, so "0712" finds 254712…
            $digits = preg_replace('/\D/', '', (string) $f['q']);
            $params[] = '%' . $f['q'] . '%';
            $params[] = '%' . ($digits !== '' ? ltrim($digits, '0') : $f['q']) . '%';
        }
        if (!empty($f['from'])) {
            [$from] = PaymentRepository::dayWindow((string) $f['from']);
            $clauses[] = 'created_at >= ?';
            $params[] = $from;
        }
        if (!empty($f['to'])) {
            [, $to] = PaymentRepository::dayWindow((string) $f['to']);
            $clauses[] = 'created_at < ?';
            $params[] = $to;
        }
        return [$clauses ? ('WHERE ' . implode(' AND ', $clauses)) : '', $params];
    }
}
