<?php
/**
 * Mobi Boost — favicon / app-icon factory.
 *
 * Renders the brand icon (rounded tile with a navy -> electric-blue diagonal
 * gradient + soft sheen, carrying a white "M" monogram) and writes the whole
 * browser/OS icon stack into public/ so `npm run build` copies it to dist/.
 *
 * No GD/Imagick needed: shapes are rasterised by hand with supersampling, which
 * keeps the output anti-aliased at every size.
 *
 * Usage (from the project root):
 *   php tools/make-icons.php                 # write public/ (premium variant)
 *   php tools/make-icons.php public navy     # pick another variant
 *   php tools/make-icons.php %TEMP% premium preview
 *
 * Variants: premium (default) | frame | blue | navy
 * `preview` also writes preview-variants.png (all variants on light + dark,
 * plus a magnified 16 px row) next to the output directory.
 *
 * Output: favicon.svg, favicon.ico (16/32/48), apple-touch-icon.png (180),
 *         icon-192.png, icon-512.png.
 */

const NAVY  = [0x00, 0x0B, 0x29];
const BLUE  = [0x00, 0x3B, 0xEC];
const WHITE = [0xFF, 0xFF, 0xFF];

/**
 * The mark is a bold geometric "M" monogram: two vertical stems plus the two
 * diagonals that meet at the middle of the baseline. It is modelled in its own
 * 0..1 box as the union of four thick segments, then clipped vertically so the
 * cap and the baseline come out perfectly flat.
 *   M_X0..M_X1 / M_Y0..M_Y1 = the glyph's tight bounding box in that space
 */
const M_X0  = 0.08228;
const M_X1  = 0.91772;
const M_Y0  = 0.14;
const M_Y1  = 0.86;
const M_HW  = 0.075;   // half the stroke weight
const M_SEGS = [
    [0.16500, 0.10, 0.16500, 0.90],   // left stem
    [0.83500, 0.10, 0.83500, 0.90],   // right stem
    [0.14639, 0.10, 0.51861, 0.90],   // left diagonal  -> passes through (0.50, 0.86)
    [0.85361, 0.10, 0.48139, 0.90],   // right diagonal -> passes through (0.50, 0.86)
];

function dist_to_segment(float $px, float $py, float $x1, float $y1, float $x2, float $y2): float {
    $dx = $x2 - $x1; $dy = $y2 - $y1;
    $len2 = $dx * $dx + $dy * $dy;
    $t = $len2 > 0 ? (($px - $x1) * $dx + ($py - $y1) * $dy) / $len2 : 0.0;
    $t = max(0.0, min(1.0, $t));
    return hypot($px - ($x1 + $t * $dx), $py - ($y1 + $t * $dy));
}

function point_in_m(float $x, float $y): bool {
    if ($y < M_Y0 || $y > M_Y1) return false;
    foreach (M_SEGS as [$x1, $y1, $x2, $y2]) {
        if (dist_to_segment($x, $y, $x1, $y1, $x2, $y2) <= M_HW) return true;
    }
    return false;
}

function in_rounded(float $x, float $y, float $x0, float $y0, float $x1, float $y1, float $r): bool {
    if ($x < $x0 || $x > $x1 || $y < $y0 || $y > $y1) return false;
    $cx = $x < $x0 + $r ? $x0 + $r : ($x > $x1 - $r ? $x1 - $r : $x);
    $cy = $y < $y0 + $r ? $y0 + $r : ($y > $y1 - $r ? $y1 - $r : $y);
    if ($cx === $x && $cy === $y) return true;
    return (($x - $cx) ** 2 + ($y - $cy) ** 2) <= $r * $r;
}

function lerp_colour(array $a, array $b, float $t): array {
    return [
        (int)round($a[0] + ($b[0] - $a[0]) * $t),
        (int)round($a[1] + ($b[1] - $a[1]) * $t),
        (int)round($a[2] + ($b[2] - $a[2]) * $t),
    ];
}

/** Diagonal navy -> electric-blue tile colour at a point. */
function tile_colour(float $x, float $y): array {
    $t = max(0.0, min(1.0, ($x + $y) / 2));
    return $t < 0.5
        ? lerp_colour(NAVY, [0x00, 0x16, 0x6B], $t * 2)
        : lerp_colour([0x00, 0x16, 0x6B], BLUE, ($t - 0.5) * 2);
}

/** Soft glassy highlight near the top of the tile (0..1 opacity). */
function sheen(float $x, float $y): float {
    $d2 = ($x - 0.5) ** 2 + ($y + 0.22) ** 2;
    return 0.22 * exp(-$d2 / 0.28);
}

/** Where the M sits inside the tile (same aspect ratio, centred). */
function mark_box(string $variant): array {
    $h = $variant === 'frame' ? 0.46 : 0.52;
    $w = $h * ((M_X1 - M_X0) / (M_Y1 - M_Y0));
    $y0 = (1 - $h) / 2;
    $x0 = (1 - $w) / 2;
    return [$x0, $y0, $x0 + $w, $y0 + $h];
}

function mark_hit(float $x, float $y, string $variant): bool {
    [$tx0, $ty0, $tx1, $ty1] = mark_box($variant);
    if ($x < $tx0 || $x > $tx1 || $y < $ty0 || $y > $ty1) return false;
    $mx = M_X0 + ($x - $tx0) / ($tx1 - $tx0) * (M_X1 - M_X0);
    $my = M_Y0 + ($y - $ty0) / ($ty1 - $ty0) * (M_Y1 - M_Y0);
    return point_in_m($mx, $my);
}

/**
 * Render one icon variant.
 * $bleed: true => the tile fills the whole square (maskable / apple-touch-icon).
 */
function render_icon(string $variant, int $size, bool $bleed): array {
    $ss = $size <= 64 ? 8 : ($size <= 256 ? 4 : 2);
    $buf = array_fill(0, $size, array_fill(0, $size, [0, 0, 0, 0]));
    $inv = 1.0 / ($ss * $ss);

    for ($py = 0; $py < $size; $py++) {
        for ($px = 0; $px < $size; $px++) {
            $ar = $ag = $ab = $aa = 0.0;
            for ($sy = 0; $sy < $ss; $sy++) {
                for ($sx = 0; $sx < $ss; $sx++) {
                    $x = ($px + ($sx + 0.5) / $ss) / $size;
                    $y = ($py + ($sy + 0.5) / $ss) / $size;

                    $onTile = $bleed || in_rounded($x, $y, 0, 0, 1, 1, 0.225);
                    if (!$onTile) continue;

                    if ($variant === 'premium') {
                        $c = tile_colour($x, $y);
                        $s = sheen($x, $y);
                        if ($s > 0.001) $c = lerp_colour($c, [255, 255, 255], $s);
                        if (mark_hit($x, $y, $variant)) $c = [255, 255, 255];
                    } elseif ($variant === 'frame') {
                        $c = NAVY;
                        if (in_rounded($x, $y, 0.105, 0.105, 0.895, 0.895, 0.135)) {
                            $c = mark_hit($x, $y, $variant) ? BLUE : WHITE;
                        }
                    } elseif ($variant === 'blue') {
                        $c = mark_hit($x, $y, $variant) ? [255, 255, 255] : BLUE;
                    } else { // navy
                        $c = mark_hit($x, $y, $variant) ? BLUE : NAVY;
                    }

                    $ar += $c[0]; $ag += $c[1]; $ab += $c[2]; $aa += 1.0;
                }
            }
            if ($aa <= 0) continue;
            $buf[$py][$px] = [
                (int)round($ar / $aa),
                (int)round($ag / $aa),
                (int)round($ab / $aa),
                (int)round($aa * $inv * 255),
            ];
        }
    }
    return [$size, $size, $buf];
}

/**
 * Vector source for a variant (64x64 viewBox).
 *
 * The M is drawn exactly like the rasteriser builds it: four stroked lines with
 * butt caps, clipped to the cap/baseline band, so both stay in sync.
 */
function svg_icon(string $variant): string {
    [$tx0, $ty0, $tx1, $ty1] = mark_box($variant);
    $mapX = fn(float $mx): float => ($tx0 + ($mx - M_X0) / (M_X1 - M_X0) * ($tx1 - $tx0)) * 64;
    $mapY = fn(float $my): float => ($ty0 + ($my - M_Y0) / (M_Y1 - M_Y0) * ($ty1 - $ty0)) * 64;
    $strokeW = ($tx1 - $tx0) * 64 / (M_X1 - M_X0) * 2 * M_HW;

    $segments = '';
    foreach (M_SEGS as [$x1, $y1, $x2, $y2]) {
        $segments .= sprintf(
            "    <line x1=\"%.2f\" y1=\"%.2f\" x2=\"%.2f\" y2=\"%.2f\"/>\n",
            $mapX($x1), $mapY($y1), $mapX($x2), $mapY($y2)
        );
    }
    $clipTop = sprintf('%.2f', $mapY(M_Y0));
    $clipH   = sprintf('%.2f', $mapY(M_Y1) - $mapY(M_Y0));

    $tileFill = 'url(#mb-tile)';
    $inner = '';
    $mark = '#FFFFFF';
    if ($variant === 'frame') {
        $tileFill = '#000B29';
        $inner = '  <rect x="6.7" y="6.7" width="50.6" height="50.6" rx="8.6" fill="#FFFFFF"/>' . "\n";
        $mark = '#003BEC';
    } elseif ($variant === 'blue') {
        $tileFill = '#003BEC';
    } elseif ($variant === 'navy') {
        $tileFill = '#000B29';
        $mark = '#003BEC';
    }

    $gradients = $variant === 'premium'
        ? "    <linearGradient id=\"mb-tile\" x1=\"0\" y1=\"0\" x2=\"1\" y2=\"1\">\n"
            . "      <stop offset=\"0\" stop-color=\"#000B29\"/>\n"
            . "      <stop offset=\"0.5\" stop-color=\"#00166B\"/>\n"
            . "      <stop offset=\"1\" stop-color=\"#003BEC\"/>\n"
            . "    </linearGradient>\n"
            . "    <radialGradient id=\"mb-sheen\" cx=\"0.5\" cy=\"-0.22\" r=\"0.72\">\n"
            . "      <stop offset=\"0\" stop-color=\"#FFFFFF\" stop-opacity=\"0.22\"/>\n"
            . "      <stop offset=\"1\" stop-color=\"#FFFFFF\" stop-opacity=\"0\"/>\n"
            . "    </radialGradient>\n"
        : '';

    $svg  = "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 64 64\" role=\"img\" aria-label=\"Mobi Boost\">\n";
    $svg .= "  <defs>\n" . $gradients
        . "    <clipPath id=\"mb-mark\"><rect x=\"0\" y=\"$clipTop\" width=\"64\" height=\"$clipH\"/></clipPath>\n"
        . "  </defs>\n";
    $svg .= "  <rect x=\"0\" y=\"0\" width=\"64\" height=\"64\" rx=\"14.4\" fill=\"$tileFill\"/>\n";
    if ($variant === 'premium') {
        $svg .= "  <rect x=\"0\" y=\"0\" width=\"64\" height=\"64\" rx=\"14.4\" fill=\"url(#mb-sheen)\"/>\n";
    }
    $svg .= $inner;
    $svg .= sprintf("  <g fill=\"none\" stroke=\"%s\" stroke-width=\"%.2f\" stroke-linecap=\"butt\" clip-path=\"url(#mb-mark)\">\n", $mark, $strokeW);
    $svg .= $segments;
    $svg .= "  </g>\n</svg>\n";
    return $svg;
}

function png_bytes(int $w, int $h, array $rgba): string {
    $raw = '';
    for ($y = 0; $y < $h; $y++) {
        $raw .= "\x00";
        for ($x = 0; $x < $w; $x++) {
            [$r, $g, $b, $a] = $rgba[$y][$x];
            $raw .= chr($r) . chr($g) . chr($b) . chr($a);
        }
    }
    $chunk = fn(string $type, string $data): string =>
        pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    return "\x89PNG\r\n\x1a\n"
        . $chunk('IHDR', pack('NNCCCCC', $w, $h, 8, 6, 0, 0, 0))
        . $chunk('IDAT', gzcompress($raw, 9))
        . $chunk('IEND', '');
}

/** Pack PNG buffers into a multi-size .ico (PNG-compressed entries). */
function write_ico(string $file, array $pngs): void {
    $count = count($pngs);
    $dir = pack('vvv', 0, 1, $count);
    $offset = 6 + 16 * $count;
    $body = '';
    foreach ($pngs as [$size, $bytes]) {
        $dim = $size >= 256 ? 0 : $size;
        $dir .= pack('CCCCvvVV', $dim, $dim, 0, 0, 1, 32, strlen($bytes), $offset);
        $body .= $bytes;
        $offset += strlen($bytes);
    }
    file_put_contents($file, $dir . $body);
}

/** Optional comparison sheet: every variant on light + dark, plus magnified 16 px. */
function write_preview(string $file): void {
    $variants = ['premium', 'frame', 'blue', 'navy'];
    $cell = 96; $gap = 20;
    $sw = $gap + count($variants) * ($cell + $gap);
    $sh = $gap + $cell + $gap + $cell + $gap + $cell + $gap;
    $sheet = array_fill(0, $sh, array_fill(0, $sw, [255, 255, 255, 255]));

    $col = 0;
    foreach ($variants as $v) {
        [, , $buf] = render_icon($v, 256, false);
        $f = 256 / $cell;
        $big = array_fill(0, $cell, array_fill(0, $cell, [0, 0, 0, 0]));
        for ($y = 0; $y < $cell; $y++) {
            for ($x = 0; $x < $cell; $x++) {
                $r = $g = $b = $a = 0; $n = 0;
                for ($sy = (int)($y * $f); $sy < (int)(($y + 1) * $f); $sy++) {
                    for ($sx = (int)($x * $f); $sx < (int)(($x + 1) * $f); $sx++) {
                        [$pr, $pg, $pb, $pa] = $buf[$sy][$sx];
                        $r += $pr; $g += $pg; $b += $pb; $a += $pa; $n++;
                    }
                }
                $big[$y][$x] = $n ? [(int)($r/$n), (int)($g/$n), (int)($b/$n), (int)($a/$n)] : [0, 0, 0, 0];
            }
        }
        [, , $small] = render_icon($v, 16, false);
        $mag = array_fill(0, $cell, array_fill(0, $cell, [0, 0, 0, 0]));
        for ($y = 0; $y < $cell; $y++) {
            for ($x = 0; $x < $cell; $x++) {
                $mag[$y][$x] = $small[(int)($y / ($cell / 16))][(int)($x / ($cell / 16))];
            }
        }

        $ox = $gap + $col * ($cell + $gap);
        $rows = [[$gap, $big, [244, 246, 244]], [$gap + $cell + $gap, $big, [32, 33, 36]], [$gap + 2 * ($cell + $gap), $mag, [244, 246, 244]]];
        foreach ($rows as [$oy, $src, $bg]) {
            for ($y = 0; $y < $cell; $y++) {
                for ($x = 0; $x < $cell; $x++) {
                    [$r, $g, $b, $a] = $src[$y][$x];
                    $al = $a / 255;
                    $sheet[$oy + $y][$ox + $x] = [
                        (int)round($r * $al + $bg[0] * (1 - $al)),
                        (int)round($g * $al + $bg[1] * (1 - $al)),
                        (int)round($b * $al + $bg[2] * (1 - $al)),
                        255,
                    ];
                }
            }
        }
        $col++;
    }
    file_put_contents($file, png_bytes($sw, $sh, $sheet));
}

// ------------------------------------------------------------------- CLI
$out     = $argv[1] ?? 'public';
$variant = $argv[2] ?? 'premium';
$preview = ($argv[3] ?? '') === 'preview';

if (!in_array($variant, ['premium', 'frame', 'blue', 'navy'], true)) {
    fwrite(STDERR, "unknown variant: $variant\n");
    exit(1);
}
if (!is_dir($out)) mkdir($out, 0777, true);

file_put_contents("$out/favicon.svg", svg_icon($variant));

$pngs = [];
foreach ([16, 32, 48] as $s) {
    [$w, $h, $buf] = render_icon($variant, $s, false);
    $pngs[] = [$s, png_bytes($w, $h, $buf)];
}
write_ico("$out/favicon.ico", $pngs);

foreach ([[180, 'apple-touch-icon.png'], [192, 'icon-192.png'], [512, 'icon-512.png']] as [$s, $name]) {
    [$w, $h, $buf] = render_icon($variant, $s, true);
    file_put_contents("$out/$name", png_bytes($w, $h, $buf));
}

echo "variant=$variant -> $out/favicon.svg, favicon.ico, apple-touch-icon.png, icon-192.png, icon-512.png\n";
if ($preview) {
    write_preview("$out/preview-variants.png");
    echo "preview -> $out/preview-variants.png\n";
}
