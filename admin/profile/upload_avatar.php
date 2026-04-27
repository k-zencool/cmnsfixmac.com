<?php
session_start();
require_once '../../includes/db.php';

// เช็คว่าล็อกอินไหม
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
    
    $file = $_FILES['avatar'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
    $max_size = 2 * 1024 * 1024; // ลิมิต 2MB

    // 1. เช็คประเภทไฟล์
    if (!in_array($file['type'], $allowed_types)) {
        $_SESSION['error'] = "อัปโหลดได้เฉพาะไฟล์ JPG, PNG, WEBP เท่านั้น!";
        header("Location: index.php");
        exit();
    }

    // 2. เช็คขนาดไฟล์
    if ($file['size'] > $max_size) {
        $_SESSION['error'] = "ขนาดรูปต้องไม่เกิน 2MB ว่ะเพื่อน!";
        header("Location: index.php");
        exit();
    }

    // =========================================================
    // ✅ 3. สร้างระบบโฟลเดอร์ย่อย (ปี/เดือน) ให้เร็วปรู๊ดปร๊าด!
    // =========================================================
    $year_month = date('Y/m'); // จะได้ค่าออกมาแบบ "2026/03"
    $base_upload_dir = '../../uploads/avatars/';
    $upload_dir = $base_upload_dir . $year_month . '/'; // รวมเป็น ../../uploads/avatars/2026/03/

    // ถ้าโฟลเดอร์ ปี/เดือน นี้ยังไม่มี ก็สั่งให้มันสร้างซะ
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // 4. สุ่มชื่อไฟล์ใหม่
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = 'admin_' . $admin_id . '_' . time() . '.' . $ext;
    
    // Path จริงๆ ที่จะเอาไฟล์ไปวางในเซิร์ฟเวอร์
    $target_file = $upload_dir . $new_filename;

    // ชื่อที่จะเก็บลง Database (เก็บโฟลเดอร์ย่อยไปด้วย เช่น "2026/03/admin_1_xxx.jpg")
    $db_avatar_path = $year_month . '/' . $new_filename;

    try {
        // 5. ดึงข้อมูลรูปเก่ามาดูก่อน
        $stmt = $pdo->prepare("SELECT avatar FROM admin_users WHERE id = :id");
        $stmt->execute([':id' => $admin_id]);
        $old_avatar = $stmt->fetchColumn();

        // 6. โยนไฟล์รูปเข้าโฟลเดอร์ย่อย
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            
            // 7. อัปเดต Path ใหม่ลง Database
            $updateStmt = $pdo->prepare("UPDATE admin_users SET avatar = :avatar WHERE id = :id");
            $updateStmt->execute([
                ':avatar' => $db_avatar_path,
                ':id' => $admin_id
            ]);

            // 8. ลบรูปเก่าทิ้ง (เช็คตาม Path เดิมที่อยู่ใน DB เลย)
            if (!empty($old_avatar) && file_exists($base_upload_dir . $old_avatar)) {
                unlink($base_upload_dir . $old_avatar);
            }

            $_SESSION['success'] = "อัปโหลดรูปสำเร็จ! (เซฟลงโฟลเดอร์ ".$year_month.")";
        } else {
            $_SESSION['error'] = "อัปโหลดพัง! มึงไปเช็คสิทธิ์ Permission โฟลเดอร์ด้วย!";
        }

    } catch (PDOException $e) {
        $_SESSION['error'] = "Database Error: " . $e->getMessage();
    }

} else {
    $_SESSION['error'] = "มึงยังไม่ได้เลือกรูปเลย หรือไฟล์อาจจะพัง!";
}

header("Location: index.php");
exit();
?>