<?php
/**
 * POST stk.php  — start an M-Pesa STK Push for buy-for-myself.
 *
 * Request (JSON, from the app):
 *   { offerId, payerMsisdn, recipientMsisdn, clientRequestId, amountKsh,
 *     forSelf, route }
 *   Header: X-App-Key: <shared secret>
 *
 *   forSelf (bool) / route ("self"|"another") pick the M-Pesa product:
 *     self    → Till / Buy-Goods (default, backward compatible)
 *     another → Paybill, AccountReference = recipient MSISDN
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
$forSelf   = stk_is_self($body);

if ($offerId === '' || $clientId === '' || strlen($payer) < 12) {
    json_out(['status' => 'PAYMENT_FAILED', 'errorCode' => 'BAD_REQUEST'], 400);
}
if (!$forSelf && strlen($recipient) < 12) {
    // Buy-for-another needs a real bundle recipient for the Paybill account.
    json_out(['status' => 'PAYMENT_FAILED', 'errorCode' => 'RECIPIENT_REQUIRED'], 400);
}
if (!isset($prices[$offerId])) {
    json_out(['status' => 'PAYMENT_FAILED', 'errorCode' => 'UNKNOWN_OFFER'], 400);
}
$amount = (int) $prices[$offerId];
$route  = $forSelf ? 'self' : 'another';

$pdo = require __DIR__ . '/db.php';

// --- Atomic idempotency ----------------------------------------------------
// Claim the client_request_id by INSERTing the row (status PAYMENT_REQUESTED)
// BEFORE calling Daraja. Two concurrent identical requests race on the UNIQUE
// key: the first insert wins and fires exactly one STK; the loser's insert
// throws, and we return the existing row's status — no second STK, no 500.
try {
    $ins = $pdo->prepare(
        'INSERT INTO payments
            (client_request_id, offer_id, amount, payer, recipient, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
    );
    $ins->execute([$clientId, $offerId, $amount, $payer, $recipient, 'PAYMENT_REQUESTED']);
    $paymentId = (int) $pdo->lastInsertId();
} catch (PDOException $e) {
    // Almost certainly the UNIQUE(client_request_id) violation → idempotent replay.
    $existing = $pdo->prepare('SELECT * FROM payments WHERE client_request_id = ? LIMIT 1');
    $existing->execute([$clientId]);
    if ($row = $existing->fetch()) {
        json_out([
            'status'          => $row['status'],
            'orderReference'  => $row['checkout_request_id'],
            'customerMessage' => 'Request already in progress',
        ]);
    }
    // Some other write failure → fail cleanly, never surface a raw 500 exception.
    json_out(['status' => 'PAYMENT_FAILED', 'errorCode' => 'DB_WRITE_FAILED'], 500);
}

// We now own a fresh row; only WE will call Daraja for this clientRequestId.
$token = daraja_token($config);
if ($token === null) {
    $pdo->prepare('UPDATE payments SET status = ?, result_desc = ?, updated_at = NOW() WHERE id = ?')
        ->execute(['PAYMENT_FAILED', 'Daraja token request failed', $paymentId]);
    json_out(['status' => 'PAYMENT_FAILED', 'errorCode' => 'TOKEN_FAILED'], 502);
}

// AccountReference shown on the M-Pesa/Paybill statement.
//  - Buy-for-myself → a fixed brand tag "MyBingwa" so every self-purchase to the
//    Paybill lands under one clear account.
//  - Buy-for-another → the recipient's number (CLAUDE.md §7), so the owner can see
//    which line the bundle is for. (Another-number is mocked in the app for now.)
$account = $forSelf ? 'MyBingwa' : ($recipient !== '' ? $recipient : $payer);
$resp = daraja_stk_push($config, $token, $amount, $payer, $account, $route);

if (is_array($resp) && (string) ($resp['ResponseCode'] ?? '') === '0') {
    $checkoutId = (string) $resp['CheckoutRequestID'];
    $pdo->prepare('UPDATE payments SET checkout_request_id = ?, updated_at = NOW() WHERE id = ?')
        ->execute([$checkoutId, $paymentId]);

    json_out([
        'status'          => 'PAYMENT_REQUESTED',
        'orderReference'  => $checkoutId,
        'customerMessage' => (string) ($resp['CustomerMessage'] ?? 'Check your phone'),
    ]);
}

// Daraja rejected the request up front → mark the row failed and return cleanly.
$errorCode = (string) ($resp['errorCode'] ?? $resp['ResponseCode'] ?? 'STK_REJECTED');
$pdo->prepare('UPDATE payments SET status = ?, result_desc = ?, updated_at = NOW() WHERE id = ?')
    ->execute(['PAYMENT_FAILED', substr('STK rejected: ' . $errorCode, 0, 191), $paymentId]);
json_out([
    'status'    => 'PAYMENT_FAILED',
    'errorCode' => $errorCode,
], 502);
