<?php
/********************************************************************
 * admin/inventory/bins.php  –  shelves and slots (ชั้นเก็บของ)
 *
 * A shelf ("A") holds numbered slots, one box each ("A-03"). Each slot
 * is fixed to one item type + root category, and only matching items go
 * in (includes/storage_bin_lib.php). A QR label on the box (print_bins.php,
 * CMNS:B-<id>) opens the slot in the scanner, where items are moved in
 * and out; this page does the same from a desk.
 *
 *   bins.php           all shelves, slot grid, new shelf
 *   bins.php?bin=<id>  one slot: its items, add/remove, settings
 *
 * Everything here needs parts.manage. Forms post back here and redirect
 * (PRG), with a one-shot flash message.
 ********************************************************************/

session_start();
date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/storage_bin_lib.php';
require_login();
require_perms(['parts.manage']);

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function bins_back(string $msg, bool $err = false, ?int $bin = null): void {
    $_SESSION['bins_flash'] = ['msg' => $msg, 'err' => $err];
    header('Location: bins.php' . ($bin ? '?bin=' . $bin : ''));
    exit;
}

/* Tables may not exist yet on a server that has not run the migration */
$ready = true;
try { $pdo->query("SELECT bin_id FROM inventory LIMIT 1"); $pdo->query("SELECT 1 FROM storage_bins LIMIT 1"); }
catch (PDOException $e) { $ready = false; }

$types    = sbin_types();
$rootCats = $pdo->query("SELECT id, name FROM parts_categories WHERE parent_id IS NULL ORDER BY name")->fetchAll(PDO::FETCH_KEY_PAIR);

/* A posted type + category pair, both valid or both empty */
function bins_kind(array $types, array $rootCats): array {
    $t = $_POST['item_type'] ?? '';
    $c = (int)($_POST['category_id'] ?? 0);
    return [isset($types[$t]) ? $t : null, isset($rootCats[$c]) ? $c : null];
}

/* ── POST ── */
if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_shelf') {
        $code  = strtoupper(trim((string)($_POST['code'] ?? '')));
        $name  = trim((string)($_POST['name'] ?? ''));
        $slots = (int)($_POST['slots'] ?? 0);
        [$t, $c] = bins_kind($types, $rootCats);
        if (!preg_match('/^[A-Z]{1,2}$/', $code)) bins_back('รหัสชั้นต้องเป็นตัวอักษรอังกฤษ 1–2 ตัว (A, B, … AA)', true);
        if ($slots < 1 || $slots > 99) bins_back('จำนวนช่องต้องอยู่ระหว่าง 1–99', true);
        $st = $pdo->prepare("SELECT 1 FROM storage_shelves WHERE code = ?");
        $st->execute([$code]);
        if ($st->fetch()) bins_back("มีชั้น $code อยู่แล้ว", true);

        $now = date('Y-m-d H:i:s');
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO storage_shelves (code, name, created_at) VALUES (?, ?, ?)")
            ->execute([$code, $name !== '' ? mb_substr($name, 0, 100) : null, $now]);
        $shelfId = (int)$pdo->lastInsertId();
        $ins = $pdo->prepare("INSERT INTO storage_bins (shelf_id, slot, item_type, category_id, created_at) VALUES (?, ?, ?, ?, ?)");
        for ($i = 1; $i <= $slots; $i++) $ins->execute([$shelfId, $i, $t, $c, $now]);
        $pdo->commit();
        bins_back("สร้างชั้น $code แล้ว — " . sbin_code($code, 1) . ' ถึง ' . sbin_code($code, $slots));
    }

    if ($action === 'rename_shelf') {
        $shelfId = (int)($_POST['shelf_id'] ?? 0);
        $code    = strtoupper(trim((string)($_POST['code'] ?? '')));
        $name    = trim((string)($_POST['name'] ?? ''));
        if (!preg_match('/^[A-Z]{1,2}$/', $code)) bins_back('รหัสชั้นต้องเป็นตัวอักษรอังกฤษ 1–2 ตัว (A, B, … AA)', true);
        $st = $pdo->prepare("SELECT code FROM storage_shelves WHERE id = ?");
        $st->execute([$shelfId]);
        $old = $st->fetchColumn();
        if ($old === false) bins_back('ไม่พบชั้นนี้', true);
        $st = $pdo->prepare("SELECT 1 FROM storage_shelves WHERE code = ? AND id <> ?");
        $st->execute([$code, $shelfId]);
        if ($st->fetch()) bins_back("มีชั้น $code อยู่แล้ว", true);
        // QR carries the slot id, so labels still scan — only the printed code goes stale
        $pdo->prepare("UPDATE storage_shelves SET code = ?, name = ? WHERE id = ?")
            ->execute([$code, $name !== '' ? mb_substr($name, 0, 100) : null, $shelfId]);
        bins_back($code !== $old
            ? "เปลี่ยนชั้น $old เป็น $code แล้ว — QR เดิมยังสแกนได้ แต่ตัวหนังสือบนฉลากเป็น $old ควรพิมพ์ใหม่"
            : "บันทึกชั้น $code แล้ว");
    }

    if ($action === 'shelf_kind') {
        $shelfId = (int)($_POST['shelf_id'] ?? 0);
        [$t, $c] = bins_kind($types, $rootCats);
        if ($t === null || $c === null) bins_back('เลือกทั้งชนิดของและหมวด', true);
        $st = $pdo->prepare("SELECT code FROM storage_shelves WHERE id = ?");
        $st->execute([$shelfId]);
        $code = $st->fetchColumn();
        if ($code === false) bins_back('ไม่พบชั้นนี้', true);
        // same rule as update_bin: a slot with items keeps the kind they were checked against
        $st = $pdo->prepare("
            SELECT b.slot FROM storage_bins b
            WHERE b.shelf_id = ? AND EXISTS (SELECT 1 FROM inventory i WHERE i.bin_id = b.id)
              AND NOT (b.item_type <=> ? AND b.category_id <=> ?)
            ORDER BY b.slot
        ");
        $st->execute([$shelfId, $t, $c]);
        $skipped = $st->fetchAll(PDO::FETCH_COLUMN);
        $pdo->prepare("
            UPDATE storage_bins b SET item_type = ?, category_id = ?
            WHERE b.shelf_id = ? AND NOT EXISTS (SELECT 1 FROM inventory i WHERE i.bin_id = b.id)
        ")->execute([$t, $c, $shelfId]);
        $msg = "ตั้งหมวดชั้น $code เป็น {$types[$t]} · {$rootCats[$c]} แล้ว";
        if ($skipped) {
            $msg .= ' — ข้าม ' . implode(', ', array_map(fn($s) => sbin_code($code, (int)$s), $skipped))
                  . ' เพราะมีของอยู่ (เอาออกก่อนถึงจะเปลี่ยนได้)';
        }
        bins_back($msg, (bool)$skipped);
    }

    if ($action === 'add_slots') {
        $shelfId = (int)($_POST['shelf_id'] ?? 0);
        $n       = (int)($_POST['count'] ?? 0);
        $st = $pdo->prepare("SELECT s.code, COALESCE(MAX(b.slot), 0) AS last_slot
                             FROM storage_shelves s LEFT JOIN storage_bins b ON b.shelf_id = s.id
                             WHERE s.id = ? GROUP BY s.id");
        $st->execute([$shelfId]);
        $shelf = $st->fetch(PDO::FETCH_ASSOC);
        if (!$shelf) bins_back('ไม่พบชั้นนี้', true);
        $last = (int)$shelf['last_slot'];
        if ($n < 1 || $last + $n > 99) bins_back('ชั้นหนึ่งมีได้สูงสุด 99 ช่อง', true);
        [$t, $c] = bins_kind($types, $rootCats);
        $now = date('Y-m-d H:i:s');
        $ins = $pdo->prepare("INSERT INTO storage_bins (shelf_id, slot, item_type, category_id, created_at) VALUES (?, ?, ?, ?, ?)");
        for ($i = $last + 1; $i <= $last + $n; $i++) $ins->execute([$shelfId, $i, $t, $c, $now]);
        bins_back('เพิ่ม ' . sbin_code($shelf['code'], $last + 1) . ($n > 1 ? ' ถึง ' . sbin_code($shelf['code'], $last + $n) : ''));
    }

    if ($action === 'update_bin') {
        $bin = sbin_get($pdo, (int)($_POST['id'] ?? 0));
        if (!$bin) bins_back('ไม่พบช่องนี้', true);
        [$t, $c] = bins_kind($types, $rootCats);
        $note = trim((string)($_POST['note'] ?? ''));
        // the kind is what the items in it were checked against — change it only once the box is empty
        $kindChanged = $t !== $bin['item_type'] || $c !== ($bin['category_id'] ? (int)$bin['category_id'] : null);
        if ($kindChanged && (int)$bin['item_count'] > 0) {
            bins_back('เปลี่ยนหมวดได้เฉพาะตอนช่องว่าง — เอาของออกก่อน (' . (int)$bin['item_count'] . ' ชิ้น)', true, (int)$bin['id']);
        }
        $pdo->prepare("UPDATE storage_bins SET item_type = ?, category_id = ?, note = ? WHERE id = ?")
            ->execute([$t, $c, $note !== '' ? mb_substr($note, 0, 200) : null, (int)$bin['id']]);
        bins_back('บันทึกช่อง ' . $bin['code'] . ' แล้ว', false, (int)$bin['id']);
    }

    if ($action === 'delete_bin') {
        $bin = sbin_get($pdo, (int)($_POST['id'] ?? 0));
        if (!$bin) bins_back('ไม่พบช่องนี้', true);
        if ((int)$bin['item_count'] > 0) bins_back('ลบได้เฉพาะช่องที่ว่าง', true, (int)$bin['id']);
        $pdo->prepare("DELETE FROM storage_bins WHERE id = ?")->execute([(int)$bin['id']]);
        bins_back('ลบช่อง ' . $bin['code'] . ' แล้ว');
    }

    if ($action === 'delete_shelf') {
        $shelfId = (int)($_POST['shelf_id'] ?? 0);
        $st = $pdo->prepare("SELECT COUNT(*) FROM inventory i JOIN storage_bins b ON b.id = i.bin_id WHERE b.shelf_id = ?");
        $st->execute([$shelfId]);
        if ((int)$st->fetchColumn() > 0) bins_back('ลบได้เฉพาะชั้นที่ทุกช่องว่าง', true);
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM storage_bins WHERE shelf_id = ?")->execute([$shelfId]);
        $pdo->prepare("DELETE FROM storage_shelves WHERE id = ?")->execute([$shelfId]);
        $pdo->commit();
        bins_back('ลบชั้นแล้ว');
    }

    if ($action === 'add_item' || $action === 'remove_item') {
        $bin = sbin_get($pdo, (int)($_POST['id'] ?? 0));
        if (!$bin) bins_back('ไม่พบช่องนี้', true);
        $itemId = (int)($_POST['item'] ?? 0);
        if ($action === 'add_item') {
            $why = sbin_move($pdo, $itemId, $bin);
            if ($why !== null) bins_back($why, true, (int)$bin['id']);
            $item = sbin_item($pdo, $itemId);
            $_SESSION['bins_flash'] = ['msg' => 'ใส่ ' . $item['name'] . ' เข้า ' . $bin['code'] . ' แล้ว', 'err' => false];
            // keep the search, to put the next one in
            header('Location: bins.php?bin=' . (int)$bin['id'] . (isset($_POST['q']) ? '&q=' . urlencode((string)$_POST['q']) : ''));
            exit;
        }
        $item = sbin_item($pdo, $itemId);
        if ($item && (int)$item['bin_id'] === (int)$bin['id']) sbin_move($pdo, $itemId, null);
        bins_back('เอาออกจาก ' . $bin['code'] . ' แล้ว', false, (int)$bin['id']);
    }

    bins_back('bad action', true);
}

$flash = $_SESSION['bins_flash'] ?? null;
unset($_SESSION['bins_flash']);

/* ── One slot ── */
$bin = null; $binItems = []; $results = []; $q = '';
if ($ready && isset($_GET['bin'])) {
    $bin = sbin_get($pdo, (int)$_GET['bin']);
    if (!$bin) { header('Location: bins.php'); exit; }
    $binItems = sbin_items($pdo, $bin);

    $q = trim((string)($_GET['q'] ?? ''));
    if ($bin['item_type'] && $bin['category_id']) {
        $where  = ["i.type = ?", "COALESCE(c.parent_id, c.id) = ?", "(i.bin_id IS NULL OR i.bin_id <> ?)", "i.status NOT IN ('sold','SOLD')"];
        $params = [$bin['item_type'], (int)$bin['category_id'], (int)$bin['id']];
        if ($q !== '') {
            $where[] = "(i.name LIKE ? OR i.sku LIKE ? OR i.asset_tag LIKE ? OR i.serial_number LIKE ?)";
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $st = $pdo->prepare("
            SELECT i.id, i.name, i.sku, i.asset_tag, i.location, s.code AS shelf_code, b.slot
            FROM inventory i
            LEFT JOIN parts_categories c ON c.id = i.category_id
            LEFT JOIN storage_bins b     ON b.id = i.bin_id
            LEFT JOIN storage_shelves s  ON s.id = b.shelf_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY i.bin_id IS NOT NULL, i.asset_tag IS NULL, i.asset_tag, i.name
            LIMIT 30
        ");
        $st->execute($params);
        $results = $st->fetchAll(PDO::FETCH_ASSOC);
    }
}

/* ── All shelves ── */
$shelves = []; $unshelved = [];
if ($ready && !$bin) {
    $rows = $pdo->query("
        SELECT s.id AS shelf_id, s.code AS shelf_code, s.name AS shelf_name,
               b.id, b.slot, b.item_type, b.category_id, c.name AS category_name, b.note,
               (SELECT COUNT(*) FROM inventory i WHERE i.bin_id = b.id) AS item_count
        FROM storage_shelves s
        LEFT JOIN storage_bins b     ON b.shelf_id = s.id
        LEFT JOIN parts_categories c ON c.id = b.category_id
        ORDER BY LENGTH(s.code), s.code, b.slot
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $sid = (int)$r['shelf_id'];
        $shelves[$sid] ??= ['id' => $sid, 'code' => $r['shelf_code'], 'name' => $r['shelf_name'], 'bins' => [], 'items' => 0];
        if ($r['id'] !== null) {
            $shelves[$sid]['bins'][] = $r;
            $shelves[$sid]['items'] += (int)$r['item_count'];
        }
    }
    // what is still waiting for a slot — the first round of sorting works this down
    $unshelved = $pdo->query("
        SELECT type, COUNT(*) FROM inventory
        WHERE bin_id IS NULL AND type IN ('machine','used') AND status NOT IN ('sold','SOLD')
        GROUP BY type
    ")->fetchAll(PDO::FETCH_KEY_PAIR);
}
$nextCode = $ready ? sbin_next_shelf_code($pdo->query("SELECT code FROM storage_shelves")->fetchAll(PDO::FETCH_COLUMN)) : 'A';

function kind_select(array $types, array $rootCats, ?string $t, $c, bool $allowEmpty = true): string {
    $o = '<select name="item_type" aria-label="ชนิดของ">' . ($allowEmpty ? '<option value="">— ชนิดของ —</option>' : '');
    foreach ($types as $k => $v) $o .= '<option value="' . $k . '"' . ($t === $k ? ' selected' : '') . '>' . h($v) . '</option>';
    $o .= '</select><select name="category_id" aria-label="หมวด">' . ($allowEmpty ? '<option value="">— หมวด —</option>' : '');
    foreach ($rootCats as $k => $v) $o .= '<option value="' . (int)$k . '"' . ((int)$c === (int)$k ? ' selected' : '') . '>' . h($v) . '</option>';
    return $o . '</select>';
}

$pageTitle = $bin ? 'ช่อง ' . $bin['code'] : 'ชั้นเก็บของ';
require_once __DIR__ . '/../templates/header_admin.php';
?>


<link rel="stylesheet" href="assets/css/bins.css?v=<?= asset_ver('/admin/inventory/assets/css/bins.css') ?>">

<div class="bn-page">

<?php if (!$ready): ?>
    <a href="index.php" class="bn-back"><span class="material-symbols-rounded">arrow_back</span> คลังอะไหล่</a>
    <h1 class="bn-title">ชั้นเก็บของ</h1>
    <div class="bn-flash is-err">ยังไม่ได้รัน <code>migration_storage_bins.sql</code> บนเซิร์ฟเวอร์นี้</div>

<?php elseif ($bin): ?>
    <!-- ════════ One slot ════════ -->
    <?php $binSet = $bin['item_type'] && $bin['category_id']; ?>
    <a href="bins.php" class="bn-back"><span class="material-symbols-rounded">arrow_back</span> ชั้นเก็บของ</a>

    <header class="bn-hero">
        <div class="bn-hero-code"><?= h($bin['code']) ?></div>
        <div class="bn-hero-info">
            <div class="bn-hero-where"><?= h($bin['shelf_name'] ?: 'ชั้น ' . $bin['shelf_code']) ?> · ช่อง <?= (int)$bin['slot'] ?></div>
            <?php if ($binSet): ?>
            <span class="bn-kind"><?= h($types[$bin['item_type']]) ?> · <?= h($bin['category_name']) ?></span>
            <?php else: ?>
            <span class="bn-kind is-unset">ยังไม่ตั้งหมวด</span>
            <?php endif; ?>
            <?php if ($bin['note']): ?><p class="bn-hero-note"><?= h($bin['note']) ?></p><?php endif; ?>
        </div>
        <div class="bn-hero-act">
            <a class="bn-btn" href="print_bins.php?ids=<?= (int)$bin['id'] ?>" target="_blank">
                <span class="material-symbols-rounded">print</span><span class="bn-btn-t">พิมพ์ฉลาก</span>
            </a>
            <button type="button" class="bn-btn" data-open="dlgBin">
                <span class="material-symbols-rounded">tune</span><span class="bn-btn-t">ตั้งค่าช่อง</span>
            </button>
        </div>
    </header>

    <?php if ($flash): ?><div class="bn-flash<?= $flash['err'] ? ' is-err' : '' ?>"><?= h($flash['msg']) ?></div><?php endif; ?>

    <div class="bn-split">
        <section class="bn-panel">
            <h2 class="bn-panel-hd">ในช่องนี้ <span class="bn-count"><?= count($binItems) ?></span></h2>
            <div class="bn-card">
                <?php if (!$binItems): ?>
                <div class="bn-empty">
                    <span class="material-symbols-rounded">inbox</span>
                    ช่องว่าง<?= $binSet ? ' — ค้นหาแล้วกด “ใส่” หรือสแกนฉลากของเข้าช่อง' : '' ?>
                </div>
                <?php endif; ?>
                <?php foreach ($binItems as $it): ?>
                <div class="bn-row">
                    <span class="bn-row-main">
                        <span class="bn-row-name"><?= h($it['name']) ?></span>
                        <span class="bn-row-sub">
                            <code><?= h($it['tag'] ?: '—') ?></code><span><?= h($it['status']) ?></span>
                            <?php if ($it['stripped']): ?><span class="bn-chip">ถูกแกะแล้ว</span><?php endif; ?>
                            <?php if ($it['mismatch']): ?><span class="bn-chip is-err">ไม่ตรงหมวดช่อง</span><?php endif; ?>
                        </span>
                    </span>
                    <form method="post" class="bn-row-act">
                        <input type="hidden" name="action" value="remove_item">
                        <input type="hidden" name="id" value="<?= (int)$bin['id'] ?>">
                        <input type="hidden" name="item" value="<?= $it['id'] ?>">
                        <button type="submit" class="bn-pill is-danger">เอาออก</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="bn-panel">
            <h2 class="bn-panel-hd">ใส่ของเข้าช่องนี้</h2>
            <?php if (!$binSet): ?>
            <div class="bn-card">
                <div class="bn-empty">
                    <span class="material-symbols-rounded">rule</span>
                    ตั้งชนิดของและหมวดให้ช่องนี้ก่อน ถึงจะใส่ของได้
                    <button type="button" class="bn-btn is-primary" data-open="dlgBin">ตั้งค่าช่อง</button>
                </div>
            </div>
            <?php else: ?>
            <form method="get" class="bn-search">
                <input type="hidden" name="bin" value="<?= (int)$bin['id'] ?>">
                <span class="material-symbols-rounded">search</span>
                <input type="search" name="q" value="<?= h($q) ?>" placeholder="ชื่อ, asset tag, SKU, serial" autocomplete="off" enterkeyhint="search">
            </form>
            <p class="bn-hint">เฉพาะ<?= h($types[$bin['item_type']]) ?> หมวด <?= h($bin['category_name']) ?> · ของที่ยังไม่เข้าช่องขึ้นก่อน</p>
            <div class="bn-card bn-results">
                <?php if (!$results): ?><div class="bn-empty">ไม่พบรายการ</div><?php endif; ?>
                <?php foreach ($results as $r): ?>
                <div class="bn-row">
                    <span class="bn-row-main">
                        <span class="bn-row-name"><?= h($r['name']) ?></span>
                        <span class="bn-row-sub">
                            <code><?= h($r['asset_tag'] ?: ($r['sku'] ?: '—')) ?></code>
                            <?php if ($r['shelf_code']): ?>
                            <span>อยู่ <b><?= h(sbin_code($r['shelf_code'], (int)$r['slot'])) ?></b></span>
                            <?php else: ?>
                            <span class="is-warn">ยังไม่เข้าช่อง<?= $r['location'] ? ' · เดิม ' . h($r['location']) : '' ?></span>
                            <?php endif; ?>
                        </span>
                    </span>
                    <form method="post" class="bn-row-act">
                        <input type="hidden" name="action" value="add_item">
                        <input type="hidden" name="id" value="<?= (int)$bin['id'] ?>">
                        <input type="hidden" name="item" value="<?= (int)$r['id'] ?>">
                        <input type="hidden" name="q" value="<?= h($q) ?>">
                        <button type="submit" class="bn-pill<?= $r['shelf_code'] ? '' : ' is-primary' ?>"><?= $r['shelf_code'] ? 'ย้ายมา' : 'ใส่' ?></button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>
    </div>

    <!-- slot settings -->
    <dialog class="bn-dlg" id="dlgBin">
        <div class="bn-dlg-hd">
            <h2>ตั้งค่าช่อง <?= h($bin['code']) ?></h2>
            <button type="button" class="bn-x" data-close aria-label="ปิด"><span class="material-symbols-rounded">close</span></button>
        </div>
        <form method="post" class="bn-dlg-sec">
            <input type="hidden" name="action" value="update_bin">
            <input type="hidden" name="id" value="<?= (int)$bin['id'] ?>">
            <label class="bn-lbl">ของที่ใส่ช่องนี้ได้</label>
            <div class="bn-2col"><?= kind_select($types, $rootCats, $bin['item_type'], $bin['category_id'], true) ?></div>
            <?php if ((int)$bin['item_count'] > 0): ?>
            <p class="bn-hint">มีของอยู่ <?= (int)$bin['item_count'] ?> ชิ้น — เปลี่ยนชนิด/หมวดได้เมื่อช่องว่างเท่านั้น</p>
            <?php endif; ?>
            <label class="bn-lbl" for="binNote">โน้ต</label>
            <input type="text" id="binNote" name="note" value="<?= h($bin['note']) ?>" maxlength="200" placeholder="ไม่บังคับ">
            <button type="submit" class="bn-btn is-primary is-block">บันทึก</button>
        </form>
        <div class="bn-dlg-sec is-danger">
            <?php if ((int)$bin['item_count'] === 0): ?>
            <form method="post" onsubmit="return confirm('ลบช่อง <?= h($bin['code']) ?>? ฉลากที่ติดอยู่จะสแกนไม่เจอ')">
                <input type="hidden" name="action" value="delete_bin">
                <input type="hidden" name="id" value="<?= (int)$bin['id'] ?>">
                <button type="submit" class="bn-btn is-danger is-block">
                    <span class="material-symbols-rounded">delete</span> ลบช่องนี้
                </button>
            </form>
            <?php else: ?>
            <p class="bn-hint">ลบช่องได้เมื่อช่องว่าง — เอาของออกก่อน</p>
            <?php endif; ?>
        </div>
    </dialog>

<?php else: ?>
    <!-- ════════ All shelves ════════ -->
    <a href="index.php" class="bn-back"><span class="material-symbols-rounded">arrow_back</span> คลังอะไหล่</a>
    <header class="bn-top">
        <div>
            <h1 class="bn-title">ชั้นเก็บของ</h1>
            <p class="bn-stats">
                ยังไม่เข้าช่อง · เครื่องซาก <b class="<?= !empty($unshelved['machine']) ? 'is-warn' : '' ?>"><?= number_format((int)($unshelved['machine'] ?? 0)) ?></b>
                · มือสอง <b><?= number_format((int)($unshelved['used'] ?? 0)) ?></b>
            </p>
        </div>
        <?php if ($shelves): ?>
        <button type="button" class="bn-btn is-primary" data-open="dlgCreate">
            <span class="material-symbols-rounded">add</span><span class="bn-btn-t">สร้างชั้นใหม่</span>
        </button>
        <?php endif; ?>
    </header>

    <?php if ($flash): ?><div class="bn-flash<?= $flash['err'] ? ' is-err' : '' ?>"><?= h($flash['msg']) ?></div><?php endif; ?>

    <?php if (!$shelves): ?>
    <div class="bn-card bn-empty is-big">
        <span class="material-symbols-rounded">shelves</span>
        <b>ยังไม่มีชั้นเก็บของ</b>
        ช่องจะชื่อ <?= h(sbin_code($nextCode, 1)) ?>, <?= h(sbin_code($nextCode, 2)) ?>, … ติดฉลาก QR หน้ากล่องแล้วสแกนใส่ของได้เลย
        <button type="button" class="bn-btn is-primary" data-open="dlgCreate"><span class="material-symbols-rounded">add</span> สร้างชั้นแรก</button>
    </div>
    <?php endif; ?>

    <?php foreach ($shelves as $s):
        // one kind for the whole shelf? say it once in the header, not on every tile
        $kinds = array_unique(array_map(fn($b) => $b['item_type'] . ':' . $b['category_id'], $s['bins']));
        $common = null;
        if (count($kinds) === 1 && $s['bins'][0]['item_type'] && $s['bins'][0]['category_id']) $common = $s['bins'][0];
        [$kt, $kc] = count($kinds) === 1 ? explode(':', reset($kinds)) + [null, null] : [null, null];
        $filled = count(array_filter($s['bins'], fn($b) => (int)$b['item_count'] > 0));
    ?>
    <section class="bn-shelf">
        <div class="bn-shelf-hd">
            <span class="bn-shelf-badge"><?= h($s['code']) ?></span>
            <div class="bn-shelf-info">
                <h2><?= $s['name'] ? h($s['name']) : 'ชั้น ' . h($s['code']) ?></h2>
                <span class="bn-dim">
                    <?= count($s['bins']) ?> ช่อง · ใช้แล้ว <?= $filled ?> · <?= $s['items'] ?> ชิ้น
                    <?php if ($common): ?> · <?= h($types[$common['item_type']] . ' · ' . $common['category_name']) ?><?php endif; ?>
                </span>
            </div>
            <div class="bn-shelf-act">
                <?php if ($s['bins']): ?>
                <a class="bn-btn" href="print_bins.php?ids=<?= h(implode(',', array_column($s['bins'], 'id'))) ?>" target="_blank" aria-label="พิมพ์ฉลากทั้งชั้น">
                    <span class="material-symbols-rounded">print</span><span class="bn-btn-t">ฉลากทั้งชั้น</span>
                </a>
                <?php endif; ?>
                <button type="button" class="bn-btn" data-open="dlgShelf<?= $s['id'] ?>" aria-label="ตั้งค่าชั้น">
                    <span class="material-symbols-rounded">tune</span><span class="bn-btn-t">ตั้งค่าชั้น</span>
                </button>
            </div>
        </div>
        <?php if ($s['bins']): ?>
        <div class="bn-grid">
            <?php foreach ($s['bins'] as $b):
                $set = $b['item_type'] && $b['category_id'];
                $n = (int)$b['item_count'];
            ?>
            <a class="bn-slot<?= !$set ? ' is-unset' : '' ?><?= $n ? ' is-used' : '' ?>" href="bins.php?bin=<?= (int)$b['id'] ?>">
                <span class="bn-slot-code"><?= h(sbin_code($s['code'], (int)$b['slot'])) ?></span>
                <span class="bn-slot-n"><?= $n ? $n . ' ชิ้น' : 'ว่าง' ?></span>
                <?php if (!$set): ?>
                <span class="bn-slot-kind">ยังไม่ตั้งหมวด</span>
                <?php elseif (!$common): ?>
                <span class="bn-slot-kind"><?= h($types[$b['item_type']] . ' · ' . $b['category_name']) ?></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="bn-card bn-empty">ชั้นนี้ยังไม่มีช่อง — เพิ่มจาก “ตั้งค่าชั้น”</div>
        <?php endif; ?>
    </section>

    <!-- shelf settings -->
    <dialog class="bn-dlg" id="dlgShelf<?= $s['id'] ?>">
        <div class="bn-dlg-hd">
            <h2>ตั้งค่าชั้น <?= h($s['code']) ?></h2>
            <button type="button" class="bn-x" data-close aria-label="ปิด"><span class="material-symbols-rounded">close</span></button>
        </div>

        <form method="post" class="bn-dlg-sec"
              onsubmit="return this.code.value.trim().toUpperCase() === this.code.defaultValue || confirm('เปลี่ยนรหัสชั้นจาก <?= h($s['code']) ?>? QR เดิมยังสแกนได้ แต่ตัวหนังสือบนฉลากจะไม่ตรง ต้องพิมพ์ใหม่')">
            <input type="hidden" name="action" value="rename_shelf">
            <input type="hidden" name="shelf_id" value="<?= $s['id'] ?>">
            <h3>ชื่อชั้น</h3>
            <div class="bn-codename">
                <input type="text" name="code" value="<?= h($s['code']) ?>" maxlength="2" pattern="[A-Za-z]{1,2}" required class="bn-in-code" aria-label="รหัสชั้น">
                <input type="text" name="name" value="<?= h($s['name']) ?>" maxlength="100" placeholder="ชื่อเรียก เช่น ชั้นหลังร้าน" aria-label="ชื่อเรียกชั้น">
            </div>
            <p class="bn-hint">เปลี่ยนรหัส (A → B) แล้ว QR เดิมยังสแกนได้ แต่ต้องพิมพ์ฉลากใหม่ให้ตัวหนังสือตรง</p>
            <button type="submit" class="bn-btn is-block">บันทึกชื่อ</button>
        </form>

        <?php if ($s['bins']): ?>
        <form method="post" class="bn-dlg-sec">
            <input type="hidden" name="action" value="shelf_kind">
            <input type="hidden" name="shelf_id" value="<?= $s['id'] ?>">
            <h3>หมวดทุกช่อง</h3>
            <div class="bn-2col"><?= kind_select($types, $rootCats, $kt ?: null, $kc ?: null, true) ?></div>
            <p class="bn-hint">ตั้งให้ทุกช่องพร้อมกัน — ช่องที่มีของอยู่จะถูกข้าม</p>
            <button type="submit" class="bn-btn is-block">ตั้งหมวดทั้งชั้น</button>
        </form>
        <?php endif; ?>

        <form method="post" class="bn-dlg-sec">
            <input type="hidden" name="action" value="add_slots">
            <input type="hidden" name="shelf_id" value="<?= $s['id'] ?>">
            <h3>เพิ่มช่อง</h3>
            <div class="bn-addrow">
                <label>จำนวน <input type="number" name="count" value="1" min="1" max="99" inputmode="numeric"></label>
            </div>
            <div class="bn-2col"><?= kind_select($types, $rootCats, $kt ?: null, $kc ?: null, true) ?></div>
            <button type="submit" class="bn-btn is-block">เพิ่มช่อง</button>
        </form>

        <div class="bn-dlg-sec is-danger">
            <?php if ($s['items'] === 0): ?>
            <form method="post" onsubmit="return confirm('ลบชั้น <?= h($s['code']) ?> และทุกช่อง? ฉลากที่ติดอยู่จะสแกนไม่เจอ')">
                <input type="hidden" name="action" value="delete_shelf">
                <input type="hidden" name="shelf_id" value="<?= $s['id'] ?>">
                <button type="submit" class="bn-btn is-danger is-block">
                    <span class="material-symbols-rounded">delete</span> ลบชั้น <?= h($s['code']) ?> ทั้งชั้น
                </button>
            </form>
            <?php else: ?>
            <p class="bn-hint">ลบชั้นได้เมื่อทุกช่องว่าง — ตอนนี้มีของอยู่ <?= $s['items'] ?> ชิ้น</p>
            <?php endif; ?>
        </div>
    </dialog>
    <?php endforeach; ?>

    <!-- new shelf -->
    <dialog class="bn-dlg" id="dlgCreate">
        <div class="bn-dlg-hd">
            <h2>สร้างชั้นใหม่</h2>
            <button type="button" class="bn-x" data-close aria-label="ปิด"><span class="material-symbols-rounded">close</span></button>
        </div>
        <form method="post" class="bn-dlg-sec">
            <input type="hidden" name="action" value="create_shelf">
            <div class="bn-addrow">
                <label>รหัสชั้น <input type="text" name="code" value="<?= h($nextCode) ?>" maxlength="2" pattern="[A-Za-z]{1,2}" required class="bn-in-code"></label>
                <label>จำนวนช่อง <input type="number" name="slots" value="10" min="1" max="99" inputmode="numeric" required></label>
            </div>
            <input type="text" name="name" maxlength="100" placeholder="ชื่อเรียกชั้น (ไม่บังคับ) เช่น ชั้นหลังร้าน" aria-label="ชื่อเรียกชั้น">
            <label class="bn-lbl">ของที่ใส่ได้</label>
            <div class="bn-2col"><?= kind_select($types, $rootCats, 'machine', null, true) ?></div>
            <p class="bn-hint">ตั้งให้ทุกช่องพร้อมกัน แก้รายช่องทีหลังได้</p>
            <button type="submit" class="bn-btn is-primary is-block">สร้างชั้น</button>
        </form>
    </dialog>
<?php endif; ?>

</div>

<script>
/* dialogs: [data-open=<id>] opens, [data-close] or a tap on the backdrop closes */
(function () {
    document.querySelectorAll('[data-open]').forEach(function (b) {
        b.addEventListener('click', function () {
            var d = document.getElementById(b.dataset.open);
            if (d && d.showModal) d.showModal();
        });
    });
    document.querySelectorAll('.bn-dlg').forEach(function (d) {
        d.addEventListener('click', function (e) {
            if (e.target === d || e.target.closest('[data-close]')) d.close();
        });
    });
})();
</script>

<?php include '../templates/footer_admin.php'; ?>
