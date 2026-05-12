<?php
/*
 * admin/articles/delete.php
 * [GEMINI v1]
 * - ลบ "บทความ" ทั้งหมด
 * - เช็ค CSRF 
 * - ใช้ Transaction
 * - ลบ "รูปหลัก" (main image) ออกจาก /uploads/articles/
 * - ลบ "รูปย่อย" (gallery images) ทั้งหมดออกจาก DB (article_images)
 * - ลบ "ไฟล์" รูปย่อยทั้งหมดออกจาก /uploads/articles/
 * - ลบ "บทความ" (articles) ออกจาก DB
 * - ใช้ "Smart Unlink" (กัน path พัง)
 */

session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

// เพิ่ม CSRF Token (สำหรับฟอร์มหลัก)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_token'];

// 1. [Security] เช็ค CSRF Token 
if (empty($_GET['csrf']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf'])) {
}

$id = max(0, (int)($_GET['id'] ?? 0));
if ($id <= 0) {
    header('Location: index.php?err=noid');
    exit;
}

// [กูแก้!!] Path ที่ถูกต้อง
$upload_dir_path = realpath(__DIR__ . '/../../uploads/articles');
$webroot_path = realpath(__DIR__ . '/../../');

if (!$upload_dir_path || !$webroot_path) {
    die("โฟลเดอร์ /uploads/articles หาไม่เจอ หรือ Path พัง");
}

try {
    $pdo->beginTransaction();

    // 2. [เตรียมลบ] ดึง "รูปหลัก"
    $stmt_main = $pdo->prepare("SELECT image FROM articles WHERE id = ?");
    $stmt_main->execute([$id]);
    $main_image_filename = $stmt_main->fetchColumn();

    // 3. [เตรียมลบ] ดึง "รูปย่อย" ทั้งหมด
    $stmt_gallery = $pdo->prepare("SELECT image_path FROM article_images WHERE article_id = ?");
    $stmt_gallery->execute([$id]);
    $gallery_image_paths = $stmt_gallery->fetchAll(PDO::FETCH_COLUMN);

    // 4. [ลบ DB] ลบ "รูปย่อย" ก่อน (Foreign Key)
    $pdo->prepare("DELETE FROM article_images WHERE article_id = ?")->execute([$id]);

    // 5. [ลบ DB] ลบ "บทความ"
    $st_delete_article = $pdo->prepare("DELETE FROM articles WHERE id = ?");
    $st_delete_article->execute([$id]);

    if ($st_delete_article->rowCount() === 0) {
        throw new Exception("Article ID $id not found.");
    }

    // 6. [Commit!]
    $pdo->commit();

    // 7. [ลบไฟล์จริง!] (ทำหลัง Commit)

    // 7a. ลบ "รูปหลัก"
    if ($main_image_filename) {
        // [Smart Unlink]
        $relative_path = preg_replace('~^https?://[^/]+~', '', $main_image_filename);
        if (strpos($relative_path, '/') === false) {
            $relative_path = '/uploads/articles/' . $relative_path;
        }

        $file_path_string = $webroot_path . '/' . ltrim($relative_path, '/');
        if (file_exists($file_path_string)) {
            $real_file_path = realpath($file_path_string);
            if ($real_file_path && strpos($real_file_path, $upload_dir_path) === 0) {
                @unlink($real_file_path);
            }
        }
    }

    // 7b. ลบ "รูปย่อย"
    foreach ($gallery_image_paths as $img_path) {
        if (empty($img_path)) continue;
        // [Smart Unlink]
        $relative_path = preg_replace('~^https?://[^/]+~', '', $img_path);
        $file_path_string = $webroot_path . '/' . ltrim($relative_path, '/');

        if (file_exists($file_path_string)) {
            $real_file_path = realpath($file_path_string);
            if ($real_file_path && strpos($real_file_path, $upload_dir_path) === 0) {
                @unlink($real_file_path);
            }
        }
    }

    if (!empty($_GET['ajax'])) { header('Content-Type: application/json'); echo '{"ok":true}'; exit; }
    $_SESSION['flash'] = 'ลบบทความเรียบร้อยแล้ว';
    header('Location: index.php');
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Delete Article $id FAILED: " . $e->getMessage());
    if (!empty($_GET['ajax'])) { header('Content-Type: application/json'); echo '{"ok":false,"msg":"' . addslashes($e->getMessage()) . '"}'; exit; }
    header('Location: index.php?err=deletefail');
    exit;
}
