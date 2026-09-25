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
   cells packed edge to edge, so one cut line serves both neighbours. QR on
   the left, name + SKU on the right; the block is centred on the sheet.
   Offsets from the sheet's top-left, all in mm.

   Several sizes, picked on the print page (?size=) and remembered per
   device: small for bags and small bins, bigger for boxes and shelves or
   when the QR has to read from further away. Each carries its own type
   scale so the text grows with the QR. */
if (!function_exists('plb_sizes')) {
    function plb_sizes(): array {
        return [
            's'  => ['label' => 'เล็ก',      'cols' => 5, 'rows' => 14, 'w' => 38,   'h' => 19, 'top' => 15.5, 'left' => 10,
                     'qr' => 14, 'name' => 6.6,  'models' => 5.6, 'sku' => 5,    'brand' => 3.8],
            'm'  => ['label' => 'กลาง',      'cols' => 4, 'rows' => 11, 'w' => 47.5, 'h' => 25, 'top' => 11,   'left' => 10,
                     'qr' => 20, 'name' => 8,    'models' => 6.8, 'sku' => 6.2,  'brand' => 4.6],
            'l'  => ['label' => 'ใหญ่',      'cols' => 3, 'rows' => 7,  'w' => 63,   'h' => 38, 'top' => 15.5, 'left' => 10.5,
                     'qr' => 30, 'name' => 10.5, 'models' => 9,   'sku' => 8,    'brand' => 5.5],
            'xl' => ['label' => 'ใหญ่มาก',   'cols' => 2, 'rows' => 5,  'w' => 95,   'h' => 54, 'top' => 13.5, 'left' => 10,
                     'qr' => 44, 'name' => 14,   'models' => 12,  'sku' => 10.5, 'brand' => 7],
        ];
    }
}

/* one size's grid; unknown keys fall back to the small (original) size */
if (!function_exists('plb_sheet')) {
    function plb_sheet(string $size = 's'): array {
        $all = plb_sizes();
        $s = $all[$size] ?? $all['s'];
        $s['pitchX'] = $s['w'];
        $s['pitchY'] = $s['h'];
        return $s;
    }
}

if (!function_exists('plb_per_sheet')) {
    function plb_per_sheet(string $size = 's'): int { $s = plb_sheet($size); return $s['cols'] * $s['rows']; }
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

/* A label's name split into lines that read at a glance, instead of one
   block that wraps model codes across lines:
     title  what the part is      "LCD Panel MacBook Air 13""
     codes  Apple models it fits  ["A2681","A3113","A3240"]  (name + compatible_models)
     tags   what was in brackets  ["M2","M3","M4"]
   Nothing is dropped — every word of the name lands on one of the lines.
   A name that is nothing but codes keeps itself as the title. */
if (!function_exists('plb_label_parts')) {
    function plb_label_parts(string $name, ?string $compat): array {
        $codes = [];
        foreach ([$name, (string)$compat] as $src) {
            if (preg_match_all('/\bA\d{4}\b/i', $src, $m)) {
                foreach ($m[0] as $c) $codes[strtoupper($c)] = strtoupper($c);
            }
        }
        $tags = [];
        $title = preg_replace_callback('/\(([^()]*)\)/u', function ($m) use (&$tags) {
            foreach (preg_split('/\s*[\/,]\s*/u', trim($m[1])) as $t) if ($t !== '') $tags[] = $t;
            return ' ';
        }, $name);
        // drop the codes and whatever joined them ("A2681/A3113", "A1706 - A1708")
        $title = preg_replace('/\bA\d{4}\b(?:\s*[\/,\-]\s*\bA\d{4}\b)*/i', ' ', $title);
        $title = trim(preg_replace(['/\s*\/\s*(?=\s|$)/u', '/\s{2,}/u'], [' ', ' '], $title), " \t/-,");
        if ($title === '') { $title = $name; $codes = []; }
        // no Apple codes: a free-text compatible_models line still says something new
        $extra = '';
        if (!$codes && ($c = trim((string)$compat)) !== '' && stripos($name, $c) === false) $extra = $c;
        return ['title' => $title, 'codes' => array_values($codes), 'tags' => $tags, 'extra' => $extra];
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
