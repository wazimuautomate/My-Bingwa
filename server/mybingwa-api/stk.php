<?php
/**
 * POST stk.php  — start an M-Pesa STK Push for buy-for-myself.
 *
 * Request (JSON, from the app):
 *   { offerId, payerMsisdn, recipientMsisdn, clientRequestId, amountKsh }
 *   Header: X-App-Key: <shared secret>
 *
 * Response (JSON, to the app):
 *   { status, orderReference, customerMessage, errorCode }
 *   status is one of: PAYMENT_REQUESTED | PAYMENT_FAILED
 *
 * The server recomputes the amount from offerId; the app's amount is ignored.
 */

$config = require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';
$prices = require __DIR__ . '/offers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['status' => 'PAYMENT_FAILED', 'errorCode' => 'METHOD_NOT_ALLOWED'], 405);
}
require_app_key($config);

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$offerId   = (string) ($body['offerId'] ?? '');
$payer     = preg_replace('/\D/', '', (string) ($body['payerMsisdn'] ?? ''));
$recipient = preg_replace('/\D/', '', (string) ($body['recipientMsisdn'] ?? ''));
$clientId  = (string) ($body['clientRequestId'] ?? '');

if ($offerId === '' || $clientId === '' || strlen($payer) < 12) {
    json_out(['status' => 'PAYMENT_FAILED', 'errorCode' => 'BAD_REQUEST'], 400);
}
if (!isset($prices[$offerId])) {
    json_out(['status' => 'PAYMENT_FAILED', 'errorCode' => 'UNKNOWN_OFFER'], 400);
}
$amount = (int) $prices[$offerId];

$pdo = require __DIR__ . '/db.php';

// Idempotency: the same clientRequestId must never charge twice.
$existing = $pdo->prepare('SELECT * FROM payments WHERE client_request_id = ? LIMIT 1');
$existing->execute([$clientId]);
if ($row = $existing->fetch()) {
    json_out([
        'status'         => $row['status'],
        'orderReference' => $row['checkout_request_id'],
        'customerMessage'=> 'Request already in progress',
    ]);
}

$token = daraja_token($config);
if ($token === null) {
    json_out(['status' => 'PAYMENT_FAILED', 'errorCode' => 'TOKEN_FAILED'], 502);
}

$resp = daraja_stk_push($config, $token, $amount, $payer, $recipient !== '' ? $recipient : $payer);

if (is_array($resp) && (string) ($resp['ResponseCode'] ?? '') === '0') {
    $checkoutId = (string) $resp['CheckoutRequestID'];
    $ins = $pdo->prepare(
        'INSERT INTO payments
            (client_request_id, checkout_request_id, offer_id, amount, payer, recipient, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
    );
    $ins->execute([$clientId, $checkoutId, $offerId, $amount, $payer, $recipient, 'PAYMENT_REQUESTED']);

    json_out([
        'status'          => 'PAYMENT_REQUESTED',
        'orderReference'  => $checkoutId,
        'customerMessage' => (string) ($resp['CustomerMessage'] ?? 'Check your phone'),
    ]);
}

// Daraja rejected the request up front.
json_out([
    'status'    => 'PAYMENT_FAILED',
    'errorCode' => (string) ($resp['errorCode'] ?? $resp['ResponseCode'] ?? 'STK_REJECTED'),
], 502);
