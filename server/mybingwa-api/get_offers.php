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
    $snap = published_snapshot($pdo);
    if ($snap !== null && !empty($snap['offers'])) {
        // Serve exactly what the owner published in the admin.
        foreach ($snap['offers'] as $o) {
            $offers[] = [
                'id'        => $o['id'] ?? '',
                'category'  => $o['category'] ?? '',
                'name'      => $o['name'] ?? '',
                'price'     => (int) ($o['price'] ?? 0),
                'validity'  => $o['validity'] ?? '',
                'band'      => $o['band'] ?? '',
                'dailyRule' => $o['dailyRule'] ?? '',
            ];
        }
    } else {
        // Legacy fallback: the unprefixed offers table.
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
    }
} catch (Throwable $e) {
    // Nothing available yet → empty list; the app keeps its cached/local catalogue.
    $offers = [];
}

json_out(['offers' => $offers]);
