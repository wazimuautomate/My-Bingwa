<?php
/**
 * Read-only public sync API. ONE endpoint the Android app polls in the background:
 *
 *   GET /api/app-data
 *
 * It returns the latest PUBLISHED, app-safe snapshot — offers, billboard adverts,
 * message templates, support details, app configuration, update information and the
 * published version. Never admin users, drafts, audit logs or any secret.
 *
 * The app reads "configVersion" to decide whether anything changed; an ETag on the
 * snapshot checksum lets a device get a cheap 304 Not Modified when nothing has.
 */

namespace App\Controllers\Api;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\PublishingService;
use App\Services\RateLimiter;
use Throwable;

final class SyncController
{
    /** Per-IP rate limit + an optional shared X-Sync-Key. */
    private function gate(Request $request): void
    {
        $perMinute = (int) Config::get('sync.rate_limit_per_minute', 60);
        if (!RateLimiter::allow('sync:' . $request->ip(), $perMinute)) {
            Response::json(['error' => 'rate_limited'], 429, ['Retry-After' => '60']);
        }
        $required = (string) Config::get('security.sync_api_key', '');
        if ($required !== '') {
            $sent = $request->header('X-Sync-Key');
            if (!hash_equals($required, (string) $sent)) {
                Response::json(['error' => 'unauthorised'], 401);
            }
        }
    }

    public function appData(Request $request): void
    {
        $this->gate($request);
        $rel = PublishingService::currentRelease();
        if (!$rel) {
            Response::json(['error' => 'no_published_configuration'], 503);
        }
        $etag = '"' . $rel['checksum'] . '"';
        if (trim((string) $request->header('If-None-Match')) === $etag) {
            Response::json([], 304, ['ETag' => $etag, 'Cache-Control' => 'no-cache']);
        }
        // snapshot_json is already canonical and app-safe: it contains configVersion,
        // publishedAt, offers, billboards, templates, support, appConfig and version
        // (the update info). Serve it verbatim so empty maps stay {} and the bytes match
        // the checksum.
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        header('ETag: ' . $etag);
        header('Cache-Control: no-cache');
        header('X-Config-Version: ' . (int) $rel['version']);
        echo $rel['snapshot_json'];
        exit;
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
            'time' => gmdate('Y-m-d\TH:i:s\Z'),
        ]);
    }
}
