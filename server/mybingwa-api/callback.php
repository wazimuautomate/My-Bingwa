<?php
/**
 * POST callback.php — Daraja posts the STK result here.
 *
 * We store the outcome against the CheckoutRequestID so status.php can serve it.
 * We always ack with ResultCode 0 so Daraja stops retrying. No X-App-Key here
 * (Daraja cannot send it); we only ever UPDATE an existing, known checkout row.
 */

$config = require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';

$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$cb = $payload['Body']['stkCallback'] ?? null;

if ($cb && isset($cb['CheckoutRequestID'])) {
    $checkoutId = (string) $cb['CheckoutRequestID'];
    $resultCode = $cb['ResultCode'] ?? null;
    $status     = map_result_code($resultCode);

    // Pull the M-Pesa receipt from the metadata on success.
    $receipt = null;
    if ($status === 'PAYMENT_CONFIRMED' && !empty($cb['CallbackMetadata']['Item'])) {
        foreach ($cb['CallbackMetadata']['Item'] as $item) {
            if (($item['Name'] ?? '') === 'MpesaReceiptNumber') {
                $receipt = (string) ($item['Value'] ?? '');
            }
        }
    }

    $pdo = require __DIR__ . '/db.php';
    $upd = $pdo->prepare(
        'UPDATE payments
            SET status = ?, mpesa_receipt = ?, result_code = ?, result_desc = ?, updated_at = NOW()
          WHERE checkout_request_id = ?'
    );
    $upd->execute([
        $status,
        $receipt,
        (string) $resultCode,
        (string) ($cb['ResultDesc'] ?? ''),
        $checkoutId,
    ]);
}

// Always acknowledge so Daraja does not keep retrying.
json_out(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
