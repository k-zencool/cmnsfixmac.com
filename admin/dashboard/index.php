<?php
session_start();
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$pageTitle = "Dashboard";

/* ═══════════════════════════════════════════
   KPI CARDS
═══════════════════════════════════════════ */
$active_statuses = ['QS','WC','OK','RW','FN','NCF','NCS'];
$in_placeholders = implode(',', array_fill(0, count($active_statuses), '?'));

$kpi = [];

// งานที่กำลัง active
$s = $pdo->prepare("SELECT COUNT(*) FROM tracking WHERE status IN ($in_placeholders)");
$s->execute($active_statuses);
$kpi['active_jobs'] = (int)$s->fetchColumn();

// งานเข้าวันนี้
$kpi['today_in'] = (int)$pdo->query("SELECT COUNT(*) FROM tracking WHERE DATE(created_at) = CURDATE()")->fetchColumn();

// งานส่งมอบวันนี้ (DV)
$kpi['today_done'] = (int)$pdo->query("SELECT COUNT(*) FROM tracking WHERE status='DV' AND DATE(updated_at)=CURDATE()")->fetchColumn();

// งานซ่อมทั้งหมด
$kpi['total_jobs'] = (int)$pdo->query("SELECT COUNT(*) FROM tracking")->fetchColumn();

// ใบประกัน active
$pdo->exec("UPDATE warranties SET status='expired' WHERE status='active' AND end_date < CURDATE()");
$kpi['warranties'] = (int)$pdo->query("SELECT COUNT(*) FROM warranties WHERE status='active'")->fetchColumn();

// ประกันจะหมดใน 30 วัน
$kpi['expiring_soon'] = (int)$pdo->query("SELECT COUNT(*) FROM warranties WHERE status='active' AND end_date <= DATE_ADD(CURDATE(),INTERVAL 30 DAY)")->fetchColumn();

// อะไหล่ใกล้หมด (OOS = out of stock)
$kpi['low_stock'] = (int)$pdo->query("SELECT COUNT(*) FROM inventory WHERE status='OOS' AND type IN ('new','used')")->fetchColumn();

// บทความที่เผยแพร่
$kpi['articles'] = (int)$pdo->query("SELECT COUNT(*) FROM articles WHERE status='published'")->fetchColumn();

// สินค้าในร้าน
$kpi['shop'] = (int)$pdo->query("SELECT COUNT(*) FROM shop_listings WHERE status='active'")->fetchColumn();

// Chat contacts
$kpi['chats'] = (int)$pdo->query("SELECT COUNT(*) FROM chat_contacts")->fetchColumn();

/* ═══════════════════════════════════════════
   STATUS BREAKDOWN (Donut chart)
═══════════════════════════════════════════ */
$statusMap = [
    'QS'=>['รอเช็คราคา','#f59e0b'],
    'WC'=>['รอคอนเฟิร์ม','#3b82f6'],
    'OK'=>['กำลังซ่อม','#8b5cf6'],
    'RW'=>['งานแก้/เคลม','#ef4444'],
    'FN'=>['ซ่อมเสร็จ/รอรับ','#10b981'],
    'NCF'=>['ติดต่อไม่ได้','#6b7280'],
    'NCS'=>['ติดต่อไม่ได้(เสนอ)','#9ca3af'],
    'DV'=>['ส่งมอบแล้ว','#1e40af'],
    'XX'=>['ยกเลิก','#dc2626'],
    'RT'=>['รับคืนแล้ว','#374151'],
];
$status_rows = $pdo->query("SELECT status, COUNT(*) as cnt FROM tracking GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
$status_data = [];
foreach ($status_rows as $r) {
    $info = $statusMap[$r['status']] ?? [$r['status'],'#9ca3af'];
    $status_data[] = ['label'=>$info[0], 'color'=>$info[1], 'cnt'=>(int)$r['cnt']];
}

// Active only donut (งานที่ยังไม่เสร็จ)
$active_status_data = array_filter($status_data, fn($d) => in_array(array_search($d, $status_data) >= 0, [true]) && $d['cnt'] > 0 && !in_array($d['label'], ['ส่งมอบแล้ว','รับคืนแล้ว']));

/* ═══════════════════════════════════════════
   DAILY JOBS — 30 วัน (Line chart)
═══════════════════════════════════════════ */
$daily_rows = $pdo->query("
    SELECT DATE(created_at) as day, COUNT(*) as cnt
    FROM tracking
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    GROUP BY DATE(created_at)
    ORDER BY day ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Fill missing dates
$daily_map = array_column($daily_rows, 'cnt', 'day');
$daily_labels = [];
$daily_values = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $daily_labels[] = date('d/m', strtotime($d));
    $daily_values[] = (int)($daily_map[$d] ?? 0);
}

/* ═══════════════════════════════════════════
   MONTHLY JOBS — 6 เดือน (Bar chart)
═══════════════════════════════════════════ */
$monthly_rows = $pdo->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') as month, COUNT(*) as cnt
    FROM tracking
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month ORDER BY month ASC
")->fetchAll(PDO::FETCH_ASSOC);
$monthly_map = array_column($monthly_rows, 'cnt', 'month');
$monthly_labels = [];
$monthly_values = [];
$th_months = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-{$i} months"));
    $mn = (int)date('n', strtotime("-{$i} months"));
    $yr = date('y', strtotime("-{$i} months"));
    $monthly_labels[] = $th_months[$mn]." '".$yr;
    $monthly_values[] = (int)($monthly_map[$m] ?? 0);
}

/* ═══════════════════════════════════════════
   DEVICE TYPE — top 7 (Horizontal bar)
═══════════════════════════════════════════ */
$device_rows = $pdo->query("
    SELECT device_type, COUNT(*) as cnt FROM tracking
    WHERE device_type != '' AND device_type IS NOT NULL
    GROUP BY device_type ORDER BY cnt DESC LIMIT 7
")->fetchAll(PDO::FETCH_ASSOC);
$device_labels = array_column($device_rows, 'device_type');
$device_values = array_map('intval', array_column($device_rows, 'cnt'));
$device_colors = ['#2563eb','#7c3aed','#059669','#d97706','#dc2626','#0891b2','#4f46e5'];

/* ═══════════════════════════════════════════
   RECENT JOBS TABLE
═══════════════════════════════════════════ */
$recent_jobs = $pdo->query("
    SELECT id, ticket_number, customer_name, device_type, device_model, status, created_at, final_cost
    FROM tracking ORDER BY id DESC LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

/* ═══════════════════════════════════════════
   EXPIRING WARRANTIES
═══════════════════════════════════════════ */
$expiring = $pdo->query("
    SELECT warranty_no, customer_name, device_model, end_date,
           DATEDIFF(end_date, CURDATE()) as days_left
    FROM warranties WHERE status='active' AND end_date <= DATE_ADD(CURDATE(),INTERVAL 30 DAY)
    ORDER BY end_date ASC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

/* ═══════════════════════════════════════════
   LOW STOCK PARTS
═══════════════════════════════════════════ */
$low_parts = $pdo->query("
    SELECT i.name, i.type, i.status,
           COALESCE(SUM(l.qty_remaining),0) AS quantity, i.min_qty
    FROM inventory i
    LEFT JOIN inventory_lots l ON l.inventory_id = i.id
    WHERE i.type IN ('new','used')
    GROUP BY i.id, i.name, i.type, i.status, i.min_qty
    HAVING quantity <= i.min_qty OR i.status = 'OOS'
    ORDER BY quantity ASC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../templates/header_admin.php';
?>
<link rel="stylesheet" href="<?= $assets_base ?>css/inventory-dashboard.css?v=3">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<style>
/* ── Layout ── */
.db-page { padding:0 0 40px; }
.db-section-title { font-size:.72rem; font-weight:800; text-transform:uppercase; letter-spacing:.8px; color:var(--text-muted); margin:28px 0 14px; display:flex; align-items:center; gap:8px; }
.db-section-title .material-symbols-rounded { font-size:16px; }

/* ── KPI Grid ── */
.db-kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:6px; }
.db-kpi-grid.sm { grid-template-columns:repeat(4,1fr); gap:10px; }
.db-kpi { background:var(--bg-surface); border:1px solid var(--border); border-radius:14px; padding:18px 20px; display:flex; align-items:center; gap:14px; transition:.15s; }
.db-kpi:hover { border-color:var(--primary); transform:translateY(-2px); box-shadow:0 4px 16px rgba(37,99,235,.1); }
.db-kpi.sm { padding:14px 16px; border-radius:12px; }
.db-kpi-icon { width:46px; height:46px; border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.db-kpi.sm .db-kpi-icon { width:38px; height:38px; border-radius:10px; }
.db-kpi-icon .material-symbols-rounded { font-size:22px; }
.db-kpi.sm .db-kpi-icon .material-symbols-rounded { font-size:18px; }
.db-kpi-val { font-size:1.7rem; font-weight:900; color:var(--text-main); line-height:1; }
.db-kpi.sm .db-kpi-val { font-size:1.3rem; }
.db-kpi-lbl { font-size:.76rem; color:var(--text-muted); margin-top:3px; }

/* ── Chart Grid ── */
.db-chart-grid { display:grid; grid-template-columns:2fr 1fr; gap:16px; }
.db-chart-grid.half { grid-template-columns:1fr 1fr; }
.db-card { background:var(--bg-surface); border:1px solid var(--border); border-radius:14px; padding:20px 22px; }
.db-card-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
.db-card-title { font-size:.88rem; font-weight:700; color:var(--text-main); display:flex; align-items:center; gap:7px; }
.db-card-title .material-symbols-rounded { font-size:17px; color:var(--primary); }
.db-card-link { font-size:.78rem; color:var(--primary); text-decoration:none; display:flex; align-items:center; gap:3px; }
.db-card-link:hover { text-decoration:underline; }
.chart-wrap { position:relative; }

/* ── Status badges ── */
.st { display:inline-flex; align-items:center; padding:2px 9px; border-radius:20px; font-size:.73rem; font-weight:700; border:1px solid transparent; white-space:nowrap; }
.st-QS { background:rgba(245,158,11,.12); color:#b45309; border-color:rgba(245,158,11,.3); }
.st-WC { background:rgba(37,99,235,.1); color:var(--primary); border-color:rgba(37,99,235,.2); }
.st-OK { background:rgba(139,92,246,.1); color:#7c3aed; border-color:rgba(139,92,246,.2); }
.st-RW { background:rgba(239,68,68,.1); color:#dc2626; border-color:rgba(239,68,68,.2); }
.st-FN { background:rgba(16,185,129,.1); color:#059669; border-color:rgba(16,185,129,.2); }
.st-NCF,.st-NCS { background:var(--bg-surface-alt); color:var(--text-muted); border-color:var(--border); }
.st-DV { background:rgba(30,64,175,.1); color:#1e40af; border-color:rgba(30,64,175,.2); }
.st-XX,.st-RT { background:var(--bg-surface-alt); color:var(--text-muted); border-color:var(--border); }

/* ── Table ── */
.db-table-wrap { overflow-x:auto; }
.db-table { width:100%; border-collapse:collapse; font-size:.84rem; }
.db-table th { padding:9px 12px; text-align:left; font-size:.72rem; font-weight:800; text-transform:uppercase; letter-spacing:.5px; color:var(--text-muted); border-bottom:1px solid var(--border); }
.db-table td { padding:10px 12px; border-bottom:1px solid var(--border); vertical-align:middle; }
.db-table tr:last-child td { border-bottom:none; }
.db-table tr:hover td { background:var(--bg-surface-alt); }
.db-ticket { font-weight:700; font-family:monospace; font-size:.82rem; color:var(--primary); }
.db-name { font-weight:600; }
.db-sub { font-size:.76rem; color:var(--text-muted); }

/* ── Warning/Alert items ── */
.db-alert-item { display:flex; align-items:center; gap:10px; padding:10px 0; border-bottom:1px solid var(--border); }
.db-alert-item:last-child { border-bottom:none; }
.db-alert-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
.db-empty { text-align:center; padding:24px; color:var(--text-muted); font-size:.85rem; }
.db-empty .material-symbols-rounded { font-size:32px; display:block; opacity:.3; margin-bottom:6px; }

/* ── Quick actions ── */
.db-actions { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-bottom:28px; }
.db-action-btn { background:var(--bg-surface); border:1.5px solid var(--border); border-radius:12px; padding:14px 10px; text-align:center; text-decoration:none; color:var(--text-main); transition:.15s; display:flex; flex-direction:column; align-items:center; gap:6px; font-size:.8rem; font-weight:600; }
.db-action-btn .material-symbols-rounded { font-size:24px; color:var(--primary); }
.db-action-btn:hover { border-color:var(--primary); background:var(--primary-light); }

/* ── Days remaining badge ── */
.days-badge { display:inline-block; padding:1px 7px; border-radius:10px; font-size:.72rem; font-weight:700; }
.days-ok   { background:#d1fae5; color:#065f46; }
.days-warn { background:#fef3c7; color:#92400e; }
.days-over { background:#fee2e2; color:#991b1b; }

@media(max-width:1100px){ .db-kpi-grid,.db-kpi-grid.sm { grid-template-columns:repeat(2,1fr); } }
@media(max-width:900px) { .db-chart-grid,.db-chart-grid.half { grid-template-columns:1fr; } .db-actions { grid-template-columns:repeat(2,1fr); } }
@media(max-width:600px) { .db-kpi-grid,.db-kpi-grid.sm { grid-template-columns:repeat(2,1fr); } }
</style>

<div class="main-content db-page">

<!-- ── Quick Actions ── -->
<div class="db-section-title"><span class="material-symbols-rounded">bolt</span>Quick Actions</div>
<div class="db-actions">
    <a href="/admin/tracking/create.php" class="db-action-btn">
        <span class="material-symbols-rounded">add_task</span> เปิดงานซ่อม
    </a>
    <a href="/admin/warranty/create.php" class="db-action-btn">
        <span class="material-symbols-rounded">verified_user</span> ออกใบประกัน
    </a>
    <a href="/admin/inventory/" class="db-action-btn">
        <span class="material-symbols-rounded">inventory_2</span> คลังอะไหล่
    </a>
    <a href="/admin/articles/create.php" class="db-action-btn">
        <span class="material-symbols-rounded">edit_note</span> เขียนบทความ
    </a>
</div>

<!-- ── KPI Main ── -->
<div class="db-section-title"><span class="material-symbols-rounded">monitoring</span>ภาพรวมวันนี้</div>
<div class="db-kpi-grid">
    <div class="db-kpi">
        <div class="db-kpi-icon" style="background:rgba(37,99,235,.1);">
            <span class="material-symbols-rounded" style="color:var(--primary);">build_circle</span>
        </div>
        <div>
            <div class="db-kpi-val"><?= number_format($kpi['active_jobs']) ?></div>
            <div class="db-kpi-lbl">งานที่กำลังดำเนินการ</div>
        </div>
    </div>
    <div class="db-kpi">
        <div class="db-kpi-icon" style="background:rgba(16,185,129,.1);">
            <span class="material-symbols-rounded" style="color:#059669;">today</span>
        </div>
        <div>
            <div class="db-kpi-val"><?= number_format($kpi['today_in']) ?></div>
            <div class="db-kpi-lbl">งานเข้าวันนี้</div>
        </div>
    </div>
    <div class="db-kpi">
        <div class="db-kpi-icon" style="background:rgba(30,64,175,.1);">
            <span class="material-symbols-rounded" style="color:#1e40af;">task_alt</span>
        </div>
        <div>
            <div class="db-kpi-val"><?= number_format($kpi['today_done']) ?></div>
            <div class="db-kpi-lbl">ส่งมอบวันนี้</div>
        </div>
    </div>
    <div class="db-kpi">
        <div class="db-kpi-icon" style="background:rgba(107,114,128,.1);">
            <span class="material-symbols-rounded" style="color:#6b7280;">folder_open</span>
        </div>
        <div>
            <div class="db-kpi-val"><?= number_format($kpi['total_jobs']) ?></div>
            <div class="db-kpi-lbl">งานทั้งหมดในระบบ</div>
        </div>
    </div>
</div>

<!-- ── KPI Secondary ── -->
<div class="db-kpi-grid sm" style="margin-top:10px;">
    <div class="db-kpi sm" style="<?= $kpi['warranties']>0?'':'opacity:.6;' ?>">
        <div class="db-kpi-icon" style="background:rgba(16,185,129,.1);">
            <span class="material-symbols-rounded" style="color:#059669; font-size:18px;">verified_user</span>
        </div>
        <div>
            <div class="db-kpi-val"><?= $kpi['warranties'] ?></div>
            <div class="db-kpi-lbl">ประกัน active</div>
        </div>
    </div>
    <div class="db-kpi sm" style="<?= $kpi['expiring_soon']>0?'border-color:rgba(245,158,11,.4);':'' ?>">
        <div class="db-kpi-icon" style="background:rgba(245,158,11,.1);">
            <span class="material-symbols-rounded" style="color:#b45309; font-size:18px;">schedule</span>
        </div>
        <div>
            <div class="db-kpi-val"><?= $kpi['expiring_soon'] ?></div>
            <div class="db-kpi-lbl">ประกันหมดใน 30 วัน</div>
        </div>
    </div>
    <div class="db-kpi sm" style="<?= $kpi['low_stock']>0?'border-color:rgba(239,68,68,.4);':'' ?>">
        <div class="db-kpi-icon" style="background:rgba(239,68,68,.1);">
            <span class="material-symbols-rounded" style="color:#dc2626; font-size:18px;">warning</span>
        </div>
        <div>
            <div class="db-kpi-val"><?= $kpi['low_stock'] ?></div>
            <div class="db-kpi-lbl">อะไหล่ใกล้หมด</div>
        </div>
    </div>
    <div class="db-kpi sm">
        <div class="db-kpi-icon" style="background:rgba(139,92,246,.1);">
            <span class="material-symbols-rounded" style="color:#7c3aed; font-size:18px;">storefront</span>
        </div>
        <div>
            <div class="db-kpi-val"><?= $kpi['shop'] ?></div>
            <div class="db-kpi-lbl">สินค้าในร้านค้า</div>
        </div>
    </div>
</div>

<!-- ── Charts Row 1: Line + Donut ── -->
<div class="db-section-title" style="margin-top:32px;"><span class="material-symbols-rounded">bar_chart</span>สถิติงานซ่อม</div>
<div class="db-chart-grid">
    <!-- Line chart: งานซ่อม 30 วัน -->
    <div class="db-card">
        <div class="db-card-header">
            <div class="db-card-title"><span class="material-symbols-rounded">show_chart</span>งานซ่อมรายวัน — 30 วันล่าสุด</div>
            <a href="/admin/tracking/" class="db-card-link"><span class="material-symbols-rounded" style="font-size:14px;">open_in_new</span>ดูทั้งหมด</a>
        </div>
        <div class="chart-wrap" style="height:200px;">
            <canvas id="chartDaily"></canvas>
        </div>
    </div>
    <!-- Donut: สถานะงาน -->
    <div class="db-card">
        <div class="db-card-header">
            <div class="db-card-title"><span class="material-symbols-rounded">donut_large</span>สัดส่วนสถานะงาน</div>
        </div>
        <div class="chart-wrap" style="height:200px;">
            <canvas id="chartStatus"></canvas>
        </div>
    </div>
</div>

<!-- ── Charts Row 2: Bar monthly + Device types ── -->
<div class="db-chart-grid half" style="margin-top:16px;">
    <!-- Bar: รายเดือน -->
    <div class="db-card">
        <div class="db-card-header">
            <div class="db-card-title"><span class="material-symbols-rounded">bar_chart</span>งานซ่อมรายเดือน — 6 เดือน</div>
        </div>
        <div class="chart-wrap" style="height:200px;">
            <canvas id="chartMonthly"></canvas>
        </div>
    </div>
    <!-- Horizontal bar: Device type -->
    <div class="db-card">
        <div class="db-card-header">
            <div class="db-card-title"><span class="material-symbols-rounded">devices</span>อุปกรณ์ที่ซ่อมมากสุด</div>
        </div>
        <div class="chart-wrap" style="height:200px;">
            <canvas id="chartDevice"></canvas>
        </div>
    </div>
</div>

<!-- ── Tables Row ── -->
<div class="db-section-title" style="margin-top:32px;"><span class="material-symbols-rounded">table_rows</span>รายละเอียด</div>
<div class="db-chart-grid">
    <!-- Recent jobs -->
    <div class="db-card" style="padding:0; overflow:hidden;">
        <div class="db-card-header" style="padding:16px 20px; border-bottom:1px solid var(--border);">
            <div class="db-card-title"><span class="material-symbols-rounded">receipt_long</span>งานซ่อมล่าสุด</div>
            <a href="/admin/tracking/" class="db-card-link">ดูทั้งหมด →</a>
        </div>
        <div class="db-table-wrap">
        <table class="db-table">
            <thead>
                <tr>
                    <th>Ticket</th>
                    <th>ลูกค้า</th>
                    <th>อุปกรณ์</th>
                    <th>สถานะ</th>
                    <th>วันที่</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($recent_jobs)): ?>
                <tr><td colspan="5"><div class="db-empty"><span class="material-symbols-rounded">inbox</span>ยังไม่มีงาน</div></td></tr>
            <?php else: foreach ($recent_jobs as $j): ?>
                <tr>
                    <td>
                        <a href="/admin/tracking/edit.php?id=<?= $j['id'] ?>" style="text-decoration:none;">
                            <span class="db-ticket"><?= h($j['ticket_number']) ?></span>
                        </a>
                    </td>
                    <td>
                        <div class="db-name"><?= h($j['customer_name']) ?></div>
                    </td>
                    <td>
                        <div><?= h($j['device_type']) ?></div>
                        <div class="db-sub"><?= h(mb_substr($j['device_model'] ?? '', 0, 20)) ?></div>
                    </td>
                    <td><span class="st st-<?= h($j['status']) ?>"><?= h($statusMap[$j['status']][0] ?? $j['status']) ?></span></td>
                    <td class="db-sub"><?= date('d/m/y', strtotime($j['created_at'])) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- Right column: warranties + low stock -->
    <div style="display:flex; flex-direction:column; gap:16px;">

        <!-- Expiring warranties -->
        <div class="db-card">
            <div class="db-card-header">
                <div class="db-card-title"><span class="material-symbols-rounded">schedule</span>ประกันจะหมดเร็วๆ นี้</div>
                <a href="/admin/warranty/" class="db-card-link">ดูทั้งหมด →</a>
            </div>
            <?php if (empty($expiring)): ?>
                <div class="db-empty"><span class="material-symbols-rounded">verified</span>ไม่มีประกันใกล้หมด</div>
            <?php else: ?>
            <?php foreach ($expiring as $w):
                $d = (int)$w['days_left'];
                $bc = $d <= 7 ? 'days-over' : ($d <= 14 ? 'days-warn' : 'days-ok');
            ?>
            <div class="db-alert-item">
                <div class="db-alert-dot" style="background:<?= $d<=7?'#ef4444':($d<=14?'#f59e0b':'#10b981') ?>;"></div>
                <div style="flex:1; min-width:0;">
                    <div style="font-size:.82rem; font-weight:700; font-family:monospace; color:var(--primary);"><?= h($w['warranty_no']) ?></div>
                    <div class="db-sub"><?= h($w['customer_name']) ?> · <?= h(mb_substr($w['device_model'],0,18)) ?></div>
                </div>
                <span class="days-badge <?= $bc ?>">เหลือ <?= $d ?> วัน</span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Low stock parts -->
        <div class="db-card">
            <div class="db-card-header">
                <div class="db-card-title"><span class="material-symbols-rounded">warning</span>อะไหล่ใกล้หมด</div>
                <a href="/admin/inventory/" class="db-card-link">คลัง →</a>
            </div>
            <?php if (empty($low_parts)): ?>
                <div class="db-empty"><span class="material-symbols-rounded">check_circle</span>อะไหล่เพียงพอ</div>
            <?php else: ?>
            <?php foreach ($low_parts as $p):
                $qty = (int)$p['quantity'];
                $min = (int)$p['min_qty'];
                $pct = $min > 0 ? min(100, round($qty / $min * 100)) : 0;
                $is_oos = $qty === 0 || $p['status'] === 'OOS';
            ?>
            <div class="db-alert-item">
                <div class="db-alert-dot" style="background:<?= $is_oos?'#ef4444':'#f59e0b' ?>;"></div>
                <div style="flex:1; min-width:0;">
                    <div style="font-size:.82rem; font-weight:600;"><?= h(mb_substr($p['name'],0,24)) ?></div>
                    <div class="db-sub"><?= h($p['type']) ?> · <?= $is_oos?'หมด':'ใกล้หมด' ?></div>
                    <div style="height:4px; background:var(--border); border-radius:4px; margin-top:5px; overflow:hidden;">
                        <div style="height:100%; width:<?= $pct ?>%; background:<?= $is_oos?'#ef4444':'#f59e0b' ?>; border-radius:4px;"></div>
                    </div>
                </div>
                <div style="font-size:.88rem; font-weight:800; color:<?= $is_oos?'#dc2626':'#b45309' ?>; flex-shrink:0; margin-left:8px;">
                    <?= $qty ?> / <?= $min ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div><!-- right col -->
</div><!-- table grid -->

</div><!-- .db-page -->

<!-- ── Chart.js Init ── -->
<script>
const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
const gridColor  = isDark ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.06)';
const textColor  = isDark ? '#94a3b8' : '#6b7280';
const chartDefaults = {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: { mode:'index', intersect:false } },
    scales: {
        x: { grid:{ color:gridColor }, ticks:{ color:textColor, font:{size:10} } },
        y: { grid:{ color:gridColor }, ticks:{ color:textColor, font:{size:10} }, beginAtZero:true }
    }
};

// ── Daily line chart ──
new Chart(document.getElementById('chartDaily'), {
    type: 'line',
    data: {
        labels: <?= json_encode($daily_labels) ?>,
        datasets: [{
            label: 'งาน', data: <?= json_encode($daily_values) ?>,
            borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,.08)',
            fill: true, tension: .4, pointRadius: 3, pointHoverRadius: 5,
            borderWidth: 2, pointBackgroundColor: '#2563eb'
        }]
    },
    options: { ...chartDefaults, plugins:{ ...chartDefaults.plugins, tooltip:{ callbacks:{ title: t => t[0].label+' (30 วัน)' } } } }
});

// ── Status donut ──
const statusData = <?= json_encode(array_values($status_data)) ?>;
const activeStatus = statusData.filter(d => d.cnt > 0);
new Chart(document.getElementById('chartStatus'), {
    type: 'doughnut',
    data: {
        labels: activeStatus.map(d => d.label),
        datasets: [{ data: activeStatus.map(d => d.cnt), backgroundColor: activeStatus.map(d => d.color), borderWidth:2, borderColor: isDark?'#1e293b':'#fff', hoverOffset:6 }]
    },
    options: {
        responsive:true, maintainAspectRatio:false,
        cutout:'65%',
        plugins: {
            legend: { display:true, position:'right', labels:{ color:textColor, font:{size:10}, padding:8, boxWidth:10 } },
            tooltip: { callbacks:{ label: c => ` ${c.label}: ${c.raw} งาน` } }
        }
    }
});

// ── Monthly bar chart ──
new Chart(document.getElementById('chartMonthly'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($monthly_labels) ?>,
        datasets: [{
            label: 'งาน', data: <?= json_encode($monthly_values) ?>,
            backgroundColor: 'rgba(37,99,235,.75)', borderRadius:8, borderSkipped:false
        }]
    },
    options: { ...chartDefaults }
});

// ── Device horizontal bar ──
new Chart(document.getElementById('chartDevice'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($device_labels) ?>,
        datasets: [{
            label: 'งาน', data: <?= json_encode($device_values) ?>,
            backgroundColor: <?= json_encode($device_colors) ?>,
            borderRadius: 6, borderSkipped: false
        }]
    },
    options: {
        ...chartDefaults,
        indexAxis: 'y',
        scales: {
            x: { grid:{ color:gridColor }, ticks:{ color:textColor, font:{size:10} }, beginAtZero:true },
            y: { grid:{ display:false }, ticks:{ color:textColor, font:{size:11} } }
        }
    }
});
</script>

<?php include __DIR__ . '/../templates/footer_admin.php'; ?>
