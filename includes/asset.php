<?php
/**
 * Cache-busting version for a CSS/JS file, taken from its mtime.
 *
 * Replaces the `?v=<?= time() ?>` that used to sit on admin asset tags: time()
 * changed every second, so every URL was unique and the browser re-downloaded
 * every stylesheet and script on every page load — the cache never hit once.
 * An mtime changes only when the file actually changes, so browsers keep the
 * cached copy until a deploy edits the file, and nobody has to remember to bump
 * a number by hand.
 *
 * $path is relative to the project root, e.g. '/admin/templates/assets/css/admin.css'.
 * The URL in the tag is left alone — this only supplies the number after ?v=.
 * Falls back to '1' if the file is missing so a bad path degrades to a stale
 * cache rather than a broken tag.
 */
if (!function_exists('asset_ver')) {
    function asset_ver(string $path): string {
        static $cache = [];
        if (isset($cache[$path])) return $cache[$path];

        $mtime = @filemtime(dirname(__DIR__) . '/' . ltrim($path, '/'));
        return $cache[$path] = (string)($mtime ?: 1);
    }
}
