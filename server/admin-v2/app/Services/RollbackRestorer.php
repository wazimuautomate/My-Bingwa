<?php
/**
 * Restores a published snapshot's contents into the working tables so a rollback
 * becomes a normal draft that is then re-published as a NEW version. The old snapshot
 * is never touched — nothing here writes to configuration_releases.
 *
 * Every section is restored only when the snapshot actually carries it. A release
 * published before a section existed (an older schema) leaves those working tables
 * completely alone rather than wiping them, so rolling back to an old version can never
 * silently delete notifications, categories or feature flags that the old snapshot
 * simply had no way to describe.
 *
 * Billboard image files are not part of a published snapshot: assets are matched back by
 * their stored filename where they still exist on disk, otherwise the media reference is
 * left as it is. See the migration/cutover guide.
 */

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;

final class RollbackRestorer
{
    public static function apply(array $snap): void
    {
        $actor = Auth::user()['name'] ?? 'system';

        self::restoreOffers($snap['offers'] ?? [], $actor);
        self::restoreSupport($snap['support'] ?? [], $actor);
        self::restoreAppConfig($snap['appConfig'] ?? [], $actor);
        self::restoreVersion($snap['version'] ?? [], $actor);

        // Sections introduced after the first releases. Absent key => untouched table.
        if (array_key_exists('categories', $snap) && is_array($snap['categories'])) {
            self::restoreCategories($snap['categories']);
        }
        if (array_key_exists('featureFlags', $snap) && is_array($snap['featureFlags'])) {
            self::restoreFeatureFlags($snap['featureFlags']);
        }
        if (array_key_exists('notifications', $snap) && is_array($snap['notifications'])) {
            self::restoreNotifications($snap['notifications'], $actor);
        }
        if (array_key_exists('billboards', $snap) && is_array($snap['billboards'])) {
            self::restoreBillboards($snap['billboards'], $actor);
        }
    }

    private static function restoreOffers(array $offers, string $actor): void
    {
        $t = Database::table('offers');
        $keep = [];
        foreach ($offers as $o) {
            $keep[] = $o['id'];
            Database::run(
                "INSERT INTO {$t}
                    (offer_id, category, name, price, validity, band, daily_rule, max_per_day,
                     commercial_tag, offline_eligible, restrictions, status, row_version, created_at, updated_at, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), ?)
                 ON DUPLICATE KEY UPDATE category=VALUES(category), name=VALUES(name), price=VALUES(price),
                    validity=VALUES(validity), band=VALUES(band), daily_rule=VALUES(daily_rule),
                    max_per_day=VALUES(max_per_day), commercial_tag=VALUES(commercial_tag),
                    offline_eligible=VALUES(offline_eligible), restrictions=VALUES(restrictions),
                    status='active', row_version = row_version + 1, updated_at=UTC_TIMESTAMP(), updated_by=VALUES(updated_by)",
                [
                    $o['id'], $o['category'], $o['name'], (int) $o['price'], $o['validity'], $o['band'],
                    $o['policy'] ?? 'MULTIPLE_PER_DAY', $o['maxPerDay'] ?? null,
                    $o['commercialTag'] ?? '', !empty($o['offlineEligible']) ? 1 : 0, $o['restrictions'] ?? '', $actor,
                ]
            );
        }
        // Archive active offers that did not exist in the restored version.
        if ($keep !== []) {
            $in = implode(',', array_fill(0, count($keep), '?'));
            Database::run(
                "UPDATE {$t} SET status='archived', updated_at=UTC_TIMESTAMP(), updated_by=? WHERE status='active' AND offer_id NOT IN ({$in})",
                array_merge([$actor], $keep)
            );
        }
    }

    /* --------------------------------------------------------------- categories */

    private static function restoreCategories(array $categories): void
    {
        $t = Database::table('offer_categories');
        $keep = [];
        foreach ($categories as $c) {
            if (!is_array($c) || (string) ($c['id'] ?? '') === '') {
                continue;
            }
            $keep[] = (string) $c['id'];
            Database::run(
                "INSERT INTO {$t} (category_key, label, description, accent, sort_order, enabled, is_system, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, 1, 0, UTC_TIMESTAMP(), UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE label=VALUES(label), description=VALUES(description),
                    accent=VALUES(accent), sort_order=VALUES(sort_order), enabled=1, updated_at=UTC_TIMESTAMP()",
                [
                    (string) $c['id'], (string) ($c['label'] ?? $c['id']), (string) ($c['description'] ?? ''),
                    (string) ($c['accent'] ?? ''), (int) ($c['sortOrder'] ?? 100),
                ]
            );
        }
        if ($keep !== []) {
            $in = implode(',', array_fill(0, count($keep), '?'));
            Database::run(
                "UPDATE {$t} SET enabled = 0, updated_at = UTC_TIMESTAMP() WHERE enabled = 1 AND category_key NOT IN ({$in})",
                $keep
            );
        }
    }

    /* ------------------------------------------------------------ feature flags */

    /** flagKey => bool. Flags absent from the snapshot keep their current value. */
    private static function restoreFeatureFlags(array $flags): void
    {
        $t = Database::table('feature_flags');
        foreach ($flags as $key => $enabled) {
            $key = (string) $key;
            if ($key === '') {
                continue;
            }
            Database::run(
                "INSERT INTO {$t} (flag_key, label, description, enabled, is_system, sort_order, created_at, updated_at)
                 VALUES (?, ?, '', ?, 0, 100, UTC_TIMESTAMP(), UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), updated_at = UTC_TIMESTAMP()",
                [$key, $key, !empty($enabled) ? 1 : 0]
            );
        }
    }

    /* ------------------------------------------------------------ notifications */

    /**
     * Campaigns are identified by their numeric id, which is what the snapshot carries.
     * Variations are replaced wholesale for a restored campaign (they have no stable id
     * of their own in the snapshot), and campaigns missing from the snapshot are disabled
     * rather than deleted.
     */
    private static function restoreNotifications(array $notifications, string $actor): void
    {
        $t = Database::table('notification_campaigns');
        $v = Database::table('notification_variations');
        $keep = [];

        foreach ($notifications as $n) {
            if (!is_array($n) || (int) ($n['id'] ?? 0) <= 0) {
                continue;
            }
            $id = (int) $n['id'];
            $keep[] = $id;
            $variations = is_array($n['variations'] ?? null) ? $n['variations'] : [];
            $first = is_array($variations[0] ?? null) ? $variations[0] : ['title' => '', 'body' => ''];
            $days = is_array($n['daysOfWeek'] ?? null) ? implode(',', array_map('intval', $n['daysOfWeek'])) : '';

            Database::run(
                "INSERT INTO {$t}
                    (id, name, title, body, deep_link, linked_offer_id, category, trigger_type, trigger_event,
                     priority, starts_on, ends_on, days_of_week, allowed_time_start, allowed_time_end,
                     cooldown_minutes, frequency_cap, respect_quiet_hours, suppress_recent_purchase,
                     expires_at, enabled, status, row_version, created_at, updated_at, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'active', 1,
                         UTC_TIMESTAMP(), UTC_TIMESTAMP(), ?)
                 ON DUPLICATE KEY UPDATE name=VALUES(name), title=VALUES(title), body=VALUES(body),
                    deep_link=VALUES(deep_link), linked_offer_id=VALUES(linked_offer_id),
                    category=VALUES(category), trigger_type=VALUES(trigger_type), trigger_event=VALUES(trigger_event),
                    priority=VALUES(priority), starts_on=VALUES(starts_on), ends_on=VALUES(ends_on),
                    days_of_week=VALUES(days_of_week), allowed_time_start=VALUES(allowed_time_start),
                    allowed_time_end=VALUES(allowed_time_end), cooldown_minutes=VALUES(cooldown_minutes),
                    frequency_cap=VALUES(frequency_cap), respect_quiet_hours=VALUES(respect_quiet_hours),
                    suppress_recent_purchase=VALUES(suppress_recent_purchase), expires_at=VALUES(expires_at),
                    enabled=1, status='active', row_version = row_version + 1,
                    updated_at=UTC_TIMESTAMP(), updated_by=VALUES(updated_by)",
                [
                    $id,
                    (string) ($n['name'] ?? ('Notification #' . $id)),
                    (string) ($first['title'] ?? ''),
                    (string) ($first['body'] ?? ''),
                    (string) ($n['deepLink'] ?? ''),
                    ($n['linkedOfferId'] ?? '') !== '' ? $n['linkedOfferId'] : null,
                    (string) ($n['category'] ?? ''),
                    (string) ($n['trigger'] ?? 'manual'),
                    (string) ($n['triggerEvent'] ?? ''),
                    (string) ($n['priority'] ?? 'normal'),
                    $n['startsOn'] ?? null,
                    $n['endsOn'] ?? null,
                    $days,
                    (string) ($n['timeStart'] ?? ''),
                    (string) ($n['timeEnd'] ?? ''),
                    (int) ($n['cooldownMinutes'] ?? 0),
                    (int) ($n['frequencyCap'] ?? 1),
                    !empty($n['respectQuietHours']) ? 1 : 0,
                    !empty($n['suppressRecentPurchase']) ? 1 : 0,
                    self::dbDatetime($n['expiresAt'] ?? null),
                    $actor,
                ]
            );

            Database::run("DELETE FROM {$v} WHERE campaign_id = ?", [$id]);
            $order = 0;
            foreach ($variations as $variation) {
                if (!is_array($variation)) {
                    continue;
                }
                Database::run(
                    "INSERT INTO {$v} (campaign_id, title, body, sort_order, enabled, created_at, updated_at)
                     VALUES (?, ?, ?, ?, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
                    [$id, (string) ($variation['title'] ?? ''), (string) ($variation['body'] ?? ''), $order++]
                );
            }
        }

        if ($keep !== []) {
            $in = implode(',', array_fill(0, count($keep), '?'));
            Database::run(
                "UPDATE {$t} SET enabled = 0, updated_at = UTC_TIMESTAMP(), updated_by = ?
                  WHERE enabled = 1 AND status = 'active' AND id NOT IN ({$in})",
                array_merge([$actor], $keep)
            );
        }
    }

    /* -------------------------------------------------------------- billboards */

    /**
     * Restore the published content, media descriptors and tap target of billboards that
     * still exist. Billboards are never re-created here (their internal name and uploaded
     * asset are not part of a snapshot); ones absent from the snapshot are switched off so
     * the app sees exactly the carousel that version served.
     */
    private static function restoreBillboards(array $billboards, string $actor): void
    {
        $t = Database::table('billboards');
        $keep = [];
        foreach ($billboards as $b) {
            if (!is_array($b) || (int) ($b['id'] ?? 0) <= 0) {
                continue;
            }
            $id = (int) $b['id'];
            $keep[] = $id;
            Database::run(
                "UPDATE {$t} SET
                    priority = ?, display_order = ?, linked_offer_id = ?, tag = ?, headline = ?, body = ?,
                    cta_label = ?, cta_destination = ?, media_type = ?, alt_text = ?,
                    target_action = ?, click_url = ?, internal_action = ?, target_category = ?,
                    audience_rule = ?, frequency_cap = ?, image_asset_id = COALESCE(?, image_asset_id),
                    thumb_asset_id = COALESCE(?, thumb_asset_id),
                    enabled = 1, status = 'active',
                    row_version = row_version + 1, updated_at = UTC_TIMESTAMP(), updated_by = ?
                  WHERE id = ?",
                [
                    (int) ($b['priority'] ?? 5),
                    (int) ($b['displayOrder'] ?? 0),
                    ($b['linkedOfferId'] ?? '') !== '' ? $b['linkedOfferId'] : null,
                    (string) ($b['tag'] ?? ''),
                    (string) ($b['headline'] ?? ''),
                    (string) ($b['body'] ?? ''),
                    (string) ($b['ctaLabel'] ?? 'Buy now'),
                    (string) ($b['ctaDestination'] ?? ''),
                    (string) ($b['mediaType'] ?? 'none'),
                    (string) ($b['altText'] ?? ''),
                    (string) ($b['targetAction'] ?? 'none'),
                    (string) ($b['clickUrl'] ?? ''),
                    (string) ($b['internalAction'] ?? ''),
                    (string) ($b['targetCategory'] ?? ''),
                    (string) ($b['audienceRule'] ?? 'all'),
                    (int) ($b['frequencyCap'] ?? 0),
                    self::assetIdForUrl($b['imageUrl'] ?? ''),
                    self::assetIdForUrl($b['thumbUrl'] ?? ''),
                    $actor,
                    $id,
                ]
            );
        }
        if ($keep !== []) {
            $in = implode(',', array_fill(0, count($keep), '?'));
            Database::run(
                "UPDATE {$t} SET enabled = 0, updated_at = UTC_TIMESTAMP(), updated_by = ?
                  WHERE enabled = 1 AND status IN ('active','scheduled') AND id NOT IN ({$in})",
                array_merge([$actor], $keep)
            );
        }
    }

    /** 'uploads/abc.webp' => the asset row id, or null when the file is unknown. */
    private static function assetIdForUrl($url): ?int
    {
        $name = basename((string) $url);
        if ($name === '' || $name === '.') {
            return null;
        }
        $row = Database::fetch(
            'SELECT id FROM ' . Database::table('billboard_assets') . ' WHERE stored_name = ? LIMIT 1',
            [$name]
        );
        return $row ? (int) $row['id'] : null;
    }

    /** ISO-8601 Z back to the DATETIME string MySQL stores, or null. */
    private static function dbDatetime($iso): ?string
    {
        $iso = trim((string) $iso);
        if ($iso === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($iso, new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /* ---------------------------------------------------------------- singletons */

    private static function restoreSupport(array $s, string $actor): void
    {
        if ($s === []) {
            return;
        }
        Database::run(
            'UPDATE ' . Database::table('support_config') . ' SET
                till_number=?, paybill_number=?, support_number=?, support_whatsapp=?,
                offline_self_instructions=?, offline_other_instructions=?, support_banner=?, working_hours=?,
                row_version = row_version + 1, updated_at=UTC_TIMESTAMP(), updated_by=? WHERE id=1',
            [
                $s['tillNumber'] ?? '', $s['paybillNumber'] ?? '', $s['supportNumber'] ?? '', $s['supportWhatsapp'] ?? '',
                $s['offlineSelfInstructions'] ?? '', $s['offlineOtherInstructions'] ?? '', $s['supportBanner'] ?? '', $s['workingHours'] ?? '', $actor,
            ]
        );
    }

    private static function restoreAppConfig(array $c, string $actor): void
    {
        if ($c === []) {
            return;
        }
        Database::run(
            'UPDATE ' . Database::table('app_config') . ' SET
                maintenance_mode=?, maintenance_message=?, sync_interval_minutes=?, general_support_message=?,
                row_version = row_version + 1, updated_at=UTC_TIMESTAMP(), updated_by=? WHERE id=1',
            [
                !empty($c['maintenanceMode']) ? 1 : 0, $c['maintenanceMessage'] ?? '',
                (int) ($c['syncIntervalMinutes'] ?? 360), $c['generalSupportMessage'] ?? '', $actor,
            ]
        );
    }

    private static function restoreVersion(array $v, string $actor): void
    {
        if ($v === []) {
            return;
        }
        // Deactivate all, then upsert the restored rule as the active one.
        Database::run("UPDATE " . Database::table('app_versions') . " SET status='inactive'");
        $existing = Database::fetch(
            'SELECT id FROM ' . Database::table('app_versions') . ' WHERE latest_version_code = ? LIMIT 1',
            [(int) ($v['latestVersionCode'] ?? 1)]
        );
        if ($existing) {
            Database::run(
                'UPDATE ' . Database::table('app_versions') . ' SET
                    latest_version_name=?, min_supported_version_code=?, mandatory=?, play_store_url=?, apk_url=?,
                    apk_sha256=?, rollout_percent=?, release_notes=?, status=\'active\',
                    row_version = row_version + 1, updated_at=UTC_TIMESTAMP(), updated_by=? WHERE id=?',
                [
                    $v['latestVersionName'] ?? '1.0.0', (int) ($v['minSupportedVersionCode'] ?? 1), !empty($v['mandatory']) ? 1 : 0,
                    $v['playStoreUrl'] ?? '', $v['apkUrl'] ?? '', $v['apkSha256'] ?? '', (int) ($v['rolloutPercent'] ?? 100),
                    $v['releaseNotes'] ?? '', $actor, $existing['id'],
                ]
            );
        } else {
            Database::run(
                'INSERT INTO ' . Database::table('app_versions') . '
                    (latest_version_code, latest_version_name, min_supported_version_code, mandatory,
                     play_store_url, apk_url, apk_sha256, rollout_percent, release_notes, status,
                     row_version, created_at, updated_at, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'active\', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), ?)',
                [
                    (int) ($v['latestVersionCode'] ?? 1), $v['latestVersionName'] ?? '1.0.0', (int) ($v['minSupportedVersionCode'] ?? 1),
                    !empty($v['mandatory']) ? 1 : 0, $v['playStoreUrl'] ?? '', $v['apkUrl'] ?? '', $v['apkSha256'] ?? '',
                    (int) ($v['rolloutPercent'] ?? 100), $v['releaseNotes'] ?? '', $actor,
                ]
            );
        }
    }
}
