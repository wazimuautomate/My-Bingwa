<?php
/**
 * Signs published configuration snapshots so the Android app can verify authenticity
 * with an embedded PUBLIC key — the private key never leaves the server, and no shared
 * secret is placed in the APK.
 *
 * Algorithm: RSA (or EC) private key, SHA-256, signature base64-encoded. The signed
 * bytes are the CANONICAL JSON of the snapshot (see Snapshot::canonical). A SHA-256
 * checksum of the same bytes is published alongside for a cheap integrity pre-check.
 *
 * If no signing key is configured, publishing still works (checksum only) but the
 * manifest is marked unsigned; the deploy guide explains how to generate the keypair.
 */

namespace App\Core;

use Throwable;

final class Signer
{
    public static function isConfigured(): bool
    {
        $path = (string) Config::get('signing.private_key_path', '');
        return $path !== '' && is_file($path);
    }

    public static function algorithm(): string
    {
        return (string) Config::get('signing.algorithm', 'RS256');
    }

    public static function checksum(string $canonicalJson): string
    {
        return hash('sha256', $canonicalJson);
    }

    /** Returns base64 signature, or null if signing is not configured / fails. */
    public static function sign(string $canonicalJson): ?string
    {
        if (!self::isConfigured()) {
            return null;
        }
        try {
            $pem = file_get_contents((string) Config::get('signing.private_key_path'));
            $passphrase = (string) Config::get('signing.private_key_passphrase', '');
            $key = openssl_pkey_get_private($pem, $passphrase ?: null);
            if ($key === false) {
                return null;
            }
            $signature = '';
            $ok = openssl_sign($canonicalJson, $signature, $key, OPENSSL_ALGO_SHA256);
            return $ok ? base64_encode($signature) : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** Verify a signature with the configured public key (used by tests/health check). */
    public static function verify(string $canonicalJson, string $base64Signature): bool
    {
        $pubPath = (string) Config::get('signing.public_key_path', '');
        if ($pubPath === '' || !is_file($pubPath)) {
            return false;
        }
        try {
            $pub = openssl_pkey_get_public(file_get_contents($pubPath));
            if ($pub === false) {
                return false;
            }
            $sig = base64_decode($base64Signature, true);
            return $sig !== false
                && openssl_verify($canonicalJson, $sig, $pub, OPENSSL_ALGO_SHA256) === 1;
        } catch (Throwable $e) {
            return false;
        }
    }
}
