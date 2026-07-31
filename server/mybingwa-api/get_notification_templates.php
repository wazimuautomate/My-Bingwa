<?php
/**
 * GET get_notification_templates.php — the notification WORDING the on-device composer
 * picks from, in the app's NotificationTemplateSet shape. App-key guarded.
 *   Header: X-App-Key: <shared secret>
 *
 * Response (JSON):
 *   { version, templates: [ { id, category, title, body, weight, enabled } ] }
 *
 * These are templates, not sends. The server never decides that a customer should be
 * notified — the app applies the permission state, quiet hours, frequency caps,
 * recent-purchase suppression and deduplication (CLAUDE.md §9) and only then chooses
 * one wording variant. Syncing them lets the owner refresh copy without an app release.
 *
 * With no published snapshot (or the section absent) this returns a valid EMPTY set,
 * never an error, so the app keeps its in-APK seed templates or its last synced set.
 *
 * ADMIN CONSOLE: populated by Notifications (migration 014_notifications_v2.sql →
 * `notification_campaigns` + `notification_variations`) and carried into the published
 * snapshot as `notifications` by PublishingService::buildNotifications(). Each enabled
 * campaign contributes one template per enabled wording variation.
 */

$config = require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';

require_app_key($config);

$templates = [];
$version   = 0;

try {
    $pdo  = require __DIR__ . '/db.php';
    $snap = published_snapshot($pdo);

    if ($snap !== null) {
        $version = (int) ($snap['configVersion'] ?? 0);

        foreach (($snap['notifications'] ?? []) as $campaign) {
            $campaignId = (string) ($campaign['id'] ?? '');
            if ($campaignId === '') {
                continue;
            }
            // The app keeps the category as a raw STRING, so a category this build does
            // not know is simply never selected rather than crashing.
            $category = strtoupper(trim((string) ($campaign['category'] ?? '')));

            $index = 0;
            foreach (($campaign['variations'] ?? []) as $variation) {
                $body = trim((string) ($variation['body'] ?? ''));
                if ($body === '') {
                    continue; // an empty notification is worse than no notification
                }
                $templates[] = [
                    // Stable per campaign + slot, so the app can dedupe across syncs.
                    'id'       => 'c' . $campaignId . '-v' . $index,
                    'category' => $category,
                    'title'    => (string) ($variation['title'] ?? ''),
                    'body'     => $body,
                    // The admin has no per-variation weight field yet; every variation is
                    // equally likely. Add the column and publish it here to change that.
                    'weight'   => 1,
                    'enabled'  => true,
                ];
                $index++;
            }
        }
    }
} catch (Throwable $e) {
    // Nothing available yet → empty set; the app keeps its bundled/last-synced copy.
    $templates = [];
    $version   = 0;
}

json_out(['version' => $version, 'templates' => $templates]);
