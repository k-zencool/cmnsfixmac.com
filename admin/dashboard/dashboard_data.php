<?php
if (!defined('IN_APP')) {
    // เด้งกลับไปที่หน้า index ของ dashboard
    header("Location: /admin/dashboard/");
    exit();
}
// ...

// ★★★ ลบ session_start, require, require_login ที่ซ้ำซ้อนทิ้งไปให้หมด! ★★★
// เพราะไฟล์ index.php (เจ้านาย) จัดการให้แล้ว

// ฟังก์ชัน e() ควรจะอยู่ในไฟล์กลางๆ ที่เรียกใช้ทุกหน้า หรือใน header
function e($string)
{
    return htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8');
}

$username = $_SESSION['admin_username'] ?? 'Admin';

try {
    // ==========================================================
    //  ส่วน Logic การดึงข้อมูล... เหมือนเดิมทุกประการ
    // ==========================================================

    // --- 1. Summary Cards Data ---
    $totalPartTypes = (int) $pdo->query("SELECT COUNT(*) FROM parts")->fetchColumn();
    $totalStockQuantity = (int) $pdo->query("SELECT SUM(quantity) FROM parts")->fetchColumn();

    // เราต้องเพิ่ม low_stock_threshold เข้าไปในตาราง parts ก่อนถึงจะใช้ได้
    $lowStockCount = (int) $pdo->query("SELECT COUNT(*) FROM parts WHERE quantity > 0 AND quantity <= low_stock_threshold")->fetchColumn();

    $stmtMonth = $pdo->prepare("SELECT COUNT(*) FROM stock_log WHERE quantity_change < 0 AND MONTH(log_date) = ? AND YEAR(log_date) = ?");
    $stmtMonth->execute([date('m'), date('Y')]);
    $checkoutsThisMonth = (int) $stmtMonth->fetchColumn();


    // --- 2. Chart Data ---
    $currentYear = date('Y');
    $chartMonths = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

    $stockInData = array_fill(0, 12, 0);
    $stmtIn = $pdo->prepare("SELECT MONTH(log_date) AS month, SUM(quantity_change) AS total FROM stock_log WHERE quantity_change > 0 AND YEAR(log_date) = ? GROUP BY MONTH(log_date)");
    $stmtIn->execute([$currentYear]);
    foreach ($stmtIn->fetchAll(PDO::FETCH_KEY_PAIR) as $month => $total) {
        $stockInData[$month - 1] = (int)$total;
    }

    $stockOutData = array_fill(0, 12, 0);
    $stmtOut = $pdo->prepare("SELECT MONTH(log_date) AS month, SUM(quantity_change) AS total FROM stock_log WHERE quantity_change < 0 AND YEAR(log_date) = ? GROUP BY MONTH(log_date)");
    $stmtOut->execute([$currentYear]);
    foreach ($stmtOut->fetchAll(PDO::FETCH_KEY_PAIR) as $month => $total) {
        $stockOutData[$month - 1] = abs((int)$total);
    }


    // --- 3. Latest Items Data ---
    $latestAddedParts = $pdo->query("SELECT part_id, part_name, part_number, created_at FROM parts ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

    $latestStockMovements = $pdo->query("
        SELECT sl.log_date, sl.quantity_change, sl.notes, p.part_name, au.username 
        FROM stock_log sl
        JOIN parts p ON sl.part_id = p.part_id
        JOIN admin_users au ON sl.admin_id = au.id
        ORDER BY sl.log_date DESC LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    $lowStockParts = $pdo->query(
        "
        SELECT part_id, part_name, part_number, quantity 
        FROM parts 
        WHERE quantity > 0 AND quantity <= low_stock_threshold 
        ORDER BY quantity ASC LIMIT 5"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// ไฟล์นี้ไม่มี HTML
