<?php
/*
 * migrate_files_articles.php
 * [สคริปต์สำหรับ "ย้าย" ไฟล์เก่า... รันแค่ครั้งเดียว!]
 * [FIXED v3] - ใช้วิธี copy() + unlink()
 * - ย้าย 'articles.image' (จาก /uploads/ -> /uploads/articles/)
 * - ย้าย 'article_images.image_path' (จาก /uploads/ -> /uploads/articles/)
 */

session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login(); 

// 1. [ตั้งค่า] Path
$old_dir_server = realpath(__DIR__ . '/../../uploads');
$new_dir_server = realpath(__DIR__ . '/../../uploads/articles');
$new_dir_db = '/uploads/articles'; // Path ที่จะเก็บลง DB

if (!$old_dir_server || !$new_dir_server) {
    die("ฉิบหาย! โฟลเดอร์ /uploads หรือ /uploads/articles หาไม่เจอ (มึงสร้าง /uploads/articles หรือยัง?)");
}
if (!is_writable($new_dir_server) || !is_writable($old_dir_server)) {
    die("ฉิบหาย! โฟลเดอร์ /uploads หรือ /uploads/articles เขียนไม่ได้ (มึงรัน 'docker exec -u root' รึยัง?)");
}

echo "<style>body { font-family: sans-serif; line-height: 1.6; padding: 20px; } .log { border-left: 3px solid; padding-left: 10px; margin: 5px 0; } .ok { border-color: green; } .err { border-color: red; } .skip { border-color: #ccc; } pre { background: #eee; padding: 10px; border-radius: 8px; }</style>";
echo "<h1>🚀 เริ่มย้ายไฟล์... (v3 - Articles)</h1>";

$moved = 0;
$skipped_db = 0;
$failed_file = 0;
$failed_db = 0;

try {
    $pdo->beginTransaction();

    // --- 1. ย้าย "รูปหลัก" (articles.image) ---
    // [FIX] หาเฉพาะอันที่ยังไม่มี / (คือ Path เก่า)
    $stmt_main = $pdo->query("SELECT id, image FROM articles WHERE image IS NOT NULL AND image <> '' AND image NOT LIKE '/%'");
    $mains = $stmt_main->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>เจอ 'รูปหลัก' (articles) ที่ต้องย้าย: " . count($mains) . " รายการ...</p><hr>";
    $st_update_main = $pdo->prepare("UPDATE articles SET image = ? WHERE id = ?");

    foreach ($mains as $row) {
        $id = $row['id'];
        $filename = $row['image'];
        $old_file = $old_dir_server . '/' . $filename;
        $new_file = $new_dir_server . '/' . $filename;

        if (!file_exists($old_file)) {
            echo "<div class='log err'>[พลาด!] (Main) ไฟล์เก่าหาไม่เจอ: " . htmlspecialchars($old_file) . "</div>";
            $failed_file++;
            continue; 
        }
        
        if (copy($old_file, $new_file)) {
            echo "<div class='log ok'>[ก๊อปปี้สำเร็จ] (Main) " . htmlspecialchars($filename) . "</div>";
            @unlink($old_file); 
            $moved++;
            
            $new_db_path = $new_dir_db . '/' . $filename; 
            if ($st_update_main->execute([$new_db_path, $id])) {
                 echo "<div class='log ok' style='margin-left: 20px;'>[DB OK] อัปเดต (Main) ID: $id</div>";
            } else {
                 echo "<div class='log err' style='margin-left: 20px;'>[DB พลาด!] อัปเดต (Main) ID: $id ไม่สำเร็จ</div>";
                 $failed_db++;
            }
        } else {
            echo "<div class='log err'>[พลาด!] ก๊อปปี้ (Main) ไม่สำเร็จ: " . htmlspecialchars($filename) . "</div>";
            $failed_file++;
            continue;
        }
    }
    echo "<hr>";

    // --- 2. ย้าย "รูปย่อย" (article_images.image_path) ---
    // [FIX] หาเฉพาะอันที่ยังไม่มี / (คือ Path เก่า)
    $stmt_gallery = $pdo->query("SELECT id, image_path FROM article_images WHERE image_path IS NOT NULL AND image_path <> '' AND image_path NOT LIKE '/%'");
    $gallery = $stmt_gallery->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>เจอ 'รูปย่อย' (article_images) ที่ต้องย้าย: " . count($gallery) . " รายการ...</p><hr>";
    $st_update_gallery = $pdo->prepare("UPDATE article_images SET image_path = ? WHERE id = ?");
    
    foreach ($gallery as $row) {
        $id = $row['id'];
        $filename = $row['image_path']; // [กูแก้] ใช้คอลัมน์ image_path
        $old_file = $old_dir_server . '/' . $filename;
        $new_file = $new_dir_server . '/' . $filename;

        if (!file_exists($old_file)) {
            echo "<div class='log err'>[พลาด!] (Gallery) ไฟล์เก่าหาไม่เจอ: " . htmlspecialchars($old_file) . "</div>";
            $failed_file++;
            continue; 
        }
        
        if (copy($old_file, $new_file)) {
            echo "<div class='log ok'>[ก๊อปปี้สำเร็จ] (Gallery) " . htmlspecialchars($filename) . "</div>";
            @unlink($old_file); 
            $moved++;
            
            $new_db_path = $new_dir_db . '/' . $filename; 
            if ($st_update_gallery->execute([$new_db_path, $id])) {
                 echo "<div class='log ok' style='margin-left: 20px;'>[DB OK] อัปเดต (Gallery) ID: $id</div>";
            } else {
                 echo "<div class='log err' style='margin-left: 20px;'>[DB พลาด!] อัปเดต (Gallery) ID: $id ไม่สำเร็จ</div>";
                 $failed_db++;
            }
        } else {
            echo "<div class='log err'>[พลาด!] ก๊อปปี้ (Gallery) ไม่สำเร็จ: " . htmlspecialchars($filename) . "</div>";
            $failed_file++;
            continue;
        }
    }
    
    $pdo->commit();

    echo "<hr><h2>✅ เสร็จสิ้น!</h2>";
    echo "<ul>";
    echo "<li><strong style='color:green;'>ย้ายไฟล์สำเร็จ (รวม 2 ตาราง): $moved</strong></li>";
    echo "<li><strong style'color:red;'>ย้ายไฟล์พลาด (ไม่เจอ/Permission): $failed_file</strong></li>";
    echo "<li><strong style='color:red;'>อัปเดต DB พลาด: $failed_db</strong></li>";
    echo "</ul>";
    echo "<h3>ไปเช็คหน้าเว็บ `articles.php` ได้เลย... (แล้วอย่าลืมลบไฟล์ `migrate_undo_articles.php` และ `migrate_files_articles.php` ทิ้ง!)</h3>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("ฉิบหาย! พังระหว่างทำงาน: " . $e->getMessage());
}
?>