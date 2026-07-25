<?php
/**
 * Restores a published snapshot's contents into the working tables so a rollback
 * becomes a normal draft that is then re-published as a NEW version. The old snapshot
 * is never touched. Fully restores the app-shipped singletons, offers and message
 * templates; billboards are restored best-effort by id (image assets are not part of a
 * published snapshot — see the migration/cutover guide).
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
        self::restoreTemplates(array_merge($snap['templates']['delivery'] ?? [], $snap['templates']['lowBalance'] ?? []), $actor);
        self::restoreSupport($snap['support'] ?? [], $actor);
        self::restoreAppConfig($snap['appConfig'] ?? [], $actor);
        self::restoreVersion($snap['version'] ?? [], $actor);
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

    private static function restoreTemplates(array $templates, string $actor): void
    {
        $t = Database::table('message_templates');
        $keep = [];
        foreach ($templates as $tpl) {
            $keep[] = $tpl['id'];
            Database::run(
                "INSERT INTO {$t}
                    (template_key, label, sender_id, purpose, category, pattern_type, pattern,
                     match_priority, correlation_window_min, status, row_version, created_at, updated_at, updated_by)
                 VALUES (?, ?, ?, ?, ?, 'regex', ?, ?, ?, 'active', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), ?)
                 ON DUPLICATE KEY UPDATE label=VALUES(label), sender_id=VALUES(sender_id), purpose=VALUES(purpose),
                    category=VALUES(category), pattern=VALUES(pattern), match_priority=VALUES(match_priority),
                    correlation_window_min=VALUES(correlation_window_min), status='active',
                    row_version = row_version + 1, updated_at=UTC_TIMESTAMP(), updated_by=VALUES(updated_by)",
                [
                    $tpl['id'], $tpl['description'] ?? $tpl['id'], $tpl['senderId'] ?? '', $tpl['purpose'] ?? 'delivery',
                    $tpl['category'] ?? 'DATA', $tpl['pattern'] ?? '', (int) ($tpl['priority'] ?? 5),
                    (int) ($tpl['correlationWindowMinutes'] ?? 30), $actor,
                ]
            );
        }
        if ($keep !== []) {
            $in = implode(',', array_fill(0, count($keep), '?'));
            Database::run(
                "UPDATE {$t} SET status='archived', updated_at=UTC_TIMESTAMP(), updated_by=? WHERE status='active' AND template_key NOT IN ({$in})",
                array_merge([$actor], $keep)
            );
        }
    }

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
