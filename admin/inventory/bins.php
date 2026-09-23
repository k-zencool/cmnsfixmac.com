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
    <a href="index.php" class="cmns-back-link"><span class="material-symbols-rounded">arrow_back</span> คลังอะไหล่</a>
    <h1 class="bn-title">ชั้นเก็บของ</h1>
    <div class="bn-flash is-err">ยังไม่ได้รัน <code>migration_storage_bins.sql</code> บนเซิร์ฟเวอร์นี้</div>

<?php elseif ($bin): ?>
    <!-- ════════ One slot ════════ -->
    <a href="bins.php" class="cmns-back-link"><span class="material-symbols-rounded">arrow_back</span> ชั้นเก็บของ</a>
    <div class="bn-head">
        <div>
            <h1 class="bn-code"><?= h($bin['code']) ?></h1>
            <p class="bn-sub">
                <?= h($bin['shelf_name'] ?: 'ชั้น ' . $bin['shelf_code']) ?> · ช่อง <?= (int)$bin['slot'] ?>
                <?php if ($bin['item_type'] && $bin['category_id']): ?>
                · <b><?= h($types[$bin['item_type']]) ?> · <?= h($bin['category_name']) ?></b>
                <?php else: ?>
                · <b class="is-warn">ยังไม่ตั้งหมวด</b>
                <?php endif; ?>
            </p>
            <?php if ($bin['note']): ?><p class="bn-note"><?= h($bin['note']) ?></p><?php endif; ?>
        </div>
        <a class="bn-btn" href="print_bins.php?ids=<?= (int)$bin['id'] ?>" target="_blank">
            <span class="material-symbols-rounded">print</span> พิมพ์ฉลาก
        </a>
    </div>

    <?php if ($flash): ?><div class="bn-flash<?= $flash['err'] ? ' is-err' : '' ?>"><?= h($flash['msg']) ?></div><?php endif; ?>

    <h2 class="bn-h2">ในช่องนี้ · <?= count($binItems) ?> ชิ้น</h2>
    <div class="bn-card">
        <?php if (!$binItems): ?><p class="bn-empty">ช่องว่าง</p><?php endif; ?>
        <?php foreach ($binItems as $it): ?>
        <div class="bn-row">
            <span class="bn-row-main">
                <span class="bn-row-name"><?= h($it['name']) ?></span>
                <span class="bn-row-sub">
                    <code><?= h($it['tag'] ?: '—') ?></code> · <?= h($it['status']) ?>
                    <?php if ($it['stripped']): ?> · <span class="bn-chip">ถูกแกะแล้ว</span><?php endif; ?>
                    <?php if ($it['mismatch']): ?> · <span class="bn-chip is-err">ไม่ตรงหมวดช่อง</span><?php endif; ?>
                </span>
            </span>
            <form method="post" class="bn-row-act">
                <input type="hidden" name="action" value="remove_item">
                <input type="hidden" name="id" value="<?= (int)$bin['id'] ?>">
                <input type="hidden" name="item" value="<?= $it['id'] ?>">
                <button type="submit" class="bn-link is-danger">เอาออก</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($bin['item_type'] && $bin['category_id']): ?>
    <h2 class="bn-h2">ใส่ของเข้าช่องนี้</h2>
    <form method="get" class="bn-search">
        <input type="hidden" name="bin" value="<?= (int)$bin['id'] ?>">
        <span class="material-symbols-rounded">search</span>
        <input type="search" name="q" value="<?= h($q) ?>" placeholder="ชื่อ, asset tag, SKU, serial" autocomplete="off">
    </form>
    <p class="bn-hint">แสดงเฉพาะ<?= h($types[$bin['item_type']]) ?> หมวด <?= h($bin['category_name']) ?> · ของที่ยังไม่เข้าช่องขึ้นก่อน</p>
    <div class="bn-card">
        <?php if (!$results): ?><p class="bn-empty">ไม่พบรายการ</p><?php endif; ?>
        <?php foreach ($results as $r): ?>
        <div class="bn-row">
            <span class="bn-row-main">
                <span class="bn-row-name"><?= h($r['name']) ?></span>
                <span class="bn-row-sub">
                    <code><?= h($r['asset_tag'] ?: ($r['sku'] ?: '—')) ?></code>
                    <?php if ($r['shelf_code']): ?>
                    · อยู่ <b><?= h(sbin_code($r['shelf_code'], (int)$r['slot'])) ?></b>
                    <?php else: ?>
                    · <span class="is-warn">ยังไม่เข้าช่อง</span><?= $r['location'] ? ' (เดิม: ' . h($r['location']) . ')' : '' ?>
                    <?php endif; ?>
                </span>
            </span>
            <form method="post" class="bn-row-act">
                <input type="hidden" name="action" value="add_item">
                <input type="hidden" name="id" value="<?= (int)$bin['id'] ?>">
                <input type="hidden" name="item" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="q" value="<?= h($q) ?>">
                <button type="submit" class="bn-link"><?= $r['shelf_code'] ? 'ย้ายมา' : 'ใส่' ?></button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <h2 class="bn-h2">ตั้งค่าช่อง</h2>
    <form method="post" class="bn-card bn-form">
        <input type="hidden" name="action" value="update_bin">
        <input type="hidden" name="id" value="<?= (int)$bin['id'] ?>">
        <div class="bn-form-row">
            <?= kind_select($types, $rootCats, $bin['item_type'], $bin['category_id'], true) ?>
        </div>
        <?php if ((int)$bin['item_count'] > 0): ?>
        <p class="bn-hint">เปลี่ยนชนิด/หมวดได้เมื่อช่องว่างเท่านั้น</p>
        <?php endif; ?>
        <input type="text" name="note" value="<?= h($bin['note']) ?>" maxlength="200" placeholder="โน้ต (ไม่บังคับ)">
        <button type="submit" class="bn-btn is-primary">บันทึก</button>
    </form>
    <?php if ((int)$bin['item_count'] === 0): ?>
    <form method="post" class="bn-del" onsubmit="return confirm('ลบช่อง <?= h($bin['code']) ?>? ฉลากที่ติดอยู่จะสแกนไม่เจอ')">
        <input type="hidden" name="action" value="delete_bin">
        <input type="hidden" name="id" value="<?= (int)$bin['id'] ?>">
        <button type="submit" class="bn-link is-danger">ลบช่องนี้</button>
    </form>
    <?php endif; ?>

<?php else: ?>
    <!-- ════════ All shelves ════════ -->
    <a href="index.php" class="cmns-back-link"><span class="material-symbols-rounded">arrow_back</span> คลังอะไหล่</a>
    <h1 class="bn-title">ชั้นเก็บของ</h1>
    <p class="bn-stats">
        ยังไม่เข้าช่อง: เครื่องซาก <b class="<?= !empty($unshelved['machine']) ? 'is-warn' : '' ?>"><?= number_format((int)($unshelved['machine'] ?? 0)) ?></b>
        · อะไหล่มือสอง <b><?= number_format((int)($unshelved['used'] ?? 0)) ?></b>
    </p>

    <?php if ($flash): ?><div class="bn-flash<?= $flash['err'] ? ' is-err' : '' ?>"><?= h($flash['msg']) ?></div><?php endif; ?>

    <?php if (!$shelves): ?>
    <p class="bn-hint">ยังไม่มีชั้น — สร้างชั้นแรกด้านล่าง ช่องจะชื่อ <?= h(sbin_code($nextCode, 1)) ?>, <?= h(sbin_code($nextCode, 2)) ?>, …</p>
    <?php endif; ?>

    <?php foreach ($shelves as $s): ?>
    <section class="bn-shelf">
        <div class="bn-shelf-hd">
            <div>
                <h2>ชั้น <?= h($s['code']) ?><?= $s['name'] ? ' · ' . h($s['name']) : '' ?></h2>
                <span class="bn-dim"><?= count($s['bins']) ?> ช่อง · <?= $s['items'] ?> ชิ้น</span>
            </div>
            <?php if ($s['bins']): ?>
            <a class="bn-btn" href="print_bins.php?ids=<?= h(implode(',', array_column($s['bins'], 'id'))) ?>" target="_blank">
                <span class="material-symbols-rounded">print</span> ฉลากทั้งชั้น
            </a>
            <?php endif; ?>
        </div>
        <div class="bn-grid">
            <?php foreach ($s['bins'] as $b): ?>
            <a class="bn-slot<?= (!$b['item_type'] || !$b['category_id']) ? ' is-unset' : '' ?>" href="bins.php?bin=<?= (int)$b['id'] ?>">
                <span class="bn-slot-code"><?= h(sbin_code($s['code'], (int)$b['slot'])) ?></span>
                <span class="bn-slot-kind">
                    <?= ($b['item_type'] && $b['category_id']) ? h($types[$b['item_type']] . ' · ' . $b['category_name']) : 'ยังไม่ตั้งหมวด' ?>
                </span>
                <span class="bn-slot-n"><?= (int)$b['item_count'] ?> ชิ้น</span>
            </a>
            <?php endforeach; ?>
        </div>
        <details class="bn-more">
            <summary>เพิ่มช่อง / ลบชั้น</summary>
            <form method="post" class="bn-form">
                <input type="hidden" name="action" value="add_slots">
                <input type="hidden" name="shelf_id" value="<?= $s['id'] ?>">
                <div class="bn-form-row">
                    <label>เพิ่ม <input type="number" name="count" value="1" min="1" max="99" inputmode="numeric"> ช่อง</label>
                    <?= kind_select($types, $rootCats, null, null, true) ?>
                </div>
                <button type="submit" class="bn-btn">เพิ่มช่อง</button>
            </form>
            <?php if ($s['items'] === 0): ?>
            <form method="post" class="bn-del" onsubmit="return confirm('ลบชั้น <?= h($s['code']) ?> และทุกช่อง? ฉลากที่ติดอยู่จะสแกนไม่เจอ')">
                <input type="hidden" name="action" value="delete_shelf">
                <input type="hidden" name="shelf_id" value="<?= $s['id'] ?>">
                <button type="submit" class="bn-link is-danger">ลบชั้น <?= h($s['code']) ?></button>
            </form>
            <?php endif; ?>
        </details>
    </section>
    <?php endforeach; ?>

    <h2 class="bn-h2">สร้างชั้นใหม่</h2>
    <form method="post" class="bn-card bn-form">
        <input type="hidden" name="action" value="create_shelf">
        <div class="bn-form-row">
            <label>รหัสชั้น <input type="text" name="code" value="<?= h($nextCode) ?>" maxlength="2" pattern="[A-Za-z]{1,2}" required class="bn-in-code"></label>
            <label>จำนวนช่อง <input type="number" name="slots" value="10" min="1" max="99" inputmode="numeric" required></label>
        </div>
        <input type="text" name="name" maxlength="100" placeholder="ชื่อเรียกชั้น (ไม่บังคับ) เช่น ชั้นหลังร้าน">
        <div class="bn-form-row">
            <?= kind_select($types, $rootCats, 'machine', null, true) ?>
        </div>
        <p class="bn-hint">ชนิด/หมวดตั้งให้ทุกช่องพร้อมกัน แก้รายช่องทีหลังได้</p>
        <button type="submit" class="bn-btn is-primary">สร้างชั้น</button>
    </form>
<?php endif; ?>

</div>

<?php include '../templates/footer_admin.php'; ?>
