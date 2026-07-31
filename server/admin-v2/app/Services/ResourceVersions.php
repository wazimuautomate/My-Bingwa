<?php
/**
 * Per-resource versioning for incremental synchronisation.
 *
 * The global config version identifies a release. On top of that, every synchronisable
 * section of the snapshot carries its own version, which moves ONLY when that section's
 * published bytes actually change. A device that already holds offers v18 and asks for a
 * manifest sees offers still at v18 and downloads nothing.
 *
 * Versions are derived, never hand-maintained: at publish time each section is canonically
 * encoded, hashed, and compared with the hash recorded in the previous release. Unchanged
 * hash → keep the old version number. Changed hash → the section takes the NEW config
 * version. That makes the numbers reproducible from the release history alone.
 */

namespace App\Services;

use App\Core\Database;
use App\Core\Signer;
use App\Core\Snapshot;

final class ResourceVersions
{
    /**
     * Snapshot section key => [admin label, is the section a list?]. Adding a future
     * resource is one line here plus a builder in PublishingService — the sync protocol
     * itself never changes.
     */
    public const RESOURCES = [
        'offers'        => ['Offers', true],
        'categories'    => ['Categories', true],
        'billboards'    => ['Billboards', true],
        'notifications' => ['Notifications', true],
        'smsRules'      => ['SMS rules', true],
        'templates'     => ['Message templates (legacy)', false],
        'support'       => ['Payment & support details', false],
        'appConfig'     => ['App configuration', false],
        'featureFlags'  => ['Feature flags', false],
        'version'       => ['App update rule', false],
    ];

    public static function label(string $key): string
    {
        return self::RESOURCES[$key][0] ?? $key;
    }

    /** @return string[] */
    public static function keys(): array
    {
        return array_keys(self::RESOURCES);
    }

    public static function isList(string $key): bool
    {
        return (bool) (self::RESOURCES[$key][1] ?? false);
    }

    /** Number of items an administrator would recognise in a section. */
    public static function countOf(string $key, $section): int
    {
        if (self::isList($key)) {
            return is_array($section) ? count($section) : 0;
        }
        return $section === null ? 0 : 1;
    }

    /**
     * Make a section's shape identical whichever side it came from, so the checksum
     * describes the CONTENT and nothing else.
     *
     * Two things would otherwise move a version without the content changing:
     *
     *  1. `templates.version` is set to the new config version on every publish, so hashing
     *     it would make the legacy templates resource look changed every single time.
     *  2. An empty map is `new stdClass()` in the working snapshot (so it publishes as the
     *     `{}` a client expects) but comes back from json_decode(..., true) as `[]`. Hashing
     *     those two gives different digests, so the resource would bump on every publish and
     *     every device would re-download SMS rules for ever. Fold objects to arrays here;
     *     the PUBLISHED bytes are untouched and still contain `{}`.
     */
    private static function normalise(string $key, $section)
    {
        if ($key === 'templates' && is_array($section)) {
            unset($section['version']);
        }
        return self::foldObjects($section);
    }

    /** Recursively turn stdClass into an array so shape can never affect a digest. */
    private static function foldObjects($value)
    {
        if ($value instanceof \stdClass) {
            $value = (array) $value;
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = self::foldObjects($v);
            }
            return $out;
        }
        return $value;
    }

    /** resource => sha256 of the canonical bytes of that snapshot section. */
    public static function checksums(array $snapshot): array
    {
        $out = [];
        foreach (self::keys() as $key) {
            if (!array_key_exists($key, $snapshot)) {
                continue;
            }
            // Wrap so a bare list and a bare map both hash through the same canonical path.
            $section = self::normalise($key, $snapshot[$key]);
            $out[$key] = Signer::checksum(Snapshot::canonical(['v' => $section]));
        }
        return $out;
    }

    /**
     * Version map for a snapshot about to be published as $newVersion.
     *
     * @param array $previous previous release's map: key => ['version'=>int,'checksum'=>string,...]
     * @return array<string, array{version:int, checksum:string, count:int, changed:bool}>
     */
    public static function compute(array $snapshot, array $previous, int $newVersion): array
    {
        $out = [];
        foreach (self::checksums($snapshot) as $key => $checksum) {
            $prev = $previous[$key] ?? null;
            $unchanged = is_array($prev) && (string) ($prev['checksum'] ?? '') === $checksum;
            $out[$key] = [
                'version'  => $unchanged ? (int) $prev['version'] : $newVersion,
                'checksum' => $checksum,
                'count'    => self::countOf($key, $snapshot[$key]),
                'changed'  => !$unchanged,
            ];
        }
        return $out;
    }

    /** Mirror the published map into mb_resource_versions for fast manifest reads. */
    public static function persist(array $map): void
    {
        $t = Database::table('resource_versions');
        $stmt = Database::pdo()->prepare(
            "INSERT INTO {$t} (resource_key, version, checksum, item_count, updated_at)
             VALUES (?, ?, ?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE version = VALUES(version), checksum = VALUES(checksum),
                                     item_count = VALUES(item_count), updated_at = VALUES(updated_at)"
        );
        foreach ($map as $key => $row) {
            $stmt->execute([$key, (int) $row['version'], (string) $row['checksum'], (int) $row['count']]);
        }
    }

    /** The stored map, keyed by resource. Empty before the first publish. */
    public static function current(): array
    {
        $out = [];
        foreach (Database::fetchAll('SELECT * FROM ' . Database::table('resource_versions')) as $r) {
            $out[$r['resource_key']] = [
                'version'   => (int) $r['version'],
                'checksum'  => (string) $r['checksum'],
                'count'     => (int) $r['item_count'],
                'updatedAt' => $r['updated_at'],
            ];
        }
        return $out;
    }

    /**
     * The map recorded ON a release row — authoritative for what a device was served.
     * Releases published before resource versioning existed return an empty map, which
     * simply means "treat every resource as changed".
     */
    public static function forRelease(?array $release): array
    {
        if (!$release || (string) ($release['resource_versions_json'] ?? '') === '') {
            return [];
        }
        $decoded = json_decode((string) $release['resource_versions_json'], true);
        return is_array($decoded) ? $decoded : [];
    }
}
