<?php
// ดึงไฟล์เชื่อมต่อฐานข้อมูล PDO ของมึงเข้ามา
include 'includes/db.php';

// --- ค่าพื้นฐานของเว็บ ---
$site_url = "https://cmnsfixmac.com";
$site_title = "CMNS FixMac | ผลงานการซ่อมสินค้า Apple";
$site_description = "รวมผลงานการซ่อม iPhone, iPad, MacBook, iMac และผลิตภัณฑ์อื่นๆ ของ Apple จากทีมงาน CMNS FixMac";

// เช็คการเชื่อมต่อ
if (!isset($pdo)) {
    die("PDO variable is not set in includes/db.php");
}

// --- ส่วนของการสร้าง XML ---
header("Content-Type: application/rss+xml; charset=utf-8");

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/">' . PHP_EOL;
echo '  <channel>' . PHP_EOL;
echo '    <title>' . htmlspecialchars($site_title) . '</title>' . PHP_EOL;
echo '    <link>' . $site_url . '</link>' . PHP_EOL;
echo '    <description>' . htmlspecialchars($site_description) . '</description>' . PHP_EOL;
echo '    <language>th-th</language>' . PHP_EOL;
echo '    <atom:link href="' . $site_url . '/rss_repairs.php" rel="self" type="application/rss+xml" />' . PHP_EOL;

try {
    /*
    ============================================================
    SQL Query สำหรับดึงข้อมูลจากตาราง 'repairs'
    ============================================================
    */
    $sql = "SELECT
                id,           -- ดึง id มาใช้สร้างลิงก์
                title,
                issue,        -- ใช้คอลัมน์ issue เป็นคำอธิบายย่อ
                image,
                created_at
            FROM
                repairs       -- ดึงจากตาราง 'repairs'
            ORDER BY
                created_at DESC
            LIMIT 20";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($posts as $row) {
        /*
        ============================================================
        !! จุดที่มึงต้องเช็ค !!
        ลิงก์สร้างจาก id นะ! เช็คชื่อไฟล์ 'repair-detail.php' ของมึงด้วย
        ============================================================
        */
        $item_link = $site_url . '/work-detail.php?id=' . $row["id"];
        
        $image_link = $site_url . '/uploads/' . htmlspecialchars($row["image"]);

        echo '    <item>' . PHP_EOL;
        echo '      <title>' . htmlspecialchars($row["title"]) . '</title>' . PHP_EOL;
        echo '      <link>' . $item_link . '</link>' . PHP_EOL;
        echo '      <guid isPermaLink="true">' . $item_link . '</guid>' . PHP_EOL;
        echo '      <pubDate>' . date(DATE_RSS, strtotime($row["created_at"])) . '</pubDate>' . PHP_EOL;
        echo '      <description><![CDATA[' . htmlspecialchars($row["issue"]) . ']]></description>' . PHP_EOL;
        echo '      <enclosure url="' . $image_link . '" type="image/jpeg" />' . PHP_EOL;
        echo '    </item>' . PHP_EOL;
    }

} catch (PDOException $e) {
    error_log("RSS Repairs Generation Error: " . $e->getMessage());
    echo '    <item><title>Error</title><description>Could not generate RSS feed.</description></item>' . PHP_EOL;
}

echo '  </channel>' . PHP_EOL;
echo '</rss>' . PHP_EOL;
?>