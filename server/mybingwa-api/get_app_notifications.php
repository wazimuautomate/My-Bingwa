<?php
/**
 * GET get_app_notifications.php — the notifications the owner published in the admin,
 * in the app's RemoteNotification shape. App-key guarded.
 *   Header: X-App-Key: <shared secret>
 *
 * Response (JSON):
 *   { notifications: [ { id, category, title, body, deepLinkRoute,
 *                        startsAt, endsAt, priority } ] }
 *   startsAt / endsAt are epoch MILLIS, or null for "no bound".
 *
 * This is CONTENT, not a send instruction. The app still applies the notification
 * permission state, quiet hours, frequency caps, recent-purchase suppression, campaign
 * caps and local/remote deduplication (CLAUDE.md §9) before anything is ever shown, and
 * it never claims a remote message arrived while the device had no internet.
 *
 * With no published snapshot (or the section absent) this returns a valid EMPTY list,
 * never an error, so the app keeps its cached notifications.
 *
 * ADMIN CONSOLE: populated by Notifications (migration 014_notifications_v2.sql →
 * `notification_campaigns`, with schedule fields `starts_on` / `ends_on` /
 * `expires_at`, `deep_link` and `priority`) and carried into the published snapshot as
 * `notifications` by PublishingService::buildNotifications().
 */

$config = require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';

require_app_key($config);

/**
 * A published date/timestamp to epoch millis, or null when absent/unparseable.
 * The admin authors dates in the seller's local day, so they are interpreted in
 * Africa/Nairobi — the same day boundary the rest of the product uses (CLAUDE.md §8).
 */
function app_notification_millis($value): ?int
{
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') {
        return null;
    }
    try {
        $dt = new DateTime($raw, new DateTimeZone('Africa/Nairobi'));
        return $dt->getTimestamp() * 1000;
    } catch (Throwable $e) {
        return null;
    }
}

/** Map the admin's priority word to the small int the app sorts by. */
function app_notification_priority($value): int
{
    switch (strtolower(trim((string) ($value ?? '')))) {
        case 'high':
        case 'urgent':
            return 2;
        case 'low':
            return 0;
        default:
            return 1;
    }
}

$notifications = [];

try {
    $pdo  = require __DIR__ . '/db.php';
    $snap = published_snapshot($pdo);

    if ($snap !== null) {
        foreach (($snap['notifications'] ?? []) as $campaign) {
            $campaignId = (string) ($campaign['id'] ?? '');
            if ($campaignId === '') {
                continue;
            }

            // One published notification per campaign, using its first wording variation
            // (the composer picks among the full variation list from
            // get_notification_templates.php; this endpoint carries the campaign itself).
            $variations = $campaign['variations'] ?? [];
            $first      = is_array($variations) && count($variations) > 0 ? $variations[0] : [];
            $body       = trim((string) ($first['body'] ?? ''));
            if ($body === '') {
                continue; // never publish an empty notification
            }

            // An explicit expiry wins over the scheduled end date, so a campaign the
            // owner expired can never keep showing.
            $endsAt = app_notification_millis($campaign['expiresAt'] ?? null);
            if ($endsAt === null) {
                $endsAt = app_notification_millis($campaign['endsOn'] ?? null);
            }

            $deepLink = trim((string) ($campaign['deepLink'] ?? ''));

            $notifications[] = [
                'id'            => 'c' . $campaignId,
                'category'      => strtoupper(trim((string) ($campaign['category'] ?? ''))) ?: 'GENERAL',
                'title'         => (string) ($first['title'] ?? ''),
                'body'          => $body,
                'deepLinkRoute' => $deepLink !== '' ? $deepLink : 'home',
                'startsAt'      => app_notification_millis($campaign['startsOn'] ?? null),
                'endsAt'        => $endsAt,
                'priority'      => app_notification_priority($campaign['priority'] ?? null),
            ];
        }
    }
} catch (Throwable $e) {
    // Nothing available yet → empty list; the app keeps its cached notifications.
    $notifications = [];
}

json_out(['notifications' => $notifications]);
