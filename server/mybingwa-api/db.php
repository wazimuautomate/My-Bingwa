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

return $pdo;
