<?php
/* =========================================================
   includes/part_label_lib.php — QR labels for inventory parts

   One label per inventory item (per SKU, not per piece): new parts are
   counted by quantity, so the label goes on the bag or bin and restocking
   never needs a reprint.

   All functions are prefixed plb_ and guarded, so the file is safe to
   include more than once (same pattern as sticker_lib.php).
   ========================================================= */

/* What a part label QR encodes: "CMNS:P-133" (inventory.id). The id, not
   the SKU: a SKU can be edited, and a label already stuck on a bag must
   keep working. Like the repair-number stickers it is NOT a URL — only the
   in-app scanner (admin/templates/assets/js/scan.js) opens it. Upper-case,
   digits, ':' and '-' keep it in QR alphanumeric mode (version 1). The
   "P-" can never be a repair ticket, which are "V" + digits. */
if (!function_exists('plb_qr_payload')) {
    function plb_qr_payload(int $id): string {
        return 'CMNS:P-' . $id;
    }
}

/* "CMNS:P-133" → 133, anything else → null */
if (!function_exists('plb_parse_payload')) {
    function plb_parse_payload(string $s): ?int {
        return preg_match('/^CMNS:P-(\d{1,9})$/i', trim($s), $m) ? (int)$m[1] : null;
    }
}

/* Plain A4 sticker paper, cut by hand (same approach as sticker_lib.php):
   5 × 14 cells of 38 × 19 mm packed edge to edge, so one cut line serves
   both neighbours. QR on the left, name + SKU on the right. Block centred:
   10 mm side, 15.5 mm top margins. Offsets from the sheet's top-left. */
if (!function_exists('plb_sheet')) {
    function plb_sheet(): array {
        return [
            'cols'   => 5,
            'rows'   => 14,
            'w'      => 38,
            'h'      => 19,
            'pitchX' => 38,
            'pitchY' => 19,
            'top'    => 15.5,
            'left'   => 10,
        ];
    }
}

if (!function_exists('plb_per_sheet')) {
    function plb_per_sheet(): int { $s = plb_sheet(); return $s['cols'] * $s['rows']; }
}

/* The "fits which model" line under the name, so two items both named
   "K/B UK" still read differently on the shelf. Pulls Apple model codes
   (A1466) out of compatible_models — the field is free text ("A1706 -
   A1708", "A1932,A2179", "Macbook Air A2941 A3114") — and drops any the
   name already shows. No codes at all → the raw text, unless the name
   already contains it. '' when there is nothing new to say. */
if (!function_exists('plb_models_line')) {
    function plb_models_line(string $name, ?string $compat): string {
        $compat = trim((string)$compat);
        if ($compat === '') return '';
        if (preg_match_all('/\bA\d{4}\b/i', $compat, $m)) {
            $codes = [];
            foreach ($m[0] as $c) {
                $c = strtoupper($c);
                if (stripos($name, $c) === false) $codes[$c] = $c;
            }
            return implode(' ', $codes);
        }
        return stripos($name, $compat) === false ? $compat : '';
    }
}

/* "1,2,x,2,3" → [1,2,3] — positive ints, de-duplicated, order kept, capped */
if (!function_exists('plb_parse_ids')) {
    function plb_parse_ids(string $csv, int $max = 500): array {
        $out = [];
        foreach (explode(',', $csv) as $p) {
            $n = (int)trim($p);
            if ($n > 0 && !isset($out[$n])) $out[$n] = $n;
            if (count($out) >= $max) break;
        }
        return array_values($out);
    }
}
