<?php
/**
 * Canonical JSON encoding for snapshots. Signing and checksums must be computed over
 * BYTES that both the server and the Android client can reproduce identically, so keys
 * are sorted recursively and encoding flags are fixed. Any drift here would break
 * signature verification on the device.
 */

namespace App\Core;

final class Snapshot
{
    /** Recursively sort associative array keys so output is deterministic. */
    public static function canonicalize($value)
    {
        if (is_array($value)) {
            $isList = array_keys($value) === range(0, count($value) - 1);
            if ($isList) {
                return array_map([self::class, 'canonicalize'], $value);
            }
            ksort($value);
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = self::canonicalize($v);
            }
            return $out;
        }
        return $value;
    }

    /** Deterministic JSON string used for checksum + signature. */
    public static function canonical(array $data): string
    {
        return json_encode(
            self::canonicalize($data),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }
}
