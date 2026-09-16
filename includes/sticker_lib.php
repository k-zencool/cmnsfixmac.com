<?php
/* =========================================================
   includes/sticker_lib.php — pre-printed repair-number stickers

   Stickers are printed ahead of time on A4 label sheets, before any job
   exists, so the QR can only carry the ticket number (V5700), never a row
   id. The shop's numbering is "V" + a running integer; legacy tickets such
   as "V5232/1" still resolve by exact match but are never generated here.

   All functions are prefixed stk_ and guarded, so the file is safe to
   include more than once (same pattern as warranty_lib.php).
   ========================================================= */

if (!function_exists('stk_prefix')) {
    function stk_prefix(): string { return 'V'; }
}

/* "V5700" / "v5700" / "5700" → 5700, anything else → null */
if (!function_exists('stk_parse_no')) {
    function stk_parse_no(string $s): ?int {
        return preg_match('/^V?(\d{1,7})$/i', trim($s), $m) ? (int)$m[1] : null;
    }
}

if (!function_exists('stk_fmt')) {
    function stk_fmt(int $n): string { return stk_prefix() . $n; }
}

/* What a sticker QR encodes: HTTPS://CMNSFIXMAC.COM/T/V5700 (.htaccess
   rewrites /T/ to admin/scan/resolve.php?t=). Every character counts on a
   13 mm code, and an all-upper-case URL fits QR's alphanumeric mode
   (~5.5 bits/char instead of 8): 30 chars → version 2, 25 × 25 modules,
   against 33 × 33 for the long /admin/scan/resolve.php?t= form. Scheme and
   host are case-insensitive. Legacy reprints ("V5508 (2)") keep the long
   form: spaces and brackets don't survive a path rewrite cleanly, and they
   are a handful of one-off labels. */
if (!function_exists('stk_scan_url')) {
    function stk_scan_url(string $ticket): string {
        $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $base   = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'cmnsfixmac.com');
        if (preg_match('/^V\d{1,7}$/', $ticket)) {
            return strtoupper($base) . '/T/' . $ticket;
        }
        return $base . '/admin/scan/resolve.php?t=' . rawurlencode($ticket);
    }
}

/* Plain A4 sticker paper, cut by hand: 11 × 14 cells of 18 × 20 mm packed
   edge to edge (pitch = size), so one cut line serves both neighbours.
   18 mm leaves ~2.3 mm between the QR and each side cut; top and bottom
   already have the shop name / number as a buffer. The block is centred
   (6 mm side, 8.5 mm top margins — inside a printer's unprintable edge).
   Offsets are measured from the sheet's top-left corner. */
if (!function_exists('stk_sheet')) {
    function stk_sheet(): array {
        return [
            'cols'   => 11,
            'rows'   => 14,
            'w'      => 18,
            'h'      => 20,
            'pitchX' => 18,
            'pitchY' => 20,
            'top'    => 8.5,
            'left'   => 6,
        ];
    }
}

if (!function_exists('stk_per_sheet')) {
    function stk_per_sheet(): int { $s = stk_sheet(); return $s['cols'] * $s['rows']; }
}

/* Highest V-number already used by a job, 0 if none */
if (!function_exists('stk_last_used_no')) {
    function stk_last_used_no(PDO $pdo): int {
        return (int)$pdo->query("
            SELECT COALESCE(MAX(CAST(SUBSTRING(ticket_number, 2) AS UNSIGNED)), 0)
            FROM tracking WHERE ticket_number REGEXP '^V[0-9]+$'
        ")->fetchColumn();
    }
}

/* Highest number ever printed in a batch, 0 if none (or table not migrated yet) */
if (!function_exists('stk_last_printed_no')) {
    function stk_last_printed_no(PDO $pdo): int {
        try {
            return (int)$pdo->query("SELECT COALESCE(MAX(end_no), 0) FROM sticker_prints WHERE kind = 'range'")->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }
}

/* Where the next batch should start: after whatever is higher, used or printed */
if (!function_exists('stk_next_no')) {
    function stk_next_no(PDO $pdo): int {
        return max(stk_last_used_no($pdo), stk_last_printed_no($pdo)) + 1;
    }
}
