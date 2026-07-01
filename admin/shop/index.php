<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// ── AJAX: update listing status ──────────────────────────────
if (($_POST['action'] ?? '') === 'update_status') {
    header('Content-Type: application/json');
    require_perms_json(['content.write']); // เปลี่ยนสถานะสินค้า: หน้าร้าน+ ขึ้นไป
    $lid    = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $allowed = ['published','draft','reserved','sold'];
    if (!$lid || !in_array($status, $allowed)) {
        echo json_encode(['ok'=>false,'msg'=>'invalid']); exit;
    }
    $pdo->prepare("UPDATE shop_listings SET status=? WHERE id=?")->execute([$status, $lid]);
    echo json_encode(['ok'=>true]); exit;
}

// ── AJAX: listing detail (inline expand) ─────────────────────
if (($_GET['action'] ?? '') === 'listing_detail') {
    $lid = (int)($_GET['id'] ?? 0);
    if (!$lid) { echo '<div style="padding:20px;color:#ef4444;">ไม่พบข้อมูล</div>'; exit; }

    $st = $pdo->prepare("
        SELECT sl.*,
               COALESCE(sl.title, inv.name) display_title,
               inv.name inv_name, inv.sku, inv.condition_grade, inv.color,
               inv.cpu_spec, inv.ram_spec, inv.storage_spec, inv.gpu_spec,
               inv.battery_health, inv.serial_number,
               inv.sell_price inv_price, inv.status inv_status,
               sc.name cat_name
        FROM shop_listings sl
        JOIN inventory inv ON inv.id = sl.inventory_id
        JOIN shop_categories sc ON sc.id = sl.category_id
        WHERE sl.id = ?
    ");
    $st->execute([$lid]);
    $d = $st->fetch(PDO::FETCH_ASSOC);
    if (!$d) { echo '<div style="padding:20px;color:#ef4444;">ไม่พบข้อมูล</div>'; exit; }

    $imgs = $pdo->prepare("SELECT * FROM shop_images WHERE listing_id = ? ORDER BY sort_order, id");
    $imgs->execute([$lid]);
    $images = $imgs->fetchAll(PDO::FETCH_ASSOC);

    $specs = array_filter([
        $d['cpu_spec'] ? 'CPU: '.$d['cpu_spec'] : null,
        $d['ram_spec'] ? 'RAM: '.$d['ram_spec'] : null,
        $d['storage_spec'] ? 'Storage: '.$d['storage_spec'] : null,
        $d['gpu_spec'] ? 'GPU: '.$d['gpu_spec'] : null,
        $d['battery_health'] ? 'Battery: '.$d['battery_health'].'%' : null,
    ]);

    $grade = $d['condition_grade'] ?? '';
    $grade_style = str_starts_with($grade,'A') ? 'background:#d1fae5;color:#065f46' : (str_starts_with($grade,'B') ? 'background:#fef3c7;color:#92400e' : 'background:#fee2e2;color:#991b1b');

    $status_map = ['published'=>['เผยแพร่','#d1fae5','#065f46'],'draft'=>['Draft','#f3f4f6','#6b7280'],'reserved'=>['จอง','#fef3c7','#92400e'],'sold'=>['ขายแล้ว','#fee2e2','#991b1b']];
    [$s_label,$s_bg,$s_color] = $status_map[$d['status']] ?? [$d['status'],'#f3f4f6','#6b7280'];
    ?>
<style>
.sl-detail-wrap{display:flex;gap:20px;padding:20px 20px 20px 84px;background:var(--bg-surface-alt);border-top:1px solid var(--border);}
.sl-detail-images{display:flex;flex-direction:column;gap:8px;flex-shrink:0;}
.sl-cover{width:160px;height:160px;border-radius:10px;object-fit:cover;border:1px solid var(--border);background:var(--bg-surface);}
.sl-thumbs{display:flex;gap:5px;flex-wrap:wrap;max-width:160px;}
.sl-thumb{width:46px;height:46px;border-radius:6px;object-fit:cover;border:1px solid var(--border);cursor:pointer;transition:.15s;}
.sl-thumb:hover{border-color:var(--primary);}
.sl-detail-body{flex:1;min-width:0;}
.sl-detail-title{font-size:16px;font-weight:800;color:var(--text-main);margin:0 0 4px;}
.sl-detail-sku{font-size:12px;color:var(--text-muted);margin-bottom:12px;}
.sl-spec-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:6px;margin-bottom:14px;}
.sl-spec-item{background:var(--bg-surface);border:1px solid var(--border);border-radius:8px;padding:7px 10px;}
.sl-spec-key{font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px;}
.sl-spec-val{font-size:13px;font-weight:600;color:var(--text-main);margin-top:2px;}
.sl-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:14px;}
.sl-desc{font-size:13px;color:var(--text-muted);line-height:1.7;margin-bottom:14px;max-height:80px;overflow:hidden;position:relative;}
.sl-actions{display:flex;gap:8px;}
</style>
<div class="sl-detail-wrap">
    <!-- Images -->
    <div class="sl-detail-images">
        <?php $cover = $images[0]['url'] ?? $d['cover_image'] ?? ''; ?>
        <?php if ($cover): ?>
        <img src="<?= h($cover) ?>" id="sl-main-img-<?= $lid ?>" class="sl-cover" alt="">
        <?php else: ?>
        <div class="sl-cover" style="display:flex;align-items:center;justify-content:center;">
            <span class="material-symbols-rounded" style="font-size:40px;color:var(--text-muted);">image</span>
        </div>
        <?php endif; ?>
        <?php if (count($images) > 1): ?>
        <div class="sl-thumbs">
            <?php foreach($images as $i => $img): ?>
            <img src="<?= h($img['url']) ?>" class="sl-thumb <?= $i===0?'active':''; ?>"
                 onclick="document.getElementById('sl-main-img-<?= $lid ?>').src=this.src;this.closest('.sl-thumbs').querySelectorAll('.sl-thumb').forEach(t=>t.style.borderColor='');this.style.borderColor='var(--primary)';"
                 alt="">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Body -->
    <div class="sl-detail-body">
        <h3 class="sl-detail-title"><?= h($d['display_title']) ?></h3>
        <div class="sl-detail-sku">
            SKU: <?= h($d['sku']) ?>
            <?php if ($d['color']): ?> · <?= h($d['color']) ?><?php endif; ?>
            <?php if ($d['serial_number']): ?> · S/N: <?= h($d['serial_number']) ?><?php endif; ?>
        </div>

        <!-- Meta badges -->
        <div class="sl-meta">
            <span style="padding:3px 9px;border-radius:5px;font-size:12px;font-weight:700;<?= $grade_style ?>">Grade <?= h($grade) ?: '—' ?></span>
            <span style="padding:3px 9px;border-radius:5px;font-size:12px;font-weight:700;background:<?= $s_bg ?>;color:<?= $s_color ?>"><?= $s_label ?></span>
            <span style="font-size:12px;color:var(--text-muted);"><?= h($d['cat_name']) ?></span>
            <span style="font-size:14px;font-weight:800;color:var(--primary);">฿<?= number_format($d['price']) ?></span>
            <?php if ($d['price_original']): ?>
            <span style="font-size:12px;color:var(--text-muted);text-decoration:line-through;">฿<?= number_format($d['price_original']) ?></span>
            <?php endif; ?>
        </div>

        <!-- Specs -->
        <?php if ($specs): ?>
        <div class="sl-spec-grid">
            <?php foreach($specs as $sp):
                [$key,$val] = explode(': ', $sp, 2);
            ?>
            <div class="sl-spec-item">
                <div class="sl-spec-key"><?= h($key) ?></div>
                <div class="sl-spec-val"><?= h($val) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Description -->
        <?php $desc = strip_tags($d['description'] ?? ''); ?>
        <?php if ($desc): ?>
        <div class="sl-desc"><?= h(mb_substr($desc,0,200)) ?><?= mb_strlen($desc)>200?'…':'' ?></div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="sl-actions">
            <button type="button" class="cmns-btn cmns-btn-primary" style="font-size:12px;padding:6px 14px;"
                    onclick="openShopModal('edit.php?id=<?= $lid ?>&modal=1')">
                <span class="material-symbols-rounded" style="font-size:15px;">edit</span> แก้ไข
            </button>
            <button type="button" class="cmns-btn cmns-btn-secondary" style="font-size:12px;padding:6px 14px;"
                    onclick="confirmDel(<?= $lid ?>,'<?= h(addslashes($d['display_title'])) ?>')">
                <span class="material-symbols-rounded" style="font-size:15px;">delete</span> ลบ
            </button>
            <?php if ($d['status'] !== 'published'): ?>
            <button type="button" class="cmns-btn" style="font-size:12px;padding:6px 14px;background:#10b981;color:#fff;border-color:#10b981;"
                    onclick="changeListingStatus(<?= $lid ?>,'published',this)">
                <span class="material-symbols-rounded" style="font-size:15px;">publish</span> เผยแพร่
            </button>
            <?php endif; ?>
            <?php if ($d['status'] === 'published'): ?>
            <button type="button" class="cmns-btn cmns-btn-secondary" style="font-size:12px;padding:6px 14px;"
                    onclick="changeListingStatus(<?= $lid ?>,'draft',this)">
                <span class="material-symbols-rounded" style="font-size:15px;">visibility_off</span> ซ่อน
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>
    <?php exit;
}

$tab = in_array($_GET['tab'] ?? '', ['available']) ? 'available' : 'listings';

// ── Stats (always load — lightweight) ────────────────────────
$stats = $pdo->query("
    SELECT COUNT(*) total,
           COALESCE(SUM(status='published'), 0) published,
           COALESCE(SUM(status='reserved'), 0)  reserved,
           COALESCE(SUM(status='sold'), 0)      sold,
           COALESCE(SUM(status='draft'), 0)     draft
    FROM shop_listings
")->fetch(PDO::FETCH_ASSOC);

$available_count = (int)$pdo->query("
    SELECT COUNT(*) FROM inventory
    WHERE type='sale' AND status IN ('READY','STOCK')
    AND NOT EXISTS (SELECT 1 FROM shop_listings sl WHERE sl.inventory_id = inventory.id)
")->fetchColumn();

$categories   = $pdo->query("SELECT * FROM shop_categories ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
$parts_cats   = $pdo->query("SELECT * FROM parts_categories ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$flash        = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);

// ── Tab: สินค้าในร้าน ─────────────────────────────────────────
$listings = []; $total_rows = 0; $total_pages = 1; $page = 1; $per = 25;
$filter_status = ''; $filter_cat = 0; $q = '';

if ($tab === 'listings') {
    $filter_status = $_GET['status'] ?? '';
    $filter_cat    = (int)($_GET['cat'] ?? 0);
    $q             = trim($_GET['q'] ?? '');
    $_rp           = (int)($_GET['per'] ?? 25);
    $per           = in_array($_rp, [25,50,100]) ? $_rp : 25;
    $page          = max(1, (int)($_GET['page'] ?? 1));
    $offset        = ($page - 1) * $per;

    $where = ['1=1']; $params = [];
    if ($filter_status) { $where[] = 'sl.status = ?'; $params[] = $filter_status; }
    if ($filter_cat)    { $where[] = 'sl.category_id = ?'; $params[] = $filter_cat; }
    if ($q) { $where[] = '(COALESCE(sl.title,inv.name) LIKE ? OR inv.sku LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
    $whereSQL = implode(' AND ', $where);

    $cnt = $pdo->prepare("SELECT COUNT(*) FROM shop_listings sl JOIN inventory inv ON inv.id = sl.inventory_id WHERE $whereSQL");
    $cnt->execute($params);
    $total_rows  = (int)$cnt->fetchColumn();
    $total_pages = max(1, (int)ceil($total_rows / $per));
    $page        = min($page, $total_pages);
    $offset      = ($page - 1) * $per;

    $lst = $pdo->prepare("
        SELECT sl.*,
               COALESCE(sl.title, inv.name) display_title,
               inv.name inv_name, inv.sku, inv.condition_grade, inv.color,
               inv.cpu_spec, inv.ram_spec, inv.storage_spec, inv.battery_health,
               sc.name cat_name,
               (SELECT COUNT(*) FROM shop_images si WHERE si.listing_id = sl.id) img_count
        FROM shop_listings sl
        JOIN inventory inv ON inv.id = sl.inventory_id
        JOIN shop_categories sc ON sc.id = sl.category_id
        WHERE $whereSQL ORDER BY sl.created_at DESC
        LIMIT $per OFFSET $offset
    ");
    $lst->execute($params);
    $listings = $lst->fetchAll(PDO::FETCH_ASSOC);
}

// ── Tab: คลังพร้อมลงขาย ──────────────────────────────────────
$available = []; $avail_total = 0; $avail_pages = 1; $avail_page = 1; $avail_per = 25;
$avail_q = ''; $avail_cat = 0;

if ($tab === 'available') {
    $avail_q    = trim($_GET['aq'] ?? '');
    $avail_cat  = (int)($_GET['acat'] ?? 0);
    $_rap       = (int)($_GET['aper'] ?? 25);
    $avail_per  = in_array($_rap, [25,50,100]) ? $_rap : 25;
    $avail_page = max(1, (int)($_GET['apage'] ?? 1));

    $aw = ["inv.type='sale'", "inv.status IN ('READY','STOCK')",
           "NOT EXISTS (SELECT 1 FROM shop_listings sl WHERE sl.inventory_id = inv.id)"];
    $ap = [];
    if ($avail_q)   { $aw[] = '(inv.name LIKE ? OR inv.sku LIKE ?)'; $ap[] = "%$avail_q%"; $ap[] = "%$avail_q%"; }
    if ($avail_cat) { $aw[] = 'inv.category_id = ?'; $ap[] = $avail_cat; }
    $awSQL = implode(' AND ', $aw);

    $acnt = $pdo->prepare("SELECT COUNT(*) FROM inventory inv WHERE $awSQL");
    $acnt->execute($ap);
    $avail_total = (int)$acnt->fetchColumn();
    $avail_pages = max(1, (int)ceil($avail_total / $avail_per));
    $avail_page  = min($avail_page, $avail_pages);
    $avail_off   = ($avail_page - 1) * $avail_per;

    $ast = $pdo->prepare("
        SELECT inv.*, pc.name parts_cat_name
        FROM inventory inv
        LEFT JOIN parts_categories pc ON pc.id = inv.category_id
        WHERE $awSQL ORDER BY inv.created_at DESC
        LIMIT $avail_per OFFSET $avail_off
    ");
    $ast->execute($ap);
    $available = $ast->fetchAll(PDO::FETCH_ASSOC);
}

// ── helpers ───────────────────────────────────────────────────
function tab_url(string $t, array $extra = []): string {
    return '?' . http_build_query(array_merge(['tab' => $t], $extra));
}
function listings_url(array $extra = []): string {
    global $filter_status, $filter_cat, $q, $per;
    $base = ['tab'=>'listings'];
    if ($filter_status) $base['status'] = $filter_status;
    if ($filter_cat)    $base['cat']    = $filter_cat;
    if ($q)             $base['q']      = $q;
    if ($per !== 25)    $base['per']    = $per;
    return '?' . http_build_query(array_merge($base, $extra));
}
function avail_url(array $extra = []): string {
    global $avail_q, $avail_cat, $avail_per;
    $base = ['tab'=>'available'];
    if ($avail_q)        $base['aq']   = $avail_q;
    if ($avail_cat)      $base['acat'] = $avail_cat;
    if ($avail_per !==25) $base['aper'] = $avail_per;
    return '?' . http_build_query(array_merge($base, $extra));
}

$pageTitle = 'จัดการร้านค้า';
include __DIR__ . '/../templates/header_admin.php';
?>
<link rel="stylesheet" href="../templates/assets/css/inventory-dashboard.css?v=<?= time() ?>">
<link rel="stylesheet" href="../templates/assets/css/inventory-logs.css?v=<?= time() ?>">
<style>
.shop-stats{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:24px;}
.stat-card{background:var(--bg-surface);border:1px solid var(--border);border-radius:12px;padding:16px 18px;display:flex;align-items:center;gap:14px;}
.stat-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.stat-icon .material-symbols-rounded{font-size:20px;}
.stat-val{font-size:22px;font-weight:800;color:var(--text-main);line-height:1;}
.stat-lbl{font-size:11px;color:var(--text-muted);margin-top:3px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;}
/* Tab nav */
.shop-tabs{display:flex;gap:2px;background:var(--bg-surface-alt);border:1px solid var(--border);border-radius:12px;padding:4px;margin-bottom:20px;width:fit-content;}
.shop-tab{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:9px;border:none;background:transparent;cursor:pointer;font-family:'Sarabun',sans-serif;font-size:13px;font-weight:600;color:var(--text-muted);text-decoration:none;transition:.15s;white-space:nowrap;}
.shop-tab:hover:not(.active){background:var(--bg-surface);color:var(--text-main);}
.shop-tab.active{background:var(--primary);color:#fff;}
.shop-tab .tab-count{font-size:11px;font-weight:800;padding:2px 7px;border-radius:20px;background:rgba(255,255,255,.25);}
.shop-tab:not(.active) .tab-count{background:var(--border);color:var(--text-muted);}
/* filter-bar */
.filter-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.filter-bar select,.filter-bar input{padding:7px 11px;border-radius:8px;border:1px solid var(--border);background:var(--bg-surface-alt);color:var(--text-main);font-size:13px;font-family:'Sarabun',sans-serif;}
.filter-bar input{min-width:220px;}
/* tables */
.shop-table{width:100%;border-collapse:collapse;font-size:13px;}
.shop-table th{padding:9px 12px;text-align:left;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid var(--border);white-space:nowrap;}
.shop-table td{padding:10px 12px;border-bottom:1px solid var(--border);vertical-align:middle;}
.shop-table tr:last-child td{border-bottom:none;}
.shop-table tbody tr:hover td{background:var(--bg-surface-alt);}
.thumb{width:52px;height:52px;border-radius:8px;object-fit:cover;border:1px solid var(--border);background:var(--bg-surface-alt);display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;}
.thumb img{width:100%;height:100%;object-fit:cover;}
.thumb .material-symbols-rounded{font-size:22px;color:var(--text-muted);}
.grade-badge{display:inline-block;padding:2px 7px;border-radius:5px;font-size:11px;font-weight:800;}
.g-A,.g-AS{background:#d1fae5;color:#065f46;}
.g-B{background:#fef3c7;color:#92400e;}
.g-C{background:#fee2e2;color:#991b1b;}
.s-badge{display:inline-flex;align-items:center;padding:3px 9px;border-radius:6px;font-size:11px;font-weight:700;white-space:nowrap;}
.s-published{background:#d1fae5;color:#065f46;}
.s-draft{background:var(--bg-surface-alt);color:var(--text-muted);border:1px solid var(--border);}
.s-reserved{background:#fef3c7;color:#92400e;}
.s-sold{background:#fee2e2;color:#991b1b;}
.icon-btn{width:30px;height:30px;border-radius:7px;border:1px solid var(--border);background:var(--bg-surface-alt);color:var(--text-muted);display:inline-flex;align-items:center;justify-content:center;cursor:pointer;transition:.15s;}
.icon-btn:hover{border-color:var(--primary);color:var(--primary);}
.icon-btn.del:hover{border-color:#ef4444;color:#ef4444;}
.icon-btn .material-symbols-rounded{font-size:15px;}
.specs-line{font-size:11px;color:var(--text-muted);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:220px;}
.empty-cell{text-align:center;padding:40px!important;color:var(--text-muted);}
.slr-row:hover td{background:var(--bg-surface-alt);}
.slr-row.row-active td{background:rgba(37,99,235,.05);border-bottom-color:transparent;}
.sl-detail-row td{border-bottom:2px solid var(--primary);}
@keyframes spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}
</style>

<div class="cmns-wrapper">
<div class="cmns-page-header">
    <div>
        <h1 class="cmns-page-title">
            <span class="material-symbols-rounded">storefront</span> จัดการร้านค้า
        </h1>
        <p class="cmns-page-subtitle">เลือกสินค้าจากคลัง (type=sale) มาลงขาย</p>
    </div>
</div>

<?php if ($flash): ?>
<div class="cmns-alert cmns-alert-success" style="margin-bottom:16px;"><?= h($flash) ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="shop-stats">
    <div class="stat-card">
        <div class="stat-icon" style="background:#eff6ff;"><span class="material-symbols-rounded" style="color:#3b82f6;">inventory_2</span></div>
        <div><div class="stat-val"><?= (int)$stats['total'] ?></div><div class="stat-lbl">สินค้าในร้าน</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#f0fdf4;"><span class="material-symbols-rounded" style="color:#10b981;">check_circle</span></div>
        <div><div class="stat-val"><?= (int)$stats['published'] ?></div><div class="stat-lbl">เผยแพร่</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fffbeb;"><span class="material-symbols-rounded" style="color:#f59e0b;">schedule</span></div>
        <div><div class="stat-val"><?= (int)$stats['reserved'] ?></div><div class="stat-lbl">จอง</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fef2f2;"><span class="material-symbols-rounded" style="color:#ef4444;">sell</span></div>
        <div><div class="stat-val"><?= (int)$stats['sold'] ?></div><div class="stat-lbl">ขายแล้ว</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#f5f3ff;"><span class="material-symbols-rounded" style="color:#8b5cf6;">add_box</span></div>
        <div><div class="stat-val"><?= $available_count ?></div><div class="stat-lbl">คลังพร้อมลงขาย</div></div>
    </div>
</div>

<!-- Tab nav -->
<div class="shop-tabs">
    <a href="<?= tab_url('listings') ?>" class="shop-tab <?= $tab==='listings' ? 'active' : '' ?>">
        <span class="material-symbols-rounded" style="font-size:17px;">storefront</span>
        สินค้าในร้าน
        <span class="tab-count"><?= (int)$stats['total'] ?></span>
    </a>
    <a href="<?= tab_url('available') ?>" class="shop-tab <?= $tab==='available' ? 'active' : '' ?>">
        <span class="material-symbols-rounded" style="font-size:17px;">add_box</span>
        คลังพร้อมลงขาย
        <span class="tab-count"><?= $available_count ?></span>
    </a>
</div>

<?php if ($tab === 'listings'): ?>
<!-- ══ Tab: สินค้าในร้าน ══ -->
<div class="cmns-card" style="padding:0;overflow:hidden;flex:1;display:flex;flex-direction:column;">
    <div style="padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        <span style="font-size:13px;color:var(--text-muted);">
            แสดง <b><?= number_format($total_rows) ?></b> รายการ
        </span>
        <form method="GET" class="filter-bar">
            <input type="hidden" name="tab" value="listings">
            <input type="text" name="q" value="<?= h($q) ?>" placeholder="ค้นหาชื่อ / SKU...">
            <select name="status" onchange="this.form.submit()">
                <option value="">ทุกสถานะ</option>
                <option value="published" <?= $filter_status==='published'?'selected':'' ?>>เผยแพร่</option>
                <option value="draft"     <?= $filter_status==='draft'    ?'selected':'' ?>>Draft</option>
                <option value="reserved"  <?= $filter_status==='reserved' ?'selected':'' ?>>จอง</option>
                <option value="sold"      <?= $filter_status==='sold'     ?'selected':'' ?>>ขายแล้ว</option>
            </select>
            <select name="cat" onchange="this.form.submit()">
                <option value="">ทุกหมวด</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $filter_cat==$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-filter">
                <span class="material-symbols-rounded">search</span> ค้นหา
            </button>
            <?php if ($q || $filter_status || $filter_cat): ?>
            <a href="<?= tab_url('listings') ?>" class="btn-reset">
                <span class="material-symbols-rounded">close</span>
            </a>
            <?php endif; ?>
        </form>
    </div>
    <div style="overflow-x:auto;flex:1;">
        <table class="shop-table">
            <thead>
                <tr>
                    <th style="width:64px;">รูป</th>
                    <th>ชื่อสินค้า</th>
                    <th>หมวด</th>
                    <th>Grade</th>
                    <th>ราคา</th>
                    <th>สถานะ</th>
                    <th style="text-align:center;">รูป</th>
                    <th>วันที่</th>
                    <th style="width:80px;"></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$listings): ?>
                <tr><td colspan="9" class="empty-cell">
                    <span class="material-symbols-rounded" style="font-size:36px;display:block;margin-bottom:8px;opacity:.3;">storefront</span>
                    <?= ($q || $filter_status || $filter_cat) ? 'ไม่พบสินค้าที่ตรงกับเงื่อนไข' : 'ยังไม่มีสินค้าในร้าน' ?>
                </td></tr>
            <?php else: foreach ($listings as $row):
                $g = $row['condition_grade'] ?? '';
                $gc = str_starts_with($g,'A') ? 'g-A' : (str_starts_with($g,'B') ? 'g-B' : 'g-C');
                $specs = implode(' / ', array_filter([$row['cpu_spec'],$row['ram_spec'],$row['storage_spec']]));
            ?>
                <tr id="lr-<?= $row['id'] ?>" class="slr-row" onclick="toggleListingDetail(<?= $row['id'] ?>)" style="cursor:pointer;">
                    <td>
                        <div class="thumb">
                        <?php if ($row['cover_image']): ?>
                            <img src="<?= h($row['cover_image']) ?>" alt=""
                                 <?= $row['cover_w']&&$row['cover_h'] ? 'width="'.$row['cover_w'].'" height="'.$row['cover_h'].'"' : '' ?>>
                        <?php else: ?>
                            <span class="material-symbols-rounded">image</span>
                        <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:600;"><?= h($row['display_title']) ?></div>
                        <div style="font-size:11px;color:var(--text-muted);"><?= h($row['sku']) ?></div>
                        <?php if ($specs): ?><div class="specs-line"><?= h($specs) ?></div><?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;font-size:12px;"><?= h($row['cat_name']) ?></td>
                    <td><?= $g ? '<span class="grade-badge '.$gc.'">'.h($g).'</span>' : '—' ?></td>
                    <td style="white-space:nowrap;">
                        <div style="font-weight:700;">฿<?= number_format($row['price']) ?></div>
                        <?php if ($row['price_original']): ?><div style="font-size:11px;color:var(--text-muted);text-decoration:line-through;">฿<?= number_format($row['price_original']) ?></div><?php endif; ?>
                    </td>
                    <td>
                        <span class="s-badge s-<?= h($row['status']) ?>"><?= ['published'=>'เผยแพร่','draft'=>'Draft','reserved'=>'จอง','sold'=>'ขายแล้ว'][$row['status']] ?? h($row['status']) ?></span>
                    </td>
                    <td style="text-align:center;color:var(--text-muted);"><?= $row['img_count'] ?></td>
                    <td style="font-size:12px;color:var(--text-muted);white-space:nowrap;"><?= date('d/m/y', strtotime($row['created_at'])) ?></td>
                    <td>
                        <div style="display:flex;gap:4px;" onclick="event.stopPropagation()">
                            <button type="button" class="icon-btn" onclick="openShopModal('edit.php?id=<?= $row['id'] ?>&modal=1')" title="แก้ไข">
                                <span class="material-symbols-rounded">edit</span>
                            </button>
                            <button type="button" class="icon-btn del" onclick="confirmDel(<?= $row['id'] ?>,'<?= h(addslashes($row['display_title'])) ?>')" title="ลบ">
                                <span class="material-symbols-rounded">delete</span>
                            </button>
                        </div>
                    </td>
                </tr>
                <tr id="sl-detail-<?= $row['id'] ?>" class="sl-detail-row" style="display:none;">
                    <td colspan="9" style="padding:0;">
                        <div id="sl-content-<?= $row['id'] ?>"></div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <div class="log-pagination">
        <div>
            แสดง <b><?= number_format(min($total_rows, ($page-1)*$per+1)) ?>–<?= number_format(min($total_rows, $page*$per)) ?></b>
            จาก <b><?= number_format($total_rows) ?></b> รายการ
            &nbsp;·&nbsp; หน้า <?= $page ?> / <?= $total_pages ?>
        </div>
        <div class="page-btns">
            <a href="<?= $page > 1 ? listings_url(['page'=>$page-1]) : '#' ?>" class="page-btn <?= $page<=1?'disabled':'' ?>">
                <span class="material-symbols-rounded" style="font-size:16px;">chevron_left</span>
            </a>
            <?php $ps=max(1,$page-2); $pe=min($total_pages,$ps+4); for($p=$ps;$p<=$pe;$p++): ?>
                <a href="<?= listings_url(['page'=>$p]) ?>" class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <a href="<?= $page < $total_pages ? listings_url(['page'=>$page+1]) : '#' ?>" class="page-btn <?= $page>=$total_pages?'disabled':'' ?>">
                <span class="material-symbols-rounded" style="font-size:16px;">chevron_right</span>
            </a>
            <select onchange="location='<?= listings_url(['per'=>'__P__','page'=>1]) ?>'.replace('__P__',this.value)"
                    style="padding:6px 10px;border-radius:8px;border:1px solid var(--border);background:var(--bg-surface);color:var(--text-main);font-size:13px;outline:none;cursor:pointer;font-family:'Sarabun',sans-serif;">
                <?php foreach([25,50,100] as $pp): ?>
                    <option value="<?= $pp ?>" <?= $per===$pp?'selected':'' ?>><?= $pp ?>/หน้า</option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ══ Tab: คลังพร้อมลงขาย ══ -->
<div class="cmns-card" style="padding:0;overflow:hidden;flex:1;display:flex;flex-direction:column;">
    <div style="padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        <span style="font-size:13px;color:var(--text-muted);">
            แสดง <b><?= number_format($avail_total) ?></b> รายการ
        </span>
        <form method="GET" class="filter-bar">
            <input type="hidden" name="tab" value="available">
            <input type="text" name="aq" value="<?= h($avail_q) ?>" placeholder="ค้นหาชื่อ / SKU...">
            <select name="acat" onchange="this.form.submit()">
                <option value="">ทุกหมวด (คลัง)</option>
                <?php foreach ($parts_cats as $pc): ?>
                <option value="<?= $pc['id'] ?>" <?= $avail_cat==$pc['id']?'selected':'' ?>><?= h($pc['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-filter">
                <span class="material-symbols-rounded">search</span> ค้นหา
            </button>
            <?php if ($avail_q || $avail_cat): ?>
            <a href="<?= tab_url('available') ?>" class="btn-reset">
                <span class="material-symbols-rounded">close</span>
            </a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (!$available): ?>
    <div class="empty-cell">
        <span class="material-symbols-rounded" style="font-size:36px;display:block;margin-bottom:8px;opacity:.3;">inventory_2</span>
        <?= ($avail_q || $avail_cat) ? 'ไม่พบสินค้าที่ตรงกับเงื่อนไข' : 'ไม่มีสินค้าในคลังที่รอลงขาย' ?>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;flex:1;">
        <table class="shop-table">
            <thead>
                <tr>
                    <th>ชื่อสินค้า</th>
                    <th>SKU</th>
                    <th>Grade</th>
                    <th>สเปก</th>
                    <th>ราคาในคลัง</th>
                    <th>หมวด (คลัง)</th>
                    <th style="width:140px;"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($available as $inv):
                $g = $inv['condition_grade'] ?? '';
                $gc = str_starts_with($g,'A') ? 'g-A' : (str_starts_with($g,'B') ? 'g-B' : 'g-C');
                $sp = array_filter([$inv['cpu_spec'],$inv['ram_spec'],$inv['storage_spec']]);
                if ($inv['battery_health']) $sp[] = 'Battery '.$inv['battery_health'].'%';
            ?>
                <tr>
                    <td>
                        <div style="font-weight:600;"><?= h($inv['name']) ?></div>
                        <?php if ($inv['color']): ?><div style="font-size:11px;color:var(--text-muted);"><?= h($inv['color']) ?></div><?php endif; ?>
                    </td>
                    <td style="font-size:12px;color:var(--text-muted);"><?= h($inv['sku']) ?></td>
                    <td><?= $g ? '<span class="grade-badge '.$gc.'">'.h($g).'</span>' : '—' ?></td>
                    <td><div class="specs-line"><?= $sp ? h(implode(' / ',$sp)) : '—' ?></div></td>
                    <td style="font-weight:700;">฿<?= number_format($inv['sell_price']) ?></td>
                    <td style="font-size:12px;color:var(--text-muted);"><?= h($inv['parts_cat_name'] ?? '—') ?></td>
                    <td>
                        <button type="button" class="cmns-btn cmns-btn-primary" style="font-size:12px;padding:6px 12px;" onclick="openShopModal('add.php?inv_id=<?= $inv['id'] ?>&modal=1')">
                            <span class="material-symbols-rounded" style="font-size:15px;">add_circle</span>
                            เพิ่มลงร้าน
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="log-pagination">
        <div>
            แสดง <b><?= number_format(min($avail_total, ($avail_page-1)*$avail_per+1)) ?>–<?= number_format(min($avail_total, $avail_page*$avail_per)) ?></b>
            จาก <b><?= number_format($avail_total) ?></b> รายการ
            &nbsp;·&nbsp; หน้า <?= $avail_page ?> / <?= $avail_pages ?>
        </div>
        <div class="page-btns">
            <a href="<?= $avail_page > 1 ? avail_url(['apage'=>$avail_page-1]) : '#' ?>" class="page-btn <?= $avail_page<=1?'disabled':'' ?>">
                <span class="material-symbols-rounded" style="font-size:16px;">chevron_left</span>
            </a>
            <?php $ps=max(1,$avail_page-2); $pe=min($avail_pages,$ps+4); for($p=$ps;$p<=$pe;$p++): ?>
                <a href="<?= avail_url(['apage'=>$p]) ?>" class="page-btn <?= $p===$avail_page?'active':'' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <a href="<?= $avail_page < $avail_pages ? avail_url(['apage'=>$avail_page+1]) : '#' ?>" class="page-btn <?= $avail_page>=$avail_pages?'disabled':'' ?>">
                <span class="material-symbols-rounded" style="font-size:16px;">chevron_right</span>
            </a>
            <select onchange="location='<?= avail_url(['aper'=>'__P__','apage'=>1]) ?>'.replace('__P__',this.value)"
                    style="padding:6px 10px;border-radius:8px;border:1px solid var(--border);background:var(--bg-surface);color:var(--text-main);font-size:13px;outline:none;cursor:pointer;font-family:'Sarabun',sans-serif;">
                <?php foreach([25,50,100] as $pp): ?>
                    <option value="<?= $pp ?>" <?= $avail_per===$pp?'selected':'' ?>><?= $pp ?>/หน้า</option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

</div><!-- /.cmns-wrapper -->

<!-- Shop Modal -->
<div id="modal-shop" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.55);align-items:center;justify-content:center;">
    <div style="background:var(--bg-surface,#fff);width:min(96vw,1000px);height:90vh;border-radius:16px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 24px 80px rgba(0,0,0,.35);">
        <iframe id="shop-iframe" src="" style="flex:1;border:none;width:100%;display:block;"></iframe>
    </div>
</div>

<script>
function openShopModal(url) {
    document.getElementById('shop-iframe').src = url;
    const m = document.getElementById('modal-shop');
    m.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeShopModal() {
    document.getElementById('modal-shop').style.display = 'none';
    document.body.style.overflow = '';
    setTimeout(() => { document.getElementById('shop-iframe').src = ''; }, 200);
}
document.getElementById('modal-shop').addEventListener('click', e => {
    if (e.target === document.getElementById('modal-shop')) closeShopModal();
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && document.getElementById('modal-shop').style.display !== 'none') closeShopModal();
});
window.addEventListener('message', function(e) {
    if (e.data === 'shop-saved') {
        closeShopModal();
        Swal.fire({ toast:true, position:'top-end', icon:'success', title:'บันทึกเรียบร้อยแล้ว', showConfirmButton:false, timer:2500, timerProgressBar:true });
        setTimeout(() => location.reload(), 400);
    }
    if (e.data === 'shop-close') closeShopModal();
    if (e.data === 'shop-saved') {
        // refresh detail row if open
        document.querySelectorAll('.sl-detail-row').forEach(r => {
            if (r.style.display === 'table-row') {
                const id = r.id.replace('sl-detail-','');
                const c = document.getElementById('sl-content-' + id);
                if (c) fetch('index.php?action=listing_detail&id=' + id).then(r=>r.text()).then(h=>{c.innerHTML=h;});
            }
        });
    }
});

function toggleListingDetail(id) {
    const detailRow = document.getElementById('sl-detail-' + id);
    const contentDiv = document.getElementById('sl-content-' + id);
    const mainRow   = document.getElementById('lr-' + id);
    if (!detailRow || !contentDiv || !mainRow) return;

    if (detailRow.style.display === 'table-row') {
        detailRow.style.display = 'none';
        mainRow.classList.remove('row-active');
        return;
    }

    document.querySelectorAll('.sl-detail-row').forEach(r => r.style.display = 'none');
    document.querySelectorAll('.slr-row').forEach(r => r.classList.remove('row-active'));

    detailRow.style.display = 'table-row';
    mainRow.classList.add('row-active');

    contentDiv.innerHTML = '<div style="padding:32px;text-align:center;color:var(--text-muted);"><span class="material-symbols-rounded" style="font-size:24px;display:inline-block;animation:spin 1s linear infinite;">sync</span></div>';

    fetch('index.php?action=listing_detail&id=' + id)
        .then(r => r.text())
        .then(html => { contentDiv.innerHTML = html; })
        .catch(() => { contentDiv.innerHTML = '<div style="padding:20px;text-align:center;color:#ef4444;">โหลดไม่สำเร็จ</div>'; });
}

function changeListingStatus(id, status, btn) {
    btn.disabled = true;
    fetch('index.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action:'update_status', id, status})
    }).then(r => r.json()).then(d => {
        if (d.ok) {
            Swal.fire({toast:true,position:'top-end',icon:'success',title:'อัปเดตสถานะแล้ว',showConfirmButton:false,timer:2000});
            setTimeout(() => location.reload(), 300);
        } else {
            Swal.fire({toast:true,position:'top-end',icon:'error',title:d.msg||'เกิดข้อผิดพลาด',showConfirmButton:false,timer:2500});
            btn.disabled = false;
        }
    }).catch(() => { btn.disabled = false; });
}
</script>

<!-- Delete Confirm Dialog -->
<div id="del-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:var(--bg-surface);width:90%;max-width:360px;border-radius:16px;overflow:hidden;border:1px solid var(--border);box-shadow:0 8px 32px rgba(0,0,0,.2);">
    <div style="padding:20px 20px 12px;text-align:center;background:rgba(239,68,68,.06);border-bottom:1px solid var(--border);">
      <div style="width:44px;height:44px;border-radius:50%;background:rgba(239,68,68,.12);color:#ef4444;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;">
        <span class="material-symbols-rounded" style="font-size:22px;">delete</span>
      </div>
      <h3 style="margin:0;font-size:15px;font-weight:700;color:#dc2626;">ยืนยันการลบ?</h3>
    </div>
    <div style="padding:16px 20px;text-align:center;font-size:14px;line-height:1.6;">
      ลบ <strong id="del-title" style="color:var(--primary)"></strong> ออกจากร้านค้า<br>
      <span style="font-size:12px;color:#6b7280;">สินค้าในคลังจะไม่ถูกลบ</span>
    </div>
    <div style="padding:12px 20px;border-top:1px solid var(--border);background:var(--bg-surface-alt);display:flex;gap:8px;justify-content:center;">
      <button onclick="closeDel()" class="cmns-btn cmns-btn-secondary">ยกเลิก</button>
      <button id="del-btn" onclick="doDel()" class="cmns-btn cmns-btn-primary" style="background:#ef4444;border-color:#ef4444;">ลบออกจากร้าน</button>
    </div>
  </div>
</div>

<script>
let _dId = null;
function confirmDel(id, title) {
    _dId = id;
    document.getElementById('del-title').textContent = title;
    document.getElementById('del-overlay').style.display = 'flex';
}
function closeDel() { document.getElementById('del-overlay').style.display = 'none'; }
function doDel() {
    if (!_dId) return;
    const btn = document.getElementById('del-btn');
    btn.disabled = true; btn.textContent = 'กำลังลบ...';
    fetch('delete.php?id=' + _dId + '&ajax=1').then(r => r.json()).then(d => {
        closeDel();
        if (d.ok) {
            const row = document.getElementById('lr-' + _dId);
            if (row) { row.style.transition='opacity .25s'; row.style.opacity='0'; setTimeout(()=>row.remove(),260); }
            Swal.fire({icon:'success',title:'ลบเรียบร้อยแล้ว',toast:true,position:'top-end',showConfirmButton:false,timer:2500,timerProgressBar:true});
        } else {
            Swal.fire({icon:'error',title:'เกิดข้อผิดพลาด',text:d.msg||'',toast:true,position:'top-end',showConfirmButton:false,timer:3000});
        }
        btn.disabled = false; btn.textContent = 'ลบออกจากร้าน';
    });
}
document.getElementById('del-overlay').addEventListener('click', e => { if (e.target===document.getElementById('del-overlay')) closeDel(); });
</script>

<?php include __DIR__ . '/../templates/footer_admin.php'; ?>
