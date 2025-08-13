<?php
// sitemap.php (Corrected Version)

require 'includes/db.php';

// Set the base URL of your website
$baseUrl = 'https://cmnsfixmac.com';

// Start XML output
header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

// Helper function to create a URL entry with hreflang tags
function createUrlEntry($loc, $lastmod, $changefreq, $priority, $alternates) {
    echo '  <url>' . "\n";
    echo '    <loc>' . htmlspecialchars($loc) . '</loc>' . "\n";
    echo '    <lastmod>' . $lastmod . '</lastmod>' . "\n";
    echo '    <changefreq>' . $changefreq . '</changefreq>' . "\n";
    echo '    <priority>' . $priority . '</priority>' . "\n";
    foreach ($alternates as $lang => $href) {
        echo '    <xhtml:link rel="alternate" hreflang="' . $lang . '" href="' . htmlspecialchars($href) . '" />' . "\n";
    }
    echo '  </url>' . "\n";
}

// --- Static Pages ---
$staticPages = [
    '', // Homepage
    'works.php',
    'products.php',
    'articles.php',
    'buyback.php'
];

foreach ($staticPages as $page) {
    $th_url = rtrim($baseUrl . '/' . $page, '/');
    if(empty($page)) $th_url .= '/'; 
    
    $en_url = rtrim($baseUrl . '/en/' . $page, '/');
    if(empty($page)) $en_url .= '/';

    $alternates = [ 'th' => $th_url, 'en' => $en_url, 'x-default' => $en_url ];
    createUrlEntry($th_url, date('Y-m-d'), 'weekly', '1.0', $alternates);
    createUrlEntry($en_url, date('Y-m-d'), 'weekly', '0.9', $alternates);
}

// --- Dynamic Pages from 'repairs' table ---
// CORRECTED: Using created_at instead of updated_at
$stmt_repairs = $pdo->query("SELECT id, created_at FROM repairs");
while ($row = $stmt_repairs->fetch(PDO::FETCH_ASSOC)) {
    $th_url = $baseUrl . '/work-detail.php?id=' . $row['id'];
    $en_url = $baseUrl . '/en/work-detail.php?id=' . $row['id'];
    $lastmod = date('Y-m-d', strtotime($row['created_at']));
    $alternates = [ 'th' => $th_url, 'en' => $en_url, 'x-default' => $en_url ];
    createUrlEntry($th_url, $lastmod, 'monthly', '0.8', $alternates);
    createUrlEntry($en_url, $lastmod, 'monthly', '0.7', $alternates);
}

// --- Dynamic Pages from 'products' table ---
// Using created_at for consistency, though products has updated_at
$stmt_products = $pdo->query("SELECT id, created_at FROM products WHERE status = 1");
while ($row = $stmt_products->fetch(PDO::FETCH_ASSOC)) {
    $th_url = $baseUrl . '/product-detail.php?id=' . $row['id'];
    $en_url = $baseUrl . '/en/product-detail.php?id=' . $row['id'];
    $lastmod = date('Y-m-d', strtotime($row['created_at']));
    $alternates = [ 'th' => $th_url, 'en' => $en_url, 'x-default' => $en_url ];
    createUrlEntry($th_url, $lastmod, 'monthly', '0.8', $alternates);
    createUrlEntry($en_url, $lastmod, 'monthly', '0.7', $alternates);
}

// --- Dynamic Pages from 'articles' table ---
// CORRECTED: Using created_at instead of updated_at
$stmt_articles = $pdo->query("SELECT slug, slug_en, created_at FROM articles WHERE status = 1");
while ($row = $stmt_articles->fetch(PDO::FETCH_ASSOC)) {
    $slug_th = $row['slug'];
    $slug_en = !empty(trim($row['slug_en'])) ? $row['slug_en'] : $slug_th;
    
    $th_url = $baseUrl . '/article-detail.php?slug=' . urlencode($slug_th);
    $en_url = $baseUrl . '/en/article-detail.php?slug=' . urlencode($slug_en);
    $lastmod = date('Y-m-d', strtotime($row['created_at']));
    $alternates = [ 'th' => $th_url, 'en' => $en_url, 'x-default' => $en_url ];
    createUrlEntry($th_url, $lastmod, 'monthly', '0.8', $alternates);
    createUrlEntry($en_url, $lastmod, 'monthly', '0.7', $alternates);
}

// End XML output
echo '</urlset>';
?>