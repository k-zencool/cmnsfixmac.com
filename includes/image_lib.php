<?php
/**
 * image_lib.php — shared image intake: validate + resize + re-encode to WebP.
 *
 * WHY: every admin upload path used to move_uploaded_file() the raw file
 * straight to /uploads, so a 3–4 MB phone photo landed untouched and bloated
 * every page that showed it. This centralises the resize-to-WebP step the
 * shop add/edit pages already did inline, so inventory/avatar uploads shrink
 * at the source instead of being compressed after the fact.
 *
 * All functions are prefixed img_ and guarded with function_exists so the file
 * is safe to include multiple times (same pattern as warranty_lib.php).
 */

if (!function_exists('img_save_webp')) {
    /**
     * Resize (only if wider than $maxW) and re-encode an uploaded image to WebP.
     * Keeps aspect ratio and preserves alpha for png/webp/gif sources.
     *
     * @param string $tmp   uploaded tmp path ($_FILES[..]['tmp_name'])
     * @param string $dest  destination path — should end in .webp
     * @param int    $maxW  max width in px (downscale only, never upscale)
     * @param int    $q     WebP quality 0–100
     * @return array|false  ['w','h','size'] on success, false on any failure
     */
    function img_save_webp(string $tmp, string $dest, int $maxW = 1200, int $q = 82) {
        if (!function_exists('imagewebp')) return false;   // GD/WebP missing
        $info = @getimagesize($tmp);
        if (!$info) return false;
        [$w, $h, $type] = $info;

        switch ($type) {
            case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($tmp); break;
            case IMAGETYPE_PNG:  $src = @imagecreatefrompng($tmp);  break;
            case IMAGETYPE_WEBP: $src = @imagecreatefromwebp($tmp); break;
            case IMAGETYPE_GIF:  $src = @imagecreatefromgif($tmp);  break;
            default:             return false;
        }
        if (!$src) return false;

        if ($w > $maxW) {
            $nh  = (int) round($h * $maxW / $w);
            $dst = imagecreatetruecolor($maxW, $nh);
            imagealphablending($dst, false);   // keep transparency intact
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $maxW, $nh, $w, $h);
            imagedestroy($src);
            $src = $dst; $w = $maxW; $h = $nh;
        }

        $ok = imagewebp($src, $dest, $q);
        imagedestroy($src);
        if (!$ok) return false;
        return ['w' => $w, 'h' => $h, 'size' => @filesize($dest)];
    }

    /**
     * MIME sniff via finfo — never trust the browser-sent type or the
     * file extension. Returns true only for real raster images we can decode.
     */
    function img_mime_ok(string $tmp, array $allow = ['image/jpeg', 'image/png', 'image/webp', 'image/gif']): bool {
        return in_array((new finfo(FILEINFO_MIME_TYPE))->file($tmp), $allow, true);
    }
}
