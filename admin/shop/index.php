<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// ── Stats ─────────────────────────────────────────────────────
$stats = $pdo->query("
    SELECT COUNT(*) total,
           SUM(status='published') published,
           SUM(status='reserved')  reserved,
           SUM(status='sold')      sold,
           SUM(status='draft')     draft
    FROM shop_listings
")->fetch(PDO::FETCH_ASSOC);

$available_count = (int)$pdo->query("
    SELECT COUNT(*) FROM inventory
    WHERE type='sale' AND status IN ('READY','STOCK')
    AND NOT EXISTS (SELECT 1 FROM shop_listings sl WHERE sl.inventory_id = inventory.id)
")->fetchColumn();

// ── Filters ───────────────────────────────────────────────────
$filter_status = $_GET['status'] ?? '';
$filter_cat    = (int)($_GET['cat'] ?? 0);
$q             = trim($_GET['q'] ?? '');
$_per          = (int)($_GET['per'] ?? 25);
$per           = in_array($_per, [25,50,100]) ? $_per : 25;
$page          = max(1, (int)($_GET['page'] ?? 1));
$offset        = ($page - 1) * $per;

$where = ['1=1'];
$params = [];
if ($filter_status) { $where[] = 'sl.status = ?'; $params[] = $filter_status; }
if ($filter_cat)    { $where[] = 'sl.category_id = ?'; $params[] = $filter_cat; }
if ($q) {
    $where[] = '(COALESCE(sl.title,inv.name) LIKE ? OR inv.sku LIKE ?)';
    $params[] = "%$q%"; $params[] = "%$q%";
}
$whereSQL = implode(' AND ', $where);

$cnt = $pdo->prepare("SELECT COUNT(*) FROM shop_listings sl JOIN inventory inv ON inv.id = sl.inventory_id WHERE $whereSQL");
$cnt->execute($params);
$total_rows = (int)$cnt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per));

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
    WHERE $whereSQL
    ORDER BY sl.created_at DESC
    LIMIT $per OFFSET $offset
");
$lst->execute($params);
$listings = $lst->fetchAll(PDO::FETCH_ASSOC);

// ── Available inventory (not yet listed) ─────────────────────
$available = $pdo->query("
    SELECT inv.*, pc.name parts_cat_name
    FROM inventory inv
    LEFT JOIN parts_categories pc ON pc.id = inv.category_id
    WHERE inv.type = 'sale'
      AND inv.status IN ('READY','STOCK')
      AND NOT EXISTS (SELECT 1 FROM shop_listings sl WHERE sl.inventory_id = inv.id)
    ORDER BY inv.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$categories = $pdo->query("SELECT * FROM shop_categories ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);

$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
$pageTitle = 'จัดการร้านค้า';
include __DIR__ . '/../templates/header_admin.php';
?>
<style>
.shop-stats{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:24px;}
.stat-card{background:var(--bg-surface);border:1px solid var(--border);border-radius:12px;padding:16px 18px;display:flex;align-items:center;gap:14px;}
.stat-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.stat-icon .material-symbols-rounded{font-size:20px;}
.stat-val{font-size:22px;font-weight:800;color:var(--text-main);line-height:1;}
.stat-lbl{font-size:11px;color:var(--text-muted);margin-top:3px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;}
.filter-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.filter-bar select,.filter-bar input{padding:7px 11px;border-radius:8px;border:1px solid var(--border);background:var(--bg-surface-alt);color:var(--text-main);font-size:13px;font-family:'Sarabun',sans-serif;}
.filter-bar input{min-width:220px;}
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
.icon-btn{width:30px;height:30px;border-radius:7px;border:1px solid var(--border);background:var(--bg-surface-alt);color:var(--text-muted);display:inline-flex;align-items:center;justify-content:center;cursor:pointer;transition:.15s;text-decoration:none;}
.icon-btn:hover{border-color:var(--primary);color:var(--primary);}
.icon-btn.del:hover{border-color:#ef4444;color:#ef4444;}
.icon-btn .material-symbols-rounded{font-size:15px;}
.specs-line{font-size:11px;color:var(--text-muted);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:220px;}
.avail-table{width:100%;border-collapse:collapse;font-size:13px;}
.avail-table th{padding:8px 12px;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid var(--border);text-align:left;}
.avail-table td{padding:9px 12px;border-bottom:1px solid var(--border);vertical-align:middle;}
.avail-table tr:last-child td{border-bottom:none;}
.avail-table tbody tr:hover td{background:var(--bg-surface-alt);}
.btn-add-shop{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:7px;background:var(--primary);color:#fff;font-size:12px;font-weight:700;text-decoration:none;white-space:nowrap;transition:opacity .15s;}
.btn-add-shop:hover{opacity:.88;}
.empty-cell{text-align:center;padding:40px!important;color:var(--text-muted);}
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

<!-- ══ สินค้าในร้าน ══ -->
<div class="cmns-card" style="padding:0;overflow:hidden;margin-bottom:28px;">
    <div style="padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        <h2 style="margin:0;font-size:15px;font-weight:700;display:flex;align-items:center;gap:7px;">
            <span class="material-symbols-rounded" style="font-size:18px;color:var(--primary);">storefront</span>
            สินค้าในร้าน
            <span style="font-size:12px;color:var(--text-muted);font-weight:400;">(<?= $total_rows ?> รายการ)</span>
        </h2>
        <form method="GET" class="filter-bar">
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
            <button type="submit" class="cmns-btn cmns-btn-secondary" style="padding:7px 14px;font-size:13px;">ค้นหา</button>
        </form>
    </div>
    <div style="overflow-x:auto;">
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
                    ยังไม่มีสินค้าในร้าน
                </td></tr>
            <?php else: foreach ($listings as $row):
                $g = $row['condition_grade'] ?? '';
                $gc = str_starts_with($g,'A') ? 'g-A' : (str_starts_with($g,'B') ? 'g-B' : 'g-C');
                $specs = implode(' / ', array_filter([$row['cpu_spec'],$row['ram_spec'],$row['storage_spec']]));
            ?>
                <tr id="lr-<?= $row['id'] ?>">
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
                        <span class="s-badge s-<?= h($row['status']) ?>"><?= match($row['status']){'published'=>'เผยแพร่','draft'=>'Draft','reserved'=>'จอง','sold'=>'ขายแล้ว',default=>h($row['status'])} ?></span>
                    </td>
                    <td style="text-align:center;color:var(--text-muted);"><?= $row['img_count'] ?></td>
                    <td style="font-size:12px;color:var(--text-muted);white-space:nowrap;"><?= date('d/m/y', strtotime($row['created_at'])) ?></td>
                    <td>
                        <div style="display:flex;gap:4px;">
                            <a href="edit.php?id=<?= $row['id'] ?>" class="icon-btn" title="แก้ไข">
                                <span class="material-symbols-rounded">edit</span>
                            </a>
                            <button type="button" class="icon-btn del" onclick="confirmDel(<?= $row['id'] ?>,'<?= h(addslashes($row['display_title'])) ?>')" title="ลบ">
                                <span class="material-symbols-rounded">delete</span>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total_pages > 1): ?>
    <div style="padding:10px 20px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
        <span style="font-size:12px;color:var(--text-muted);">หน้า <?= $page ?>/<?= $total_pages ?></span>
        <div style="display:flex;gap:6px;">
            <?php if ($page>1): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>" class="cmns-btn cmns-btn-secondary" style="padding:5px 12px;font-size:12px;">← ก่อนหน้า</a><?php endif; ?>
            <?php if ($page<$total_pages): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>" class="cmns-btn cmns-btn-secondary" style="padding:5px 12px;font-size:12px;">ถัดไป →</a><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ══ คลังพร้อมลงขาย ══ -->
<div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
    <h2 style="margin:0;font-size:15px;font-weight:700;display:flex;align-items:center;gap:7px;">
        <span class="material-symbols-rounded" style="font-size:18px;color:#8b5cf6;">add_box</span>
        คลังพร้อมลงขาย
        <span style="font-size:12px;color:var(--text-muted);font-weight:400;">(<?= count($available) ?> รายการ)</span>
    </h2>
</div>

<?php if (!$available): ?>
<div style="text-align:center;padding:40px;background:var(--bg-surface);border:1px solid var(--border);border-radius:12px;color:var(--text-muted);">
    <span class="material-symbols-rounded" style="font-size:36px;display:block;margin-bottom:8px;opacity:.3;">inventory_2</span>
    ไม่มีสินค้าในคลังที่รอลงขาย
</div>
<?php else: ?>
<div class="cmns-card" style="padding:0;overflow:hidden;">
    <div style="overflow-x:auto;">
        <table class="avail-table">
            <thead>
                <tr>
                    <th>ชื่อสินค้า</th>
                    <th>SKU</th>
                    <th>Grade</th>
                    <th>สเปก</th>
                    <th>ราคาในคลัง</th>
                    <th>หมวด (คลัง)</th>
                    <th style="width:130px;"></th>
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
                        <a href="add.php?inv_id=<?= $inv['id'] ?>" class="btn-add-shop">
                            <span class="material-symbols-rounded" style="font-size:15px;">add_circle</span>
                            เพิ่มลงร้าน
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
</div>

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
