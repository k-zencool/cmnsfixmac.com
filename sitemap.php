<?php
/**
 * sitemap.php - Final Version
 * เน้น URL สวย (/article/slug) และแก้หน้า Static ตามสั่ง
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

require 'includes/db.php'; 

$baseUrl = 'https://cmnsfixmac.com';
$today = date('Y-m-d');

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

function addUrl($loc, $lastmod, $changefreq, $priority) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($loc) . "</loc>\n";
    echo "    <lastmod>" . $lastmod . "</lastmod>\n";
    echo "    <changefreq>" . $changefreq . "</changefreq>\n";
    echo "    <priority>" . $priority . "</priority>\n";
    echo "  </url>\n";
}

// --- 1. หน้า Static (ใส่ .php ให้ตามที่ขอ) ---
$staticPages = [
    ''          => 1.0,
    'works/'    => 0.9,
    'shop'      => 0.9,
    'articles/' => 0.9,
    'buyback/'  => 0.8,
    'warranty/' => 0.7,
];

foreach ($staticPages as $page => $prio) {
    $path = empty($page) ? '' : '/' . $page;
    $url = $baseUrl . $path;
    if(empty($page)) $url .= '/'; // หน้าแรกปิดท้ายด้วย /
    
    addUrl($url, $today, 'weekly', $prio);
}

// --- 2. บทความ (Articles) ---
try {
    $stmt = $pdo->prepare("SELECT * FROM articles WHERE status = 1 LIMIT 2000"); 
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // เลือก slug (ไทย หรือ อังกฤษ)
        $mySlug = !empty($row['slug']) ? $row['slug'] : (!empty($row['slug_en']) ? $row['slug_en'] : '');
        
        // ข้ามถ้า slug ไปซ้ำกับชื่อไฟล์ระบบ (กันบั๊ก /article/articles.php)
        if ($mySlug == 'articles' || $mySlug == 'articles.php') continue;

        if ($mySlug) {
            // ใช้ URL สวย (/article/ชื่อเรื่อง)
            $url = $baseUrl . '/article/' . $mySlug;
        } else {
            // ถ้าไม่มี slug ใช้ id
            $url = $baseUrl . '/articles/detail.php?id=' . $row['id'];
        }
        
        $lastmod = !empty($row['updated_at']) ? date('Y-m-d', strtotime($row['updated_at'])) : date('Y-m-d');
        addUrl($url, $lastmod, 'monthly', '0.9');
    }
} catch (Exception $e) { }

// --- 3. Shop Listings ---
try {
    $stmt = $pdo->prepare("SELECT slug, updated_at FROM listings WHERE status = 'published' LIMIT 2000");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $url = $baseUrl . '/shop/product-detail.php?slug=' . rawurlencode($row['slug']);
        $lastmod = !empty($row['updated_at']) ? date('Y-m-d', strtotime($row['updated_at'])) : $today;
        addUrl($url, $lastmod, 'weekly', '0.8');
    }
} catch (Exception $e) { }

// --- 4. ผลงาน (Work) ---
try {
    $stmt = $pdo->prepare("SELECT * FROM repairs WHERE status = 'published' LIMIT 2000");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $mySlug = !empty($row['slug']) ? $row['slug'] : '';
        if ($mySlug) {
            $url = $baseUrl . '/work/' . $mySlug;
        } else {
            $url = $baseUrl . '/works/detail.php?id=' . $row['id'];
        }
        $lastmod = !empty($row['updated_at']) ? date('Y-m-d', strtotime($row['updated_at'])) : date('Y-m-d');
        addUrl($url, $lastmod, 'monthly', '0.8');
    }
} catch (Exception $e) { }

echo '</urlset>';
?>