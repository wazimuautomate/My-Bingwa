<?php
/**
 * Secure billboard image handling. Validates the real decoded image (not just the
 * extension/MIME the browser claims), re-encodes it through GD to strip any embedded
 * payload, resizes to a sane max width, writes it under /uploads with a RANDOM
 * filename, and records metadata. The uploads directory is non-executable (.htaccess),
 * so an uploaded "image" can never run as code.
 */

namespace App\Services;

use App\Core\Database;

final class ImageUploader
{
    private const MAX_BYTES = 3145728; // 3 MB
    private const MAX_WIDTH = 1080;
    private const ALLOWED = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /** @return array{ok:bool, assetId:?int, error:?string} */
    public static function handle(array $file, string $uploadDir): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true, 'assetId' => null, 'error' => null]; // no file supplied is fine
        }
        if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'assetId' => null, 'error' => 'Upload failed.'];
        }
        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            return ['ok' => false, 'assetId' => null, 'error' => 'Image is larger than 3 MB.'];
        }
        $tmp = $file['tmp_name'] ?? '';
        if (!is_uploaded_file($tmp)) {
            return ['ok' => false, 'assetId' => null, 'error' => 'Invalid upload.'];
        }
        $info = @getimagesize($tmp);
        if ($info === false || !in_array($info['mime'] ?? '', self::ALLOWED, true)) {
            return ['ok' => false, 'assetId' => null, 'error' => 'File is not a supported image (JPEG, PNG, WebP, GIF).'];
        }
        if (!function_exists('imagecreatefromstring')) {
            return ['ok' => false, 'assetId' => null, 'error' => 'Image processing (GD) is not available on this server.'];
        }
        $raw = file_get_contents($tmp);
        $src = @imagecreatefromstring($raw);
        if ($src === false) {
            return ['ok' => false, 'assetId' => null, 'error' => 'The image could not be decoded.'];
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $scale = $w > self::MAX_WIDTH ? self::MAX_WIDTH / $w : 1.0;
        $nw = (int) round($w * $scale);
        $nh = (int) round($h * $scale);
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);

        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }
        $stored = bin2hex(random_bytes(16));
        [$ext, $mime, $writer] = self::encoder();
        $stored .= '.' . $ext;
        $path = rtrim($uploadDir, '/\\') . '/' . $stored;
        $ok = $writer($dst, $path);
        imagedestroy($dst);
        if (!$ok) {
            return ['ok' => false, 'assetId' => null, 'error' => 'Could not save the processed image.'];
        }

        Database::run(
            'INSERT INTO ' . Database::table('billboard_assets') . '
                (stored_name, original_name, mime, width, height, bytes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [$stored, substr((string) ($file['name'] ?? ''), 0, 160), $mime, $nw, $nh, filesize($path) ?: 0]
        );
        return ['ok' => true, 'assetId' => (int) Database::pdo()->lastInsertId(), 'error' => null];
    }

    /** Prefer WebP; fall back to JPEG when the GD build lacks WebP. */
    private static function encoder(): array
    {
        if (function_exists('imagewebp')) {
            return ['webp', 'image/webp', fn($img, $p) => imagewebp($img, $p, 82)];
        }
        return ['jpg', 'image/jpeg', function ($img, $p) {
            $bg = imagecreatetruecolor(imagesx($img), imagesy($img));
            imagefilledrectangle($bg, 0, 0, imagesx($img), imagesy($img), imagecolorallocate($bg, 255, 255, 255));
            imagecopy($bg, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
            $ok = imagejpeg($bg, $p, 85);
            imagedestroy($bg);
            return $ok;
        }];
    }
}
