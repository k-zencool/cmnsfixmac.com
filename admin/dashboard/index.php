<?php
/********************************************************************
 * admin/dashboard/index.php — Modern Dashboard (Fixed Error)
 ********************************************************************/

session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pageTitle = "Dashboard | ภาพรวมระบบ";

/* --- Helpers (เพิ่ม h() กลับมาให้แล้ว) --- */
function h($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function kpi($pdo, $sql) {
    try {
        return number_format($pdo->query($sql)->fetchColumn() ?: 0);
    } catch (Exception $e) { return '0'; }
}

function qrows($pdo, $sql) {
    try {
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) { return []; }
}

/* --- 1. KPIs Data --- */
$stat_warranty_active = kpi($pdo, "SELECT COUNT(*) FROM warranty_jobs WHERE warranty_status='in_warranty'");
$stat_warranty_exp    = kpi($pdo, "SELECT COUNT(*) FROM warranty_jobs WHERE warranty_status='expired'");
$stat_repairs_total   = kpi($pdo, "SELECT COUNT(*) FROM repairs");

$stat_parts_low       = kpi($pdo, "SELECT COUNT(*) FROM parts_new WHERE quantity < min_stock");
$stat_parts_total     = kpi($pdo, "SELECT SUM(quantity) FROM parts_new");

/* --- 2. Chart Data --- */
$months = [];
$repair_counts = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $months[] = date('M Y', strtotime("-$i months"));
    try {
        $c = $pdo->query("SELECT COUNT(*) FROM repairs WHERE DATE_FORMAT(created_at, '%Y-%m') = '$m'")->fetchColumn();
        $repair_counts[] = $c ?: 0;
    } catch (Exception $e) { $repair_counts[] = 0; }
}

/* --- 3. Low Stock List --- */
$low_stock_list = qrows($pdo, "SELECT part_name, quantity, min_stock FROM parts_new WHERE quantity < min_stock ORDER BY quantity ASC LIMIT 5");

/* --- 4. Warranty Expiring Soon --- */
$expiring_soon = qrows($pdo, "SELECT warranty_no, customer_name, device_model, warranty_until 
    FROM warranty_jobs 
    WHERE warranty_until BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
    ORDER BY warranty_until ASC LIMIT 5");

include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
        --card-bg: #ffffff;
        --text-main: #1f2937;
        --text-sub: #6b7280;
        --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        --shadow-hover: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.04);
        --radius: 16px;
    }

    .dashboard-container {
        display: grid;
        grid-template-columns: repeat(12, 1fr);
        gap: 24px;
        padding-bottom: 40px;
    }

    .kpi-card {
        grid-column: span 3;
        background: var(--card-bg);
        border-radius: var(--radius);
        padding: 24px;
        box-shadow: var(--shadow);
        transition: transform 0.2s, box-shadow 0.2s;
        display: flex; flex-direction: column; justify-content: space-between;
        border: 1px solid #f3f4f6;
    }
    .kpi-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-hover); }
    .kpi-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
    .kpi-title { font-size: 0.95rem; color: var(--text-sub); font-weight: 500; }
    .kpi-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: #f3f4f6; color: #4b5563; }
    .kpi-value { font-size: 2rem; font-weight: 800; color: var(--text-main); line-height: 1; }
    .kpi-stat { font-size: 0.85rem; color: #10b981; margin-top: 8px; font-weight: 500; }
    .kpi-stat.down { color: #ef4444; }

    .icon-blue { background: #eff6ff; color: #2563eb; }
    .icon-green { background: #ecfdf5; color: #059669; }
    .icon-red { background: #fef2f2; color: #dc2626; }
    .icon-gray { background: #f3f4f6; color: #4b5563; }

    .chart-section {
        grid-column: span 8;
        background: var(--card-bg);
        border-radius: var(--radius);
        padding: 24px;
        box-shadow: var(--shadow);
        border: 1px solid #f3f4f6;
    }
    .side-section {
        grid-column: span 4;
        display: flex; flex-direction: column; gap: 24px;
    }

    .list-card {
        background: var(--card-bg);
        border-radius: var(--radius);
        padding: 20px;
        box-shadow: var(--shadow);
        border: 1px solid #f3f4f6;
        flex: 1;
    }
    .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid #f3f4f6; padding-bottom: 12px; }
    .card-title { font-size: 1.1rem; font-weight: 700; color: var(--text-main); }
    .card-more { font-size: 0.85rem; color: #3b82f6; text-decoration: none; font-weight: 600; }

    .simple-table { width: 100%; border-collapse: collapse; }
    .simple-table tr { border-bottom: 1px solid #f9fafb; }
    .simple-table tr:last-child { border-bottom: none; }
    .simple-table td { padding: 12px 0; font-size: 0.95rem; color: var(--text-main); }
    .simple-table .meta { font-size: 0.85rem; color: var(--text-sub); display: block; }
    .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; background: #f3f4f6; color: #4b5563; }
    .status-warn { background: #fff7ed; color: #c2410c; }

    @media (max-width: 1024px) {
        .kpi-card { grid-column: span 6; }
        .chart-section { grid-column: span 12; }
        .side-section { grid-column: span 12; display: grid; grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 640px) {
        .kpi-card { grid-column: span 12; }
        .side-section { grid-template-columns: 1fr; }
    }
</style>

<main class="main" id="main-content">
    <div class="topbar">
        <span><?= h($pageTitle) ?></span>
        <div style="font-size:0.9rem; color:#64748b;">
            <?= date('d F Y') ?> <span id="clock" style="font-weight:600; color:#3b82f6;"></span>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="kpi-card">
            <div class="kpi-head">
                <div class="kpi-title">ประกันที่คุ้มครองอยู่</div>
                <div class="kpi-icon icon-green"><span class="material-symbols-rounded">verified_user</span></div>
            </div>
            <div class="kpi-value"><?= $stat_warranty_active ?></div>
            <div class="kpi-stat">เครื่อง</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-head">
                <div class="kpi-title">หมดประกันแล้ว</div>
                <div class="kpi-icon icon-gray"><span class="material-symbols-rounded">gpp_bad</span></div>
            </div>
            <div class="kpi-value"><?= $stat_warranty_exp ?></div>
            <div class="kpi-stat down">เครื่อง</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-head">
                <div class="kpi-title">อะไหล่ใกล้หมด</div>
                <div class="kpi-icon icon-red"><span class="material-symbols-rounded">inventory_2</span></div>
            </div>
            <div class="kpi-value"><?= $stat_parts_low ?></div>
            <div class="kpi-stat down">รายการที่ต้องเติม</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-head">
                <div class="kpi-title">งานซ่อมทั้งหมด</div>
                <div class="kpi-icon icon-blue"><span class="material-symbols-rounded">build</span></div>
            </div>
            <div class="kpi-value"><?= $stat_repairs_total ?></div>
            <div class="kpi-stat">Jobs</div>
        </div>

        <div class="chart-section">
            <div class="card-header">
                <div class="card-title">แนวโน้มงานซ่อม (6 เดือนล่าสุด)</div>
            </div>
            <div style="height: 300px;">
                <canvas id="repairChart"></canvas>
            </div>
        </div>

        <div class="side-section">
            
            <div class="list-card">
                <div class="card-header">
                    <div class="card-title" style="color:#dc2626;">
                        <span class="material-symbols-rounded" style="vertical-align:bottom; font-size:20px;">warning</span> 
                        อะไหล่ใกล้หมด
                    </div>
                    <a href="../parts/index.php" class="card-more">จัดการสต็อก →</a>
                </div>
                <table class="simple-table">
                    <?php if(empty($low_stock_list)): ?>
                        <tr><td colspan="2" style="text-align:center; color:#9ca3af;">สต็อกปกติดีเยี่ยม 👍</td></tr>
                    <?php else: foreach($low_stock_list as $p): ?>
                    <tr>
                        <td><div style="font-weight:600;"><?= h($p['part_name']) ?></div></td>
                        <td style="text-align:right;">
                            <span class="status-badge status-warn">เหลือ <?= number_format($p['quantity']) ?> / Min <?= number_format($p['min_stock']) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </table>
            </div>

            <div class="list-card">
                <div class="card-header">
                    <div class="card-title">ประกันใกล้หมดอายุ (7 วัน)</div>
                    <a href="../warranty/index.php" class="card-more">ดูทั้งหมด →</a>
                </div>
                <table class="simple-table">
                    <?php if(empty($expiring_soon)): ?>
                        <tr><td colspan="2" style="text-align:center; color:#9ca3af;">ไม่มีรายการใกล้หมดอายุ</td></tr>
                    <?php else: foreach($expiring_soon as $w): 
                        $daysLeft = ceil((strtotime($w['warranty_until']) - time()) / 86400);
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;"><?= h($w['device_model']) ?></div>
                            <span class="meta"><?= h($w['customer_name']) ?></span>
                        </td>
                        <td style="text-align:right;">
                            <div style="font-weight:700; color:#d97706;">อีก <?= $daysLeft ?> วัน</div>
                            <span class="meta" style="font-size:0.75rem;"><?= date('d/m/y', strtotime($w['warranty_until'])) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </table>
            </div>

        </div>
    </div>
</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>

<script>
    function updateClock() {
        const now = new Date();
        document.getElementById('clock').innerText = now.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
    }
    setInterval(updateClock, 1000); updateClock();

    const ctx = document.getElementById('repairChart').getContext('2d');
    let gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(59, 130, 246, 0.2)');
    gradient.addColorStop(1, 'rgba(59, 130, 246, 0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($months) ?>,
            datasets: [{
                label: 'จำนวนงานซ่อม',
                data: <?= json_encode($repair_counts) ?>,
                borderColor: '#3b82f6', backgroundColor: gradient,
                borderWidth: 3, pointBackgroundColor: '#ffffff', pointBorderColor: '#3b82f6',
                pointRadius: 5, fill: true, tension: 0.4
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { borderDash: [4, 4], color: '#f3f4f6' }, ticks: { font: { family: 'Sarabun' } } },
                x: { grid: { display: false }, ticks: { font: { family: 'Sarabun' } } }
            }
        }
    });
</script>