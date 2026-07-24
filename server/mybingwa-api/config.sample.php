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
    'transaction_type' => 'CustomerBuyGoodsOnline',

    // Public HTTPS URL where Daraja posts the result. Must be THIS server's
    // callback.php, e.g. https://mybingwa.blazetechscope.com/callback.php
    'callback_url' => 'https://PUT_YOUR_DOMAIN/callback.php',

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
