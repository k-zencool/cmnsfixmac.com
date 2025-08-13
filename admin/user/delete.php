<?php
// === SETUP ===
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

// ★★★ พระเจ้าเท่านั้นที่สั่งลบได้! ★★★
require_role(['super_admin']);

// 1. ตรวจสอบว่ามี ID ส่งมาใน URL หรือไม่
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php"); // ถ้าไม่มี ID ก็เด้งกลับไปเฉยๆ
    exit;
}
$id_to_delete = (int)$_GET['id'];

// 2. ★★★ ป้องกันการฆ่าตัวตาย (ลบ User ตัวเอง) ★★★
// นี่คือส่วนที่สำคัญที่สุด!
if ($id_to_delete === (int)$_SESSION['admin_id']) {
    // อาจจะส่งข้อความ Error กลับไปบอกที่หน้า index ก็ได้
    // header("Location: index.php?error=cannot_delete_self");
    die("Error: ไม่สามารถลบ User ของตัวเองได้");
}

// 3. ทำการลบข้อมูล
try {
    $sql = "DELETE FROM admin_users WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_to_delete]);

    // 4. เมื่อลบสำเร็จ ให้เด้งกลับไปหน้า List พร้อมข้อความแจ้งเตือน
    header("Location: index.php?delete_success=1");
    exit;

} catch (PDOException $e) {
    // หากเกิดปัญหาในการลบ
    die("Database Error: ไม่สามารถลบ User ได้ " . $e->getMessage());
}