<?php
/**
 * sitemap.php — bilingual (TH + EN)
 *
 * RULE #1: every <loc> MUST match the page's <link rel="canonical"> EXACTLY
 *          (scheme, trailing slash, query string, language path).
 *          Mismatch => Google reports "Alternate page with proper canonical tag"
 *          and drops the URL from the index.
 *
 * Canonical map (verified against the live templates):
 *   home            TH /                                EN /en/
 *   articles list   TH /articles/                       EN /en/articles/
 *   article detail  TH /article/{slug}  (else ?id=N)     EN /en/article/{slug_en} (else ?id=N)
 *   works  list     TH /works/                           EN /en/works/
 *   work   detail   TH /works/detail.php?id=N            EN /en/works/detail.php?id=N   (always ?id=, NOT pretty)
 *   shop   list     TH /shop/                            EN /en/shop/
 *   shop   product  TH /shop/product-detail.php?id=N     EN /en/shop/product-detail.php?id=N
 *   buyback         TH /buyback/                         EN /en/buyback/
 *   warranty        TH /warranty/                        EN /en/warranty/
 *   services        TH /services/{dev}/                  EN /en/services/{dev}/
 *   testers         TH /tester/ , /tester/{x}-tester/    EN /en/tester/...
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

require 'includes/db.php';

$baseUrl = 'https://cmnsfixmac.com';
$today   = date('Y-m-d');

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

/**
 * Emit one <url>. $alt is an optional array of hreflang => absolute URL
 * for the TH/EN pair (so each side advertises the other).
 */
function addUrl($loc, $lastmod, $changefreq, $priority, array $alt = []) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($loc) . "</loc>\n";
    foreach ($alt as $lang => $href) {
        echo '    <xhtml:link rel="alternate" hreflang="' . $lang . '" href="' . htmlspecialchars($href) . "\"/>\n";
    }
    echo "    <lastmod>" . $lastmod . "</lastmod>\n";
    echo "    <changefreq>" . $changefreq . "</changefreq>\n";
    echo "    <priority>" . $priority . "</priority>\n";
    echo "  </url>\n";
}

/**
 * Helper: emit a TH page + its EN mirror as a hreflang-linked pair.
 * $thPath / $enPath are absolute-from-root (e.g. '/articles/', '/en/articles/').
 */
function addPair($baseUrl, $thPath, $enPath, $lastmod, $changefreq, $priority) {
    $thUrl = $baseUrl . $thPath;
    $enUrl = $baseUrl . $enPath;
    $alt = [
        'th'        => $thUrl,
        'en'        => $enUrl,
        'x-default' => $enUrl,
    ];
    addUrl($thUrl, $lastmod, $changefreq, $priority, $alt);
    addUrl($enUrl, $lastmod, $changefreq, $priority, $alt);
}

// ── 1. Static pages (TH path => EN path => priority) ──────────────────────
$staticPairs = [
    ['/',          '/en/',          1.0, 'weekly'],
    ['/shop/',     '/en/shop/',     0.9, 'weekly'],
    ['/articles/', '/en/articles/', 0.9, 'weekly'],
    ['/works/',    '/en/works/',    0.9, 'weekly'],
    ['/buyback/',  '/en/buyback/',  0.8, 'monthly'],
    ['/warranty/', '/en/warranty/', 0.7, 'monthly'],
    ['/tester/',   '/en/tester/',   0.6, 'monthly'],
];
foreach ($staticPairs as [$th, $en, $prio, $freq]) {
    addPair($baseUrl, $th, $en, $today, $freq, $prio);
}

// ── 2. Service pages (per device) ─────────────────────────────────────────
$services = ['macbook', 'imac', 'iphone', 'ipad', 'apple-watch', 'airpods', 'software'];
foreach ($services as $dev) {
    addPair($baseUrl, "/services/$dev/", "/en/services/$dev/", $today, 'monthly', 0.8);
}

// ── 3. Tester pages ───────────────────────────────────────────────────────
$testers = ['monitor-tester', 'keyboard-tester', 'microphone-tester', 'camera-tester', 'sounds-tester', 'touchscreen-tester'];
foreach ($testers as $t) {
    addPair($baseUrl, "/tester/$t/", "/en/tester/$t/", $today, 'monthly', 0.6);
}

// ── 4. Articles (TH slug + EN slug_en; fall back to ?id=N) ─────────────────
try {
    $stmt = $pdo->query("SELECT id, slug, slug_en, updated_at FROM articles WHERE status = 1 LIMIT 2000");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $thSlug = trim($row['slug'] ?? '');
        $enSlug = trim($row['slug_en'] ?? '') ?: $thSlug;

        // guard against a slug colliding with the directory name
        if (in_array($thSlug, ['articles', 'articles.php'], true)) $thSlug = '';

        $thUrl = $thSlug
            ? "$baseUrl/article/$thSlug"
            : "$baseUrl/articles/detail.php?id=" . (int)$row['id'];
        $enUrl = $enSlug
            ? "$baseUrl/en/article/$enSlug"
            : "$baseUrl/en/articles/detail.php?id=" . (int)$row['id'];

        $lastmod = !empty($row['updated_at']) ? date('Y-m-d', strtotime($row['updated_at'])) : $today;
        $alt = ['th' => $thUrl, 'en' => $enUrl, 'x-default' => $enUrl];

        addUrl($thUrl, $lastmod, 'monthly', '0.7', $alt);
        addUrl($enUrl, $lastmod, 'monthly', '0.7', $alt);
    }
} catch (Exception $e) { /* table missing => skip silently */ }

// ── 5. Shop products (canonical = product-detail.php?id=N) ─────────────────
try {
    $stmt = $pdo->query("SELECT sl.id, sl.updated_at
                         FROM shop_listings sl
                         JOIN inventory inv ON inv.id = sl.inventory_id
                         WHERE sl.status = 'published' AND inv.status != 'SOLD'
                         LIMIT 2000");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $id = (int)$row['id'];
        $thUrl = "$baseUrl/shop/product-detail.php?id=$id";
        $enUrl = "$baseUrl/en/shop/product-detail.php?id=$id";
        $lastmod = !empty($row['updated_at']) ? date('Y-m-d', strtotime($row['updated_at'])) : $today;
        $alt = ['th' => $thUrl, 'en' => $enUrl, 'x-default' => $enUrl];

        addUrl($thUrl, $lastmod, 'weekly', '0.8', $alt);
        addUrl($enUrl, $lastmod, 'weekly', '0.8', $alt);
    }
} catch (Exception $e) { /* skip */ }

// ── 6. Works / portfolio (canonical = works/detail.php?id=N — NOT pretty) ──
try {
    $stmt = $pdo->query("SELECT id, updated_at FROM repairs WHERE status = 'published' LIMIT 2000");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $id = (int)$row['id'];
        $thUrl = "$baseUrl/works/detail.php?id=$id";
        $enUrl = "$baseUrl/en/works/detail.php?id=$id";
        $lastmod = !empty($row['updated_at']) ? date('Y-m-d', strtotime($row['updated_at'])) : $today;
        $alt = ['th' => $thUrl, 'en' => $enUrl, 'x-default' => $enUrl];

        addUrl($thUrl, $lastmod, 'monthly', '0.7', $alt);
        addUrl($enUrl, $lastmod, 'monthly', '0.7', $alt);
    }
} catch (Exception $e) { /* skip */ }

echo '</urlset>';
