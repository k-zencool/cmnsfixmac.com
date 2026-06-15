<?php
/**
 * compress_uploads.php — one-off batch image compressor for /uploads
 *
 * WHY: legacy repair/shop/article photos were saved at full resolution
 * (2–3 MB each) before the upload pipeline added auto-resize. They bloat
 * every page that lists them (homepage work grid alone pulls ~15 MB).
 *
 * WHAT: walks the target dirs, and for any image wider than MAX_W *or*
 * heavier than SIZE_THRESHOLD, resizes to MAX_W and re-encodes IN PLACE,
 * keeping the SAME filename + extension so DB paths stay valid.
 *
 * SAFE BY DEFAULT: dry-run. It only previews unless you pass --go.
 *   Preview:  php database/compress_uploads.php
 *   Execute:  php database/compress_uploads.php --go
 *
 * Run it on the server (cPanel "Terminal"/Cron, or SSH). CLI only —
 * it refuses to run over HTTP so it can't be triggered by a visitor.
 *
 * RECOMMENDED: back up first →  tar czf uploads-repairs.bak.tgz uploads/repairs
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This maintenance script is CLI-only.\n");
}

// ── config ────────────────────────────────────────────────
const MAX_W           = 1200;              // max width in px
const QUALITY         = 82;                // jpeg/webp quality
const SIZE_THRESHOLD  = 350 * 1024;        // re-encode anything over 350 KB
$BASE = dirname(__DIR__);                  // project root
$DIRS = [
    $BASE . '/uploads/repairs',
    $BASE . '/uploads/shop',
    $BASE . '/uploads/articles',
];

$GO = in_array('--go', $argv, true);

if (!function_exists('imagewebp')) {
    exit("ERROR: PHP GD extension with WebP support is required.\n");
}

// ── helpers ───────────────────────────────────────────────
function human($b) {
    $u = ['B','KB','MB','GB']; $i = 0;
    while ($b >= 1024 && $i < 3) { $b /= 1024; $i++; }
    return round($b, 1) . ' ' . $u[$i];
}

/**
 * Resize+re-encode $path in place, preserving format. Returns
 * [old_bytes, new_bytes] on change, or null if skipped/failed.
 */
function compress(string $path): ?array {
    $info = @getimagesize($path);
    if (!$info) return null;
    [$w, $h, $type] = $info;
    $oldBytes = filesize($path);

    // skip already-small + already-narrow images
    if ($w <= MAX_W && $oldBytes <= SIZE_THRESHOLD) return null;

    switch ($type) {
        case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($path); break;
        case IMAGETYPE_PNG:  $src = @imagecreatefrompng($path);  break;
        case IMAGETYPE_WEBP: $src = @imagecreatefromwebp($path); break;
        default: return null;   // gif/svg/etc — leave alone
    }
    if (!$src) return null;

    if ($w > MAX_W) {
        $nh  = (int) round($h * MAX_W / $w);
        $dst = imagecreatetruecolor(MAX_W, $nh);
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }
        imagecopyresampled($dst, $src, 0,0,0,0, MAX_W, $nh, $w, $h);
        imagedestroy($src);
        $src = $dst;
    }

    // write to temp first, only swap if it is actually smaller + valid
    $tmp = $path . '.tmp';
    $ok = false;
    switch ($type) {
        case IMAGETYPE_JPEG: $ok = imagejpeg($src, $tmp, QUALITY); break;
        case IMAGETYPE_WEBP: $ok = imagewebp($src, $tmp, QUALITY); break;
        case IMAGETYPE_PNG:  $ok = imagepng($src, $tmp, 8);        break;
    }
    imagedestroy($src);
    if (!$ok || !file_exists($tmp)) { @unlink($tmp); return null; }

    $newBytes = filesize($tmp);
    if ($newBytes <= 0 || $newBytes >= $oldBytes) { @unlink($tmp); return null; }

    rename($tmp, $path);
    return [$oldBytes, $newBytes];
}

// ── walk ──────────────────────────────────────────────────
echo $GO ? "MODE: EXECUTE (--go)\n\n" : "MODE: DRY-RUN (add --go to apply)\n\n";

$totalOld = $totalNew = $count = $candidates = 0;

foreach ($DIRS as $dir) {
    if (!is_dir($dir)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile()) continue;
        if (!preg_match('/\.(jpe?g|png|webp)$/i', $file->getFilename())) continue;
        $path = $file->getPathname();

        $info = @getimagesize($path);
        if (!$info) continue;
        [$w, $h] = $info;
        $sz = filesize($path);
        if ($w <= MAX_W && $sz <= SIZE_THRESHOLD) continue;   // already fine
        $candidates++;

        if (!$GO) {
            printf("  would shrink  %-55s  %sx%s  %s\n",
                substr(str_replace($dir . '/', '', $path), 0, 55), $w, $h, human($sz));
            $totalOld += $sz;
            continue;
        }

        $res = compress($path);
        if ($res) {
            [$o, $n] = $res;
            $totalOld += $o; $totalNew += $n; $count++;
            printf("  ✓ %-55s  %s -> %s\n",
                substr(str_replace($dir . '/', '', $path), 0, 55), human($o), human($n));
        }
    }
}

echo "\n";
if (!$GO) {
    echo "Candidates: $candidates files, ~" . human($totalOld) . " of source images\n";
    echo "Run again with --go to compress them.\n";
} else {
    echo "Done. Compressed $count / $candidates files.\n";
    echo "Saved: " . human($totalOld) . " -> " . human($totalNew)
       . "  (freed " . human($totalOld - $totalNew) . ")\n";
}
