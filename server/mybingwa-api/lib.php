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

/**
 * Reject the request unless it carries the correct X-App-Key header.
 * Constant-time compare. If app_key is not configured we FAIL CLOSED (401) so a
 * blank/placeholder secret can never be bypassed with an empty header.
 */
function require_app_key(array $config): void
{
    $expected = (string) ($config['app_key'] ?? '');
    $sent     = (string) ($_SERVER['HTTP_X_APP_KEY'] ?? '');
    if ($expected === '' || !hash_equals($expected, $sent)) {
        json_out(['status' => 'PAYMENT_FAILED', 'errorCode' => 'UNAUTHORISED'], 401);
    }
}

// ---------------------------------------------------------------------------
// Callback authenticity (Daraja cannot send custom headers, so we gate the
// CallbackURL with a shared-secret path token + an optional source-IP allowlist).
// ---------------------------------------------------------------------------

/** Ack a callback we deliberately will NOT act on. 200 so Daraja stops retrying. */
function callback_ack_ignore(): void
{
    json_out(['ResultCode' => 0, 'ResultDesc' => 'ignored']);
}

/** True only if the callback URL carries the expected shared-secret token. */
function callback_token_ok(array $config): bool
{
    $expected = (string) ($config['callback_secret'] ?? '');
    if ($expected === '') {
        // No secret configured → we cannot authenticate, so trust nothing.
        return false;
    }
    $sent = (string) ($_GET['token'] ?? '');
    return hash_equals($expected, $sent);
}

/**
 * Resolve the client IP, honouring a configured trusted proxy header if set.
 * `trusted_proxy_header` is a PHP $_SERVER key such as 'HTTP_X_FORWARDED_FOR'.
 * Only set it when a proxy you control fronts this server, otherwise the header
 * is client-spoofable.
 */
function client_ip(array $config): string
{
    $header = (string) ($config['trusted_proxy_header'] ?? '');
    if ($header !== '' && !empty($_SERVER[$header])) {
        // A forwarded header may be "client, proxy1, proxy2" — take the first hop.
        $parts = explode(',', (string) $_SERVER[$header]);
        return trim($parts[0]);
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

/** True if the source IP is allowed. An empty allowlist means "allow all". */
function callback_ip_allowed(array $config): bool
{
    $allow = $config['callback_ip_allowlist'] ?? [];
    if (!is_array($allow) || count($allow) === 0) {
        return true;
    }
    return in_array(client_ip($config), $allow, true);
}

/**
 * Decide the payment route from the app's request body.
 * Preference order: explicit `forSelf` boolean, then a `route` string
 * ("self"/"another"), then default to self (the original Till behaviour).
 */
function stk_is_self(array $body): bool
{
    if (array_key_exists('forSelf', $body)) {
        return (bool) filter_var($body['forSelf'], FILTER_VALIDATE_BOOLEAN);
    }
    $route = strtolower(trim((string) ($body['route'] ?? '')));
    if ($route === 'another' || $route === 'other') {
        return false;
    }
    // "self", "", or anything unrecognised → keep the backward-compatible Till path.
    return true;
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

/**
 * Send an STK push. Returns the decoded Daraja response, or a synthetic
 * ['errorCode' => ...] array on transport failure (never a bare null, so callers
 * can report a clean status instead of tripping over a null offset).
 *
 * $route selects the M-Pesa product:
 *   'self'    → Till / Buy-Goods (CustomerBuyGoodsOnline), PartyB = configured Till.
 *   'another' → Paybill (CustomerPayBillOnline), PartyB = Paybill shortcode,
 *               AccountReference = the recipient MSISDN.
 * Defaults to 'self' so existing callers keep the original behaviour unchanged.
 * The STK Password ALWAYS uses the same shortcode set as BusinessShortCode.
 */
function daraja_stk_push(
    array $config,
    string $token,
    int $amount,
    string $payerMsisdn,
    string $accountRef,
    string $route = 'self'
): ?array {
    $isAnother = ($route === 'another');

    // Shortcode used both as BusinessShortCode and inside the password hash.
    $shortcode = $isAnother
        ? (string) ($config['paybill_shortcode'] ?? $config['business_shortcode'])
        : (string) $config['business_shortcode'];

    // Passkey may be overridden for the Paybill product, else reuse the Till passkey.
    $passkey = $isAnother
        ? (string) ($config['paybill_passkey'] ?? $config['passkey'])
        : (string) $config['passkey'];

    $txType = $isAnother
        ? 'CustomerPayBillOnline'
        : (string) ($config['transaction_type'] ?? 'CustomerBuyGoodsOnline');

    // For a Paybill the money party IS the paybill shortcode; for a Till it is the
    // configured Buy-Goods number (party_b).
    $partyB = $isAnother ? $shortcode : (string) $config['party_b'];

    $timestamp = date('YmdHis');
    $password  = base64_encode($shortcode . $passkey . $timestamp);

    $payload = [
        'BusinessShortCode' => $shortcode,
        'Password'          => $password,
        'Timestamp'         => $timestamp,
        'TransactionType'   => $txType,
        'Amount'            => $amount,
        'PartyA'            => $payerMsisdn,
        'PartyB'            => $partyB,
        'PhoneNumber'       => $payerMsisdn,
        'CallBackURL'       => $config['callback_url'],
        'AccountReference'  => substr($accountRef, 0, 12),
        'TransactionDesc'   => 'My Bingwa bundle',
    ];

    [$httpCode, $json] = http_json(
        'POST',
        daraja_base($config) . '/mpesa/stkpush/v1/processrequest',
        ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        json_encode($payload)
    );

    // Transport-level failure (no/invalid JSON) → return a clean error shape.
    if (!is_array($json)) {
        return ['errorCode' => 'STK_TRANSPORT_ERROR', 'httpCode' => $httpCode];
    }
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
