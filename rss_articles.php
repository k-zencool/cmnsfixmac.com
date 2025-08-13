<?php
// ดึงไฟล์เชื่อมต่อฐานข้อมูล PDO ของมึงเข้ามา
include 'includes/db.php';

// --- ค่าพื้นฐานของเว็บ ---
$site_url = "https://cmnsfixmac.com";
$site_title = "CMNS FixMac | บทความน่ารู้";
$site_description = "รวมบทความน่ารู้เกี่ยวกับผลิตภัณฑ์ Apple จาก CMNS FixMac";

// เช็คการเชื่อมต่อ
if (!isset($pdo)) { 
    // ถ้าหาตัวแปร $pdo ไม่เจอ ให้หยุดทำงานและแสดงข้อความ
    die("Error: PDO connection variable is not set in includes/db.php."); 
}

// --- ส่วนของการสร้าง XML ---
header("Content-Type: application/rss+xml; charset=utf-8");

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . PHP_EOL;
echo '  <channel>' . PHP_EOL;
echo '    <title>' . htmlspecialchars($site_title) . '</title>' . PHP_EOL;
echo '    <link>' . $site_url . '</link>' . PHP_EOL;
echo '    <description>' . htmlspecialchars($site_description) . '</description>' . PHP_EOL;
echo '    <language>th-th</language>' . PHP_EOL;
echo '    <atom:link href="' . $site_url . '/rss_articles.php" rel="self" type="application/rss+xml" />' . PHP_EOL;

try {
    // ดึงข้อมูลจากตาราง 'articles'
    $sql = "SELECT title, excerpt, slug, created_at, image
            FROM articles
            WHERE status = 1 -- ดึงเฉพาะโพสต์ที่เปิดใช้งาน
            ORDER BY created_at DESC
            LIMIT 20";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($posts as $row) {
        $item_link = $site_url . '/article-detail.php?slug=' . htmlspecialchars($row["slug"]);
        $image_link = $site_url . '/uploads/' . htmlspecialchars($row["image"]);

        echo '    <item>' . PHP_EOL;
        echo '      <title>' . htmlspecialchars($row["title"]) . '</title>' . PHP_EOL;
        echo '      <link>' . $item_link . '</link>' . PHP_EOL;
        echo '      <guid isPermaLink="true">' . $item_link . '</guid>' . PHP_EOL;
        echo '      <pubDate>' . date(DATE_RSS, strtotime($row["created_at"])) . '</pubDate>' . PHP_EOL;
        echo '      <description><![CDATA[' . htmlspecialchars($row["excerpt"]) . ']]></description>' . PHP_EOL;
        echo '      <enclosure url="' . $image_link . '" type="image/jpeg" />' . PHP_EOL;
        echo '    </item>' . PHP_EOL;
    }

} catch (PDOException $e) {
    // จัดการ Error กรณีที่ Query ไม่ผ่าน
    error_log("RSS Articles Generation Error: " . $e->getMessage());
}

echo '  </channel>' . PHP_EOL;
echo '</rss>' . PHP_EOL;
?>