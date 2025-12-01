<?php
/*
 * admin/articles/delete_image.php
 * [GEMINI v1]
 * - ลบ "รูปย่อย" (Gallery) แค่รูปเดียว
 * - เช็ค CSRF
 * - ใช้ Transaction
 * - ลบ "ไฟล์" รูปย่อยออกจาก /uploads/articles/
 * - ลบ "รูปย่อย" (article_images) ออกจาก DB
 * - ใช้ "Smart Unlink" (กัน path พัง)
 */

session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

// 1. [Security] เช็ค CSRF Token
if (empty($_GET['csrf']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf'])) {
    die('Token ไม่ถูกต้อง');
}

$id = max(0, (int)($_GET['id'] ?? 0)); // ID ของ *รูป* (article_images.id)
$article_id = max(0, (int)($_GET['article_id'] ?? 0)); // ID ของ *บทความ* (articles.id)

if ($id <= 0 || $article_id <= 0) {
    header('Location: edit.php?id=' . $article_id . '&err=noid');
    exit;
}

$upload_dir_path = realpath(__DIR__ . '/../../uploads/articles');
$webroot_path = realpath(__DIR__ . '/../../');

if (!$upload_dir_path || !$webroot_path) {
    die("โฟลเดอร์ /uploads/articles หาไม่เจอ หรือ Path พัง");
}

try {
    $pdo->beginTransaction();

    // 2. [เตรียมลบ] ดึง "รูปย่อย" 
    $stmt_find = $pdo->prepare("SELECT image_path FROM article_images WHERE id = ? AND article_id = ?");
    $stmt_find->execute([$id, $article_id]);
    $img_path = $stmt_find->fetchColumn();

    if (!$img_path) {
        throw new Exception("ไม่พบรูป (ID: $id) หรือมึงไม่ใช่เจ้าของ");
    }

    // 3. [ลบ DB]
    $st_delete = $pdo->prepare("DELETE FROM article_images WHERE id = ?");
    $st_delete->execute([$id]);

    // 4. [Commit!]
    $pdo->commit();

    // 5. [ลบไฟล์จริง!] (ทำหลัง Commit)
    // [Smart Unlink]
    $relative_path = preg_replace('~^https?://[^/]+~', '', $img_path);
    $file_path_string = $webroot_path . '/' . ltrim($relative_path, '/');

    if (file_exists($file_path_string)) {
        $real_file_path = realpath($file_path_string);
        if ($real_file_path && strpos($real_file_path, $upload_dir_path) === 0) {
            @unlink($real_file_path);
        }
    }

    header('Location: edit.php?id=' . $article_id . '&saved=1'); // เด้งกลับหน้าเดิม
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Delete Article Image $id FAILED: " . $e->getMessage());
    header('Location: edit.php?id=' . $article_id . '&err=deletefail');
    exit;
}
