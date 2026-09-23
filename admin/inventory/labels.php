<?php
/********************************************************************
 * admin/inventory/labels.php  –  QR label manager for parts
 *
 * Pick items (search / category / "never printed"), print their QR
 * labels (CMNS:P-<id>, scanned by admin/scan) on A4 sticker paper, and
 * keep a history so the whole stock can be labelled over several days
 * without losing track. One label per inventory row: per SKU for NEW /
 * USED parts, per unit for donor machines (asset tag on the label).
 *
 * Everything here needs parts.manage. Printing logs a run, then hands
 * off to print_labels.php?run= — a refresh there re-renders, never
 * re-logs (same flow as tracking/stickers.php).
 ********************************************************************/

session_start();
date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/part_label_lib.php';
require_login();
require_perms(['parts.manage']);

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$perSheet  = plb_per_sheet();
$maxItems  = 500;   // per run; ~7 sheets at 1 copy
$maxCopies = 10;
$errorMsg  = '';

/* History tables may not exist yet on a server that has not run the
   migration — the page still lists, but printing needs the log. */
$hasLog = true;
try { $pdo->query("SELECT 1 FROM part_label_runs LIMIT 1"); }
catch (PDOException $e) { $hasLog = false; }

/* NEW-part names that more than one item shares (trimmed, case-insensitive) */
function lbl_dup_names(PDO $pdo): array {
    $out = [];
    foreach ($pdo->query("SELECT LOWER(TRIM(name)) FROM inventory WHERE type = 'new'
                          GROUP BY LOWER(TRIM(name)) HAVING COUNT(*) > 1")->fetchAll(PDO::FETCH_COLUMN) as $n) {
        $out[$n] = true;
    }
    return $out;
}

/* ── POST action=rename (fetch, JSON): fix a messy name before printing ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'rename') {
    header('Content-Type: application/json; charset=utf-8');
    $id   = (int)($_POST['id'] ?? 0);
    $name = trim(preg_replace('/\s+/u', ' ', (string)($_POST['name'] ?? '')));
    if ($id < 1 || $name === '' || mb_strlen($name) > 200) {
        echo json_encode(['ok' => false, 'msg' => 'ชื่อต้องมี 1–200 ตัวอักษร']);
        exit;
    }
    $st = $pdo->prepare("UPDATE inventory SET name = ? WHERE id = ? AND type = 'new'");
    $st->execute([$name, $id]);
    $dups = lbl_dup_names($pdo);
    echo json_encode(['ok' => true, 'name' => $name, 'dup' => isset($dups[mb_strtolower($name)])], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── POST: log the run, then hand off to the print sheet ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids    = plb_parse_ids((string)($_POST['ids'] ?? ''), $maxItems);
    $copies = (int)($_POST['copies'] ?? 1);
    $slot   = max(1, min($perSheet, (int)($_POST['start_slot'] ?? 1)));

    if (!$ids) {
        $errorMsg = 'ยังไม่ได้เลือกรายการ';
    } elseif ($copies < 1 || $copies > $maxCopies) {
        $errorMsg = "ดวงต่อรายการต้องอยู่ระหว่าง 1–$maxCopies";
    } elseif (!$hasLog) {
        $errorMsg = 'ยังไม่ได้รัน migration_part_label_prints.sql บนเซิร์ฟเวอร์นี้';
    } else {
        // only ids that still exist
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("SELECT id FROM inventory WHERE id IN ($in)");
        $st->execute($ids);
        $exists = array_flip(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)));
        $ids = array_values(array_filter($ids, fn($i) => isset($exists[$i])));

        if (!$ids) {
            $errorMsg = 'ไม่พบรายการที่เลือกในระบบ';
        } else {
            $pdo->beginTransaction();
            // created_at from PHP (Asia/Bangkok) — the column default is the DB server's clock
            $pdo->prepare("INSERT INTO part_label_runs (copies, item_count, start_slot, admin_id, admin_name, created_at) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$copies, count($ids), $slot, $_SESSION['admin_id'] ?? null,
                           $_SESSION['admin_username'] ?? ($_SESSION['admin_name'] ?? null), date('Y-m-d H:i:s')]);
            $runId = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare("INSERT INTO part_label_run_items (run_id, inventory_id, sort) VALUES (?, ?, ?)");
            foreach ($ids as $i => $id) $ins->execute([$runId, $id, $i]);
            $pdo->commit();

            header('Location: print_labels.php?run=' . $runId);
            exit;
        }
    }
}

/* ── Filters ── */
$kinds = ['new' => 'ของใหม่', 'machine' => 'เครื่องซาก', 'used' => 'มือสอง'];
$kind  = isset($kinds[$_GET['kind'] ?? '']) ? $_GET['kind'] : 'new';
$isUnit = $kind === 'machine';   // one physical unit per row — no qty, has asset tag
$q     = trim((string)($_GET['q'] ?? ''));
$cat   = (int)($_GET['cat'] ?? 0);
$show  = ($_GET['show'] ?? 'unprinted') === 'all' ? 'all' : 'unprinted';
$page  = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

$cats = $pdo->query("SELECT id, name FROM parts_categories WHERE parent_id IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// a sold machine has left the shop — nothing to stick a label on
$where  = ["i.type = ?", "i.status NOT IN ('sold','SOLD')"];
$params = [$kind];
if ($q !== '') {
    $where[] = "(i.name LIKE ? OR i.sku LIKE ? OR i.part_number LIKE ? OR i.asset_tag LIKE ? OR i.serial_number LIKE ?)";
    $like = "%$q%";
    array_push($params, $like, $like, $like, $like, $like);
}
if ($cat > 0) {
    // category tree is two levels deep (see index.php stats)
    $where[] = "(c.id = ? OR c.parent_id = ?)";
    array_push($params, $cat, $cat);
}
$lastJoin = $hasLog
    ? "LEFT JOIN (SELECT ri.inventory_id, MAX(r.created_at) AS last_at, COUNT(*) AS times
                  FROM part_label_run_items ri JOIN part_label_runs r ON r.id = ri.run_id
                  GROUP BY ri.inventory_id) lp ON lp.inventory_id = i.id"
    : "LEFT JOIN (SELECT NULL AS inventory_id, NULL AS last_at, 0 AS times) lp ON 1 = 0";
if ($show === 'unprinted') $where[] = "lp.last_at IS NULL";

$from = "FROM inventory i
         LEFT JOIN parts_categories c ON c.id = i.category_id
         $lastJoin
         WHERE " . implode(' AND ', $where);

$st = $pdo->prepare("SELECT i.id $from ORDER BY i.id DESC LIMIT 1000");
$st->execute($params);
$allIds = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));   // for "เลือกทั้งหมด"

$st = $pdo->prepare("SELECT COUNT(*) $from");
$st->execute($params);
$total = (int)$st->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page  = min($page, $pages);

$st = $pdo->prepare("
    SELECT i.id, i.name, i.sku, i.asset_tag, i.serial_number, i.status, i.compatible_models, c.name AS cat_name, lp.last_at, lp.times,
           COALESCE((SELECT SUM(l.qty_remaining) FROM inventory_lots l
                     WHERE l.inventory_id = i.id AND l.qty_remaining > 0), 0) AS qty
    $from
    ORDER BY i.id DESC
    LIMIT $perPage OFFSET " . (($page - 1) * $perPage));
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
$dupNames = lbl_dup_names($pdo);

/* One-line stats (the whole tab, not the current filter) */
$st = $pdo->prepare("SELECT COUNT(*) FROM inventory WHERE type = ? AND status NOT IN ('sold','SOLD')");
$st->execute([$kind]);
$statNew = (int)$st->fetchColumn();
$statUnprinted = $statNew;
$history = [];
if ($hasLog) {
    $st = $pdo->prepare("
        SELECT COUNT(*) FROM inventory i
        WHERE i.type = ? AND i.status NOT IN ('sold','SOLD')
          AND NOT EXISTS (SELECT 1 FROM part_label_run_items ri WHERE ri.inventory_id = i.id)
    ");
    $st->execute([$kind]);
    $statUnprinted = (int)$st->fetchColumn();
    $history = $pdo->query("SELECT * FROM part_label_runs ORDER BY id DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
}

function qs(array $over): string {
    $base = ['kind' => $_GET['kind'] ?? '', 'q' => $_GET['q'] ?? '', 'cat' => $_GET['cat'] ?? '', 'show' => $_GET['show'] ?? 'unprinted', 'page' => $_GET['page'] ?? ''];
    return 'labels.php?' . http_build_query(array_filter(array_merge($base, $over), fn($v) => $v !== '' && $v !== null && $v !== 0));
}

$pageTitle = 'ฉลาก QR';
require_once __DIR__ . '/../templates/header_admin.php';
?>

<link rel="stylesheet" href="assets/css/labels.css?v=<?= asset_ver('/admin/inventory/assets/css/labels.css') ?>">

<div class="lbl-page">

    <a href="index.php" class="cmns-back-link"><span class="material-symbols-rounded">arrow_back</span> คลังอะไหล่</a>
    <h1 class="lbl-title">ฉลาก QR</h1>
    <div class="lbl-seg lbl-kinds" role="tablist">
        <?php foreach ($kinds as $k => $label): ?>
        <a href="<?= h(qs(['kind' => $k === 'new' ? '' : $k, 'q' => '', 'cat' => '', 'page' => ''])) ?>" class="<?= $kind === $k ? 'is-on' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>
    <p class="lbl-stats">
        <?= $kinds[$kind] ?> <b><?= number_format($statNew) ?></b> <?= $isUnit ? 'เครื่อง' : 'รายการ' ?> ·
        ยังไม่เคยพิมพ์ <b class="<?= $statUnprinted ? 'is-warn' : '' ?>"><?= number_format($statUnprinted) ?></b>
    </p>

    <?php if (!$hasLog): ?>
    <div class="lbl-alert">ยังไม่ได้รัน <code>migration_part_label_prints.sql</code> — ดูรายการได้ แต่พิมพ์ไม่ได้จนกว่าจะรัน</div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
    <div class="lbl-alert"><?= h($errorMsg) ?></div>
    <?php endif; ?>

    <!-- ── Filters ── -->
    <form method="get" class="lbl-filter">
        <div class="lbl-search">
            <span class="material-symbols-rounded">search</span>
            <input type="search" name="q" value="<?= h($q) ?>" placeholder="<?= $isUnit ? 'ชื่อ, asset tag, serial' : 'ชื่อ, SKU, Part No.' ?>" autocomplete="off">
        </div>
        <select name="cat" onchange="this.form.submit()" aria-label="หมวดหมู่">
            <option value="">ทุกหมวด</option>
            <?php foreach ($cats as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="hidden" name="show" value="<?= $show ?>">
        <?php if ($kind !== 'new'): ?><input type="hidden" name="kind" value="<?= $kind ?>"><?php endif; ?>
    </form>
    <div class="lbl-seg" role="tablist">
        <a href="<?= h(qs(['show' => 'unprinted', 'page' => ''])) ?>" class="<?= $show === 'unprinted' ? 'is-on' : '' ?>">ยังไม่เคยพิมพ์</a>
        <a href="<?= h(qs(['show' => 'all', 'page' => ''])) ?>" class="<?= $show === 'all' ? 'is-on' : '' ?>">ทั้งหมด</a>
    </div>

    <!-- ── List ── -->
    <div class="lbl-card">
        <div class="lbl-listhd">
            <span><?= number_format($total) ?> รายการ</span>
            <?php if ($allIds): ?>
            <span class="lbl-listhd-act">
                <button type="button" class="lbl-link" data-pick-page>เลือกทั้งหน้า</button>
                <?php if ($total > count($rows)): ?>
                <button type="button" class="lbl-link" data-pick-all>เลือกทั้งหมด <?= number_format(count($allIds)) ?></button>
                <?php endif; ?>
            </span>
            <?php endif; ?>
        </div>

        <?php if (!$rows): ?>
            <p class="lbl-empty"><?= $show === 'unprinted' && $q === '' && !$cat ? 'ติดฉลากครบทุกรายการแล้ว' : 'ไม่พบรายการ' ?></p>
        <?php endif; ?>

        <?php foreach ($rows as $r): ?>
        <?php $isDup = $kind === 'new' && isset($dupNames[mb_strtolower(trim($r['name']))]);
              $models = plb_models_line($r['name'], $r['compatible_models']); ?>
        <label class="lbl-row" data-id="<?= (int)$r['id'] ?>">
            <input type="checkbox" value="<?= (int)$r['id'] ?>" data-pick>
            <span class="lbl-row-main">
                <span class="lbl-row-name">
                    <span data-name><?= h($r['name']) ?></span>
                    <?php if ($kind === 'new'): ?>
                    <button type="button" class="lbl-edit" data-rename aria-label="แก้ชื่อ"><span class="material-symbols-rounded">edit</span></button>
                    <?php endif; ?>
                    <b class="lbl-dup" data-dup <?= $isDup ? '' : 'hidden' ?> title="มีรายการอื่นชื่อเดียวกัน — ฉลากจะดูเหมือนกัน">ชื่อซ้ำ</b>
                </span>
                <?php if ($models !== ''): ?>
                <span class="lbl-row-models">ใช้กับ <?= h($models) ?></span>
                <?php endif; ?>
                <?php if ($isUnit): ?>
                <span class="lbl-row-sub"><code><?= h($r['asset_tag'] ?: ($r['sku'] ?: '—')) ?></code><?= $r['serial_number'] ? ' · ' . h($r['serial_number']) : '' ?> · <?= h($r['status']) ?></span>
                <?php else: ?>
                <span class="lbl-row-sub"><code><?= h($r['sku'] ?: '—') ?></code> · <?= h($r['cat_name'] ?: '—') ?> · คงเหลือ <?= (int)$r['qty'] ?></span>
                <?php endif; ?>
            </span>
            <?php if ($r['last_at']): ?>
            <span class="lbl-row-st" title="พิมพ์แล้ว <?= (int)$r['times'] ?> ครั้ง">พิมพ์ <?= date('d/m/y', strtotime($r['last_at'])) ?></span>
            <?php else: ?>
            <span class="lbl-row-st is-new">ยังไม่พิมพ์</span>
            <?php endif; ?>
        </label>
        <?php endforeach; ?>

        <?php if ($pages > 1): ?>
        <nav class="lbl-pager">
            <?php if ($page > 1): ?><a href="<?= h(qs(['page' => $page - 1])) ?>">‹ ก่อนหน้า</a><?php else: ?><span></span><?php endif; ?>
            <span>หน้า <?= $page ?> / <?= $pages ?></span>
            <?php if ($page < $pages): ?><a href="<?= h(qs(['page' => $page + 1])) ?>">ถัดไป ›</a><?php else: ?><span></span><?php endif; ?>
        </nav>
        <?php endif; ?>
    </div>

    <!-- ── History ── -->
    <?php if ($history): ?>
    <h2 class="lbl-h2">พิมพ์ล่าสุด</h2>
    <div class="lbl-card">
        <?php foreach ($history as $hr): ?>
        <div class="lbl-hist">
            <span class="lbl-hist-main">
                <b><?= (int)$hr['item_count'] ?> รายการ</b>
                <span class="lbl-dim">· <?= (int)$hr['item_count'] * (int)$hr['copies'] ?> ดวง · <?= date('d/m/y H:i', strtotime($hr['created_at'])) ?><?= $hr['admin_name'] ? ' · ' . h($hr['admin_name']) : '' ?></span>
            </span>
            <a class="lbl-hist-open" href="print_labels.php?run=<?= (int)$hr['id'] ?>" title="เปิดแผ่นนี้อีกครั้ง" aria-label="เปิดแผ่นนี้อีกครั้ง">
                <span class="material-symbols-rounded">print</span>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ── Selection bar: appears once something is picked ── -->
<form method="post" class="lbl-bar" id="lblBar" hidden>
    <input type="hidden" name="ids" id="lblIds">
    <span class="lbl-bar-count"><b id="lblCount">0</b> รายการ</span>
    <label class="lbl-bar-copies" title="ดวงต่อรายการ">
        ดวงละ <input type="number" name="copies" value="1" min="1" max="<?= $maxCopies ?>" inputmode="numeric">
    </label>
    <button type="button" class="lbl-link" data-pick-clear>ล้าง</button>
    <button type="submit" class="lbl-go" <?= $hasLog ? '' : 'disabled' ?>>
        <span class="material-symbols-rounded">print</span> พิมพ์
    </button>
</form>

<script>
window.LBL_ALL_IDS = <?= json_encode($allIds) ?>;
</script>
<script src="assets/js/inventory-labels.js?v=<?= asset_ver('/admin/inventory/assets/js/inventory-labels.js') ?>"></script>

<?php include '../templates/footer_admin.php'; ?>
