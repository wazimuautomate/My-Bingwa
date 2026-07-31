<?php
/**
 * Secure billboard media handling — still images (JPEG/PNG/WebP/GIF) and animated GIFs.
 *
 * Every upload is validated by DECODING it, never by trusting the file extension or the
 * MIME type the browser claims. A still image is re-encoded through GD, which strips any
 * embedded metadata or payload and caps it at a sane width. An ANIMATED GIF is never
 * re-encoded (that would flatten the animation): it is stored byte-for-byte and only a
 * still first-frame thumbnail is derived from it.
 *
 * Files land in /uploads under a RANDOM filename. That directory is non-executable
 * (.htaccess), so an uploaded "image" can never run as code. SVG is rejected outright —
 * it is a script container, not a picture.
 *
 * Thumbnails: each upload gets its own billboard_assets row and the billboard points at
 * it through billboards.thumb_asset_id (the canonical link that
 * PublishingService::buildBillboards() reads first). The same filename is mirrored into
 * the parent asset's thumb_name column so an asset row is self-describing.
 *
 * Thumbnailing DEGRADES GRACEFULLY: if GD, or a specific format function, is missing the
 * upload still succeeds without a thumbnail.
 */

namespace App\Services;

use App\Core\Database;

final class ImageUploader
{
    /** Still images (JPEG/PNG/WebP and non-animated GIF). */
    public const MAX_BYTES_IMAGE = 4194304;   // 4 MB
    /** Animated GIFs are stored as uploaded, so they get more room. */
    public const MAX_BYTES_GIF = 8388608;     // 8 MB
    /** Stored still images are capped at this width (aspect ratio preserved). */
    public const MAX_WIDTH = 1440;
    /** Refuse absurd canvases (decompression bombs) before decoding. */
    public const MAX_SOURCE_SIDE = 8000;
    /** Width of the generated still thumbnail. */
    public const THUMB_WIDTH = 400;

    /* ------------------------------------------------------------ pure helpers ---- */

    /** The only image types we accept, checked against the DECODED file. */
    public static function allowedMimes(): array
    {
        return ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    }

    /** Byte ceiling for a given real MIME type. */
    public static function maxBytesFor(string $mime): int
    {
        return $mime === 'image/gif' ? self::MAX_BYTES_GIF : self::MAX_BYTES_IMAGE;
    }

    /** Filename extension for a validated MIME type. */
    public static function extensionFor(string $mime): string
    {
        switch ($mime) {
            case 'image/png':  return 'png';
            case 'image/webp': return 'webp';
            case 'image/gif':  return 'gif';
            default:           return 'jpg';
        }
    }

    /**
     * Number of frames in GIF bytes, counted from the Graphic Control Extension
     * introducer that precedes each frame. A plain byte scan — no extension needed.
     */
    public static function gifFrameCount(string $bytes): int
    {
        $n = substr_count($bytes, "\x00\x21\xF9\x04");
        return $n > 0 ? $n : 1;
    }

    /** Two or more frames means the GIF animates and must never be re-encoded. */
    public static function isAnimatedGifBytes(string $bytes): bool
    {
        return substr_count($bytes, "\x00\x21\xF9\x04") >= 2;
    }

    /**
     * Scale (w,h) down to fit $maxW, preserving the aspect ratio. Never upscales.
     *
     * @return array{0:int,1:int}
     */
    public static function targetDimensions(int $w, int $h, int $maxW): array
    {
        if ($w <= 0 || $h <= 0) {
            return [0, 0];
        }
        if ($maxW <= 0 || $w <= $maxW) {
            return [$w, $h];
        }
        $nh = (int) round($h * ($maxW / $w));
        return [$maxW, max(1, $nh)];
    }

    /** Human byte size for an operator-facing message. */
    public static function humanBytes(int $bytes): string
    {
        return rtrim(rtrim(number_format($bytes / 1048576, 1, '.', ''), '0'), '.') . ' MB';
    }

    /* ------------------------------------------------------------------ upload ---- */

    /**
     * Validate, store and record one uploaded file.
     *
     * Existing callers keep working: `ok`, `assetId` and `error` mean exactly what they
     * always did (no file supplied is a success with a null assetId).
     *
     * @return array{ok:bool, assetId:?int, error:?string, thumbAssetId:?int, thumbName:?string,
     *               kind:?string, mediaType:?string, isAnimated:bool, frameCount:int}
     */
    public static function handle(array $file, string $uploadDir): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return self::result(true, null, null); // no file supplied is fine
        }
        if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
            return self::fail('The upload did not complete. Please try again.');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return self::fail('Invalid upload.');
        }
        $size = (int) (@filesize($tmp) ?: ($file['size'] ?? 0));
        if ($size <= 0) {
            return self::fail('That file is empty.');
        }
        if ($size > self::MAX_BYTES_GIF) {
            return self::fail('That file is larger than ' . self::humanBytes(self::MAX_BYTES_GIF) . '.');
        }

        // Truth #1: the file must actually decode as an image of a known type.
        $info = @getimagesize($tmp);
        if (!is_array($info) || empty($info['mime'])) {
            return self::fail('That file is not a picture. Use PNG, JPEG, WebP or GIF.');
        }
        $mime = (string) $info['mime'];
        // Truth #2: an independent content sniff must not contradict it (blocks polyglot
        // files such as an "image" that is really HTML or a script). A host whose libmagic
        // simply does not know the format answers octet-stream; that is not a contradiction,
        // and getimagesize() has already decoded the file.
        $sniffed = self::sniffMime($tmp);
        if ($sniffed !== null && $sniffed !== $mime && $sniffed !== 'application/octet-stream') {
            return self::fail('That file is not a picture. Use PNG, JPEG, WebP or GIF.');
        }
        if (!in_array($mime, self::allowedMimes(), true)) {
            return self::fail('Only PNG, JPEG, WebP and GIF are allowed (SVG is not).');
        }
        if ($size > self::maxBytesFor($mime)) {
            return self::fail('That file is larger than ' . self::humanBytes(self::maxBytesFor($mime)) . '.');
        }
        $w = (int) ($info[0] ?? 0);
        $h = (int) ($info[1] ?? 0);
        if ($w < 1 || $h < 1 || $w > self::MAX_SOURCE_SIDE || $h > self::MAX_SOURCE_SIDE) {
            return self::fail('That picture is an unusual size. Keep each side under ' . self::MAX_SOURCE_SIDE . ' pixels.');
        }

        $raw = @file_get_contents($tmp);
        if ($raw === false || $raw === '') {
            return self::fail('That file could not be read.');
        }

        $isGif       = ($mime === 'image/gif');
        $frameCount  = $isGif ? self::gifFrameCount($raw) : 1;
        $isAnimated  = $isGif && self::isAnimatedGifBytes($raw);

        $dir = rtrim($uploadDir, "/\\");
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return self::fail('The uploads folder could not be created.');
        }
        $base = bin2hex(random_bytes(16));

        if ($isAnimated) {
            // Store the animation untouched — re-encoding through GD would keep only
            // the first frame and silently destroy the advert.
            $stored = $base . '.gif';
            if (!@copy($tmp, $dir . '/' . $stored)) {
                return self::fail('Could not save the uploaded picture.');
            }
            $storedMime = 'image/gif';
            $storedW = $w;
            $storedH = $h;
            $kind = 'gif';
        } elseif (self::gdReady()) {
            // Re-encode: strips metadata/appended payloads and caps the width.
            [$nw, $nh] = self::targetDimensions($w, $h, self::MAX_WIDTH);
            $src = @imagecreatefromstring($raw);
            if ($src === false) {
                return self::fail('That picture could not be opened.');
            }
            $dst = self::resample($src, $nw, $nh);
            imagedestroy($src);
            if ($dst === null) {
                return self::fail('That picture could not be processed.');
            }
            [$ext, $storedMime, $writer] = self::encoder();
            $stored = $base . '.' . $ext;
            $written = (bool) $writer($dst, $dir . '/' . $stored);
            imagedestroy($dst);
            if (!$written) {
                return self::fail('Could not save the processed picture.');
            }
            $storedW = $nw;
            $storedH = $nh;
            $kind = 'image';
        } else {
            // No GD on this host: keep the validated original rather than refusing the
            // upload. It is still a decoded, size-checked image with a random name in a
            // non-executable folder.
            $stored = $base . '.' . self::extensionFor($mime);
            if (!@copy($tmp, $dir . '/' . $stored)) {
                return self::fail('Could not save the uploaded picture.');
            }
            $storedMime = $mime;
            $storedW = $w;
            $storedH = $h;
            $kind = 'image';
        }

        // A missing thumbnail must never block an upload.
        $thumb = self::makeThumbnail($raw, $dir, $base);

        $assetsTable = Database::table('billboard_assets');
        $original = self::safeOriginalName((string) ($file['name'] ?? ''));
        $thumbAssetId = null;
        if ($thumb !== null) {
            Database::run(
                'INSERT INTO ' . $assetsTable . '
                    (stored_name, original_name, mime, width, height, bytes, kind, is_animated, frame_count, thumb_name, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1, ?, UTC_TIMESTAMP())',
                [$thumb['name'], $original, $thumb['mime'], $thumb['width'], $thumb['height'], $thumb['bytes'], 'image', '']
            );
            $thumbAssetId = (int) Database::pdo()->lastInsertId();
        }
        Database::run(
            'INSERT INTO ' . $assetsTable . '
                (stored_name, original_name, mime, width, height, bytes, kind, is_animated, frame_count, thumb_name, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [
                $stored, $original, $storedMime, $storedW, $storedH,
                (int) (@filesize($dir . '/' . $stored) ?: $size),
                $kind, $isAnimated ? 1 : 0, $frameCount, (string) ($thumb['name'] ?? ''),
            ]
        );

        return [
            'ok'           => true,
            'assetId'      => (int) Database::pdo()->lastInsertId(),
            'error'        => null,
            'thumbAssetId' => $thumbAssetId,
            'thumbName'    => $thumb['name'] ?? null,
            'kind'         => $kind,
            'mediaType'    => $isAnimated ? 'gif' : 'image',
            'isAnimated'   => $isAnimated,
            'frameCount'   => $frameCount,
        ];
    }

    /* ----------------------------------------------------------------- internals -- */

    /**
     * Still thumbnail for any upload. For an animated GIF imagecreatefromstring()
     * returns the FIRST frame, which is exactly what we want. Returns null (silently)
     * whenever GD or a writer function is unavailable.
     *
     * @return array{name:string,mime:string,width:int,height:int,bytes:int}|null
     */
    private static function makeThumbnail(string $raw, string $dir, string $base): ?array
    {
        if (!self::gdReady()) {
            return null;
        }
        try {
            $src = @imagecreatefromstring($raw);
            if ($src === false) {
                return null;
            }
            [$tw, $th] = self::targetDimensions(imagesx($src), imagesy($src), self::THUMB_WIDTH);
            $dst = self::resample($src, $tw, $th);
            imagedestroy($src);
            if ($dst === null) {
                return null;
            }
            if (function_exists('imagepng')) {
                $name = $base . '_thumb.png';
                $mime = 'image/png';
                $written = @imagepng($dst, $dir . '/' . $name, 6);
            } elseif (function_exists('imagejpeg')) {
                $name = $base . '_thumb.jpg';
                $mime = 'image/jpeg';
                $written = @imagejpeg($dst, $dir . '/' . $name, 82);
            } elseif (function_exists('imagewebp')) {
                $name = $base . '_thumb.webp';
                $mime = 'image/webp';
                $written = @imagewebp($dst, $dir . '/' . $name, 82);
            } else {
                imagedestroy($dst);
                return null;
            }
            imagedestroy($dst);
            if (!$written) {
                return null;
            }
            return [
                'name'   => $name,
                'mime'   => $mime,
                'width'  => $tw,
                'height' => $th,
                'bytes'  => (int) (@filesize($dir . '/' . $name) ?: 0),
            ];
        } catch (\Throwable $e) {
            return null; // a thumbnail is a nicety, never a blocker
        }
    }

    /** Resize onto a fresh truecolor canvas, keeping transparency. */
    private static function resample($src, int $nw, int $nh)
    {
        if ($nw < 1 || $nh < 1 || !function_exists('imagecreatetruecolor') || !function_exists('imagecopyresampled')) {
            return null;
        }
        $dst = @imagecreatetruecolor($nw, $nh);
        if ($dst === false) {
            return null;
        }
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, imagesx($src), imagesy($src));
        return $dst;
    }

    /** Is enough of GD present to decode and resize? */
    private static function gdReady(): bool
    {
        return function_exists('imagecreatefromstring')
            && function_exists('imagecreatetruecolor')
            && function_exists('imagecopyresampled');
    }

    /** Independent content sniff. Returns null when finfo is unavailable. */
    private static function sniffMime(string $path): ?string
    {
        if (!class_exists('finfo')) {
            return null;
        }
        try {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = @$finfo->file($path);
            return is_string($mime) && $mime !== '' ? strtolower($mime) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Keep a harmless record of what the operator uploaded. Never used as a path. */
    private static function safeOriginalName(string $name): string
    {
        $name = str_replace(["\\", '/'], ' ', $name);
        $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name) ?? '';
        return substr(trim($name), 0, 160);
    }

    /** Prefer WebP; fall back to JPEG when the GD build lacks WebP. */
    private static function encoder(): array
    {
        if (function_exists('imagewebp')) {
            return ['webp', 'image/webp', fn($img, $p) => @imagewebp($img, $p, 82)];
        }
        if (function_exists('imagepng')) {
            return ['png', 'image/png', fn($img, $p) => @imagepng($img, $p, 6)];
        }
        return ['jpg', 'image/jpeg', function ($img, $p) {
            $bg = imagecreatetruecolor(imagesx($img), imagesy($img));
            imagefilledrectangle($bg, 0, 0, imagesx($img), imagesy($img), imagecolorallocate($bg, 255, 255, 255));
            imagecopy($bg, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
            $ok = @imagejpeg($bg, $p, 85);
            imagedestroy($bg);
            return $ok;
        }];
    }

    /** @return array{ok:bool, assetId:?int, error:?string, ...} */
    private static function fail(string $message): array
    {
        return self::result(false, null, $message);
    }

    private static function result(bool $ok, ?int $assetId, ?string $error): array
    {
        return [
            'ok' => $ok, 'assetId' => $assetId, 'error' => $error,
            'thumbAssetId' => null, 'thumbName' => null,
            'kind' => null, 'mediaType' => null, 'isAnimated' => false, 'frameCount' => 0,
        ];
    }
}
