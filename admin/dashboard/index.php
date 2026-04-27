<?php
session_start();

/**
 * =================================================================
 * 1. DATABASE CONNECTION & AUTH CHECK
 * =================================================================
 */
require_once '../../includes/db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$pageTitle = "Dashboard Overview";

/**
 * =================================================================
 * 2. FETCH DASHBOARD DATA (REAL-TIME)
 * =================================================================
 */
try {
    // 2.1 งานค้างสะสม (Status ไม่ใช่ completed)
    $stmt_pending = $pdo->query("SELECT COUNT(*) FROM tracking WHERE status != 'completed'");
    $count_pending = $stmt_pending->fetchColumn();

    // 2.2 งานใหม่วันนี้ (อิงจากวันที่ปัจจุบัน)
    $stmt_today = $pdo->query("SELECT COUNT(*) FROM tracking WHERE DATE(created_at) = CURDATE()");
    $count_today = $stmt_today->fetchColumn();

    // 2.3 งานที่ปิดไปแล้ววันนี้ (อัปเดตสถานะเป็น completed วันนี้)
    $stmt_done_today = $pdo->query("SELECT COUNT(*) FROM tracking WHERE status = 'completed' AND DATE(updated_at) = CURDATE()");
    $count_done_today = $stmt_done_today->fetchColumn();

    // 2.4 งานซ่อมล่าสุด 5 รายการ
    $stmt_recent = $pdo->query("SELECT ticket_number, customer_name, device_model, status, created_at 
                                FROM tracking 
                                ORDER BY created_at DESC LIMIT 5");
    $recent_jobs = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);

    // 2.5 สถิติอะไหล่จากตาราง parts_new (ดึงตัวที่เหลือน้อยที่สุดของหมวด LCD และ Battery)
    $stmt_lcd = $pdo->prepare("SELECT part_name, quantity FROM parts_new WHERE category = 'LCD' ORDER BY quantity ASC LIMIT 1");
    $stmt_lcd->execute();
    $top_lcd = $stmt_lcd->fetch(PDO::FETCH_ASSOC);

    $stmt_bat = $pdo->prepare("SELECT part_name, quantity FROM parts_new WHERE category = 'Battery' ORDER BY quantity ASC LIMIT 1");
    $stmt_bat->execute();
    $top_bat = $stmt_bat->fetch(PDO::FETCH_ASSOC);

    // 2.6 นับจำนวนอะไหล่ที่ของใกล้หมด (quantity <= min_stock)
    $stmt_low = $pdo->query("SELECT COUNT(*) FROM parts_new WHERE quantity <= min_stock");
    $low_stock_count = $stmt_low->fetchColumn();

    // 2.7 รวมจำนวนอะไหล่ที่ใช้ (จอ + แบต) เพื่อไปโชว์ในการ์ด 4
    // (อันนี้กูสมมติว่าเอาจำนวนที่ 'เคยถูกอัปเดต' มาโชว์ละกัน หรือมึงจะเปลี่ยนเป็น total quantity ก็ได้)
    $stmt_total_parts = $pdo->query("SELECT SUM(quantity) FROM parts_new WHERE category IN ('LCD', 'Battery')");
    $total_parts_in_stock = $stmt_total_parts->fetchColumn() ?: 0;

} catch (PDOException $e) {
    error_log("Dashboard Error: " . $e->getMessage());
}

include '../templates/header_admin.php';
?>

<link rel="stylesheet" href="../templates/assets/css/dashboard.css?v=<?= time() ?>">

<div class="content-padding">
    
    <div class="dashboard-grid">
        <div class="dash-card card-pending">
            <div class="card-icon"><span class="material-symbols-rounded">pending_actions</span></div>
            <div class="card-info">
                <h3><?= number_format($count_pending) ?></h3>
                <p>งานค้างสะสม</p>
            </div>
        </div>

        <div class="dash-card card-new">
            <div class="card-icon"><span class="material-symbols-rounded">add_circle</span></div>
            <div class="card-info">
                <h3><?= number_format($count_today) ?></h3>
                <p>งานใหม่วันนี้</p>
            </div>
        </div>

        <div class="dash-card card-done">
            <div class="card-icon"><span class="material-symbols-rounded">check_circle</span></div>
            <div class="card-info">
                <h3><?= number_format($count_done_today) ?></h3>
                <p>ปิดงานวันนี้</p>
            </div>
        </div>

        <div class="dash-card card-parts">
            <div class="card-icon"><span class="material-symbols-rounded">inventory_2</span></div>
            <div class="card-info">
                <h3><?= number_format($total_parts_in_stock) ?></h3>
                <p>อะไหล่ในคลัง (จอ/แบต)</p>
            </div>
        </div>
    </div>

    <div class="dashboard-main-content" style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 30px;">
        
        <div class="white-card">
            <div class="card-header">
                <h4>งานซ่อมล่าสุด</h4>
                <a href="../tracking/" class="view-all-btn">ดูทั้งหมด</a>
            </div>
            <div class="table-responsive">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Ticket</th>
                            <th>ลูกค้า</th>
                            <th>รุ่นเครื่อง</th>
                            <th>สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recent_jobs)): ?>
                            <?php foreach($recent_jobs as $job): ?>
                            <tr>
                                <td><strong>#<?= htmlspecialchars($job['ticket_number']) ?></strong></td>
                                <td><?= htmlspecialchars($job['customer_name']) ?></td>
                                <td><?= htmlspecialchars($job['device_model']) ?></td>
                                <td>
                                    <span class="status-badge status-<?= strtolower($job['status']) ?>">
                                        <?= htmlspecialchars($job['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" style="text-align:center;">ยังไม่มีงานซ่อมในระบบ</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="white-card">
            <h4 class="card-header" style="margin-bottom: 20px;">สถิติอะไหล่ในคลัง</h4>
            <div class="parts-list">
                
                <div class="part-item">
                    <div class="part-icon" style="background: rgba(2, 132, 199, 0.15); color: #0284c7;">
                        <span class="material-symbols-rounded">monitor</span>
                    </div>
                    <div class="part-detail">
                        <h5><?= $top_lcd ? htmlspecialchars($top_lcd['part_name']) : 'ไม่มีข้อมูลจอ' ?></h5>
                        <p style="font-size: 11px; color: var(--text-muted); margin: 0;">รายการที่เหลือน้อยที่สุด (LCD)</p>
                        <div class="progress-bar" style="margin-top:8px;">
                            <div class="progress" style="width: <?= $top_lcd ? min($top_lcd['quantity']*10, 100) : 0 ?>%; background: #0284c7;"></div>
                        </div>
                    </div>
                    <div class="part-count"><?= $top_lcd['quantity'] ?? 0 ?></div>
                </div>

                <div class="part-item">
                    <div class="part-icon" style="background: rgba(217, 119, 6, 0.15); color: #d97706;">
                        <span class="material-symbols-rounded">battery_charging_full</span>
                    </div>
                    <div class="part-detail">
                        <h5><?= $top_bat ? htmlspecialchars($top_bat['part_name']) : 'ไม่มีข้อมูลแบต' ?></h5>
                        <p style="font-size: 11px; color: var(--text-muted); margin: 0;">รายการที่เหลือน้อยที่สุด (Battery)</p>
                        <div class="progress-bar" style="margin-top:8px;">
                            <div class="progress" style="width: <?= $top_bat ? min($top_bat['quantity']*10, 100) : 0 ?>%; background: #d97706;"></div>
                        </div>
                    </div>
                    <div class="part-count"><?= $top_bat['quantity'] ?? 0 ?></div>
                </div>

                <hr style="border: 0; border-top: 1px solid var(--border); margin: 25px 0;">
                
                <div style="display: flex; align-items: center; gap: 12px; color: #ef4444; background: rgba(239, 68, 68, 0.08); padding: 12px; border-radius: 10px; border: 1px dashed rgba(239, 68, 68, 0.3);">
                    <span class="material-symbols-rounded" style="font-size: 24px;">warning</span>
                    <div>
                        <div style="font-size: 13px; font-weight: 700;">อะไหล่ใกล้หมดคลัง!</div>
                        <div style="font-size: 11px; opacity: 0.8;">มีทั้งหมด <?= $low_stock_count ?> รายการ ที่ต่ำกว่าเกณฑ์</div>
                    </div>
                    <a href="../inventory/" style="margin-left:auto; color: #ef4444;"><span class="material-symbols-rounded">arrow_forward</span></a>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include '../templates/footer_admin.php'; ?>