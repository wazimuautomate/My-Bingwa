<?php
/**
 * Versioned, read-only public sync API. Serves ONLY published, app-safe snapshot data —
 * never admin users, roles, drafts, audit logs, or any secret. Immutable published
 * snapshots carry a SHA-256 checksum and (when a signing key is configured) an RSA
 * signature the app verifies with an embedded public key. Conditional requests return
 * 304 Not Modified so devices do not re-download an unchanged config.
 *
 * The legacy get_offers.php / get_config.php endpoints stay separate and keep working;
 * this API is the forward path (see docs/APP_SYNC_CONTRACT.md).
 */

namespace App\Controllers\Api;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\PublishingService;
use App\Services\RateLimiter;
use App\Services\Settings;
use Throwable;

final class SyncController
{
    /* ------------------------------------------------------------- guards */

    private function gate(Request $request): void
    {
        $perMinute = (int) Config::get('sync.rate_limit_per_minute', 60);
        if (!RateLimiter::allow('sync:' . $request->ip(), $perMinute)) {
            Response::json(['error' => 'rate_limited'], 429, ['Retry-After' => '60']);
        }
        $required = (string) Config::get('security.sync_api_key', '');
        if ($required !== '') {
            $sent = $request->header('X-Sync-Key');
            if (!hash_equals($required, $sent)) {
                Response::json(['error' => 'unauthorised'], 401);
            }
        }
    }

    private function absoluteUrl(string $path): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https' ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        return $scheme . '://' . $host . Response::url($path);
    }

    private function currentOr503(): array
    {
        $rel = PublishingService::currentRelease();
        if (!$rel) {
            Response::json(['error' => 'no_published_configuration'], 503);
        }
        return $rel;
    }

    private function buildManifest(array $rel): array
    {
        return [
            'schemaVersion'        => (int) $rel['schema_version'],
            'configVersion'        => (int) $rel['version'],
            'publishedAt'          => PublishingService::iso($rel['created_at']),
            'snapshotUrl'          => $this->absoluteUrl('/api/v1/app/snapshot/' . (int) $rel['version']),
            'checksum'             => $rel['checksum'],
            'checksumAlgorithm'    => 'SHA-256',
            'signature'            => $rel['signature'] ?: null,
            'signatureAlgorithm'   => $rel['signature'] ? $rel['signature_algo'] : null,
            'minClientVersionCode' => (int) $rel['min_client_version_code'],
            'validUntil'           => null, // immutable snapshot; served until a newer version publishes
        ];
    }

    /* ------------------------------------------------------------- endpoints */

    public function manifest(Request $request): void
    {
        $this->gate($request);
        $rel = $this->currentOr503();
        $etag = '"' . $rel['checksum'] . '"';
        if (trim($request->header('If-None-Match')) === $etag) {
            $this->record($request, 'not_modified', (int) $rel['version']);
            Response::json([], 304, ['ETag' => $etag, 'Cache-Control' => 'no-cache']);
        }
        $this->record($request, 'manifest', (int) $rel['version']);
        Response::json($this->buildManifest($rel), 200, [
            'ETag' => $etag,
            'Cache-Control' => 'public, max-age=60',
        ]);
    }

    public function snapshot(Request $request, string $version): void
    {
        $this->gate($request);
        $rel = PublishingService::release((int) $version);
        if (!$rel) {
            Response::json(['error' => 'version_not_found'], 404);
        }
        $etag = '"' . $rel['checksum'] . '"';
        if (trim($request->header('If-None-Match')) === $etag) {
            Response::json([], 304, ['ETag' => $etag]);
        }
        $this->record($request, 'snapshot', (int) $version);
        // The stored snapshot_json is already canonical (== what was signed). Serve verbatim.
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        header('ETag: ' . $etag);
        header('Cache-Control: public, max-age=31536000, immutable');
        header('X-Checksum-SHA256: ' . $rel['checksum']);
        if ($rel['signature']) {
            header('X-Signature: ' . $rel['signature']);
            header('X-Signature-Algorithm: ' . $rel['signature_algo']);
        }
        echo $rel['snapshot_json'];
        exit;
    }

    /** Combined conditional endpoint: manifest + snapshot in one round trip, or 304. */
    public function sync(Request $request): void
    {
        $this->gate($request);
        $rel = $this->currentOr503();
        $etag = '"' . $rel['checksum'] . '"';
        if (trim($request->header('If-None-Match')) === $etag) {
            $this->record($request, 'not_modified', (int) $rel['version']);
            Response::json([], 304, ['ETag' => $etag]);
        }
        $this->record($request, 'manifest', (int) $rel['version']);
        // Emit the snapshot as the VERBATIM stored canonical bytes (what was signed), not
        // a re-encoded array — otherwise the co-delivered signature would not verify and
        // empty maps like {} would flip to []. The manifest is small and safe to encode.
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        header('ETag: ' . $etag);
        header('Cache-Control: no-cache');
        echo '{"manifest":'
            . json_encode($this->buildManifest($rel), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . ',"snapshot":' . $rel['snapshot_json'] . '}';
        exit;
    }

    /* ------------- backward-compatible shapes (for the future app client) ------------- */

    public function offers(Request $request): void
    {
        $this->gate($request);
        $snap = PublishingService::currentSnapshot() ?: ['offers' => []];
        $offers = array_map(fn($o) => [
            'id' => $o['id'], 'category' => $o['category'], 'name' => $o['name'],
            'price' => (int) $o['price'], 'validity' => $o['validity'], 'band' => $o['band'],
            'dailyRule' => $o['dailyRule'],
        ], $snap['offers'] ?? []);
        Response::json(['offers' => $offers]);
    }

    public function config(Request $request): void
    {
        $this->gate($request);
        $snap = PublishingService::currentSnapshot();
        $s = $snap['support'] ?? [];
        Response::json([
            'tillNumber'      => $s['tillNumber'] ?? '',
            'paybillNumber'   => $s['paybillNumber'] ?? '',
            'supportNumber'   => $s['supportNumber'] ?? '',
            'supportWhatsapp' => $s['supportWhatsapp'] ?? '',
            'updatedAt'       => $snap['publishedAt'] ?? null,
        ]);
    }

    public function templates(Request $request): void
    {
        $this->gate($request);
        $snap = PublishingService::currentSnapshot();
        $t = $snap['templates'] ?? ['version' => 1, 'delivery' => [], 'lowBalance' => []];
        $strip = fn($x) => [
            'id' => $x['id'], 'senderId' => $x['senderId'], 'category' => $x['category'],
            'pattern' => $x['pattern'], 'description' => $x['description'],
        ];
        Response::json([
            'version'    => $t['version'],
            'delivery'   => array_map($strip, $t['delivery'] ?? []),
            'lowBalance' => array_map($strip, $t['lowBalance'] ?? []),
        ]);
    }

    /* ------------- anonymous telemetry (opt-in) ------------- */

    public function events(Request $request): void
    {
        $this->gate($request);
        $body = $request->jsonBody();
        $install = substr(preg_replace('/[^A-Za-z0-9\-]/', '', (string) ($body['installId'] ?? '')), 0, 64);
        $versionCode = isset($body['versionCode']) ? (int) $body['versionCode'] : null;
        $configVersion = isset($body['configVersion']) ? (int) $body['configVersion'] : null;
        $eventType = in_array(($body['event'] ?? ''), ['manifest', 'snapshot', 'not_modified', 'error'], true)
            ? $body['event'] : 'manifest';
        try {
            Database::run(
                'INSERT INTO ' . Database::table('sync_events') . '
                    (install_id, version_code, config_version, event_type, result_code, created_at)
                 VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                [$install, $versionCode, $configVersion, $eventType, substr((string) ($body['resultCode'] ?? ''), 0, 16)]
            );
            if ($install !== '') {
                Database::run(
                    'INSERT INTO ' . Database::table('anonymous_app_versions') . '
                        (install_id, version_code, config_version, last_seen_at)
                     VALUES (?, ?, ?, UTC_TIMESTAMP())
                     ON DUPLICATE KEY UPDATE version_code = VALUES(version_code),
                        config_version = VALUES(config_version), last_seen_at = UTC_TIMESTAMP()',
                    [$install, $versionCode, $configVersion]
                );
            }
        } catch (Throwable $e) {
            // Telemetry is best-effort.
        }
        Response::json(['ok' => true]);
    }

    public function health(Request $request): void
    {
        $dbOk = true;
        try {
            Database::scalar('SELECT 1');
        } catch (Throwable $e) {
            $dbOk = false;
        }
        $rel = PublishingService::currentRelease();
        Response::json([
            'ok' => $dbOk,
            'configVersion' => (int) ($rel['version'] ?? 0),
            'signed' => (bool) ($rel['signature'] ?? false),
            'time' => gmdate('Y-m-d\TH:i:s\Z'),
        ]);
    }

    private function record(Request $request, string $type, int $version): void
    {
        try {
            Database::run(
                'INSERT INTO ' . Database::table('sync_events') . '
                    (install_id, version_code, config_version, event_type, result_code, created_at)
                 VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                [
                    substr(preg_replace('/[^A-Za-z0-9\-]/', '', $request->header('X-Install-Id')), 0, 64),
                    null, $version, $type, '',
                ]
            );
        } catch (Throwable $e) {
            // never fail a sync response because telemetry insert failed
        }
    }
}
