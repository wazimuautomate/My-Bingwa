<?php
/**
 * Database connection (PDO/MySQL). Returns a ready PDO handle.
 * Usage:  $pdo = require __DIR__ . '/db.php';
 */

$config = require __DIR__ . '/config.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (Throwable $e) {
    // Never leak DB details to the client.
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'PAYMENT_FAILED', 'errorCode' => 'DB_UNAVAILABLE']);
    exit;
}

// Auto-provision the payments table so there is no manual schema import (no phpMyAdmin,
// no SQL). Idempotent; if the DB user lacks CREATE it is simply skipped and reads/writes
// continue against the existing table.
try {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS payments (
            id                   BIGINT AUTO_INCREMENT PRIMARY KEY,
            client_request_id    VARCHAR(64)  NOT NULL,
            checkout_request_id  VARCHAR(64)  DEFAULT NULL,
            offer_id             VARCHAR(32)  NOT NULL,
            amount               INT          NOT NULL,
            payer                VARCHAR(16)  NOT NULL,
            recipient            VARCHAR(16)  DEFAULT NULL,
            status               VARCHAR(24)  NOT NULL DEFAULT 'PAYMENT_REQUESTED',
            mpesa_receipt        VARCHAR(24)  DEFAULT NULL,
            result_code          VARCHAR(12)  DEFAULT NULL,
            result_desc          VARCHAR(191) DEFAULT NULL,
            created_at           DATETIME     NOT NULL,
            updated_at           DATETIME     NOT NULL,
            UNIQUE KEY uniq_client_request (client_request_id),
            KEY idx_checkout (checkout_request_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
} catch (Throwable $e) {
    // Table already exists or cannot be created here — safe to ignore.
}

return $pdo;
