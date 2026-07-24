<?php
/**
 * Shared helpers: JSON output, app-key auth, Daraja calls and result mapping.
 * Included by stk.php, status.php and callback.php.
 */

/** Send a JSON response and stop. */
function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/** Reject the request unless it carries the correct X-App-Key header. */
function require_app_key(array $config): void
{
    $sent = $_SERVER['HTTP_X_APP_KEY'] ?? '';
    if (!hash_equals($config['app_key'], $sent)) {
        json_out(['status' => 'PAYMENT_FAILED', 'errorCode' => 'UNAUTHORISED'], 401);
    }
}

/** The Daraja base host for the configured environment. */
function daraja_base(array $config): string
{
    return $config['daraja_env'] === 'production'
        ? 'https://api.safaricom.co.ke'
        : 'https://sandbox.safaricom.co.ke';
}

/** Small cURL POST/GET helper returning [httpCode, decodedJsonOrNull]. */
function http_json(string $method, string $url, array $headers, ?string $body = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $raw ? json_decode($raw, true) : null];
}

/** Get a Daraja OAuth access token, or null on failure. */
function daraja_token(array $config): ?string
{
    $auth = base64_encode($config['consumer_key'] . ':' . $config['consumer_secret']);
    [$code, $json] = http_json(
        'GET',
        daraja_base($config) . '/oauth/v1/generate?grant_type=client_credentials',
        ['Authorization: Basic ' . $auth]
    );
    return ($code === 200 && !empty($json['access_token'])) ? $json['access_token'] : null;
}

/** Send an STK push. Returns the decoded Daraja response (or null). */
function daraja_stk_push(array $config, string $token, int $amount, string $payerMsisdn, string $accountRef): ?array
{
    $timestamp = date('YmdHis');
    $password  = base64_encode($config['business_shortcode'] . $config['passkey'] . $timestamp);

    $payload = [
        'BusinessShortCode' => $config['business_shortcode'],
        'Password'          => $password,
        'Timestamp'         => $timestamp,
        'TransactionType'   => $config['transaction_type'],
        'Amount'            => $amount,
        'PartyA'            => $payerMsisdn,
        'PartyB'            => $config['party_b'],
        'PhoneNumber'       => $payerMsisdn,
        'CallBackURL'       => $config['callback_url'],
        'AccountReference'  => substr($accountRef, 0, 12),
        'TransactionDesc'   => 'My Bingwa bundle',
    ];

    [, $json] = http_json(
        'POST',
        daraja_base($config) . '/mpesa/stkpush/v1/processrequest',
        ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        json_encode($payload)
    );
    return $json;
}

/** Query the status of an STK push (fallback when the callback is slow/absent). */
function daraja_stk_query(array $config, string $token, string $checkoutId): ?array
{
    $timestamp = date('YmdHis');
    $password  = base64_encode($config['business_shortcode'] . $config['passkey'] . $timestamp);

    [, $json] = http_json(
        'POST',
        daraja_base($config) . '/mpesa/stkpushquery/v1/query',
        ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        json_encode([
            'BusinessShortCode' => $config['business_shortcode'],
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'CheckoutRequestID' => $checkoutId,
        ])
    );
    return $json;
}

/**
 * Map an M-Pesa ResultCode to one of the app's status strings.
 *   0    → PAYMENT_CONFIRMED
 *   1032 → CANCELLED (customer cancelled the prompt)
 *   1037 → TIMED_OUT (no response / could not be reached)
 *   any other numeric code → PAYMENT_FAILED (e.g. 1 insufficient, 2001 wrong PIN)
 */
function map_result_code($resultCode): string
{
    switch ((string) $resultCode) {
        case '0':    return 'PAYMENT_CONFIRMED';
        case '1032': return 'CANCELLED';
        case '1037': return 'TIMED_OUT';
        default:     return 'PAYMENT_FAILED';
    }
}
