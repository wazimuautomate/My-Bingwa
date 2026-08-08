<?php
/**
 * POST register_user.php — the app announces a new customer, once per install.
 *
 * Request (JSON, from the app):
 *   { name, msisdn, appVersion }
 *   Header: X-App-Key: <shared secret>
 *
 * Response (JSON): { status: "REGISTERED" | "REGISTER_FAILED", errorCode? }
 *
 * The app calls this exactly once, at the end of onboarding, and remembers that it
 * succeeded so it never calls again. If the phone is offline at that moment the app
 * retries on a later launch — which is why this endpoint is idempotent on the
 * number: a customer who reinstalls (or whose retry lands twice) updates their
 * existing row rather than creating a duplicate.
 *
 * What is stored is only what the customer typed in: a name and a Safaricom number.
 * No purchase history, no behaviour, nothing derived — the phone keeps all of that
 * (CLAUDE.md §10). The admin panel reads this table on its Customers page.
 */

$config = require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['status' => 'REGISTER_FAILED', 'errorCode' => 'METHOD_NOT_ALLOWED'], 405);
}
require_app_key($config);

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$name = trim((string) ($body['name'] ?? ''));
$appVersion = trim((string) ($body['appVersion'] ?? ''));
$digits = preg_replace('/\D/', '', (string) ($body['msisdn'] ?? ''));

// Canonical 2547XXXXXXXX / 2541XXXXXXXX, so one line is always one row whichever
// way the number was typed on the phone.
$tail = substr($digits, -9);
if (strlen($tail) !== 9 || !preg_match('/^[71]/', $tail)) {
    json_out(['status' => 'REGISTER_FAILED', 'errorCode' => 'BAD_MSISDN'], 400);
}
$msisdn = '254' . $tail;

// Trim to the column widths rather than rejecting: a long name is still a customer.
$name = mb_substr($name, 0, 80);
$appVersion = mb_substr($appVersion, 0, 24);

$pdo = require __DIR__ . '/db.php';

// Auto-provision, exactly like payments in db.php, so the endpoint works on an
// install where the admin panel has not run its migrations yet. Same DDL as
// admin-v2/database/migrations/019_customers.sql.
try {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS mb_customers (
            id            BIGINT AUTO_INCREMENT PRIMARY KEY,
            msisdn        VARCHAR(16)  NOT NULL,
            name          VARCHAR(80)  NOT NULL DEFAULT \'\',
            app_version   VARCHAR(24)  NOT NULL DEFAULT \'\',
            registrations INT          NOT NULL DEFAULT 1,
            created_at    DATETIME     NOT NULL,
            updated_at    DATETIME     NOT NULL,
            UNIQUE KEY uniq_customer_msisdn (msisdn),
            KEY idx_customer_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
} catch (Throwable $e) {
    // Already there, or this DB user cannot CREATE — the insert below decides.
}

try {
    // Idempotent on the number: a reinstall or a retried call updates the name and
    // counts the registration instead of creating a second customer.
    $stmt = $pdo->prepare(
        'INSERT INTO mb_customers (msisdn, name, app_version, registrations, created_at, updated_at)
              VALUES (?, ?, ?, 1, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
              name = VALUES(name),
              app_version = VALUES(app_version),
              registrations = registrations + 1,
              updated_at = NOW()'
    );
    $stmt->execute([$msisdn, $name, $appVersion]);
} catch (Throwable $e) {
    json_out(['status' => 'REGISTER_FAILED', 'errorCode' => 'DB_WRITE_FAILED'], 500);
}

json_out(['status' => 'REGISTERED']);
