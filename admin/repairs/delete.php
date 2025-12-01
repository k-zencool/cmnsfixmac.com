<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login(); 

$id = $_GET['id'] ?? null;

if ($id) {
  try {
    $pdo->beginTransaction();

    // 1. ดึงชื่อไฟล์ภาพก่อนลบ
    $stmt = $pdo->prepare("SELECT image FROM repairs WHERE id = ?");
    $stmt->execute([$id]);
    $image_filename = $stmt->fetchColumn();

    // 2. ลบข้อมูลจากฐานข้อมูล (ทำก่อน... ถ้า DB ลบสำเร็จ ค่อยลบไฟล์)
    $delete = $pdo->prepare("DELETE FROM repairs WHERE id = ?");
    $deleted = $delete->execute([$id]);

    if ($deleted) {
        $pdo->commit(); // [กูแก้] Commit DB ก่อน

        // 3. [กูแก้!!] ลบภาพจากโฟลเดอร์ /uploads/repairs/ (ทำหลัง Commit)
        if ($image_filename) {
            
            // สร้าง Path ที่ถูกต้อง
            $upload_dir_path = realpath(__DIR__ . '/../../uploads/repairs');
            $webroot_path = realpath(__DIR__ . '/../../');

            if ($upload_dir_path && $webroot_path) {
                // สร้าง Full Path (เป็นสตริง)
                $file_path_string = $upload_dir_path . '/' . $image_filename;

                // เช็คว่าไฟล์มีจริง
                if (file_exists($file_path_string)) {
                    // เช็คความปลอดภัย (กันแฮกเกอร์)
                    $real_file_path = realpath($file_path_string);
                    if ($real_file_path && strpos($real_file_path, $upload_dir_path) === 0) {
                        @unlink($real_file_path); // ลบแม่ง!
                    } else {
                        error_log("Delete Repair FAILED (Security): " . $file_path_string);
                    }
                }
            }
        }

    } else {
      // ถ้าลบ DB ไม่สำเร็จ
      $pdo->rollBack();
    }

  } catch (Exception $e) {
      if ($pdo->inTransaction()) {
          $pdo->rollBack();
      }
      error_log("Delete Repair FAILED (DB): " . $e->getMessage());
      // มึงอาจจะอยากโชว์ Error... แต่ตอนนี้เด้งกลับไปก่อน
  }
}

// กลับไปยังหน้าหลัก
header("Location: index.php");
exit;