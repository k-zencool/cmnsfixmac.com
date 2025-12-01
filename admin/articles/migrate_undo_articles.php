<?php
// migrate_undo_articles.php
// [สคริปต์ย้อนเวลา... รันแค่ครั้งเดียว!]
// - แก้ DB 'articles' และ 'article_images' กลับเป็น "ชื่อไฟล์"

session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login(); 

echo "<style>body { font-family: sans-serif; line-height: 1.6; padding: 20px; } .log { border-left: 3px solid; padding-left: 10px; margin: 5px 0; } .ok { border-color: green; } .err { border-color: red; }</style>";
echo "<h1>⏪ เริ่มย้อนเวลา DB (Articles)...</h1>";

$pdo->beginTransaction();
try {
    // 1. ย้อนเวลา "รูปหลัก" (articles.image)
    $stmt_main = $pdo->query("SELECT id, image FROM articles WHERE image IS NOT NULL AND image <> '' AND image LIKE '/uploads/articles/%'");
    $mains = $stmt_main->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>เจอ 'รูปหลัก' ที่ต้องแก้: " . count($mains) . " รายการ...</p>";
    $st_update_main = $pdo->prepare("UPDATE articles SET image = ? WHERE id = ?");
    $updated_main = 0;
    foreach ($mains as $row) {
        $new_filename = basename($row['image']); 
        if ($st_update_main->execute([$new_filename, $row['id']])) {
            echo "<div class='log ok'>[DB OK] ย้อนเวลา (Main) ID: {$row['id']} -> " . htmlspecialchars($new_filename) . "</div>";
            $updated_main++;
        }
    }
    echo "<p>ย้อนเวลา 'รูปหลัก' สำเร็จ: $updated_main</p><hr>";

    // 2. ย้อนเวลา "รูปย่อย" (article_images.image_path)
    $stmt_gallery = $pdo->query("SELECT id, image_path FROM article_images WHERE image_path IS NOT NULL AND image_path <> '' AND image_path LIKE '/uploads/articles/%'");
    $gallery = $stmt_gallery->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>เจอ 'รูปย่อย' ที่ต้องแก้: " . count($gallery) . " รายการ...</p>";
    $st_update_gallery = $pdo->prepare("UPDATE article_images SET image_path = ? WHERE id = ?");
    $updated_gallery = 0;
    foreach ($gallery as $row) {
        $new_filename = basename($row['image_path']); 
        if ($st_update_gallery->execute([$new_filename, $row['id']])) {
            echo "<div class='log ok'>[DB OK] ย้อนเวลา (Gallery) ID: {$row['id']} -> " . htmlspecialchars($new_filename) . "</div>";
            $updated_gallery++;
        }
    }
    echo "<p>ย้อนเวลา 'รูปย่อย' สำเร็จ: $updated_gallery</p>";

    $pdo->commit();
    echo "<hr><h2>✅ ย้อนเวลา DB (Articles) สำเร็จ!</h2>";
    echo "<h3>ตอนนี้ DB มึงกลับมาเป็นเหมือนเดิมแล้ว... ไปสเต็ป 4 (migrate_files_articles.php) ต่อได้เลย</h3>";

} catch (Exception $e) {
    $pdo->rollBack();
    die("ฉิบหาย! พังระหว่างย้อนเวลา: " . $e->getMessage());
}
?>