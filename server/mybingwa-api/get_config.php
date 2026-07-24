<?php
/**
 * GET get_config.php — seller config the app syncs when online and caches for
 * offline use (Till, Paybill, support number/WhatsApp).
 *   Header: X-App-Key: <shared secret>
 *
 * Response (JSON):
 *   { tillNumber, paybillNumber, supportNumber, supportWhatsapp, updatedAt }
 *
 * Values come from the `settings` table (managed later from the admin panel). If a
 * key is missing there, it falls back to config.php so the endpoint always answers.
 */

$config = require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';

require_app_key($config);

// Defaults from config.php (so this still works before the settings table is seeded).
$out = [
    'tillNumber'      => (string) ($config['party_b'] ?? ''),
    'paybillNumber'   => (string) ($config['paybill_number'] ?? '40450595'),
    'supportNumber'   => (string) ($config['support_number'] ?? '0727921038'),
    'supportWhatsapp' => (string) ($config['support_whatsapp'] ?? '254727921038'),
    'updatedAt'       => null,
];

try {
    $pdo = require __DIR__ . '/db.php';
    $rows = $pdo->query('SELECT skey, svalue, updated_at FROM settings')->fetchAll();
    $latest = null;
    foreach ($rows as $row) {
        switch ($row['skey']) {
            case 'till_number':      $out['tillNumber'] = $row['svalue']; break;
            case 'paybill_number':   $out['paybillNumber'] = $row['svalue']; break;
            case 'support_number':   $out['supportNumber'] = $row['svalue']; break;
            case 'support_whatsapp': $out['supportWhatsapp'] = $row['svalue']; break;
        }
        if ($latest === null || $row['updated_at'] > $latest) {
            $latest = $row['updated_at'];
        }
    }
    $out['updatedAt'] = $latest;
} catch (Throwable $e) {
    // Table not created yet → the config.php defaults above still answer.
}

json_out($out);
