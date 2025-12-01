<?php
/* =========================================================
   CMNS FixMac – EN Product Detail (Complete + Tab CSS Fix + XSS Safe)
   - Fetches title_en and description_en
   - Displays description_en with allowlist sanitizer
   - Related products use title_en
   - Correct EN/TH URLs and hreflang
   - Injected CSS for tabs
   - Fixed escapeHtml in JS
   - Added JSON-LD Product schema
   ========================================================= */

/* ---------- DB bootstrap ---------- */
$incl = __DIR__ . '/../../includes/db.php'; // Path relative to /en/shop/
if (!is_file($incl)) {
  $alt = __DIR__ . '/../includes/db.php'; // Fallback path
  if (is_file($alt)) $incl = $alt;
}
require_once $incl;

/* ---------- Confirm PDO ---------- */
if (!isset($pdo) || !($pdo instanceof PDO)) {
  http_response_code(500);
  error_log("Database connection not initialized in /en/shop/product-detail.php");
  exit('Database connection error.');
}

/* ---------- BASE_URL ---------- */
defined('BASE_URL') or define('BASE_URL', 'https://cmnsfixmac.com');

/* ---------- Helpers ---------- */
function h($s)
{
  return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}
function getv($k, $d = null)
{
  return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d;
}
function norm_path($src)
{
  if (!$src) return '';
  $src = trim($src);
  if ($src === '' || $src[0] === '/' || preg_match('~^https?://~', $src)) return $src;
  return '/' . ltrim($src, '/');
}

/* ---------- Safe HTML allowlist sanitizer for description ---------- */
function safe_html($html)
{
  $html = $html ?? '';
  // allow listed tags
  $allowed = '<p><br><ul><ol><li><strong><b><em><i><u><h1><h2><h3><h4><blockquote><code><pre><img><a><hr><span>';
  $clean = strip_tags($html, $allowed);

  // strip inline event handlers like onclick, onerror
  $clean = preg_replace('/\s+on\w+\s*=\s*"[^"]*"/i', '', $clean);
  $clean = preg_replace("/\s+on\w+\s*=\s*'[^']*'/i", '', $clean);

  // neutralize javascript: URLs
  $clean = preg_replace('~\s(href|src)\s*=\s*([\'"])\s*javascript:[^\\2]*\\2~i', ' $1="#"', $clean);

  // sanitize <img> tags: only allow http/https or root-relative src
  $clean = preg_replace_callback('~<img\s+[^>]*>~i', function ($m) {
    $tag = $m[0];
    if (!preg_match('~\s src\s*=\s*([\'"])(https?://|/)[^\\1]+\\1~i', $tag)) {
      return ''; // drop img without safe src
    }
    $tag = preg_replace('/\s+on\w+\s*=\s*"[^"]*"/i', '', $tag);
    $tag = preg_replace("/\s+on\w+\s*=\s*'[^']*'/i", '', $tag);
    return $tag;
  }, $clean);

  return $clean;
}

$q = getv('q', '');

/* ---------- Get Product ID ---------- */
$id = max(0, (int)getv('id', 0));

/* ---------- Init Vars ---------- */
$product = null;
$attrs = [];
$all_images = [];
$related_items = [];
$error = '';
$tags = [];

/* ---------- Fetch Data ---------- */
if ($id > 0) {
  // 1) Product (Fetch EN fields)
  $sqlProduct = "
      SELECT
        l.*,
        l.title_en AS name,
        JSON_UNQUOTE(JSON_EXTRACT(l.attrs, '$.description_en')) as description_en,
        JSON_UNQUOTE(JSON_EXTRACT(l.attrs, '$.description')) as description_th,
        JSON_UNQUOTE(JSON_EXTRACT(l.attrs, '$.meta_description')) as meta_description,
        JSON_UNQUOTE(JSON_EXTRACT(l.attrs, '$.tags')) as tags_json
      FROM listings l
      WHERE l.id = :id AND l.status = 'published'
    ";
  $st = $pdo->prepare($sqlProduct);
  $st->execute([':id' => $id]);
  $product = $st->fetch(PDO::FETCH_ASSOC);

  // Fallback logic if EN fields are empty
  if ($product) {
    if (empty($product['name'])) {
      $product['name'] = $product['title'] ?? 'Product';
    }
    if (empty($product['description_en'])) {
      $product['description_en'] = $product['description_th'] ?? '';
    }
  }

  if ($product) {
    // 2) Attributes
    $sqlAttrs = "SELECT a.key_name, v.value_string, v.value_int FROM listing_attr_values v JOIN attrs a ON a.id = v.attr_id WHERE v.listing_id = :id ORDER BY a.id";
    $st = $pdo->prepare($sqlAttrs);
    $st->execute([':id' => $id]);
    $attrs = $st->fetchAll(PDO::FETCH_ASSOC);

    // 3) Gallery Images
    $sqlGallery = "SELECT url FROM listing_images WHERE listing_id = :id ORDER BY sort_order";
    $st = $pdo->prepare($sqlGallery);
    $st->execute([':id' => $id]);
    $gallery_images = $st->fetchAll(PDO::FETCH_COLUMN);
    $all_images = [];
    if (!empty($product['main_image'])) $all_images[] = norm_path($product['main_image']);
    foreach ($gallery_images as $img) {
      $img = norm_path($img);
      if (!empty($img) && !in_array($img, $all_images, true)) $all_images[] = $img;
    }
    if (empty($all_images)) $all_images[] = '';

    // 4) Related Products (Fetch EN titles)
    $sqlRelated = "
          SELECT l.id, l.title_en name, l.title name_th, l.brand as category, l.price, l.price_old, l.stock_qty, l.created_at, l.main_image, l.in_stock
          FROM listings l
          WHERE l.brand = :brand AND l.id != :id AND l.status = 'published' AND l.in_stock = 1
          ORDER BY RAND() LIMIT 4
        ";
    $st = $pdo->prepare($sqlRelated);
    $st->execute([':brand' => ($product['brand'] ?? ''), ':id' => $id]);
    $related_items = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($related_items as &$item) {
      if (empty($item['name'])) {
        $item['name'] = $item['name_th'] ?? 'Related Product';
      }
      unset($item['name_th']);
    }
    unset($item);

    // 5) Tags
    if (!empty($product['brand'])) $tags[] = $product['brand'];
    if (!empty($product['tags_json'])) {
      $json_tags = json_decode($product['tags_json'], true);
      if (is_array($json_tags)) $tags = array_merge($tags, $json_tags);
    }
    foreach ($attrs as $attr) {
      if ($attr['key_name'] === 'year' && !empty($attr['value_int'])) {
        $tags[] = (string)$attr['value_int'];
        break;
      }
    }
    $tags = array_values(array_unique(array_filter($tags)));
  } else {
    $error = "Product not found";
  }
} else {
  $error = "Invalid Product ID";
}

if ($error) http_response_code(404);

/* ---------- Language Links ---------- */
$request_uri_en = $_SERVER['REQUEST_URI'];
if (!$product) $request_uri_en = '/en/shop/';
$current_en_url = rtrim(BASE_URL, '/') . str_replace('/index.php', '/', $request_uri_en);
$request_uri_th = preg_replace('~^/en(/|$)~', '/', $_SERVER['REQUEST_URI']);
if (!$product) $request_uri_th = '/shop/';
$th_version_url = rtrim(BASE_URL, '/') . str_replace('/index.php', '/', $request_uri_th);

/* ---------- SEO & Page Vars (Using EN Name) ---------- */
$page_title = "Product Not Found";
$meta_description = "The product you were looking for was not found. Please try again or return to the main shop page.";
$og_image = BASE_URL . "/assets/img/Logo1.png";
$name = '';

if ($product) {
  $name = $product['name'] ?? 'Product';
  $page_title = h($name);
  $desc_for_meta = $product['description_en'] ?: ($product['description_th'] ?? '');
  $clean_desc = trim(preg_replace('/\s+/', ' ', strip_tags($desc_for_meta)));
  $meta_description = h(!empty($product['meta_description']) ? $product['meta_description'] : mb_substr($clean_desc, 0, 155));
  $main_img_path = norm_path($all_images[0] ?? '');
  if ($main_img_path) $og_image = rtrim(BASE_URL, '/') . $main_img_path;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title><?= $page_title ?> | CMNS FixMac</title>
  <meta name="description" content="<?= $meta_description ?>">

  <link rel="canonical" href="<?= h($current_en_url) ?>" />
  <link rel="alternate" hreflang="th" href="<?= h($th_version_url) ?>" />
  <link rel="alternate" hreflang="en" href="<?= h($current_en_url) ?>" />
  <link rel="alternate" hreflang="x-default" href="<?= h($current_en_url) ?>" />

  <meta property="og:title" content="<?= $page_title ?> | CMNS FixMac" />
  <meta property="og:description" content="<?= $meta_description ?>" />
  <meta property="og:image" content="<?= h($og_image) ?>" />
  <?php if ($product): ?>
    <meta property="og:url" content="<?= h($current_en_url) ?>" />
    <meta property="og:type" content="product" /><?php endif; ?>

  <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/img/favicon1.png" />
  <link rel="stylesheet" href="/shop/assets/css/shop-style.css">
  <link rel="stylesheet" href="/shop/assets/css/hero.css">
  <link rel="stylesheet" href="/shop/assets/css/cart-receipt.css">
  <link rel="stylesheet" href="/shop/assets/css/product-detail.css">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />

  <style>
    .pdp-tabs {
      margin-top: 2rem;
      border-top: 1px solid #eee;
      padding-top: 1.5rem;
    }

    .pdp-tab-nav {
      display: flex;
      align-items: center;
      gap: 8px;
      border-bottom: 1px solid #ddd;
      margin-bottom: 1.5rem;
    }

    .pdp-tab-btn {
      font-family: inherit;
      font-size: 1rem;
      font-weight: 600;
      color: #555;
      background: none;
      border: none;
      padding: 10px 16px;
      border-bottom: 3px solid transparent;
      cursor: pointer;
      margin-bottom: -1px;
      transition: all 0.2s ease;
    }

    .pdp-tab-btn:hover {
      background-color: #f9f9f9;
      color: #111;
    }

    .pdp-tab-btn.is-active {
      color: var(--brand, #fc7404);
      border-bottom-color: var(--brand, #fc7404);
    }

    .pdp-tab-content {
      display: none;
      padding: 1rem 0;
      line-height: 1.7;
      animation: fadeIn 0.3s ease;
    }

    .pdp-tab-content.is-active {
      display: block;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(10px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .pdp-specs-table {
      width: 100%;
      max-width: 600px;
      border-collapse: collapse;
    }

    .pdp-specs-table th,
    .pdp-specs-table td {
      padding: 12px 10px;
      text-align: left;
      border-bottom: 1px solid #f0f0f0;
    }

    .pdp-specs-table th {
      font-weight: 600;
      color: #333;
      width: 180px;
      background: #fdfdfd;
    }

    .pdp-specs-table td {
      color: #555;
    }

    .pdp-specs-table tr:last-child th,
    .pdp-specs-table tr:last-child td {
      border-bottom: none;
    }
  </style>
  <?php
  // --- Keywords (optional) ---
  if ($product) {
    $keywords = [];
    $keywords[] = h($name);
    if (!empty($product['brand'])) $keywords[] = h($product['brand']);
    $keywords = array_merge($keywords, ['Used', 'Second-hand', 'Chiang Mai', 'CMNS FixMac']);
    $title_parts = explode(' ', (string)$name);
    foreach ($title_parts as $part) {
      $part = trim($part, ',./\\()[]{}<>?!"\'');
      if (mb_strlen($part) > 2 && !is_numeric($part)) $keywords[] = h($part);
    }
    foreach ($attrs as $attr) {
      $value = $attr['value_string'] ?? $attr['value_int'];
      if ($value !== null && $value !== '') $keywords[] = h($value);
    }
    if (!empty($product['brand']) && !in_array(h($product['brand']), $keywords, true)) $keywords[] = h($product['brand']);
    $keywords[] = 'Apple';
    $keywords = array_values(array_unique(array_filter($keywords)));
    $keyword_string = implode(', ', array_slice($keywords, 0, 20));
  } else {
    $keyword_string = 'Apple, Used, Second-hand, Chiang Mai, MacBook, iPhone, iPad, Repair, Sale';
  }
  ?>
  <meta name="keywords" content="<?= $keyword_string ?>">

  <?php if ($product): ?>
    <!-- JSON-LD Product schema -->
    <script type="application/ld+json">
      <?= json_encode([
        "@context" => "https://schema.org",
        "@type" => "Product",
        "name" => $name,
        "image" => array_values(array_filter(array_map(function ($p) {
          return $p ? (rtrim(BASE_URL, '/') . $p) : null;
        }, $all_images))),
        "brand" => ["@type" => "Brand", "name" => $product['brand'] ?: 'Unbranded'],
        "sku" => $product['sku'] ?: null,
        "description" => strip_tags($product['description_en'] ?: ($product['description_th'] ?? '')),
        "offers" => [
          "@type" => "Offer",
          "priceCurrency" => "THB",
          "price" => (string) number_format((float)$product['price'], 2, '.', ''),
          "availability" => ((int)($product['in_stock'] ?? 0) === 1 && (int)($product['stock_qty'] ?? 0) > 0) ? "https://schema.org/InStock" : "https://schema.org/OutOfStock",
          "url" => rtrim(BASE_URL, '/') . ('/en/shop/product-detail.php?id=' . (int)$product['id'])
        ]
      ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>
    </script>
  <?php endif; ?>
</head>

<body>
  <header class="navbar navbar-top">
    <div class="nav-container">
      <div class="nav-logo"><a href="/en/shop/"><img src="/assets/img/Logo1.png" alt="CMNS FixMac Logo"></a></div>
      <div class="menu-desktop-only">
        <nav class="menu">
          <a href="/en/" class="highlight-home"><span class="material-symbols-rounded">home</span> Home</a>
          <a href="/en/shop/" class="active"><span class="material-symbols-rounded">storefront</span> Shop</a>
          <a href="/en/works.php"><span class="material-symbols-rounded">construction</span> Our Work</a>
          <a href="/en/articles.php"><span class="material-symbols-rounded">description</span> Articles</a>
          <a href="/en/buyback.php"><span class="material-symbols-rounded">laptop_mac</span> Sell Device</a>
          <a href="/en/warranty.php"><span class="material-symbols-rounded">verified</span> Check Warranty</a>
        </nav>
      </div>
      <div class="nav-actions">
        <form class="nav-search search-form" action="/en/shop/" method="get" role="search">
          <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search products...">
          <button type="submit" aria-label="Search"><span class="material-symbols-rounded">search</span></button>
        </form>
        <a href="#!" class="nav-cart" aria-label="Cart"><span class="material-symbols-rounded">shopping_cart</span><span class="cart-count">0</span></a>
        <a href="<?= h($th_version_url) ?>" class="language-switch-btn" title="Switch to Thai"><span class="material-symbols-rounded">language</span> TH</a>
        <button id="hamburger" class="hamburger" type="button" onclick="toggleSidebar()" aria-label="Menu"><span></span><span></span><span></span></button>
      </div>
      <div id="sidebar" class="sidebar">
        <div class="sidebar-header"><a href="/en/"><img src="/assets/img/Logo1.png" alt="CMNS Logo" style="height:36px; margin-bottom:16px;"></a><span class="close-btn" onclick="toggleSidebar()">✕</span></div>
        <nav class="sidebar-menu">
          <a href="/en/" class="highlight-home"><span class="material-symbols-rounded">home</span> Home</a>
          <a href="/en/works.php"><span class="material-symbols-rounded">construction</span> Our Work</a>
          <a href="/en/shop/" class="active"><span class="material-symbols-rounded">storefront</span> Shop</a>
          <a href="/en/articles.php"><span class="material-symbols-rounded">description</span> Articles</a>
          <a href="/en/buyback.php"><span class="material-symbols-rounded">laptop_mac</span> Sell Device</a>
          <a href="/en/warranty.php"><span class="material-symbols-rounded">verified</span> Check Warranty</a>
          <a href="tel:0841511684"><span class="material-symbols-rounded">call</span> Call Us</a>
          <a href="<?= h($th_version_url) ?>" class="language-switch-btn" title="Switch to Thai"><span class="material-symbols-rounded">language</span> TH</a>
        </nav>
        <div class="sidebar-dropdown">
          <button class="dropdown-toggle" onclick="toggleSidebarDropdown(this)"><span class="material-symbols-rounded">smart_toy</span> Device Tester<span class="material-symbols-rounded dropdown-icon">expand_more</span></button>
          <div class="dropdown-submenu">
            <a href="/en/tester/monitor-tester/"><span class="material-symbols-rounded">monitor</span> Monitor</a><a href="/en/tester/keyboard-tester/"><span class="material-symbols-rounded">keyboard</span> Keyboard</a><a href="/en/tester/microphone-tester/"><span class="material-symbols-rounded">mic</span> Microphone</a><a href="/en/tester/camera-tester/"><span class="material-symbols-rounded">photo_camera</span> Camera</a><a href="/en/tester/sounds-tester/"><span class="material-symbols-rounded">volume_up</span> Speakers</a><a href="/en/tester/touchscreen-tester/"><span class="material-symbols-rounded">touch_app</span> Touchscreen</a>
          </div>
        </div>
      </div>
      <div id="sidebar-overlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>
    </div>
  </header>

  <main class="pdp-container">
    <nav class="pdp-breadcrumb" aria-label="breadcrumb">
      <ol class="breadcrumb-list">
        <li><a href="/en/" title="Home"><span class="material-symbols-rounded">home</span></a></li>
        <li><span class="breadcrumb-sep">></span><a href="/en/shop/">Shop</a></li>
        <?php if ($product && !empty($product['brand'])): ?><li><span class="breadcrumb-sep">></span><a href="/en/shop/?cat=<?= urlencode($product['brand']) ?>"><?= h($product['brand']) ?></a></li><?php endif; ?>
        <?php if ($product): ?><li><span class="breadcrumb-sep">></span><span class="breadcrumb-current" aria-current="page"><?= h(mb_strimwidth($name, 0, 50, "...")) ?></span></li><?php endif; ?>
      </ol>
    </nav>

    <?php if ($error): ?>
      <div class="pdp-error">
        <h1><?= h($error) ?></h1>
        <p>We couldn't find the product you're looking for, or it may have been removed.</p>
        <a href="/en/shop/" class="btn-line-full" style="display:inline-flex; width:auto; padding: 0 20px;"><span class="material-symbols-rounded">storefront</span> Back to All Products</a>
      </div>

    <?php elseif ($product): ?>
      <?php
      // --- Prepare Vars ---
      $url   = '/en/shop/product-detail.php?id=' . (int)$product['id'];
      $img   = norm_path($all_images[0] ?? '');
      $name  = $product['name'] ?? 'Product';
      $brand = $product['brand'] ?: 'Unbranded';
      $sku   = $product['sku'] ?? null;
      $price = (float)$product['price'];
      $old = (float)($product['price_old'] ?? 0);
      $disc = $old > $price ? ($old - $price) : 0;
      $pct   = $old > 0 ? round($disc / $old * 100) : 0;
      $qty = (int)($product['stock_qty'] ?? 0);
      $in_stock = ((int)($product['in_stock'] ?? 0) === 1) && $qty > 0;
      $stock_class = 'out-stock';
      $stock_text = 'Out of Stock';
      if ($in_stock) {
        if ($qty <= 2) {
          $stock_class = 'low-stock';
          $stock_text = "Only $qty left";
        } else {
          $stock_class = 'in-stock';
          $stock_text = 'In Stock';
        }
      }
      $fallback_icon_html = '<div class="pdp-gallery-icon"><span class="material-symbols-rounded" aria-hidden="true">image</span></div>';
      $thumb_icon_html = '<div class="pdp-gallery-icon"><span class="material-symbols-rounded" aria-hidden="true">image</span></div>';
      function render_pdp_image($src, $alt, $class, $icon_html)
      {
        $alt = h($alt);
        if (empty($src)) return $icon_html;
        $src = norm_path($src);
        $src_h = h($src);
        $onerror_js = "this.onerror=null; this.outerHTML='" . addslashes($icon_html) . "';";
        return "<img src=\"$src_h\" alt=\"$alt\" class=\"$class\" loading=\"lazy\" decoding=\"async\" onerror=\"" . h($onerror_js) . "\">";
      }
      ?>
      <div class="pdp-layout">
        <div class="pdp-gallery">
          <div class="pdp-gallery-main" id="pdp-main-image-wrapper">
            <?= render_pdp_image($img, $name, 'pdp-main-img', $fallback_icon_html) ?>
            <?php if (count($all_images) > 1): ?>
              <button class="pdp-gallery-nav prev" aria-label="Previous image"><span class="material-symbols-rounded">arrow_back_ios</span></button>
              <button class="pdp-gallery-nav next" aria-label="Next image"><span class="material-symbols-rounded">arrow_forward_ios</span></button>
            <?php endif; ?>
          </div>
          <?php if (count($all_images) > 1): ?>
            <div class="pdp-gallery-thumbs" id="pdp-gallery-thumbs">
              <?php foreach ($all_images as $i => $thumb_src): $thumb_src = norm_path($thumb_src); ?>
                <div class="pdp-thumb-item <?= $i === 0 ? 'is-active' : '' ?>" data-index="<?= $i ?>" data-full-src="<?= h($thumb_src) ?>">
                  <?= render_pdp_image($thumb_src, "Thumbnail " . ($i + 1), 'pdp-thumb-img', $thumb_icon_html) ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="pdp-info">
          <div class="pdp-cat"><?= h($brand) ?></div>
          <h1 class="pdp-title"><?= h($name) ?></h1>
          <div class="pdp-meta"><span>Brand: <?= h($brand) ?></span><?php if ($sku): ?><span>SKU: <?= h($sku) ?></span><?php endif; ?></div>
          <div class="pdp-sub-actions"><button type="button"><span class="material-symbols-rounded">favorite</span> Add to Wishlist</button><a href="#"><span class="material-symbols-rounded">share</span> Share</a></div>
          <div class="pdp-price-box"><span class="pdp-price-now">฿<?= number_format($price, 0) ?></span><?php if ($disc > 0): ?><span class="pdp-price-old">฿<?= number_format($old, 0) ?></span><span class="pdp-discount-badge">Save <?= number_format($disc, 0) ?> ฿ (-<?= $pct ?>%)</span><?php endif; ?></div>
          <div class="pdp-qty-selector"><label for="pdp-qty">Quantity:</label>
            <div class="pdp-qty-input-wrap"><button type="button" class="pdp-qty-btn minus" aria-label="Decrease quantity">-</button><input type="number" id="pdp-qty" class="pdp-qty-input" value="1" min="1" max="<?= $in_stock ? $qty : 1 ?>" aria-label="Quantity"><button type="button" class="pdp-qty-btn plus" aria-label="Increase quantity">+</button></div><span class="pdp-stock <?= $stock_class ?>">(<?= $stock_text ?>)</span>
          </div>
          <div class="pdp-actions">
            <?php if ($in_stock): ?>
              <button class="pdp-add-to-cart-btn card-cart-btn" type="button" aria-label="Add to cart" data-id="<?= (int)$product['id'] ?>" data-name="<?= h($name) ?>" data-price="<?= (float)$price ?>" data-img="<?= h($img) ?>" data-url="<?= h($url) ?>"><span class="material-symbols-rounded">add_shopping_cart</span> Add to Cart</button>
            <?php else: ?>
              <button class="pdp-add-to-cart-btn" type="button" style="background:#aaa;color:#fff;cursor:not-allowed;"><span class="material-symbols-rounded">notifications</span> Notify Me</button>
            <?php endif; ?>
          </div>
          <?php if ($tags): ?><div class="pdp-tags"><?php foreach ($tags as $tag): ?><a href="/en/shop/?q=<?= urlencode($tag) ?>" class="pdp-tag-item">#<?= h($tag) ?></a><?php endforeach; ?></div><?php endif; ?>
        </div>
      </div>

      <div class="pdp-tabs">
        <nav class="pdp-tab-nav" role="tablist">
          <?php $has_en_desc = !empty($product['description_en']); ?>
          <?php if ($has_en_desc): ?>
            <button class="pdp-tab-btn is-active" id="tab-desc-btn" role="tab" aria-selected="true" aria-controls="tab-desc-content">Product Details</button>
          <?php endif; ?>
          <?php if ($attrs): ?>
            <button class="pdp-tab-btn <?= !$has_en_desc ? 'is-active' : '' ?>" id="tab-spec-btn" role="tab" aria-selected="<?= !$has_en_desc ? 'true' : 'false' ?>" aria-controls="tab-spec-content">Specifications</button>
          <?php endif; ?>
        </nav>

        <?php if ($has_en_desc): ?>
          <div class="pdp-tab-content pdp-description <?= $has_en_desc ? 'is-active' : '' ?>" id="tab-desc-content" role="tabpanel" aria-labelledby="tab-desc-btn">
            <?= safe_html($product['description_en']) ?>
          </div>
        <?php endif; ?>

        <?php if ($attrs): ?>
          <div class="pdp-tab-content <?= !$has_en_desc ? 'is-active' : '' ?>" id="tab-spec-content" role="tabpanel" aria-labelledby="tab-spec-btn">
            <table class="pdp-specs-table">
              <tbody>
                <?php $key_to_label_map_en = ['ram_gb' => 'RAM', 'ssd_gb' => 'SSD Storage', 'year' => 'Year', 'color' => 'Color', 'model' => 'Model', 'cpu' => 'Chip / CPU', 'gpu' => 'Graphics / GPU', 'cycle_count' => 'Cycle Count'];
                foreach ($attrs as $attr): $key = $attr['key_name'];
                  $label = $key_to_label_map_en[$key] ?? ucwords(str_replace('_', ' ', $key)); ?>
                  <tr>
                    <th><?= h($label) ?></th>
                    <td><?= h($attr['value_string'] ?? $attr['value_int']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($related_items): ?>
        <div class="pdp-related-wrapper">
          <h2 class="pdp-section-title">Related Products</h2>
          <section class="cmnsx-products" style="padding:0;max-width:none;">
            <ul class="cmnsx-grid">
              <?php foreach ($related_items as $row):
                $urlR = '/en/shop/product-detail.php?id=' . (int)$row['id'];
                $imgR = norm_path(trim((string)($row['main_image'] ?? '')));
                $nameR = $row['name'] ?: 'Related Product';
                $catR  = $row['category'] ?: 'Other';
                $priceR = (float)$row['price'];
                $oldR = (float)($row['price_old'] ?? 0);
                $discR = $oldR > $priceR ? ($oldR - $priceR) : 0;
                $pctR = $oldR > 0 ? round($discR / $oldR * 100) : 0;
                $qtyR = (int)($row['stock_qty'] ?? 0);
                $lowR = ((int)($row['in_stock'] ?? 0) === 1) && $qtyR > 0 && $qtyR <= 1;
              ?>
                <li class="cmnsx-card">
                  <?php if ($discR > 0): ?><div class="cmnsx-badge">Save <?= number_format($discR, 0) ?> ฿<?= $pctR ? " (-{$pctR}%)" : "" ?></div><?php endif; ?>
                  <a href="<?= h($urlR) ?>" class="cmnsx-thumb">
                    <?php if ($imgR === ''): echo $fallback_icon_html;
                    else: $onerror_js = "this.onerror=null; this.outerHTML='" . addslashes($fallback_icon_html) . "';"; ?>
                      <img src="<?= h($imgR) ?>" alt="<?= h($nameR) ?>" class="cmnsx-img" loading="lazy" decoding="async" onerror="<?= h($onerror_js) ?>">
                    <?php endif; ?>
                  </a>
                  <div class="cmnsx-info">
                    <div class="cmnsx-cat"><?= h($catR) ?></div>
                    <h3 class="cmnsx-name"><a href="<?= h($urlR) ?>" class="cmnsx-link"><?= h($nameR) ?></a></h3>
                    <?php if ($lowR): ?><div class="cmnsx-low">• Low stock</div><?php endif; ?>
                    <div class="cmnsx-price">
                      <span class="cmnsx-price-now">฿<?= number_format($priceR, 0) ?></span>
                      <?php if ($discR > 0): ?><span class="cmnsx-price-old">฿<?= number_format($oldR, 0) ?></span><?php endif; ?>
                      <button class="card-cart-btn cart-on-price" type="button" aria-label="Add to cart" data-id="<?= (int)$row['id'] ?>" data-name="<?= h($nameR) ?>" data-price="<?= (float)$priceR ?>" data-img="<?= h($imgR) ?>" data-url="<?= h($urlR) ?>">
                        <span class="material-symbols-rounded">add_shopping_cart</span>
                      </button>
                    </div>
                    <?php $line_url = 'https://line.me/R/ti/p/@cmns'; ?>
                    <a class="btn-line-full" href="<?= h($line_url) ?>" target="_blank" rel="noopener"><span class="material-symbols-rounded">chat_bubble</span> Order via LINE</a>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          </section>
        </div>
      <?php endif; ?>

    <?php endif; ?>
  </main>

  <div id="mini-cart-overlay" style="display:none;"></div>
  <aside id="mini-cart" aria-label="Shopping Cart" style="display:none;" tabindex="-1">
    <header class="mc-head">
      <h3>Shopping Cart</h3><button type="button" class="mc-close" aria-label="Close cart"><span class="material-symbols-rounded">close</span></button>
    </header>
    <div class="mc-body">
      <ul class="mc-list" id="mc-list"></ul>
      <div class="mc-empty" id="mc-empty">Your cart is empty</div>
    </div>
    <footer class="mc-foot">
      <div class="mc-sum"><span>Total</span><strong id="mc-total">฿0</strong></div>
      <div class="mc-actions"><button type="button" id="mc-clear" class="mc-btn ghost"><span class="material-symbols-rounded">delete</span> Clear Cart</button><a href="#!" id="mc-checkout" class="mc-btn primary"><span class="material-symbols-rounded">receipt_long</span> Checkout</a></div>
    </footer>
  </aside>
  <div id="receipt-backdrop" aria-hidden="true" style="display:none;"></div>
  <aside id="receipt-modal" role="dialog" aria-modal="true" aria-labelledby="receipt-title" style="display:none;">
    <header class="rcp-head">
      <h3 id="receipt-title">Order Summary</h3><button type="button" class="rcp-close" aria-label="Close popup"><span class="material-symbols-rounded">close</span></button>
    </header>
    <div class="rcp-body">
      <ul id="rcp-list" class="rcp-list"></ul>
      <div class="rcp-sum">
        <div class="row"><span>Subtotal</span><strong id="rcp-subtotal">฿0</strong></div>
        <div class="row"><span>Shipping</span><strong id="rcp-ship">Free</strong></div>
        <div class="split"></div>
        <div class="row grand"><span>Grand Total</span><strong id="rcp-total">฿0</strong></div>
      </div>
      <div class="rcp-qr">
        <div class="qr-box"><img src="/shop/assets/img/line.png" alt="LINE QR @cmnsfixmac" loading="lazy" decoding="async"></div>
        <div class="qr-text">
          <h4>Payment & Contact</h4>
          <p>Scan the QR to add our <b>LINE Official @cmnsfixmac</b>. Send your payment slip, check stock, or arrange pickup.</p><a class="rcp-btn line small" href="https://line.me/R/ti/p/@cmns" target="_blank" rel="noopener"><span class="material-symbols-rounded">chat_bubble</span> Open LINE</a>
        </div>
      </div>
    </div>
    <footer class="rcp-foot"><button type="button" id="rcp-copy" class="rcp-btn ghost"><span class="material-symbols-rounded">content_copy</span> Copy Summary</button><a id="rcp-line" class="rcp-btn line" href="#" target="_blank" rel="noopener"><span class="material-symbols-rounded">send</span> Send Details to LINE</a><button type="button" id="rcp-print" class="rcp-btn"><span class="material-symbols-rounded">print</span> Print / Save as PDF</button></footer>
  </aside>

  <footer class="cmns-footer">
    <div class="footer-container">
      <div class="footer-grid">
        <div class="footer-col" id="f-brand"><a href="/en/shop/" class="f-logo"><img src="/assets/img/Logo1.png" alt="CMNS FixMac Logo"></a>
          <p>Chiang Mai's #1 shop for quality used Apple products and repairs.</p>
          <div class="f-socials"><a href="https://line.me/R/ti/p/@cmns" target="_blank" rel="noopener" aria-label="LINE"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" fill="currentColor">
                <path d="M224 336.1c-63.6 0-115.3-51.6-115.3-115.3S160.4 105.6 224 105.6s115.3 51.6 115.3 115.3-51.7 115.2-115.3 115.2zm0-207c-50.5 0-91.7 41.2-91.7 91.7s41.2 91.7 91.7 91.7 91.7-41.2 91.7-91.7-41.2-91.7-91.7-91.7zM448 220.7c0-101.9-82.8-184.6-184.8-184.6S78.4 118.8 78.4 220.7c0 82.2 53.7 151.6 125.7 174.4 6.9 2.2 11.2 8.9 11.2 16.2v.2c0 10.9-11.2 19.8-22.1 19.8-11.1 0-22.1-8.9-22.1-19.8 0-11.3-9.2-20.5-20.5-20.5-11.3 0-20.5 9.2-20.5 20.5 0 33.3 27 60.3 60.3 60.3s60.3-27 60.3-60.3v-.2c0-7.3 4.3-14 11.2-16.2 72-22.8 125.7-92.2 125.7-174.4zm-48.7 0c0 75.2-61.1 136.3-136.3 136.3s-136.3-61.1-136.3-136.3 61.1-136.3 136.3-136.3 136.3 61.1 136.3 136.3zM161.2 214.2c0-3.3-2.7-6-6-6s-6 2.7-6 6v13.1c0 3.3 2.7 6 6 6s6-2.7 6-6v-13.1zm51.4 0c0-3.3-2.7-6-6-6s-6 2.7-6 6v13.1c0 3.3 2.7 6 6 6s6-2.7 6-6v-13.1zm51.3 0c0-3.3-2.7-6-6-6s-6 2.7-6 6v13.1c0 3.3 2.7 6 6 6s6-2.7 6-6v-13.1z" />
              </svg></a><a href="https://www.facebook.com/cmnsfixmac" target="_blank" rel="noopener" aria-label="Facebook"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="currentColor">
                <path d="M504 256C504 119 393 8 256 8S8 119 8 256c0 123.78 90.69 226.38 209.25 245V327.69h-63V256h63v-54.64c0-62.15 37-96.48 93.67-96.48 27.14 0 55.52 4.84 55.52 4.84v61h-31.28c-30.8 0-40.41 19.12-40.41 38.73V256h68.78l-11 71.69h-57.78V501C413.31 482.38 504 379.78 504 256z" />
              </svg></a><a href="https://www.instagram.com/cmnsfixmac" target="_blank" rel="noopener" aria-label="Instagram"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" fill="currentColor">
                <path d="M224.1 141c-63.6 0-114.9 51.3-114.9 114.9s51.3 114.9 114.9 114.9S339 319.5 339 255.9 287.7 141 224.1 141zm0 189.6c-41.1 0-74.7-33.5-74.7-74.7s33.5-74.7 74.7-74.7 74.7 33.5 74.7 74.7-33.6 74.7-74.7 74.7zm146.4-194.3c0 14.9-12 26.8-26.8 26.8-14.9 0-26.8-12-26.8-26.8s12-26.8 26.8-26.8 26.8 12 26.8 26.8zm76.1 27.2c-1.7-35.9-9.9-67.7-36.2-93.9-26.2-26.2-58-34.4-93.9-36.2-37.2-2.1-147.9-2.1-185.1 0-35.9 1.7-67.7 9.9-93.9 36.2-26.2 26.2-34.4 58-36.2 93.9-2.1 37.2-2.1 147.9 0 185.1 1.7 35.9 9.9 67.7 36.2 93.9 26.2 26.2 58 34.4 93.9 36.2 37.2 2.1 147.9 2.1 185.1 0 35.9-1.5 67.7-8.2 74.6 31.3 23.1 23.1 29.8 46.2 31.3 74.6 1.8 36.9 1.8 140.3 0 177.2z" />
              </svg></a><a href="https://www.tiktok.com/@cmnsfixmac" target="_blank" rel="noopener" aria-label="TikTok"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" fill="currentColor">
                <path d="M448,209.91a210.06,210.06,0,0,1-122.77-39.25V349.38A162.55,162.55,0,1,1,185,188.31V278.2a74.62,74.62,0,1,0,52.23,71.18V0l88,0a121.18,121.18,0,0,0,1.86,22.17h0A122.18,122.18,0,0,0,381,102.39a121.43,121.43,0,0,0,67,20.14Z" />
              </svg></a></div>
        </div>
        <div class="footer-col">
          <h4>Our Services</h4>
          <ul>
            <li><a href="/en/shop/">Shop (Used Products)</a></li>
            <li><a href="/en/works.php">Repair Gallery</a></li>
            <li><a href="/en/buyback.php">Sell Your Device</a></li>
            <li><a href="/en/warranty.php">Check Warranty</a></li>
          </ul>
        </div>
        <div class="footer-col">
          <h4>Help</h4>
          <ul>
            <li><a href="/en/articles.php">Articles</a></li>

          </ul>
        </div>
        <div class="footer-col">
          <h4>Contact Us</h4>
          <ul class="f-contact">
            <li><span class="material-symbols-rounded">location_on</span><a href="https://maps.app.goo.gl/r6f1sHhfa8mzxZXD9" target="_blank" rel="noopener">Our Store (Chiang Mai)</a></li>
            <li><span class="material-symbols-rounded">call</span><a href="tel:0841511684">084-151-1684</a></li>
            <li><span class="material-symbols-rounded">chat_bubble</span><a href="https://line.me/R/ti/p/@cmns" target="_blank" rel="noopener">LINE: @cmns</a></li>
            <li><span class="material-symbols-rounded">mail</span><a href="mailto:info@cmnsfixmac.com">info@cmnsfixmac.com</a></li>
          </ul>
        </div>
      </div>
      <div class="footer-bottom">
        <p>© <?= date('Y') ?> CMNS FixMac. All rights reserved.</p>
      </div>
    </div>
  </footer>

  <script src="/shop/assets/js/hero.js" defer></script>
  <script>
    function toggleSidebar() {
      document.getElementById('sidebar')?.classList.toggle('open');
      document.getElementById('sidebar-overlay')?.classList.toggle('show');
      document.getElementById('hamburger')?.classList.toggle('open');
    }

    function toggleSidebarDropdown(btn) {
      btn.closest('.sidebar-dropdown').classList.toggle('open');
    }
    (function handleNavbarScroll() {
      const n = document.querySelector('.navbar');

      function o() {
        window.scrollY > 30 ? n?.classList.add('scrolled') : n?.classList.remove('scrolled')
      }
      window.addEventListener('scroll', o);
      o();
    })();
    (function() {
      const mainWrapper = document.getElementById('pdp-main-image-wrapper');
      const thumbs = document.querySelectorAll('.pdp-thumb-item');
      if (!mainWrapper || !thumbs.length) return;
      const mainIcon = mainWrapper.querySelector('.pdp-gallery-icon');
      const fallbackIconHtml = mainIcon ? mainIcon.outerHTML : '<div class="pdp-gallery-icon"><span class="material-symbols-rounded" aria-hidden="true">image</span></div>';
      const images = Array.from(thumbs).map(t => t.dataset.fullSrc);
      let currentIndex = 0;

      function normalizeSrc(s) {
        if (!s) return '';
        return (/^(https?:)?\//.test(s)) ? s : '/' + String(s).replace(/^\/+/, '');
      }

      function showPrev() {
        updateMainImage((currentIndex - 1 + images.length) % images.length);
      }

      function showNext() {
        updateMainImage((currentIndex + 1) % images.length);
      }

      function updateMainImage(index) {
        currentIndex = index;
        const fullSrc = images[currentIndex];
        const altEl = document.querySelector('.pdp-title');
        const alt = altEl ? altEl.textContent.trim() : 'Product Image';
        const needNav = images.length > 1;
        mainWrapper.innerHTML = '';
        if (!fullSrc) {
          const tmp = document.createElement('div');
          tmp.innerHTML = fallbackIconHtml;
          mainWrapper.appendChild(tmp.firstElementChild);
        } else {
          const img = document.createElement('img');
          img.className = 'pdp-main-img';
          img.loading = 'lazy';
          img.decoding = 'async';
          img.alt = alt;
          img.src = normalizeSrc(fullSrc);
          img.onerror = function() {
            this.onerror = null;
            mainWrapper.innerHTML = fallbackIconHtml;
          };
          mainWrapper.appendChild(img);
        }
        if (needNav) {
          const prev = document.createElement('button');
          prev.className = 'pdp-gallery-nav prev';
          prev.setAttribute('aria-label', 'Previous image');
          prev.innerHTML = '<span class="material-symbols-rounded">arrow_back_ios</span>';
          prev.addEventListener('click', showPrev);
          const next = document.createElement('button');
          next.className = 'pdp-gallery-nav next';
          next.setAttribute('aria-label', 'Next image');
          next.innerHTML = '<span class="material-symbols-rounded">arrow_forward_ios</span>';
          next.addEventListener('click', showNext);
          mainWrapper.appendChild(prev);
          mainWrapper.appendChild(next);
        }
        thumbs.forEach((t, i) => t.classList.toggle('is-active', i === currentIndex));
      }
      thumbs.forEach(thumb => {
        thumb.addEventListener('click', function(e) {
          e.preventDefault();
          updateMainImage(parseInt(thumb.dataset.index, 10));
        });
      });
      const firstActiveThumb = document.querySelector('.pdp-thumb-item.is-active');
      if (firstActiveThumb) updateMainImage(parseInt(firstActiveThumb.dataset.index, 10));
      else if (images.length > 0) updateMainImage(0);
    })();
    (function() {
      const qtyInput = document.getElementById('pdp-qty');
      const minusBtn = document.querySelector('.pdp-qty-btn.minus');
      const plusBtn = document.querySelector('.pdp-qty-btn.plus');
      if (!qtyInput || !minusBtn || !plusBtn) return;
      const maxQty = parseInt(qtyInput.max, 10) || 1;
      minusBtn.addEventListener('click', () => {
        let v = parseInt(qtyInput.value, 10);
        if (v > 1) qtyInput.value = v - 1;
      });
      plusBtn.addEventListener('click', () => {
        let v = parseInt(qtyInput.value, 10);
        if (v < maxQty) qtyInput.value = v + 1;
      });
      qtyInput.addEventListener('change', () => {
        let v = parseInt(qtyInput.value, 10);
        if (isNaN(v) || v < 1) qtyInput.value = 1;
        else if (v > maxQty) qtyInput.value = maxQty;
      });
    })();
    (function() {
      const tabButtons = document.querySelectorAll('.pdp-tab-btn');
      const tabContents = document.querySelectorAll('.pdp-tab-content');
      if (!tabButtons.length || !tabContents.length) return;
      tabButtons.forEach(button => {
        button.addEventListener('click', () => {
          tabContents.forEach(c => c.classList.remove('is-active'));
          tabButtons.forEach(btn => {
            btn.classList.remove('is-active');
            btn.setAttribute('aria-selected', 'false');
          });
          const id = button.getAttribute('aria-controls');
          const target = document.getElementById(id);
          if (target) target.classList.add('is-active');
          button.classList.add('is-active');
          button.setAttribute('aria-selected', 'true');
        });
      });
    })();
    (function() {
      const $ = s => document.querySelector(s);
      const LS_KEY = 'cmnsx_cart';
      const fmt = n => '฿' + (Number(n) || 0).toLocaleString('th-TH', {
        maximumFractionDigits: 0
      });

      function escapeHtml(t) {
        return String(t ?? '')
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#39;');
      }

      function loadCart() {
        try {
          return JSON.parse(localStorage.getItem(LS_KEY)) || []
        } catch (e) {
          return []
        }
      }

      function saveCart(t) {
        localStorage.setItem(LS_KEY, JSON.stringify(t));
      }

      function cartCount() {
        return loadCart().reduce((t, e) => t + (Number(e.qty) || 1), 0);
      }

      function cartTotal() {
        return loadCart().reduce((t, e) => t + (Number(e.price) || 0) * (Number(e.qty) || 1), 0);
      }

      function upsertItem({
        id: t,
        name: e,
        price: r,
        img: o,
        url: c
      }, n = 1) {
        const i = loadCart();
        const s = i.findIndex(x => String(x.id) === String(t));
        if (s > -1) i[s].qty = Math.min(99, (Number(i[s].qty) || 1) + n);
        else i.push({
          id: t,
          name: e,
          price: Number(r) || 0,
          img: o,
          url: c,
          qty: Math.max(1, n)
        });
        saveCart(i);
        render();
      }

      function setQty(t, e) {
        const r = loadCart();
        const o = r.findIndex(x => String(x.id) === String(t));
        if (o > -1) {
          r[o].qty = Math.max(1, Math.min(99, Number(e) || 1));
          saveCart(r);
          render();
        }
      }

      function removeItem(t) {
        saveCart(loadCart().filter(e => String(e.id) !== String(t)));
        render();
      }

      function clearCart() {
        saveCart([]);
        render();
      }
      const overlay = $('#mini-cart-overlay'),
        drawer = $('#mini-cart'),
        listEl = $('#mc-list'),
        emptyEl = $('#mc-empty'),
        totalEl = $('#mc-total'),
        navCart = $('.nav-cart'),
        cartBadge = $('.nav-cart .cart-count');
      window.openCart = function() {
        overlay.style.display = 'block';
        drawer.style.display = 'flex';
        document.body.classList.add('mc-open');
        drawer.focus();
      };
      window.closeCart = function() {
        document.body.classList.remove('mc-open');
        setTimeout(() => {
          overlay.style.display = 'none';
          drawer.style.display = 'none';
        }, 320);
      };
      $('.mc-close')?.addEventListener('click', closeCart);
      overlay?.addEventListener('click', closeCart);

      function badgeBump() {
        if (!cartBadge) return;
        cartBadge.classList.remove('pop');
        void cartBadge.offsetWidth;
        cartBadge.classList.add('pop');
      }

      function showToast(t) {
        const e = document.createElement('div');
        e.className = 'toast';
        e.textContent = t || 'Added to cart';
        document.body.appendChild(e);
        requestAnimationFrame(() => e.classList.add('show'));
        setTimeout(() => {
          e.classList.remove('show');
          setTimeout(() => e.remove(), 250)
        }, 1600);
      }

      function flyToCart(btn) {
        if (!navCart) return;
        let el;
        const card = btn.closest('.cmnsx-card');
        if (card) el = card.querySelector('.cmnsx-thumb img,.cmnsx-thumb .cmnsx-thumb-icon');
        else el = $('#pdp-main-image-wrapper')?.querySelector('img,.pdp-gallery-icon');
        if (!el) return;
        const r = el.getBoundingClientRect();
        const c = el.getAttribute('src');
        const n = navCart.getBoundingClientRect();
        const dx = (n.left + n.width / 2) - (r.left + r.width / 2);
        const dy = (n.top + n.height / 2) - (r.top + r.height / 2);
        const fly = document.createElement(c ? 'img' : 'div');
        fly.className = 'fly-img';
        if (c) {
          fly.src = c;
        } else {
          fly.innerHTML = '<span class="material-symbols-rounded">image</span>';
          fly.style.display = 'grid';
          fly.style.placeItems = 'center';
          fly.style.background = '#f0f0f0';
          fly.style.color = '#888';
          fly.style.borderRadius = '8px';
          fly.style.overflow = 'hidden';
        }
        fly.style.left = (r.left + r.width / 2 - 32) + 'px';
        fly.style.top = (r.top + r.height / 2 - 32) + 'px';
        document.body.appendChild(fly);
        requestAnimationFrame(() => {
          fly.style.transform = `translate(${dx}px,${dy}px) scale(.35)`;
          fly.style.opacity = '0.2';
        });
        setTimeout(() => fly.remove(), 650);
      }
      navCart && navCart.addEventListener('click', e => {
        e.preventDefault();
        openCart();
      });

      function render() {
        const t = loadCart();
        if (cartBadge) cartBadge.textContent = String(cartCount());
        if (emptyEl) emptyEl.style.display = t.length ? 'none' : 'block';
        if (listEl) listEl.innerHTML = '';
        t.forEach(item => {
          const li = document.createElement('li');
          li.className = 'mc-item';
          let r = item.img || '/assets/img/placeholder.jpg';
          if (r && !r.startsWith('/') && !r.startsWith('http')) r = '/assets/img/placeholder.jpg';
          li.innerHTML = `<img class="mc-thumb" src="${r}" alt="" onerror="this.onerror=null;this.src='/assets/img/placeholder.jpg';"><div><div class="mc-title"><a href="${item.url||'#'}">${escapeHtml(item.name||'Product')}</a></div><div class="mc-meta"><div class="mc-qty" data-id="${item.id}"><button type="button" class="mc-minus" aria-label="Decrease quantity"><span class="material-symbols-rounded">remove</span></button><input type="text" class="mc-input" value="${Number(item.qty)||1}" inputmode="numeric" pattern="[0-9]*" /><button type="button" class="mc-plus" aria-label="Increase quantity"><span class="material-symbols-rounded">add</span></button></div><div class="mc-price">${fmt(item.price)}</div></div></div><button type="button" class="mc-remove" data-id="${item.id}" aria-label="Remove"><span class="material-symbols-rounded">delete</span></button>`;
          listEl?.appendChild(li);
        });
        if (totalEl) totalEl.textContent = fmt(cartTotal());
      }
      document.addEventListener('click', function(e) {
        const btn = e.target.closest('.card-cart-btn');
        if (!btn) return;
        e.preventDefault();
        btn.disabled = true;
        const card = btn.closest('.cmnsx-card');
        let q = 1;
        if (!card) {
          const qEl = $('#pdp-qty');
          q = parseInt(qEl?.value, 10) || 1;
        }
        const payload = {
          id: btn.dataset.id,
          name: btn.dataset.name,
          price: Number(btn.dataset.price) || 0,
          img: btn.dataset.img,
          url: btn.dataset.url
        };
        upsertItem(payload, q);
        try {
          navigator.vibrate && navigator.vibrate(30)
        } catch (_) {}
        btn.classList.add('added');
        setTimeout(() => {
          btn.classList.remove('added');
          btn.disabled = false;
        }, 320);
        flyToCart(btn);
        badgeBump();
        showToast('Added to cart');
      });
      $('#mc-list')?.addEventListener('click', function(e) {
        const minus = e.target.closest('.mc-minus'),
          plus = e.target.closest('.mc-plus'),
          rm = e.target.closest('.mc-remove');
        if (minus || plus) {
          const box = e.target.closest('.mc-qty'),
            id = box?.dataset.id,
            i = box?.querySelector('.mc-input');
          if (!id || !i) return;
          let v = Number(i.value) || 1;
          v = minus ? Math.max(1, v - 1) : Math.min(99, v + 1);
          setQty(id, v);
          return;
        }
        if (rm) {
          const id = rm.dataset.id;
          if (id) removeItem(id);
        }
      });
      $('#mc-list')?.addEventListener('input', function(e) {
        const input = e.target.closest('.mc-input');
        if (!input) return;
        const box = e.target.closest('.mc-qty'),
          id = box?.dataset.id;
        if (!id) return;
        const v = Math.max(1, Math.min(99, Number(input.value.replace(/[^\d]/g, '')) || 1));
        setQty(id, v);
      });
      const bd = $('#receipt-backdrop'),
        md = $('#receipt-modal'),
        rList = $('#rcp-list'),
        rSub = $('#rcp-subtotal'),
        rShip = $('#rcp-ship'),
        rTot = $('#rcp-total'),
        rClose = md?.querySelector('.rcp-close'),
        rCopy = $('#rcp-copy'),
        rPrint = $('#rcp-print'),
        rLine = $('#rcp-line'),
        SHIPPING = 0;

      function showReceipt() {
        if (bd && md) {
          bd.style.display = 'block';
          md.style.display = 'grid';
        }
      }

      function hideReceipt() {
        if (bd && md) {
          bd.style.display = 'none';
          md.style.display = 'none';
        }
      }

      function buildSummaryText(items, grand) {
        const lines = items.map(x => `• ${x.name} x ${x.qty} = ${fmt((Number(x.price)||0)*(Number(x.qty)||1))}`);
        lines.push(`\nGrand Total: ${fmt(grand)}`);
        lines.push('\nContact: LINE @cmnsfixmac');
        return lines.join('\n');
      }

      function openReceipt() {
        const items = loadCart();
        if (!items.length) {
          alert('Your cart is empty');
          return;
        }
        if (rList) rList.innerHTML = '';
        items.forEach(x => {
          const li = document.createElement('li');
          li.className = 'rcp-item';
          let r = x.img || '/assets/img/placeholder.jpg';
          if (r && !r.startsWith('/') && !r.startsWith('http')) r = '/assets/img/placeholder.jpg';
          li.innerHTML = `<img class="rcp-thumb" src="${r}" alt="" onerror="this.onerror=null;this.src='/assets/img/placeholder.jpg';"><div><div class="rcp-title">${escapeHtml(x.name||'Product')}</div><div class="rcp-meta">Quantity: ${Number(x.qty)||1}</div></div><div class="rcp-price">${fmt((Number(x.price)||0)*(Number(x.qty)||1))}</div>`;
          rList?.appendChild(li);
        });
        const sub = items.reduce((t, e) => t + (Number(e.price) || 0) * (Number(e.qty) || 1), 0);
        const ship = SHIPPING,
          grand = sub + ship;
        if (rSub) rSub.textContent = fmt(sub);
        if (rShip) rShip.textContent = ship ? fmt(ship) : 'Free';
        if (rTot) rTot.textContent = fmt(grand);
        const summary = buildSummaryText(items, grand);
        if (rCopy) rCopy.onclick = async () => {
          try {
            await navigator.clipboard.writeText(summary);
            rCopy.innerHTML = '<span class="material-symbols-rounded">check</span> Copied!';
            setTimeout(() => rCopy.innerHTML = '<span class="material-symbols-rounded">content_copy</span> Copy Summary', 1200)
          } catch (err) {
            alert('Could not copy');
          }
        };
        if (rLine) rLine.href = 'https://line.me/R/ti/p/@cmns';
        if (rPrint) rPrint.onclick = () => window.print();
        showReceipt();
      }
      document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
          hideReceipt();
          closeCart();
        }
      });
      $('#mc-clear')?.addEventListener('click', function() {
        if (confirm('Clear entire cart?')) clearCart();
      });
      $('#mc-checkout')?.addEventListener('click', function(e) {
        e.preventDefault();
        openReceipt();
      });
      bd?.addEventListener('click', hideReceipt);
      rClose?.addEventListener('click', hideReceipt);
      render();
    })();
  </script>

</body>

</html>