<?php
/**
 * Fixed-window per-key rate limiter backed by the mb_rate_limits table (no APCu needed
 * on shared hosting). Best-effort: if the table is briefly unavailable it fails OPEN so
 * a monitoring hiccup never blocks legitimate app syncs.
 */

namespace App\Services;

use App\Core\Database;
use Throwable;

final class RateLimiter
{
    /** @return bool true if allowed, false if the limit is exceeded this minute. */
    public static function allow(string $key, int $perMinute): bool
    {
        if ($perMinute <= 0) {
            return true;
        }
        $minute = (int) floor(time() / 60);
        $rlKey = substr($key . ':' . $minute, 0, 80);
        try {
            $t = Database::table('rate_limits');
            Database::run(
                "INSERT INTO {$t} (rl_key, hits, window_start) VALUES (?, 1, ?)
                 ON DUPLICATE KEY UPDATE hits = hits + 1",
                [$rlKey, $minute]
            );
            $hits = (int) (Database::scalar("SELECT hits FROM {$t} WHERE rl_key = ?", [$rlKey]) ?? 0);
            // Opportunistic cleanup of old windows (1% of requests).
            if (random_int(1, 100) === 1) {
                Database::run("DELETE FROM {$t} WHERE window_start < ?", [$minute - 5]);
            }
            return $hits <= $perMinute;
        } catch (Throwable $e) {
            return true; // fail open
        }
    }
}
