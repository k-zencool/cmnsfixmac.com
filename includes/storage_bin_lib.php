<?php
/* =========================================================
   includes/storage_bin_lib.php — shelves, slots and slot QR labels

   A shelf ("A") has numbered slots, one box each. A slot's code is
   shelf + 2-digit slot: "A-03" — two digits so A-10 sorts after A-09,
   and a shelf can grow to A-99 without renaming what is already
   labelled. The slot decides what may go in (item type + root
   category); anything else is refused.

   All functions are prefixed sbin_ and guarded, so the file is safe to
   include more than once (same pattern as part_label_lib.php).
   ========================================================= */

/* What a slot label QR encodes: "CMNS:B-12" (storage_bins.id). The id, not
   "A-03": a slot can be renumbered or its shelf renamed, and the label
   already stuck on the box must keep working. Not a URL — only the in-app
   scanner opens it. "B-" can never be a repair ticket ("V" + digits) or a
   part label ("P-"). */
if (!function_exists('sbin_qr_payload')) {
    function sbin_qr_payload(int $id): string {
        return 'CMNS:B-' . $id;
    }
}

/* "A", 3 → "A-03" */
if (!function_exists('sbin_code')) {
    function sbin_code(string $shelf, int $slot): string {
        return $shelf . '-' . str_pad((string)$slot, 2, '0', STR_PAD_LEFT);
    }
}

/* Item types a slot can be set to, in the words used on the floor */
if (!function_exists('sbin_types')) {
    function sbin_types(): array {
        return [
            'machine' => 'เครื่องซาก',
            'used'    => 'อะไหล่มือสอง',
            'new'     => 'อะไหล่ใหม่',
            'sale'    => 'เครื่องขาย',
        ];
    }
}

/* Next shelf code after the ones in use: A … Z, then AA, AB … */
if (!function_exists('sbin_next_shelf_code')) {
    function sbin_next_shelf_code(array $used): string {
        $used = array_flip(array_map('strtoupper', $used));
        for ($n = 0; $n < 702; $n++) {
            $c = $n < 26 ? chr(65 + $n) : chr(64 + intdiv($n, 26)) . chr(65 + $n % 26);
            if (!isset($used[$c])) return $c;
        }
        return 'ZZ';
    }
}

/* One slot with its shelf, category name and item count, or null */
if (!function_exists('sbin_get')) {
    function sbin_get(PDO $pdo, int $id): ?array {
        $st = $pdo->prepare("
            SELECT b.*, s.code AS shelf_code, s.name AS shelf_name, c.name AS category_name,
                   (SELECT COUNT(*) FROM inventory i WHERE i.bin_id = b.id) AS item_count
            FROM storage_bins b
            JOIN storage_shelves s ON s.id = b.shelf_id
            LEFT JOIN parts_categories c ON c.id = b.category_id
            WHERE b.id = ?
        ");
        $st->execute([$id]);
        $b = $st->fetch(PDO::FETCH_ASSOC);
        if (!$b) return null;
        $b['code'] = sbin_code($b['shelf_code'], (int)$b['slot']);
        return $b;
    }
}

/* Why this item may not go in this slot, or null when it may. $item needs
   type + root_category_id (see sbin_item()). */
if (!function_exists('sbin_refusal')) {
    function sbin_refusal(array $bin, array $item): ?string {
        if (!$bin['item_type'] || !$bin['category_id']) {
            return 'ช่อง ' . $bin['code'] . ' ยังไม่ได้ตั้งหมวด — ตั้งที่หน้าชั้นเก็บของก่อน';
        }
        $types = sbin_types();
        if ($item['type'] !== $bin['item_type']) {
            return 'ช่อง ' . $bin['code'] . ' เก็บ' . $types[$bin['item_type']]
                 . ' — ชิ้นนี้เป็น' . ($types[$item['type']] ?? $item['type']);
        }
        if ((int)$item['root_category_id'] !== (int)$bin['category_id']) {
            return 'ช่อง ' . $bin['code'] . ' เก็บหมวด ' . $bin['category_name']
                 . ' — ชิ้นนี้อยู่หมวด ' . ($item['root_category_name'] ?: '—');
        }
        return null;
    }
}

/* An inventory item with its root category (the tree is two levels deep)
   and the slot it currently sits in, or null */
if (!function_exists('sbin_item')) {
    function sbin_item(PDO $pdo, int $id): ?array {
        $st = $pdo->prepare("
            SELECT i.id, i.name, i.sku, i.asset_tag, i.serial_number, i.location, i.disassembly_status,
                   i.type, i.status, i.bin_id,
                   COALESCE(c.parent_id, c.id) AS root_category_id,
                   COALESCE(p.name, c.name)    AS root_category_name,
                   s.code AS bin_shelf, b.slot AS bin_slot
            FROM inventory i
            LEFT JOIN parts_categories c ON c.id = i.category_id
            LEFT JOIN parts_categories p ON p.id = c.parent_id
            LEFT JOIN storage_bins b     ON b.id = i.bin_id
            LEFT JOIN storage_shelves s  ON s.id = b.shelf_id
            WHERE i.id = ?
        ");
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) return null;
        $r['bin_code'] = $r['bin_shelf'] ? sbin_code($r['bin_shelf'], (int)$r['bin_slot']) : null;
        return $r;
    }
}

/* Items in a slot, for the slot sheet and bins.php — machines first by
   asset tag, parts by name. `mismatch` flags anything that no longer fits
   the slot (e.g. a machine since put up for sale) so it can be taken out. */
if (!function_exists('sbin_items')) {
    function sbin_items(PDO $pdo, array $bin): array {
        $st = $pdo->prepare("
            SELECT i.id, i.name, i.sku, i.asset_tag, i.type, i.status, i.disassembly_status,
                   COALESCE(c.parent_id, c.id) AS root_category_id
            FROM inventory i
            LEFT JOIN parts_categories c ON c.id = i.category_id
            WHERE i.bin_id = ?
            ORDER BY i.asset_tag IS NULL, i.asset_tag, i.name
        ");
        $st->execute([(int)$bin['id']]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[] = [
                'id'       => (int)$r['id'],
                'name'     => $r['name'],
                'tag'      => $r['asset_tag'] ?: $r['sku'],
                'type'     => $r['type'],
                'status'   => $r['status'],
                'stripped' => in_array($r['disassembly_status'], ['stripped', 'partially_stripped'], true),
                'mismatch' => $r['type'] !== $bin['item_type'] || (int)$r['root_category_id'] !== (int)$bin['category_id'],
            ];
        }
        return $out;
    }
}

/* Put an item in a slot (bin null = take it out) and log it to
   inventory_status_log. Returns null on success or the refusal text. */
if (!function_exists('sbin_move')) {
    function sbin_move(PDO $pdo, int $itemId, ?array $bin): ?string {
        $item = sbin_item($pdo, $itemId);
        if (!$item) return 'ไม่พบรายการนี้ในคลัง';

        $from = $item['bin_code'];
        if ($bin) {
            if ((int)$item['bin_id'] === (int)$bin['id']) return null;   // already there
            if (($why = sbin_refusal($bin, $item)) !== null) return $why;
        } elseif ($item['bin_id'] === null) {
            return null;                                                  // already out
        }

        $pdo->prepare("UPDATE inventory SET bin_id = ? WHERE id = ?")
            ->execute([$bin ? (int)$bin['id'] : null, $itemId]);

        // created_at from PHP (Asia/Bangkok) — the column default is the DB server's clock
        $pdo->prepare("INSERT INTO inventory_status_log
                (inventory_id, action, created_by, admin_name, note, created_at)
                VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([
                $itemId,
                $bin ? 'bin_in' : 'bin_out',
                $_SESSION['admin_id'] ?? null,
                $_SESSION['admin_username'] ?? ($_SESSION['admin_name'] ?? null),
                $bin ? (($from ? $from . ' → ' : '') . $bin['code']) : ('เอาออกจาก ' . $from),
                (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s'),
            ]);
        return null;
    }
}

/* Slot label paper: plain A4, 2 × 3 cells of 72 × 88 mm, cut by hand —
   the cell hugs a 66 mm QR with the code under it. Block centred:
   33 mm side, 16.5 mm top margins. */
if (!function_exists('sbin_sheet')) {
    function sbin_sheet(): array {
        return ['cols' => 2, 'rows' => 3, 'w' => 72, 'h' => 88, 'top' => 16.5, 'left' => 33];
    }
}
