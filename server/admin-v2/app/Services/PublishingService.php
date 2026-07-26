<?php
/**
 * Draft → validate → preview → publish → rollback engine.
 *
 * The "working state" is the live editable tables (offers, billboards, templates,
 * support_config, app_config, app_versions). Publishing serialises the CURRENT working
 * state into a canonical, app-safe snapshot, validates it, and writes an IMMUTABLE row
 * to configuration_releases with an incrementing version, a SHA-256 checksum and a
 * signature. A "draft change" is simply any difference between the working state and
 * the latest published snapshot — surfaced as the pending-changes list.
 *
 * Rollback never mutates an old snapshot: it copies a chosen version's contents back
 * into the working tables, and the subsequent publish creates a NEW, later version.
 */

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Signer;
use App\Core\Snapshot;
use Throwable;

final class PublishingService
{
    public const SCHEMA_VERSION = 1;

    /* -------------------------------------------------------- shell status */

    public static function status(): array
    {
        $latest = self::currentRelease();
        $pending = self::pendingChanges();
        return [
            'environment'   => Config::isProduction() ? 'Production' : 'Staging',
            'version'       => $latest['version'] ?? 0,
            'lastPublishAt' => $latest['created_at'] ?? null,
            'signed'        => $latest ? ($latest['signature'] !== null && $latest['signature'] !== '') : false,
            'draftCount'    => count($pending),
        ];
    }

    public static function currentRelease(): ?array
    {
        return Database::fetch(
            'SELECT * FROM ' . Database::table('configuration_releases') . ' ORDER BY version DESC LIMIT 1'
        );
    }

    public static function release(int $version): ?array
    {
        return Database::fetch(
            'SELECT * FROM ' . Database::table('configuration_releases') . ' WHERE version = ? LIMIT 1',
            [$version]
        );
    }

    public static function releases(int $limit = 50): array
    {
        return Database::fetchAll(
            'SELECT * FROM ' . Database::table('configuration_releases') . ' ORDER BY version DESC LIMIT ' . (int) $limit
        );
    }

    public static function currentSnapshot(): ?array
    {
        $rel = self::currentRelease();
        if (!$rel) {
            return null;
        }
        $decoded = json_decode($rel['snapshot_json'], true);
        return is_array($decoded) ? $decoded : null;
    }

    private static function nextVersion(): int
    {
        $max = Database::scalar('SELECT MAX(version) FROM ' . Database::table('configuration_releases'));
        return ((int) $max) + 1;
    }

    /* -------------------------------------------------- build working snapshot */

    /** Serialise the current working state into the app-safe snapshot structure. */
    public static function buildWorkingSnapshot(): array
    {
        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'offers'        => self::buildOffers(),
            'billboards'    => self::buildBillboards(),
            'templates'     => self::buildTemplates(),
            'support'       => self::buildSupport(),
            'appConfig'     => self::buildAppConfig(),
            'version'       => self::buildVersion(),
        ];
    }

    private static function buildOffers(): array
    {
        $rows = Database::fetchAll(
            "SELECT * FROM " . Database::table('offers') . "
              WHERE status = 'active' ORDER BY sort_hint, category, price"
        );
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'             => $r['offer_id'],
                'category'       => $r['category'],
                'name'           => $r['name'],
                'price'          => (int) $r['price'],
                'validity'       => $r['validity'],
                'band'           => $r['band'],
                'dailyRule'      => self::appDailyRule($r['daily_rule']),
                'policy'         => $r['daily_rule'],
                'maxPerDay'      => $r['max_per_day'] !== null ? (int) $r['max_per_day'] : null,
                'commercialTag'  => $r['commercial_tag'],
                'offlineEligible'=> (int) $r['offline_eligible'] === 1,
                'restrictions'   => $r['restrictions'],
            ];
        }
        return $out;
    }

    /** Map a v2 daily policy to the string the shipped v1 app understands. */
    public static function appDailyRule(string $policy): string
    {
        return $policy === 'ONCE_PER_RECIPIENT_PER_DAY' ? 'ONCE_PER_DAY' : 'MULTIPLE_PER_DAY';
    }

    private static function buildBillboards(): array
    {
        $rows = Database::fetchAll(
            "SELECT b.*, a.stored_name AS image_name
               FROM " . Database::table('billboards') . " b
               LEFT JOIN " . Database::table('billboard_assets') . " a ON a.id = b.image_asset_id
              WHERE b.status IN ('active','scheduled') ORDER BY b.priority ASC, b.id ASC"
        );
        $offersById = [];
        foreach (Database::fetchAll("SELECT offer_id, name, price, validity FROM " . Database::table('offers') . " WHERE status='active'") as $o) {
            $offersById[$o['offer_id']] = $o;
        }
        $out = [];
        foreach ($rows as $b) {
            $resolved = BillboardService::resolveContent($b, $offersById[$b['linked_offer_id']] ?? null);
            if ($resolved === null) {
                continue; // linked offer unavailable → billboard is disabled (never publish unresolved tokens)
            }
            $out[] = [
                'id'          => (int) $b['id'],
                'kind'        => $b['kind'],
                'priority'    => (int) $b['priority'],
                'linkedOfferId' => $b['linked_offer_id'],
                'tag'         => $resolved['tag'],
                'headline'    => $resolved['headline'],
                'body'        => $resolved['body'],
                'ctaLabel'    => $b['cta_label'],
                'ctaDestination' => $b['cta_destination'],
                'imageUrl'    => $b['image_name'] ? ('uploads/' . $b['image_name']) : '',
                'altText'     => $b['alt_text'],
                'audienceRule'=> $b['audience_rule'],
                'frequencyCap'=> (int) $b['frequency_cap'],
                'startsAt'    => self::iso($b['starts_at']),
                'endsAt'      => self::iso($b['ends_at']),
            ];
        }
        return $out;
    }

    private static function buildTemplates(): array
    {
        $rows = Database::fetchAll(
            "SELECT * FROM " . Database::table('message_templates') . "
              WHERE status = 'active' ORDER BY match_priority, template_key"
        );
        $delivery = [];
        $lowBalance = [];
        foreach ($rows as $r) {
            $entry = [
                'id'          => $r['template_key'],
                'senderId'    => $r['sender_id'],
                'category'    => $r['category'],
                'pattern'     => $r['pattern'],
                'description' => $r['label'],
                'purpose'     => $r['purpose'],
                'priority'    => (int) $r['match_priority'],
                'correlationWindowMinutes' => (int) $r['correlation_window_min'],
            ];
            if ($r['purpose'] === 'delivery') {
                $delivery[] = $entry;
            } else {
                $lowBalance[] = $entry;
            }
        }
        return [
            'version'    => self::nextVersion(), // increases with each publish
            'delivery'   => $delivery,
            'lowBalance' => $lowBalance,
        ];
    }

    private static function buildSupport(): array
    {
        $r = Database::fetch("SELECT * FROM " . Database::table('support_config') . " WHERE id = 1") ?: [];
        return [
            'tillNumber'      => $r['till_number'] ?? '',
            'paybillNumber'   => $r['paybill_number'] ?? '',
            'supportNumber'   => $r['support_number'] ?? '',
            'supportWhatsapp' => $r['support_whatsapp'] ?? '',
            'offlineSelfInstructions'  => $r['offline_self_instructions'] ?? '',
            'offlineOtherInstructions' => $r['offline_other_instructions'] ?? '',
            'supportBanner'   => $r['support_banner'] ?? '',
            'workingHours'    => $r['working_hours'] ?? '',
        ];
    }

    private static function buildAppConfig(): array
    {
        $r = Database::fetch("SELECT * FROM " . Database::table('app_config') . " WHERE id = 1") ?: [];
        $sync = (int) ($r['sync_interval_minutes'] ?? 360);
        $sync = max(60, min(1440, $sync));
        return [
            'maintenanceMode'       => (int) ($r['maintenance_mode'] ?? 0) === 1,
            'maintenanceMessage'    => $r['maintenance_message'] ?? '',
            'syncIntervalMinutes'   => $sync,
            'generalSupportMessage' => $r['general_support_message'] ?? '',
        ];
    }

    private static function buildVersion(): array
    {
        $r = Database::fetch(
            "SELECT * FROM " . Database::table('app_versions') . " WHERE status = 'active' ORDER BY latest_version_code DESC LIMIT 1"
        ) ?: [];
        $source = $r['update_source'] ?? 'github';
        if (!in_array($source, ['github', 'play'], true)) {
            $source = 'github';
        }
        return [
            'latestVersionCode' => (int) ($r['latest_version_code'] ?? 1),
            'latestVersionName' => $r['latest_version_name'] ?? '1.0.0',
            'minSupportedVersionCode' => (int) ($r['min_supported_version_code'] ?? 1),
            'mandatory'         => (int) ($r['mandatory'] ?? 0) === 1,
            'updateSource'      => $source,
            'playStoreUrl'      => $r['play_store_url'] ?? '',
            'apkUrl'            => $r['apk_url'] ?? '',
            'apkSha256'         => $r['apk_sha256'] ?? '',
            'rolloutPercent'    => (int) ($r['rollout_percent'] ?? 100),
            'releaseNotes'      => $r['release_notes'] ?? '',
        ];
    }

    /* ---------------------------------------------------------- validation */

    /** @return array{errors:string[], warnings:string[]} */
    public static function validate(array $snapshot): array
    {
        $errors = [];
        $warnings = [];

        // Offers: unique ids, valid prices, sane availability, offline ambiguity warning.
        $seen = [];
        $offlinePrices = [];
        foreach ($snapshot['offers'] as $o) {
            if (isset($seen[$o['id']])) {
                $errors[] = "Duplicate offer id: {$o['id']}.";
            }
            $seen[$o['id']] = true;
            if (!is_int($o['price']) || $o['price'] < 1) {
                $errors[] = "Offer {$o['id']} has an invalid price.";
            }
            if (!in_array($o['category'], ['DATA', 'SMS', 'MINUTES', 'SPECIAL'], true)) {
                $errors[] = "Offer {$o['id']} has an unknown category.";
            }
            if ($o['offlineEligible']) {
                $offlinePrices[$o['price']][] = $o['id'];
            }
        }
        foreach ($offlinePrices as $price => $ids) {
            if (count($ids) > 1) {
                $warnings[] = 'Offline reconciliation ambiguity: offers ' . implode(', ', $ids)
                    . " share price KSh {$price}. The operator must load the correct bundle manually.";
            }
        }

        // Billboards: no unresolved tokens (already stripped in builder; double-check).
        foreach ($snapshot['billboards'] as $b) {
            foreach (['headline', 'body', 'tag'] as $f) {
                if (strpos((string) $b[$f], '{{') !== false) {
                    $errors[] = "Billboard #{$b['id']} still contains unresolved tokens in {$f}.";
                }
            }
        }

        // Version rules.
        $v = $snapshot['version'];
        if ($v['minSupportedVersionCode'] > $v['latestVersionCode']) {
            $errors[] = 'Minimum supported version cannot be higher than the latest version.';
        }
        if ($v['mandatory'] && $v['playStoreUrl'] === '' && $v['apkUrl'] === '') {
            $errors[] = 'A forced update needs a valid Play Store or APK destination.';
        }
        if ($v['rolloutPercent'] < 0 || $v['rolloutPercent'] > 100) {
            $errors[] = 'Rollout percent must be between 0 and 100.';
        }

        // Support routes present.
        if (($snapshot['support']['tillNumber'] ?? '') === '' && ($snapshot['support']['paybillNumber'] ?? '') === '') {
            $warnings[] = 'No Till or Paybill number is configured — offline purchase will be disabled in the app.';
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /* ------------------------------------------------------------- publish */

    /**
     * Publish the current working state as a new immutable release.
     * @return array{ok:bool, version:?int, errors:string[], warnings:string[]}
     */
    public static function publish(string $reason = '', ?int $rolledBackFrom = null): array
    {
        $snapshot = self::buildWorkingSnapshot();
        $check = self::validate($snapshot);
        if ($check['errors'] !== []) {
            return ['ok' => false, 'version' => null, 'errors' => $check['errors'], 'warnings' => $check['warnings']];
        }

        $user = Auth::user() ?? []; // may be empty during the install-time baseline publish
        try {
            $version = Database::transaction(function () use ($snapshot, $reason, $rolledBackFrom, $user) {
                $version = self::nextVersion();
                $snapshot['configVersion'] = $version;
                $snapshot['publishedAt'] = gmdate('Y-m-d\TH:i:s\Z');
                // Keep the templates.version in step with the release version.
                $snapshot['templates']['version'] = $version;

                $canonical = Snapshot::canonical($snapshot);
                $checksum = Signer::checksum($canonical);
                $signature = Signer::sign($canonical);
                $algo = $signature !== null ? Signer::algorithm() : '';

                // Re-encode the stored snapshot canonically so what we sign == what we serve.
                $storeJson = $canonical;

                Database::run(
                    'INSERT INTO ' . Database::table('configuration_releases') . '
                        (version, schema_version, snapshot_json, checksum, signature, signature_algo,
                         min_client_version_code, published_by, published_by_id, notes, rolled_back_from, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                    [
                        $version, self::SCHEMA_VERSION, $storeJson, $checksum, $signature, $algo,
                        (int) Config::get('sync.min_client_version_code', 1),
                        ($user['name'] ?? null) ?: 'system', $user['id'] ?? null,
                        substr($reason, 0, 500), $rolledBackFrom,
                    ]
                );
                $releaseId = (int) Database::pdo()->lastInsertId();

                self::writeReleaseItems($releaseId, $version, $snapshot);

                Settings::set('last_publish_at', gmdate('Y-m-d\TH:i:s\Z'));
                Settings::set('sync_hint_version', (string) $version);

                Audit::log([
                    'action'      => $rolledBackFrom ? 'rollback.execute' : 'publish.execute',
                    'entity_type' => 'configuration_release',
                    'entity_id'   => $version,
                    'reason'      => $reason,
                    'version'     => $version,
                    'after'       => ['version' => $version, 'checksum' => $checksum, 'signed' => $signature !== null],
                ]);

                return $version;
            });
            return ['ok' => true, 'version' => $version, 'errors' => [], 'warnings' => $check['warnings']];
        } catch (Throwable $e) {
            return ['ok' => false, 'version' => null, 'errors' => ['Publish failed: ' . $e->getMessage()], 'warnings' => $check['warnings']];
        }
    }

    /** Record a per-entity change breakdown against the previous published snapshot. */
    private static function writeReleaseItems(int $releaseId, int $version, array $newSnap): void
    {
        $prevRel = Database::fetch(
            'SELECT snapshot_json FROM ' . Database::table('configuration_releases') . '
              WHERE version < ? ORDER BY version DESC LIMIT 1',
            [$version]
        );
        $prev = $prevRel ? (json_decode($prevRel['snapshot_json'], true) ?: []) : [];
        $items = self::diffSnapshots($prev, $newSnap);
        $stmt = Database::pdo()->prepare(
            'INSERT INTO ' . Database::table('configuration_release_items') . '
                (release_id, version, entity_type, entity_id, change_type, summary)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($items as $it) {
            $stmt->execute([$releaseId, $version, $it['entity_type'], $it['entity_id'], $it['change_type'], substr($it['summary'], 0, 255)]);
        }
    }

    /* ------------------------------------------------------- pending / diff */

    /** The list of changes the working state has over the latest published snapshot. */
    public static function pendingChanges(): array
    {
        $current = self::currentSnapshot() ?: [];
        $working = self::buildWorkingSnapshot();
        return self::diffSnapshots($current, $working);
    }

    /**
     * Human-readable per-entity diff between two snapshots.
     * @return array<int,array{entity_type:string, entity_id:string, change_type:string, summary:string}>
     */
    public static function diffSnapshots(array $old, array $new): array
    {
        $items = [];

        // Keyed collections: offers (by id), billboards (by id), templates (delivery+lowBalance by id).
        self::diffKeyed($items, 'offer', $old['offers'] ?? [], $new['offers'] ?? [], 'id', fn($o) => "{$o['category']} {$o['name']} — KSh {$o['price']}");

        self::diffKeyed($items, 'billboard', $old['billboards'] ?? [], $new['billboards'] ?? [], 'id', fn($b) => $b['headline'] ?: ('Billboard #' . $b['id']));

        $oldT = array_merge($old['templates']['delivery'] ?? [], $old['templates']['lowBalance'] ?? []);
        $newT = array_merge($new['templates']['delivery'] ?? [], $new['templates']['lowBalance'] ?? []);
        self::diffKeyed($items, 'template', $oldT, $newT, 'id', fn($t) => $t['description'] ?? $t['id']);

        // Singletons: support, appConfig, version.
        foreach (['support' => 'Support & payment details', 'appConfig' => 'App configuration', 'version' => 'App version rule'] as $key => $label) {
            if (json_encode($old[$key] ?? null) !== json_encode($new[$key] ?? null)) {
                $items[] = ['entity_type' => $key, 'entity_id' => $key, 'change_type' => 'changed', 'summary' => $label . ' changed'];
            }
        }
        return $items;
    }

    private static function diffKeyed(array &$items, string $type, array $old, array $new, string $idKey, callable $describe): void
    {
        $oldById = [];
        foreach ($old as $o) {
            $oldById[(string) $o[$idKey]] = $o;
        }
        $newById = [];
        foreach ($new as $n) {
            $newById[(string) $n[$idKey]] = $n;
        }
        foreach ($newById as $id => $n) {
            if (!isset($oldById[$id])) {
                $items[] = ['entity_type' => $type, 'entity_id' => $id, 'change_type' => 'added', 'summary' => 'Added: ' . $describe($n)];
            } elseif (json_encode($oldById[$id]) !== json_encode($n)) {
                $items[] = ['entity_type' => $type, 'entity_id' => $id, 'change_type' => 'changed', 'summary' => 'Changed: ' . $describe($n)];
            }
        }
        foreach ($oldById as $id => $o) {
            if (!isset($newById[$id])) {
                $items[] = ['entity_type' => $type, 'entity_id' => $id, 'change_type' => 'removed', 'summary' => 'Removed: ' . $describe($o)];
            }
        }
    }

    /* ---------------------------------------------------------- rollback */

    /**
     * Restore a previous version's contents into the working tables (creates a "draft"
     * that the operator then previews and publishes as a new version). Never mutates the
     * old snapshot.
     */
    public static function restoreWorkingFrom(int $version): bool
    {
        $rel = self::release($version);
        if (!$rel) {
            return false;
        }
        $snap = json_decode($rel['snapshot_json'], true);
        if (!is_array($snap)) {
            return false;
        }
        Database::transaction(function () use ($snap) {
            RollbackRestorer::apply($snap);
        });
        return true;
    }

    /* ------------------------------------------------------------- helpers */

    public static function iso(?string $dbDatetime): ?string
    {
        if (!$dbDatetime) {
            return null;
        }
        try {
            return (new \DateTimeImmutable($dbDatetime, new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
        } catch (Throwable $e) {
            return null;
        }
    }
}
