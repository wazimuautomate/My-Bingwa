<?php
/**
 * GET get_sms_rules.php — the on-device SMS recognition rules, in the app's SmsRuleSet
 * shape. App-key guarded.
 *   Header: X-App-Key: <shared secret>
 *
 * Response (JSON):
 *   { version, updatedAt,
 *     rules: [ { id, name, senderId, pattern, matchType, eventTypes, priority,
 *                enabled, description } ] }
 *
 * These rules let the owner fix Safaricom message matching — a changed sender ID, a
 * reworded confirmation — WITHOUT shipping an app update. Matching happens entirely on
 * the phone: the server never sees a message, a phone number or a balance.
 *
 * With no published snapshot (or the section absent, e.g. an admin that predates SMS
 * Rules) this returns a valid EMPTY set, never an error, so the app keeps its bundled
 * seed rules or its last synced set.
 *
 * ADMIN CONSOLE: populated by SMS Rules (migration 013_sms_rules.sql → the `sms_rules`
 * table) and carried into the published snapshot as `smsRules` by
 * PublishingService::buildSmsRules(). Only enabled rules are published.
 */

$config = require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';

require_app_key($config);

$rules     = [];
$version   = 0;
$updatedAt = 0;

try {
    $pdo  = require __DIR__ . '/db.php';
    $snap = published_snapshot($pdo);

    if ($snap !== null) {
        $version = (int) ($snap['configVersion'] ?? 0);

        foreach (($snap['smsRules'] ?? []) as $r) {
            $id      = (string) ($r['id'] ?? '');
            $pattern = (string) ($r['pattern'] ?? '');
            if ($id === '' || $pattern === '') {
                continue; // a rule with no id or no pattern can never match anything
            }

            // The app's event list is the primary event plus any secondary events, all
            // upper-cased. An event name this app build does not know is simply never
            // acted on — it must never crash the parser.
            $events = [];
            $primary = strtoupper(trim((string) ($r['event'] ?? '')));
            if ($primary !== '') {
                $events[] = $primary;
            }
            foreach (($r['secondaryEvents'] ?? []) as $secondary) {
                $secondary = strtoupper(trim((string) $secondary));
                if ($secondary !== '' && !in_array($secondary, $events, true)) {
                    $events[] = $secondary;
                }
            }

            $rules[] = [
                'id'          => $id,
                'name'        => (string) ($r['name'] ?? ''),
                'senderId'    => (string) ($r['senderId'] ?? ''),
                'pattern'     => $pattern,
                // v2 stores 'regex' / 'contains'; the app expects REGEX / CONTAINS.
                'matchType'   => strtoupper(trim((string) ($r['patternType'] ?? 'regex'))),
                'eventTypes'  => $events,
                'priority'    => (int) ($r['priority'] ?? 100),
                // Only enabled rules are ever published, so this is always true. It is
                // still sent explicitly so the app never has to infer it.
                'enabled'     => true,
                // The admin's per-rule Description column exists but is not yet carried
                // into the published snapshot by PublishingService::buildSmsRules(); it
                // is read here so it starts flowing the moment that is added.
                'description' => (string) ($r['description'] ?? ''),
            ];
        }
    }

    // The publish timestamp is advisory only (diagnostics); the app compares versions
    // and checksums, never clocks.
    try {
        $row = $pdo->query(
            'SELECT created_at FROM mb_configuration_releases ORDER BY version DESC LIMIT 1'
        )->fetch();
        $ts = $row ? strtotime((string) ($row['created_at'] ?? '')) : false;
        $updatedAt = $ts ? $ts * 1000 : 0;
    } catch (Throwable $e) {
        $updatedAt = 0;
    }
} catch (Throwable $e) {
    // Nothing available yet → empty set; the app keeps its bundled/last-synced rules.
    $rules     = [];
    $version   = 0;
    $updatedAt = 0;
}

json_out(['version' => $version, 'updatedAt' => $updatedAt, 'rules' => $rules]);
