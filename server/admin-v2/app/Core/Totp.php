<?php
/**
 * RFC 6238 TOTP (time-based one-time password) and RFC 4648 base32 — implemented
 * in-house so 2FA needs no external package. Compatible with Google Authenticator,
 * Authy, Microsoft Authenticator, etc. (SHA1, 6 digits, 30s step).
 */

namespace App\Core;

final class Totp
{
    private const DIGITS = 6;
    private const PERIOD = 30;

    /** Generate a new random base32 secret (160-bit). */
    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    /** otpauth:// URI to render as a QR code / show for manual entry. */
    public static function provisioningUri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::PERIOD;
    }

    /** Verify a submitted 6-digit code, allowing +/- $window steps for clock drift. */
    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== self::DIGITS) {
            return false;
        }
        $counter = (int) floor(time() / self::PERIOD);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::hotp($secret, $counter + $i), $code)) {
                return true;
            }
        }
        return false;
    }

    private static function hotp(string $secret, int $counter): string
    {
        $key = self::base32Decode($secret);
        if ($key === '') {
            return '';
        }
        $binCounter = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $binCounter, $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $part = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );
        return str_pad((string) ($part % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    public static function base32Encode(string $data): string
    {
        if ($data === '') {
            return '';
        }
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($binary, 5) as $chunk) {
            $out .= $alphabet[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }
        return $out;
    }

    public static function base32Decode(string $b32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
        if ($b32 === '') {
            return '';
        }
        $binary = '';
        foreach (str_split($b32) as $char) {
            $binary .= str_pad(decbin(strpos($alphabet, $char)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($binary, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }

    /** Generate one-time recovery codes (returned plaintext once, stored hashed). */
    public static function recoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = sprintf('%04s-%04s', bin2hex(random_bytes(2)), bin2hex(random_bytes(2)));
        }
        return $codes;
    }
}
