<?php
/**
 * GET get_templates.php — the notification/SMS-recognition templates, in the app's
 * TemplateSet shape. The app can sync these to update Safaricom message matching
 * without an app release (future RemoteTemplateSync). App-key guarded.
 *
 * Response: { version, delivery: [ {id, senderId, category, pattern, description} ],
 *             lowBalance: [ ... ] }
 */

$config = require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';

require_app_key($config);

$delivery = [];
$lowBalance = [];
$version = 1;
try {
    $pdo = require __DIR__ . '/db.php';
    $snap = published_snapshot($pdo);
    if ($snap !== null && isset($snap['templates'])) {
        // Serve the templates the owner published in the admin.
        $t = $snap['templates'];
        $version = (int) ($t['version'] ?? 1);
        $strip = function (array $x): array {
            return [
                'id'          => $x['id'] ?? '',
                'senderId'    => $x['senderId'] ?? '',
                'category'    => $x['category'] ?? '',
                'pattern'     => $x['pattern'] ?? '',
                'description' => $x['description'] ?? '',
            ];
        };
        foreach (($t['delivery'] ?? []) as $x)   { $delivery[]   = $strip($x); }
        foreach (($t['lowBalance'] ?? []) as $x) { $lowBalance[] = $strip($x); }
    } else {
        // Legacy fallback: the unprefixed templates table.
        $rows = $pdo->query('SELECT tkey, ttype, sender_id, category, pattern, label
                             FROM templates WHERE active = 1 ORDER BY ttype, tkey')->fetchAll();
        foreach ($rows as $r) {
            $entry = [
                'id'          => $r['tkey'],
                'senderId'    => $r['sender_id'],
                'category'    => $r['category'],
                'pattern'     => $r['pattern'],
                'description' => $r['label'],
            ];
            if (($r['ttype'] ?? 'delivery') === 'low_balance') {
                $lowBalance[] = $entry;
            } else {
                $delivery[] = $entry;
            }
        }
    }
} catch (Throwable $e) {
    // Nothing available yet → empty set; the app keeps its bundled templates.
}

json_out(['version' => $version, 'delivery' => $delivery, 'lowBalance' => $lowBalance]);
