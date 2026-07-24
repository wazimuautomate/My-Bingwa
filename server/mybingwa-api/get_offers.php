<?php
/**
 * GET get_offers.php — the catalogue the app syncs when online and caches offline.
 *   Header: X-App-Key: <shared secret>
 *
 * Response (JSON): { offers: [ { id, category, name, price, validity, band, dailyRule } ] }
 * Only active offers are returned. Managed from the admin panel.
 */

$config = require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';

require_app_key($config);

$offers = [];
try {
    $pdo = require __DIR__ . '/db.php';
    $rows = $pdo->query(
        'SELECT offer_id, category, name, price, validity, band, daily_rule
           FROM offers WHERE active = 1 ORDER BY sort_order, category, price'
    )->fetchAll();
    foreach ($rows as $r) {
        $offers[] = [
            'id'        => $r['offer_id'],
            'category'  => $r['category'],
            'name'      => $r['name'],
            'price'     => (int) $r['price'],
            'validity'  => $r['validity'],
            'band'      => $r['band'],
            'dailyRule' => $r['daily_rule'],
        ];
    }
} catch (Throwable $e) {
    // Table not created yet → return an empty list; the app keeps its cached/local catalogue.
    $offers = [];
}

json_out(['offers' => $offers]);
