<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pageTitle = "ราคาค่าซ่อม";

$type_filter = $_GET['type'] ?? '';
$search      = trim($_GET['q'] ?? '');

$where  = ['1=1'];
$params = [];

if ($type_filter) {
    $where[]  = 'sp.device_type = ?';
    $params[] = $type_filter;
}
if ($search) {
    $where[]  = '(sp.device_name LIKE ? OR pc.name LIKE ? OR sp.price_note LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// ── Pagination ──
$per  = max(10, min(200, (int)($_GET['per'] ?? 25)));
$page = max(1, (int)($_GET['page'] ?? 1));

$order_expr = "FIELD(sp.device_type,'iPhone','iPad','MacBook','iMac','AirPods','Apple Watch','Software','Other')";
$base_from  = "FROM service_pricing sp
        LEFT JOIN pricing_categories pc ON sp.category_id = pc.id
        WHERE " . implode(' AND ', $where);

// total (ก่อน limit) สำหรับ pager
$cst = $pdo->prepare("SELECT COUNT(*) $base_from");
$cst->execute($params);
$total  = (int)$cst->fetchColumn();
$pages  = max(1, (int)ceil($total / $per));
if ($page > $pages) $page = $pages;
$offset = ($page - 1) * $per;

$sql = "SELECT sp.*, pc.name AS category_name, pc.sort_order AS cat_order
        $base_from
        ORDER BY $order_expr, sp.device_name, pc.sort_order
        LIMIT $per OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

function prc_page_url($i)
{
    $q = $_GET;
    $q['page'] = max(1, (int)$i);
    return '?' . http_build_query($q);
}

// Stats
$stats = $pdo->query("SELECT
    COUNT(*) AS total,
    COALESCE(SUM(show_on_web = 1), 0) AS on_web,
    COALESCE(SUM(is_active = 0), 0) AS inactive,
    COALESCE(SUM(updated_at < DATE_SUB(NOW(), INTERVAL 90 DAY)), 0) AS outdated
    FROM service_pricing")->fetch(PDO::FETCH_ASSOC);

// Tab counts
$tab_rows   = $pdo->query("SELECT device_type, COUNT(*) AS cnt FROM service_pricing GROUP BY device_type")->fetchAll(PDO::FETCH_ASSOC);
$tab_counts = array_column($tab_rows, 'cnt', 'device_type');
$device_order = ['iPhone','iPad','MacBook','iMac','AirPods','Apple Watch','Software','Other'];

include __DIR__ . '/../templates/header_admin.php';
?>

<link rel="stylesheet" href="<?= $assets_base ?>css/inventory-dashboard.css?v=<?= asset_ver('/admin/templates/assets/css/inventory-dashboard.css') ?>">

<style>
/* ── Stats ── */
.prc-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 24px; }
.prc-stat  { background: var(--bg-surface); border: 1px solid var(--border); border-radius: 12px; padding: 16px 20px; display: flex; align-items: center; gap: 14px; }
.prc-stat-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.prc-stat-icon .material-symbols-rounded { font-size: 22px; }
.prc-stat-val { font-size: 1.6rem; font-weight: 800; color: var(--text-main); line-height: 1; }
.prc-stat-lbl { font-size: 0.78rem; color: var(--text-muted); margin-top: 3px; }

/* ── Tabs ── */
.prc-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 18px; }
.prc-tab { padding: 7px 16px; border-radius: 20px; border: 1.5px solid var(--border); background: var(--bg-surface); cursor: pointer; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); text-decoration: none; transition: 0.15s; display: inline-flex; align-items: center; gap: 6px; }
.prc-tab:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
.prc-tab.active { background: var(--primary); border-color: var(--primary); color: #fff; }
.prc-tab .cnt { background: rgba(0,0,0,0.12); border-radius: 10px; padding: 0 7px; font-size: 0.75rem; }
.prc-tab.active .cnt { background: rgba(255,255,255,0.25); }

/* ── Search bar ── */
.prc-search { display: flex; gap: 10px; margin-bottom: 20px; }
.prc-search-wrap { flex: 1; position: relative; }
.prc-search-wrap .material-symbols-rounded { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 20px; pointer-events: none; }
.prc-search-input { width: 100%; padding: 10px 12px 10px 40px; border: 1.5px solid var(--border); border-radius: 8px; font-size: 0.95rem; background: var(--bg-surface); color: var(--text-main); }
.prc-search-input:focus { outline: none; border-color: var(--primary); }

/* ── Table ── */
.prc-table-wrap { background: var(--bg-surface); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.prc-table { width: 100%; border-collapse: collapse; }
.prc-table th { padding: 12px 16px; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); background: var(--bg-surface-alt); border-bottom: 1px solid var(--border); text-align: left; }
.prc-table th.r { text-align: right; }
.prc-table th.c { text-align: center; }
.prc-table td { padding: 13px 16px; border-bottom: 1px solid var(--border); color: var(--text-main); vertical-align: middle; }
.prc-table tr:last-child td { border-bottom: none; }
.prc-table tbody tr:hover { background: var(--bg-surface-alt); }
.prc-table .r { text-align: right; }
.prc-table .c { text-align: center; }

/* Group header row */
.prc-group-row td { background: var(--bg-surface-alt) !important; font-size: 0.77rem; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase; color: var(--text-muted); padding: 7px 16px; }

/* Outdated row */
.prc-outdated { background: rgba(239,68,68,0.04) !important; }
.prc-outdated:hover { background: rgba(239,68,68,0.08) !important; }

/* ── Cell styles ── */
.prc-device-name { font-weight: 600; color: var(--text-main); font-size: 0.95rem; }
.prc-device-type { font-size: 0.75rem; color: var(--text-muted); margin-top: 2px; }
.prc-cat-badge { display: inline-flex; align-items: center; gap: 4px; background: var(--bg-surface-alt); color: var(--text-muted); font-size: 0.8rem; padding: 3px 10px; border-radius: 6px; border: 1px solid var(--border); }
.prc-price { font-weight: 700; color: #059669; font-size: 1rem; }
.prc-price-note { font-size: 0.75rem; color: var(--text-muted); margin-top: 2px; }
.prc-outdated-badge { font-size: 0.7rem; color: #ef4444; display: flex; align-items: center; justify-content: flex-end; gap: 2px; }
.prc-warranty { font-size: 0.85rem; color: var(--text-muted); }

/* ── Toggle buttons ── */
.tog { width: 32px; height: 32px; border-radius: 8px; border: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: 0.15s; }
.tog .material-symbols-rounded { font-size: 17px; }
.tog-web-1 { background: #dbeafe; color: #2563eb; } .tog-web-1:hover { background: #bfdbfe; }
.tog-web-0 { background: var(--bg-surface-alt); color: var(--text-muted); } .tog-web-0:hover { background: var(--border); }
.tog-act-1 { background: #dcfce7; color: #16a34a; } .tog-act-1:hover { background: #bbf7d0; }
.tog-act-0 { background: #fee2e2; color: #ef4444; } .tog-act-0:hover { background: #fecaca; }

/* ── Action buttons (มาตรฐานเดียวกับหน้าอื่น: .t-btn) ── */
.t-btn { width:30px; height:30px; display:inline-flex; align-items:center; justify-content:center; border-radius:7px; border:1px solid var(--border); background:var(--bg-surface-alt); color:var(--text-muted); cursor:pointer; transition:all .18s; padding:0; flex-shrink:0; }
.t-btn .material-symbols-rounded { font-size:16px; line-height:1; }
.t-btn:hover { transform:translateY(-1px); box-shadow:0 3px 8px rgba(0,0,0,.1); }
.t-edit:hover { color:var(--primary); background:rgba(37,99,235,.07); border-color:var(--primary); }
.t-del:hover  { color:#ef4444; background:rgba(239,68,68,.07); border-color:#ef4444; }

/* ── Pagination (มาตรฐานเดียวกับหน้าอื่น) ── */
.log-pagination { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; font-size: 13px; color: var(--text-muted); border-top: 1px solid var(--border); flex-wrap: wrap; gap: 10px; }
.page-btns { display: flex; gap: 5px; }
.page-btn { min-width: 36px; height: 36px; padding: 0 10px; border-radius: 9px; border: 1px solid var(--border); background: var(--bg-surface-alt); color: var(--text-main); font-size: 13px; text-decoration: none; font-weight: 600; transition: .2s; display: inline-flex; align-items: center; justify-content: center; }
.page-btn:hover:not(.disabled) { border-color: var(--primary); color: var(--primary); background: rgba(37,99,235,.06); }
.page-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); box-shadow: 0 2px 8px rgba(37,99,235,.3); }
.page-btn.disabled { opacity: .3; pointer-events: none; }
@media (max-width: 640px) { .log-pagination { flex-direction: column; align-items: flex-start; } }

/* ── Empty state ── */
.prc-empty { text-align: center; padding: 60px 20px; color: var(--text-muted); }
.prc-empty .material-symbols-rounded { font-size: 52px; opacity: 0.35; display: block; margin-bottom: 10px; }

/* ── Pricing modal ── */
#modal-pricing { display:none; position:fixed; inset:0; z-index:1000; background:rgba(0,0,0,.55); align-items:center; justify-content:center; }
#modal-pricing.open { display:flex; }
#pricing-modal-inner { background:var(--bg-surface,#fff); width:min(96vw,680px); height:90vh; border-radius:16px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 24px 80px rgba(0,0,0,.4); }

@media (max-width: 900px) {
    .prc-stats { grid-template-columns: repeat(2, 1fr); }
}
</style>

<div class="cmns-wrapper">

    <!-- Header -->
    <div class="cmns-header-bar">
        <div>
            <h1 class="cmns-page-title" style="color:var(--primary);">
                <span class="material-symbols-rounded" style="font-size:30px;">price_check</span>
                ราคาค่าซ่อม
            </h1>
            <p style="color:var(--text-muted);margin-top:4px;font-size:13px;">จัดการตารางราคามาตรฐาน</p>
        </div>
        <div class="cmns-action-buttons">
            <a href="categories.php" class="cmns-btn cmns-btn-secondary" style="font-size:14px;">
                <span class="material-symbols-rounded" style="font-size:16px;">category</span> หมวดบริการ
            </a>
            <button type="button" onclick="openPricingModal('form.php?modal=1')" class="cmns-btn cmns-btn-primary">
                <span class="material-symbols-rounded" style="font-size:16px;">add</span> เพิ่มราคา
            </button>
        </div>
    </div>

    <!-- Stats -->
    <div class="prc-stats">
        <div class="prc-stat">
            <div class="prc-stat-icon" style="background:#eff6ff;">
                <span class="material-symbols-rounded" style="color:#3b82f6;">format_list_bulleted</span>
            </div>
            <div>
                <div class="prc-stat-val"><?= number_format($stats['total'] ?? 0) ?></div>
                <div class="prc-stat-lbl">รายการทั้งหมด</div>
            </div>
        </div>
        <div class="prc-stat">
            <div class="prc-stat-icon" style="background:#f0fdf4;">
                <span class="material-symbols-rounded" style="color:#16a34a;">public</span>
            </div>
            <div>
                <div class="prc-stat-val"><?= number_format($stats['on_web'] ?? 0) ?></div>
                <div class="prc-stat-lbl">แสดงบนเว็บ</div>
            </div>
        </div>
        <div class="prc-stat">
            <div class="prc-stat-icon" style="background:#fff7ed;">
                <span class="material-symbols-rounded" style="color:#ea580c;">visibility_off</span>
            </div>
            <div>
                <div class="prc-stat-val"><?= number_format($stats['inactive'] ?? 0) ?></div>
                <div class="prc-stat-lbl">ปิดการใช้งาน</div>
            </div>
        </div>
        <div class="prc-stat">
            <div class="prc-stat-icon" style="background:#fff1f2;">
                <span class="material-symbols-rounded" style="color:#e11d48;">schedule</span>
            </div>
            <div>
                <div class="prc-stat-val"><?= number_format($stats['outdated'] ?? 0) ?></div>
                <div class="prc-stat-lbl">ไม่อัปเดต &gt;90 วัน</div>
            </div>
        </div>
    </div>

    <!-- Device tabs -->
    <div class="prc-tabs">
        <a href="?<?= http_build_query(['q' => $search]) ?>" class="prc-tab <?= !$type_filter ? 'active' : '' ?>">
            ทั้งหมด <span class="cnt"><?= array_sum($tab_counts) ?></span>
        </a>
        <?php foreach ($device_order as $dt):
            if (!isset($tab_counts[$dt])) continue; ?>
        <a href="?<?= http_build_query(['type' => $dt, 'q' => $search]) ?>" class="prc-tab <?= $type_filter === $dt ? 'active' : '' ?>">
            <?= htmlspecialchars($dt) ?> <span class="cnt"><?= $tab_counts[$dt] ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Search -->
    <form method="GET" class="prc-search">
        <?php if ($type_filter): ?>
        <input type="hidden" name="type" value="<?= htmlspecialchars($type_filter) ?>">
        <?php endif; ?>
        <div class="prc-search-wrap">
            <span class="material-symbols-rounded">search</span>
            <input type="text" name="q" class="prc-search-input" value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหา ชื่อรุ่น, หมวดบริการ, หมายเหตุ...">
        </div>
        <button type="submit" class="cmns-btn cmns-btn-primary" style="font-size:14px;">
            <span class="material-symbols-rounded" style="font-size:16px;">search</span> ค้นหา
        </button>
        <?php if ($search || $type_filter): ?>
        <a href="index.php" class="cmns-btn cmns-btn-secondary" style="font-size:14px;color:#ef4444;border-color:#ef4444;">
            <span class="material-symbols-rounded" style="font-size:16px;">close</span> ล้าง
        </a>
        <?php endif; ?>
    </form>

    <!-- Table -->
    <div class="prc-table-wrap">
        <table class="prc-table">
            <thead>
                <tr>
                    <th style="width:28%">รุ่นอุปกรณ์</th>
                    <th style="width:20%">หมวดบริการ</th>
                    <th class="r" style="width:14%">ราคา (฿)</th>
                    <th class="c" style="width:10%">ประกัน</th>
                    <th class="c" style="width:9%">เว็บ</th>
                    <th class="c" style="width:9%">ร้าน</th>
                    <th class="c" style="width:10%">จัดการ</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="7">
                        <div class="prc-empty">
                            <span class="material-symbols-rounded">price_check</span>
                            ยังไม่มีรายการราคา<?= ($search || $type_filter) ? ' ที่ตรงเงื่อนไข' : '' ?>
                            <?php if (!$search && !$type_filter): ?>
                            <div style="margin-top:12px;">
                                <button type="button" onclick="openPricingModal('form.php?modal=1')" class="cmns-btn cmns-btn-primary" style="font-size:14px;">
                                    <span class="material-symbols-rounded" style="font-size:16px;">add</span> เพิ่มรายการแรก
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php else:
                $last_type = null;
                foreach ($items as $item):
                    $outdated = strtotime($item['updated_at']) < strtotime('-90 days');
                    if (!$type_filter && $item['device_type'] !== $last_type):
                        $last_type = $item['device_type'];
            ?>
                <tr class="prc-group-row">
                    <td colspan="7">
                        <span class="material-symbols-rounded" style="font-size:14px;vertical-align:-3px;margin-right:5px;">devices</span>
                        <?= htmlspecialchars($item['device_type']) ?>
                        <span style="font-weight:400;opacity:0.7;margin-left:5px;">(<?= $tab_counts[$item['device_type']] ?? 0 ?> รายการ)</span>
                    </td>
                </tr>
            <?php endif; ?>
                <tr class="<?= $outdated ? 'prc-outdated' : '' ?>" id="row-<?= $item['id'] ?>">
                    <td>
                        <div class="prc-device-name"><?= htmlspecialchars($item['device_name']) ?></div>
                        <?php if ($type_filter): ?>
                        <div class="prc-device-type"><?= htmlspecialchars($item['device_type']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="prc-cat-badge">
                            <span class="material-symbols-rounded" style="font-size:13px;">build_circle</span>
                            <?= htmlspecialchars($item['category_name'] ?? '—') ?>
                        </span>
                    </td>
                    <td class="r">
                        <div class="prc-price"><?= number_format($item['price']) ?></div>
                        <?php if ($item['price_note']): ?>
                        <div class="prc-price-note"><?= htmlspecialchars($item['price_note']) ?></div>
                        <?php endif; ?>
                        <?php if ($outdated): ?>
                        <div class="prc-outdated-badge">
                            <span class="material-symbols-rounded" style="font-size:12px;">warning</span> ล้าสมัย
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="c"><span class="prc-warranty"><?= $item['warranty_days'] ?> วัน</span></td>
                    <td class="c">
                        <button class="tog tog-web-<?= $item['show_on_web'] ?>"
                            title="<?= $item['show_on_web'] ? 'แสดงบนเว็บ' : 'ซ่อนจากเว็บ' ?>"
                            onclick="toggleField(this, <?= $item['id'] ?>, 'show_on_web')">
                            <span class="material-symbols-rounded"><?= $item['show_on_web'] ? 'public' : 'visibility_off' ?></span>
                        </button>
                    </td>
                    <td class="c">
                        <button class="tog tog-act-<?= $item['is_active'] ?>"
                            title="<?= $item['is_active'] ? 'เปิดใช้งาน' : 'ปิดการใช้งาน' ?>"
                            onclick="toggleField(this, <?= $item['id'] ?>, 'is_active')">
                            <span class="material-symbols-rounded"><?= $item['is_active'] ? 'check_circle' : 'cancel' ?></span>
                        </button>
                    </td>
                    <td class="c">
                        <div style="display:flex;gap:5px;justify-content:center;">
                            <button type="button" class="t-btn t-edit" title="แก้ไข"
                                onclick="openPricingModal('form.php?id=<?= $item['id'] ?>&modal=1')">
                                <span class="material-symbols-rounded">edit</span>
                            </button>
                            <button type="button" class="t-btn t-del" title="ลบ"
                                onclick="deleteRow(<?= $item['id'] ?>, '<?= htmlspecialchars($item['device_name'] . ' — ' . $item['category_name'], ENT_QUOTES) ?>')">
                                <span class="material-symbols-rounded">delete</span>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>

        <?php if ($total > 0): ?>
        <div class="log-pagination">
            <div>
                แสดง <b><?= number_format(min($total, $offset+1)) ?>–<?= number_format(min($total, $offset+$per)) ?></b>
                จาก <b><?= number_format($total) ?></b> รายการ
                &nbsp;·&nbsp; หน้า <?= $page ?> / <?= $pages ?>
            </div>
            <div class="page-btns">
                <a href="<?= $page > 1 ? prc_page_url($page-1) : '#' ?>" class="page-btn <?= $page<=1?'disabled':'' ?>">
                    <span class="material-symbols-rounded" style="font-size:16px;">chevron_left</span>
                </a>
                <?php
                $pStart = max(1, $page - 2);
                $pEnd   = min($pages, $pStart + 4);
                for ($p = $pStart; $p <= $pEnd; $p++): ?>
                    <a href="<?= prc_page_url($p) ?>" class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <a href="<?= $page < $pages ? prc_page_url($page+1) : '#' ?>" class="page-btn <?= $page>=$pages?'disabled':'' ?>">
                    <span class="material-symbols-rounded" style="font-size:16px;">chevron_right</span>
                </a>
                <select onchange="goPerPage(this)"
                        style="padding:6px 10px;border-radius:8px;border:1px solid var(--border);background:var(--bg-surface);color:var(--text-main);font-size:13px;outline:none;cursor:pointer;font-family:'Sarabun',sans-serif;">
                    <?php foreach([25,50,100] as $pp): ?>
                        <option value="<?= $pp ?>" <?= $per===$pp?'selected':'' ?>><?= $pp ?>/หน้า</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>

<!-- Pricing Modal -->
<div id="modal-pricing">
    <div id="pricing-modal-inner">
        <iframe id="pricing-iframe" src="" style="flex:1;border:none;width:100%;display:block;"></iframe>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer_admin.php'; ?>

<script>
function openPricingModal(url) {
    document.getElementById('pricing-iframe').src = url;
    const m = document.getElementById('modal-pricing');
    m.classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closePricingModal() {
    document.getElementById('modal-pricing').classList.remove('open');
    document.body.style.overflow = '';
    setTimeout(() => { document.getElementById('pricing-iframe').src = ''; }, 200);
}
document.getElementById('modal-pricing').addEventListener('click', e => {
    if (e.target === document.getElementById('modal-pricing')) closePricingModal();
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && document.getElementById('modal-pricing').classList.contains('open')) closePricingModal();
});
window.addEventListener('message', e => {
    if (e.data === 'pricing-saved') {
        closePricingModal();
        Swal.fire({ toast:true, position:'top-end', icon:'success', title:'บันทึกเรียบร้อยแล้ว', showConfirmButton:false, timer:2500, timerProgressBar:true });
        setTimeout(() => location.reload(), 400);
    }
    if (e.data === 'pricing-close') closePricingModal();
});

function goPerPage(sel) {
    const u = new URL(location.href);
    u.searchParams.set('per', sel.value);
    u.searchParams.set('page', '1');
    location = u.toString();
}

async function toggleField(btn, id, field) {
    btn.disabled = true;
    btn.style.opacity = '0.4';
    try {
        const fd = new FormData();
        fd.append('action', 'toggle');
        fd.append('id', id);
        fd.append('field', field);
        const res  = await fetch('ajax.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.ok) throw new Error();
        const v    = data.value;
        const icon = btn.querySelector('.material-symbols-rounded');
        if (field === 'show_on_web') {
            btn.className = `tog tog-web-${v}`;
            icon.textContent = v ? 'public' : 'visibility_off';
            btn.title = v ? 'แสดงบนเว็บ' : 'ซ่อนจากเว็บ';
        } else {
            btn.className = `tog tog-act-${v}`;
            icon.textContent = v ? 'check_circle' : 'cancel';
            btn.title = v ? 'เปิดใช้งาน' : 'ปิดการใช้งาน';
        }
    } catch { alert('เกิดข้อผิดพลาด'); }
    finally { btn.disabled = false; btn.style.opacity = '1'; }
}

async function deleteRow(id, label) {
    if (!confirm(`ลบรายการ "${label}" ?\n\nไม่สามารถกู้คืนได้`)) return;
    try {
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        const res  = await fetch('ajax.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) {
            const row = document.getElementById('row-' + id);
            row.style.transition = 'opacity 0.3s';
            row.style.opacity = '0';
            setTimeout(() => row.remove(), 300);
        }
    } catch { alert('เกิดข้อผิดพลาด'); }
}
</script>
