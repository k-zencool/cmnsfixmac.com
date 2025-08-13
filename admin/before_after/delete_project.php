<?php
session_start();
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_login(); 

// 1. เช็คว่ามี ID ส่งมาไหม
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "ID โปรเจกต์ไม่ถูกต้อง";
    header("Location: index.php");
    exit;
}
$project_id = $_GET['id'];

try {
    // 2. ดึงข้อมูล Path ของรูปภาพมาก่อนที่จะลบ
    $stmt = $pdo->prepare("SELECT before_image_path, after_image_path, combined_image_path FROM photo_projects WHERE id = ?");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($project) {
        // 3. ลบไฟล์รูปภาพออกจากเซิร์ฟเวอร์ (ถ้ามี)
        $files_to_delete = [
            $project['before_image_path'],
            $project['after_image_path'],
            $project['combined_image_path']
        ];

        foreach ($files_to_delete as $file_path) {
            if (!empty($file_path)) {
                $full_path = __DIR__ . '/../../' . $file_path;
                if (file_exists($full_path)) {
                    unlink($full_path);
                }
            }
        }
    }

    // 4. ลบแถวข้อมูลออกจากฐานข้อมูล
    $delete_stmt = $pdo->prepare("DELETE FROM photo_projects WHERE id = ?");
    if ($delete_stmt->execute([$project_id])) {
        $_SESSION['success_message'] = "ลบโปรเจกต์สำเร็จแล้ว";
    } else {
        $_SESSION['error_message'] = "ไม่สามารถลบโปรเจกต์ได้";
    }

} catch (PDOException $e) {
    $_SESSION['error_message'] = "เกิดข้อผิดพลาดกับฐานข้อมูล: " . $e->getMessage();
}

// 5. เด้งกลับไปหน้าแรก
header("Location: index.php");
exit;
?>