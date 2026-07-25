<?php
/**
 * SAMPLE config. Copy this to `config.php` ON THE SERVER and fill in the real
 * values. `config.php` is git-ignored and must NEVER be committed — it holds your
 * Daraja secrets, which live ONLY on the cPanel server.
 *
 * (The .htaccess in this folder also blocks the web from downloading either file,
 * and PHP never serves its source as text.)
 */

return [

    // ---- Shared secret with the Android app -------------------------------
    // Any long random string. The app sends it as the "X-App-Key" header so only
    // your app can trigger an STK push. Put the SAME value in the app's
    // PAYMENTS_APP_KEY build config (GitHub secret).
    'app_key' => 'PUT_A_LONG_RANDOM_STRING_HERE',

    // ---- Daraja environment ----------------------------------------------
    // 'sandbox' while testing, 'production' when live.
    'daraja_env' => 'sandbox',

    // ---- Daraja credentials (from the Safaricom Daraja portal) ------------
    'consumer_key'    => 'PUT_CONSUMER_KEY',
    'consumer_secret' => 'PUT_CONSUMER_SECRET',

    // The Lipa na M-Pesa Online passkey for your short code.
    'passkey' => 'PUT_PASSKEY',

    // Your short code used to build the request password. For a Buy Goods (Till)
    // set this to the Head Office / store number tied to the till. For a Paybill,
    // this is the Paybill number.
    'business_shortcode' => 'PUT_SHORTCODE',

    // The number that actually RECEIVES the money.
    //  - Buy Goods (Till): the Till number (e.g. 4953696).
    //  - Paybill: the Paybill number.
    'party_b' => 'PUT_TILL_NUMBER',

    // 'CustomerBuyGoodsOnline' for a Till, 'CustomerPayBillOnline' for a Paybill.
    // This is the SELF / buy-for-myself route (Till). Buy-for-another always uses
    // 'CustomerPayBillOnline' regardless of this value (see paybill_shortcode below).
    'transaction_type' => 'CustomerBuyGoodsOnline',

    // ---- Buy-for-another (Paybill) route ----------------------------------
    // When the app sends forSelf=false (route "another"), the STK is sent as a
    // Paybill payment and the AccountReference is the bundle recipient's number.
    // paybill_shortcode is the Paybill number used BOTH as BusinessShortCode and
    // in the STK password. If omitted, it falls back to business_shortcode above.
    'paybill_shortcode' => 'PUT_PAYBILL_SHORTCODE',
    // Optional: a separate passkey for the Paybill product. If omitted, the Till
    // 'passkey' above is reused. Only set this if Daraja gave you a distinct one.
    'paybill_passkey' => 'PUT_PAYBILL_PASSKEY_OPTIONAL',

    // ---- Daraja callback (result webhook) authenticity --------------------
    // Daraja cannot send a custom header, so callback.php is protected by a
    // shared-secret token in its URL: callback.php?token=<callback_secret>.
    // A POST without the correct token (or from a disallowed IP) is acked but
    // IGNORED — this is what stops attackers spoofing a "paid" result.
    // Use a long random string. It MUST match the ?token=... in callback_url.
    'callback_secret' => 'PUT_A_LONG_RANDOM_CALLBACK_TOKEN',

    // Optional Safaricom source-IP allowlist. Empty array = allow all IPs.
    // To lock down, list Daraja's callback egress IPs, e.g.
    //   'callback_ip_allowlist' => ['196.201.214.200', '196.201.214.206'],
    'callback_ip_allowlist' => [],

    // Optional: if a proxy/CDN you CONTROL fronts this server, name the PHP
    // $_SERVER key that carries the real client IP (e.g. 'HTTP_X_FORWARDED_FOR').
    // Leave '' when Daraja hits this server directly — otherwise it is spoofable.
    'trusted_proxy_header' => '',

    // Public HTTPS URL where Daraja posts the result. Must be THIS server's
    // callback.php AND MUST include the secret token, and must be exactly the URL
    // you register in the Daraja portal, e.g.
    //   https://mybingwa.blazetechscope.com/callback.php?token=PUT_A_LONG_RANDOM_CALLBACK_TOKEN
    'callback_url' => 'https://PUT_YOUR_DOMAIN/callback.php?token=PUT_A_LONG_RANDOM_CALLBACK_TOKEN',

    // ---- Admin panel login (admin/ folder) --------------------------------
    // Used to sign in to the offers/settings/templates manager. Change these.
    'admin_user' => 'admin',
    'admin_pass' => 'PUT_A_STRONG_ADMIN_PASSWORD',

    // ---- Fallback seller details (only used if the settings table is empty) --
    // Normally you manage these from the admin panel; these are just defaults.
    'paybill_number'   => '40450595',
    'support_number'   => '0727921038',
    'support_whatsapp' => '254727921038',

    // ---- MySQL database (create it in cPanel → MySQL Databases) -----------
    'db_host' => 'localhost',
    'db_name' => 'PUT_DB_NAME',
    'db_user' => 'PUT_DB_USER',
    'db_pass' => 'PUT_DB_PASSWORD',
];
