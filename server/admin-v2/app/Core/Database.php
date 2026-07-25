<?php
/**
 * PDO/MySQL access with prepared statements only. A single shared connection.
 *
 * All admin-v2 tables use the configurable prefix (default `mb_`) so this application
 * coexists in the SAME MySQL database as the legacy `offers`/`settings`/`templates`/
 * `payments` tables without colliding with them. Use Database::table('offers') to get
 * the prefixed name (`mb_offers`). The legacy `payments` table is read via its real
 * name through Database::legacyTable('payments').
 */

namespace App\Core;

use PDO;
use PDOStatement;
use Throwable;

final class Database
{
    private static ?PDO $pdo = null;
    private static string $prefix = 'mb_';

    public static function boot(): void
    {
        self::$prefix = (string) Config::get('db.prefix', 'mb_');
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $host    = (string) Config::get('db.host', 'localhost');
        $name    = (string) Config::get('db.name', '');
        $charset = (string) Config::get('db.charset', 'utf8mb4');
        $dsn     = "mysql:host={$host};dbname={$name};charset={$charset}";
        try {
            self::$pdo = new PDO(
                $dsn,
                (string) Config::get('db.user', ''),
                (string) Config::get('db.pass', ''),
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (Throwable $e) {
            http_response_code(500);
            if (!Config::isProduction()) {
                header('Content-Type: text/plain');
                exit('Database connection failed: ' . $e->getMessage());
            }
            exit('Database unavailable.');
        }
        return self::$pdo;
    }

    /** Prefixed application table name, e.g. table('offers') => 'mb_offers'. */
    public static function table(string $name): string
    {
        return self::$prefix . $name;
    }

    public static function prefix(): string
    {
        return self::$prefix;
    }

    /** A legacy (unprefixed) table shared with the existing API, e.g. 'payments'. */
    public static function legacyTable(string $name): string
    {
        return $name;
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function scalar(string $sql, array $params = [])
    {
        $stmt = self::run($sql, $params);
        $val = $stmt->fetchColumn();
        return $val === false ? null : $val;
    }

    public static function transaction(callable $fn)
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Does a prefixed table exist? Used by the migration/health checks. */
    public static function tableExists(string $unprefixed): bool
    {
        $full = self::table($unprefixed);
        $row = self::fetch(
            'SELECT COUNT(*) AS c FROM information_schema.tables
              WHERE table_schema = DATABASE() AND table_name = ?',
            [$full]
        );
        return (int) ($row['c'] ?? 0) > 0;
    }
}
