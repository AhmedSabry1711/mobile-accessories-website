<?php
/**
 * Mobi Boost — uploaded image derivatives.
 *
 * A phone photo uploaded from the admin can be 4000–6000 px and several MB.
 * Serving that file to a 170 px product card is why the shop feels heavy and
 * why some older devices fail to decode it at all.
 *
 * This helper writes small, static files next to the upload:
 *
 *   /uploads/abc123.webp          -> /uploads/_opt/abc123-400.webp
 *                                    /uploads/_opt/abc123-800.webp
 *                                    /uploads/_opt/abc123-1600.webp
 *                                  + the same three as .jpg for browsers
 *                                    without WebP support.
 *
 * Nothing in the database changes: products keep the original URL, and the
 * storefront asks for the derivative that matches the screen (see
 * src/components/ResponsiveImage.jsx). If GD/WebP is unavailable, or the file
 * is too large to decode safely, zero files are written and the original is
 * still used — so an upload can never break because of this optimisation.
 */
declare(strict_types=1);

/** Widths requested by the storefront's srcset. */
const MB_IMG_WIDTHS = [400, 800, 1600];

const MB_IMG_WEBP_QUALITY = 78;
const MB_IMG_JPEG_QUALITY = 82;

/** Refuse to decode absurd images (memory safety on shared hosting). */
const MB_IMG_MAX_PIXELS = 36000000;

/** True when this PHP build can encode both WebP and JPEG. */
function mb_img_can_process(): bool {
    return function_exists('imagecreatefromstring')
        && function_exists('imagecreatetruecolor')
        && function_exists('imagecopyresampled')
        && function_exists('imagewebp')
        && function_exists('imagejpeg');
}

/** JPG/PNG/WebP only — animated GIFs stay untouched (they lose their frames). */
function mb_img_is_raster(string $path): bool {
    return preg_match('/\.(?:jpe?g|png|webp)$/i', $path) === 1;
}

function mb_img_optimized_dir(string $sourcePath): string {
    return dirname($sourcePath) . DIRECTORY_SEPARATOR . '_opt';
}

function mb_img_variant_basename(string $sourcePath, int $width, string $ext): string {
    return pathinfo($sourcePath, PATHINFO_FILENAME) . '-' . $width . '.' . $ext;
}

/** Animated WebP must stay as-is: GD would only keep its first frame. */
function mb_img_is_animated_webp(string $bytes): bool {
    return substr($bytes, 0, 4) === 'RIFF'
        && substr($bytes, 8, 4) === 'WEBP'
        && (strpos($bytes, 'ANIM') !== false || strpos($bytes, 'ANMF') !== false);
}

/** Rotate phone photos that carry an EXIF orientation tag. */
function mb_img_orient($image, string $path, array $info) {
    if (($info[2] ?? null) !== IMAGETYPE_JPEG || !function_exists('exif_read_data')) {
        return $image;
    }
    $exif = @exif_read_data($path);
    if (!is_array($exif)) {
        return $image;
    }
    $angle = [3 => 180, 6 => -90, 8 => 90][(int)($exif['Orientation'] ?? 1)] ?? 0;
    if ($angle === 0) {
        return $image;
    }
    $turned = @imagerotate($image, $angle, 0);
    if ($turned === false) {
        return $image;
    }
    imagedestroy($image);
    return $turned;
}

/**
 * Write the WebP + JPEG derivatives for one uploaded photo.
 *
 * @return int Number of files written (0 means "keep using the original").
 */
function mb_img_generate_variants(string $sourcePath): int {
    if (!mb_img_can_process() || !is_file($sourcePath) || !mb_img_is_raster($sourcePath)) {
        return 0;
    }

    $bytes = @file_get_contents($sourcePath);
    if ($bytes === false || $bytes === '') {
        return 0;
    }
    if (mb_img_is_animated_webp($bytes)) {
        return 0;
    }
    $info = @getimagesizefromstring($bytes);
    if (!is_array($info) || (int)($info[0] ?? 0) < 1 || (int)($info[1] ?? 0) < 1) {
        return 0;
    }
    if ((int)$info[0] * (int)$info[1] > MB_IMG_MAX_PIXELS) {
        return 0;
    }

    $source = @imagecreatefromstring($bytes);
    if ($source === false) {
        return 0;
    }
    $source = mb_img_orient($source, $sourcePath, $info);
    $srcW = imagesx($source);
    $srcH = imagesy($source);

    $dir = mb_img_optimized_dir($sourcePath);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        imagedestroy($source);
        return 0;
    }

    $written = 0;
    foreach (MB_IMG_WIDTHS as $width) {
        // Never upscale: a 500 px upload simply gets a 500 px "1600" candidate.
        $targetW = max(1, min($width, $srcW));
        $targetH = max(1, (int)round($srcH * ($targetW / $srcW)));

        $frame = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($frame, false);
        imagesavealpha($frame, true);
        $clear = imagecolorallocatealpha($frame, 0, 0, 0, 127);
        imagefilledrectangle($frame, 0, 0, $targetW, $targetH, $clear);
        imagecopyresampled($frame, $source, 0, 0, 0, 0, $targetW, $targetH, $srcW, $srcH);

        // Modern browsers: small WebP with transparency preserved.
        $webp = $dir . DIRECTORY_SEPARATOR . mb_img_variant_basename($sourcePath, $width, 'webp');
        if (@imagewebp($frame, $webp, MB_IMG_WEBP_QUALITY)) {
            $written++;
        }

        // Older browsers: JPEG flattened on white (transparent PNGs included).
        $flat = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($flat, true);
        imagesavealpha($flat, false);
        $white = imagecolorallocate($flat, 255, 255, 255);
        imagefilledrectangle($flat, 0, 0, $targetW, $targetH, $white);
        imagecopy($flat, $frame, 0, 0, 0, 0, $targetW, $targetH);
        $jpg = $dir . DIRECTORY_SEPARATOR . mb_img_variant_basename($sourcePath, $width, 'jpg');
        if (@imagejpeg($flat, $jpg, MB_IMG_JPEG_QUALITY)) {
            $written++;
        }

        imagedestroy($flat);
        imagedestroy($frame);
    }

    imagedestroy($source);
    return $written;
}
