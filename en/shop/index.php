<?php
require_once __DIR__ . '/../../includes/db.php';

/* ---------- Helpers ---------- */
function h($s)
{
  return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}
function getv($k, $d = null)
{
  return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d;
}
function build_url($patch)
{
  $qs = array_merge($_GET, $patch);
  foreach ($qs as $k => $v) {
    if ($v === '' || $v === null) unset($qs[$k]);
  }
  if (isset($qs['page']) && (int)$qs['page'] === 1) unset($qs['page']);
  $q = http_build_query($qs);
  return '/en/shop/' . ($q ? '?' . $q : '');
}

/* ---------- Lang links (EN page) ---------- */
$th_version_url = str_replace('/en', '', $_SERVER['REQUEST_URI']);
if ($th_version_url === '' || $th_version_url === '/') $th_version_url = '/';
if (strpos($th_version_url, '/shop') === false) $th_version_url = '/shop/';
$th_version_url = 'https://cmnsfixmac.com' . str_replace('/index.php', '/', $th_version_url);

$current_en_url = 'https://cmnsfixmac.com' . str_replace('/index.php', '/', $_SERVER['REQUEST_URI']);

/* ---------- Filters ---------- */
$q        = getv('q', '');
$cat      = getv('cat', '');
$min      = getv('min', '') !== '' ? (float)getv('min') : null;
$max      = getv('max', '') !== '' ? (float)getv('max') : null;

$ram_min  = getv('ram_min', '')  !== '' ? (int)getv('ram_min')  : null;
$ssd_min  = getv('ssd_min', '')  !== '' ? (int)getv('ssd_min')  : null;
$year_min = getv('year_min', '') !== '' ? (int)getv('year_min') : null;
$year_max = getv('year_max', '') !== '' ? (int)getv('year_max') : null;
$color    = getv('color', '');

$sort     = getv('sort', 'new'); // new|price_asc|price_desc
$page     = max(1, (int)getv('page', 1));
$pp       = min(60, max(12, (int)getv('pp', 24)));
$off      = ($page - 1) * $pp;

/* ---------- Base WHERE ---------- */
$where  = ["l.status='published'", "l.in_stock=1"];
$params = [];
// ใช้ title_en ถ้ามีคอลัมน์นี้ใน DB; ถ้าไม่มี เปลี่ยนกลับเป็น l.title
$has_title_en = true; // set true ถ้ามีคอลัมน์ title_en
if ($q !== '') {
  if ($has_title_en) {
    $where[] = "(l.title_en LIKE :q OR l.title LIKE :q)";
  } else {
    $where[] = "(l.title LIKE :q)";
  }
  $params[':q'] = "%$q%";
}
if ($cat !== '') {
  // ฝั่ง TH ใช้ brand เป็นหมวด; ถ้า EN ก็ใช้ brand เช่นกันให้ตรงกัน
  $where[] = "l.brand = :cat";
  $params[':cat'] = $cat;
}
if ($min !== null) {
  $where[] = "l.price >= :min";
  $params[':min'] = $min;
}
if ($max !== null) {
  $where[] = "l.price <= :max";
  $params[':max'] = $max;
}

$WHERE_BASE = 'WHERE ' . implode(' AND ', $where);

/* ---------- Base IDs for facets ---------- */
$sqlBaseIds = "SELECT l.id FROM listings l $WHERE_BASE";
$st = $pdo->prepare($sqlBaseIds);
$st->execute($params);
$baseIds = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
$idsList = $baseIds ? implode(',', $baseIds) : '0';

/* ---------- Facets (1 query) ---------- */
$attrKeys = ['ram_gb', 'ssd_gb', 'year', 'color'];
$attrMap  = $pdo->query("SELECT key_name,id FROM attrs WHERE key_name IN ('" . implode("','", $attrKeys) . "')")->fetchAll(PDO::FETCH_KEY_PAIR);
$idToKeyMap = $attrMap ? array_flip($attrMap) : [];

$facets = ['ram' => [], 'ssd' => [], 'year' => [], 'color' => []];

if ($baseIds && $attrMap) {
  $attrIds = array_values($attrMap);
  $ph = implode(',', array_fill(0, count($attrIds), '?'));
  $sqlFacets = "
    SELECT v.attr_id, v.value_int, v.value_string, COUNT(DISTINCT v.listing_id) c
    FROM listing_attr_values v
    WHERE v.listing_id IN ($idsList)
      AND v.attr_id IN ($ph)
    GROUP BY v.attr_id, v.value_int, v.value_string
  ";
  $st = $pdo->prepare($sqlFacets);
  $st->execute($attrIds);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  $tmp = ['ram_gb' => [], 'ssd_gb' => [], 'year' => [], 'color' => []];
  foreach ($rows as $r) {
    $key = $idToKeyMap[$r['attr_id']] ?? null;
    if (!$key) continue;
    if ($key === 'color') {
      if ($r['value_string'] !== '') $tmp[$key][] = ['val' => (string)$r['value_string'], 'c' => (int)$r['c']];
    } else {
      if ($r['value_int'] !== null)   $tmp[$key][] = ['val' => (int)$r['value_int'], 'c' => (int)$r['c']];
    }
  }
  usort($tmp['ram_gb'], fn($a, $b) => $a['val'] <=> $b['val']);
  usort($tmp['ssd_gb'], fn($a, $b) => $a['val'] <=> $b['val']);
  usort($tmp['year'],   fn($a, $b) => $b['val'] <=> $a['val']);
  usort($tmp['color'],  fn($a, $b) => strcasecmp((string)$a['val'], (string)$b['val']));

  $facets['ram'] = $tmp['ram_gb'];
  $facets['ssd'] = $tmp['ssd_gb'];
  $facets['year'] = $tmp['year'];
  $facets['color'] = $tmp['color'];
}

/* ---------- Items WHERE + chosen facets ---------- */
$whereItems = $where;
$paramsItems = $params;
$facetConds = [];
$facetParams = [];
$activeAttrKeys = [];

$facetFiltersConfig = [
  'ram_min'  => ['key' => 'ram_gb', 'param' => $ram_min, 'type' => 'int', 'op' => '>='],
  'ssd_min'  => ['key' => 'ssd_gb', 'param' => $ssd_min, 'type' => 'int', 'op' => '>='],
  'year_min' => ['key' => 'year',  'param' => $year_min, 'type' => 'int', 'op' => '>='],
  'year_max' => ['key' => 'year',  'param' => $year_max, 'type' => 'int', 'op' => '<='],
  'color'    => ['key' => 'color', 'param' => $color,  'type' => 'string', 'op' => '='],
];
foreach ($facetFiltersConfig as $cfg) {
  $attrKey = $cfg['key'];
  $val = $cfg['param'];
  $op = $cfg['op'];
  if ($val === null || $val === '') continue;
  if (empty($attrMap[$attrKey])) continue;
  $attrId = (int)$attrMap[$attrKey];
  $col = $cfg['type'] === 'int' ? 'v.value_int' : 'v.value_string';
  $facetConds[] = "($col $op ? AND v.attr_id = ?)";
  $facetParams[] = $val;
  $facetParams[] = $attrId;
  $activeAttrKeys[$attrKey] = 1;
}
$activeCount = count($activeAttrKeys);
if ($activeCount > 0) {
  $sqlFacetFilter = "
    SELECT v.listing_id
    FROM listing_attr_values v
    WHERE " . implode(' OR ', $facetConds) . "
    GROUP BY v.listing_id
    HAVING COUNT(DISTINCT v.attr_id) = " . (int)$activeCount;
  $st = $pdo->prepare($sqlFacetFilter);
  $st->execute($facetParams);
  $matching = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
  if ($matching) $whereItems[] = "l.id IN (" . implode(',', $matching) . ")";
  else $whereItems[] = "1=0";
}
$WHERE_ITEMS = 'WHERE ' . implode(' AND ', $whereItems);

/* ---------- ORDER ---------- */
$ORDER = 'ORDER BY l.created_at DESC, l.id DESC';
if ($sort === 'price_asc')  $ORDER = 'ORDER BY l.price ASC, l.id DESC';
if ($sort === 'price_desc') $ORDER = 'ORDER BY l.price DESC, l.id DESC';

/* ---------- COUNT ---------- */
$st = $pdo->prepare("SELECT COUNT(DISTINCT l.id) FROM listings l $WHERE_ITEMS");
$st->execute($paramsItems);
$total = (int)$st->fetchColumn();

/* ---------- ITEMS ---------- */
// ใช้ title_en ถ้ามี ไม่งั้น fallback title
$selectTitle = $has_title_en ? "COALESCE(l.title_en,l.title)" : "l.title";
$sqlItems = "SELECT l.id, $selectTitle AS name, l.brand AS category, l.price, l.price_old, l.stock_qty, l.created_at, l.main_image, l.in_stock
             FROM listings l
             $WHERE_ITEMS
             $ORDER
             LIMIT :lim OFFSET :off";
$st = $pdo->prepare($sqlItems);
foreach ($paramsItems as $k => $v) $st->bindValue($k, $v);
$st->bindValue(':lim', $pp, PDO::PARAM_INT);
$st->bindValue(':off', $off, PDO::PARAM_INT);
$st->execute();
$items = $st->fetchAll(PDO::FETCH_ASSOC);

/* ---------- Render (AJAX-ready chunk) ---------- */
ob_start(); ?>
<section id="cmnsx-products" class="cmnsx-products" aria-label="All Products">
  <h2 class="cmnsx-title">All Products (<?= number_format($total) ?>)</h2>

  <?php if (empty($items)): ?>
    <p class="cmnsx-empty">No products found. Try clearing the filters.</p>
  <?php else: ?>
    <ul class="cmnsx-grid">
      <?php
      $fallback_icon_html = '<div class="cmnsx-thumb-icon"><span class="material-symbols-rounded" aria-hidden="true">image</span></div>';
      foreach ($items as $row):
        $url = '/en/shop/product-detail.php?id=' . (int)$row['id'];
        $img = trim((string)($row['main_image'] ?? ''));
        if ($img !== '' && substr($img, 0, 1) !== '/' && !preg_match('~^https?://~', $img)) {
          $img = '/' . ltrim($img, '/');
        }
        $name = $row['name'];
        $cat  = $row['category'] ?: 'Others';
        $price = (float)$row['price'];
        $old  = (float)($row['price_old'] ?? 0);
        $disc = $old > $price ? ($old - $price) : 0;
        $pct  = $old > 0 ? round($disc / $old * 100) : 0;
        $qty  = (int)($row['stock_qty'] ?? 0);
        $in_stock = (int)($row['in_stock'] ?? 0);
        $low  = $in_stock === 1 && $qty > 0 && $qty <= 1;
      ?>
        <li class="cmnsx-card">
          <?php if ($disc > 0): ?>
            <div class="cmnsx-badge">Save <?= number_format($disc, 0) ?> ฿<?= $pct ? " (-$pct%)" : "" ?></div>
          <?php endif; ?>
          <a href="<?= h($url) ?>" class="cmnsx-thumb">
            <?php if ($img === ''): ?>
              <?= $fallback_icon_html ?>
            <?php else:
              $onerror_js = "this.onerror=null; this.outerHTML='" . addslashes($fallback_icon_html) . "';";
            ?>
              <img src="<?= h($img) ?>" alt="<?= h($name) ?>" class="cmnsx-img" loading="lazy" decoding="async" onerror="<?= h($onerror_js) ?>">
            <?php endif; ?>
          </a>
          <div class="cmnsx-info">
            <div class="cmnsx-cat"><?= h($cat) ?></div>
            <h3 class="cmnsx-name"><a href="<?= h($url) ?>" class="cmnsx-link"><?= h($name) ?></a></h3>
            <?php if ($low): ?><div class="cmnsx-low">• Low stock</div><?php endif; ?>
            <div class="cmnsx-price">
              <span class="cmnsx-price-now">฿<?= number_format($price, 0) ?></span>
              <?php if ($disc > 0): ?><span class="cmnsx-price-old">฿<?= number_format($old, 0) ?></span><?php endif; ?>
              <button class="card-cart-btn cart-on-price" type="button" aria-label="Add to cart"
                data-id="<?= (int)$row['id'] ?>" data-name="<?= h($name) ?>" data-price="<?= (float)$price ?>"
                data-img="<?= h($img) ?>" data-url="<?= h($url) ?>">
                <span class="material-symbols-rounded" aria-hidden="true">add_shopping_cart</span>
              </button>
            </div>
            <a class="btn-line-full" href="https://line.me/R/ti/p/@cmns" target="_blank" rel="noopener">
              <span class="material-symbols-rounded" aria-hidden="true">chat_bubble</span>
              Order via LINE
            </a>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>

    <?php $pages = (int)ceil($total / $pp);
    if ($pages > 1): ?>
      <nav class="cmnsx-pager" aria-label="pagination">
        <ul class="cmnsx-pager-list">
          <?php
          $first = 1;
          $last = max(1, $pages);
          $prev = max($first, $page - 1);
          $next = min($last, $page + 1);
          $mk = fn($p) => h(build_url(['page' => $p])) . '#cmnsx-products';
          ?>
          <li class="cmnsx-pager-item cmnsx-pager-nav <?= $page <= $first ? 'is-disabled' : '' ?>">
            <?= $page > $first ? '<a href="' . $mk($first) . '" aria-label="First page">«</a>' : '<span aria-disabled="true">«</span>' ?>
          </li>
          <li class="cmnsx-pager-item cmnsx-pager-nav <?= $page <= $first ? 'is-disabled' : '' ?>">
            <?= $page > $first ? '<a href="' . $mk($prev) . '" aria-label="Previous page">‹</a>' : '<span aria-disabled="true">‹</span>' ?>
          </li>
          <?php
          $links = [];
          $window = 2;
          if ($pages > 1) {
            $links[1] = 1;
            for ($i = max(2, $page - $window); $i <= min($pages - 1, $page + $window); $i++) $links[$i] = $i;
            $links[$pages] = $pages;
          }
          $withEllipsis = [];
          $lastNum = 0;
          foreach ($links as $n) {
            if ($lastNum !== 0 && $n > $lastNum + 1) $withEllipsis[] = '...';
            $withEllipsis[] = $n;
            $lastNum = $n;
          }
          foreach ($withEllipsis as $p) {
            echo '<li class="cmnsx-pager-item">';
            if ($p === '...') echo '<span class="cmnsx-pager-dots">...</span>';
            elseif ($p === $page) echo '<span class="cmnsx-pager-current" aria-current="page">' . $p . '</span>';
            else echo '<a class="cmnsx-pager-link" href="' . $mk($p) . '">' . $p . '</a>';
            echo '</li>';
          }
          ?>
          <li class="cmnsx-pager-item cmnsx-pager-nav <?= $page >= $last ? 'is-disabled' : '' ?>">
            <?= $page < $last ? '<a href="' . $mk($next) . '" aria-label="Next page">›</a>' : '<span aria-disabled="true">›</span>' ?>
          </li>
          <li class="cmnsx-pager-item cmnsx-pager-nav <?= $page >= $last ? 'is-disabled' : '' ?>">
            <?= $page < $last ? '<a href="' . $mk($last) . '" aria-label="Last page">»</a>' : '<span aria-disabled="true">»</span>' ?>
          </li>
        </ul>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php $__products_html = ob_get_clean();

/* ---------- AJAX short-circuit ---------- */
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
  header('Content-Type: text/html; charset=UTF-8');
  echo $__products_html;
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CMNS FixMac | Used Apple Store Chiang Mai – Used MacBook, iPhone, iPad with Warranty</title>
  <meta name="description" content="Used MacBook, Used iPhone, Used iPad, and Apple Watch. Good condition with after-sales warranty. Nationwide shipping. Physical store in Chiang Mai by CMNS FixMac.">
  <link rel="canonical" href="<?= h($current_en_url) ?>" />
  <link rel="alternate" hreflang="th" href="<?= h($th_version_url) ?>" />
  <link rel="alternate" hreflang="en" href="<?= h($current_en_url) ?>" />
  <link rel="alternate" hreflang="x-default" href="<?= h($current_en_url) ?>" />
  <link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png" />
  <link rel="stylesheet" href="/shop/assets/css/shop-style.css">
  <link rel="stylesheet" href="/shop/assets/css/hero.css">
  <link rel="stylesheet" href="/shop/assets/css/cart-receipt.css">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
</head>

<body>

  <header class="navbar navbar-top">
    <div class="nav-container">
      <div class="nav-logo">
        <a href="/en/shop/"><img src="/assets/img/Logo1.png" alt="CMNS FixMac Logo"></a>
      </div>
      <div class="menu-desktop-only">
        <nav class="menu">
          <a href="/en/" class="highlight-home"><span class="material-symbols-rounded">home</span> Home</a>
          <a href="/en/shop/" class="active"><span class="material-symbols-rounded">storefront</span> Shop</a>
          <a href="/en/works.php"><span class="material-symbols-rounded">construction</span> Our Work</a>
          <a href="/en/articles.php"><span class="material-symbols-rounded">description</span> Articles</a>
          <a href="/en/buyback.php"><span class="material-symbols-rounded">laptop_mac</span> Sell Your Device</a>
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
        <div class="sidebar-header">
          <a href="/en/"><img src="/assets/img/Logo1.png" alt="CMNS FixMac Logo" style="height:36px; margin-bottom:16px;"></a>
          <span class="close-btn" onclick="toggleSidebar()">✕</span>
        </div>
        <nav class="sidebar-menu">
          <a href="/en/" class="highlight-home"><span class="material-symbols-rounded">home</span> Home</a>
          <a href="/en/works.php"><span class="material-symbols-rounded">construction</span> Our Work</a>
          <a href="/en/shop/" class="active"><span class="material-symbols-rounded">storefront</span> Shop</a>
          <a href="/en/articles.php"><span class="material-symbols-rounded">description</span> Articles</a>
          <a href="/en/buyback.php"><span class="material-symbols-rounded">laptop_mac</span> Sell Your Device</a>
          <a href="/en/warranty.php"><span class="material-symbols-rounded">verified</span> Check Warranty</a>
          <a href="tel:0841511684"><span class="material-symbols-rounded">call</span> Call Us</a>
          <a href="<?= h($th_version_url) ?>" class="language-switch-btn" title="Switch to Thai"><span class="material-symbols-rounded">language</span> TH</a>
        </nav>
        <div class="sidebar-dropdown">
          <button class="dropdown-toggle" onclick="toggleSidebarDropdown(this)"><span class="material-symbols-rounded">smart_toy</span> Device Tester <span class="material-symbols-rounded dropdown-icon">expand_more</span></button>
          <div class="dropdown-submenu">
            <a href="/en/tester/monitor-tester/"><span class="material-symbols-rounded">monitor</span> Monitor</a>
            <a href="/en/tester/keyboard-tester/"><span class="material-symbols-rounded">keyboard</span> Keyboard</a>
            <a href="/en/tester/microphone-tester/"><span class="material-symbols-rounded">mic</span> Microphone</a>
            <a href="/en/tester/camera-tester/"><span class="material-symbols-rounded">photo_camera</span> Camera</a>
            <a href="/en/tester/sounds-tester/"><span class="material-symbols-rounded">volume_up</span> Speakers</a>
            <a href="/en/tester/touchscreen-tester/"><span class="material-symbols-rounded">touch_app</span> Touchscreen</a>
          </div>
        </div>
      </div>
      <div id="sidebar-overlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>
    </div>
  </header>

  <section id="hero-split" class="hero-split">
    <div class="hero-left">
      <div class="hero-track">
        <article class="hero-slide is-active">
          <img src="https://images.unsplash.com/photo-1517336714731-489689fd1ca8?q=80&w=2000&auto=format&fit=crop" alt="MacBook">
          <div class="hero-caption">
            <h2>Used MacBook Pro</h2>
            <p>Great condition, full warranty, special price</p><a href="/en/shop/?cat=MacBook#cmnsx-products" class="btn-hero">Shop Now</a>
          </div>
        </article>
        <article class="hero-slide">
          <img src="https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?q=80&w=2000&auto=format&fit=crop" alt="iPhone">
          <div class="hero-caption">
            <h2>Great iPhone Deals</h2>
            <p>Guaranteed to work. Great value.</p><a href="/en/shop/?cat=iPhone#cmnsx-products" class="btn-hero">Shop iPhones</a>
          </div>
        </article>
        <article class="hero-slide">
          <img src="https://images.unsplash.com/photo-1542751110-97427bbecf20?q=80&w=2000&auto=format&fit=crop" alt="iPad">
          <div class="hero-caption">
            <h2>iPad Ready to Go</h2>
            <p>Perfect for study and work.</p><a href="/en/shop/?cat=iPad#cmnsx-products" class="btn-hero">Shop iPads</a>
          </div>
        </article>
      </div>
      <button class="hero-nav prev" aria-label="Previous">‹</button>
      <button class="hero-nav next" aria-label="Next">›</button>
      <div class="hero-dots" aria-label="Slide indicators"></div>
    </div>
    <aside class="hero-right">
      <div class="promo-card">
        <img src="https://images.unsplash.com/photo-1542751110-97427bbecf20?q=80&w=2000&auto=format&fit=crop" alt="Promotion">
        <div class="promo-caption">
          <h3>Special Promotion</h3>
          <p>Up to 30% off MacBooks</p><a href="/en/shop/?promo=mb#cmnsx-products" class="btn-hero btn-ghost">View Deals</a>
        </div>
      </div>
    </aside>
  </section>

  <section class="seo-hero">
    <div class="seo-inner">
      <h1>Used Apple Store Chiang Mai – Quality MacBook, iPhone, iPad</h1>
      <p>Buy & Sell <strong>Used MacBook</strong>, <strong>Used iPhone</strong>, <strong>Used iPad</strong> and genuine Apple devices at great prices with after-sales warranty. Store in Chiang Mai, nationwide shipping.</p>
    </div>
  </section>

  <section class="cat-gallery" aria-label="Shop by Category">
    <h2 class="sec-title">Shop by Category</h2>
    <ul class="cat-grid">
      <li class="cat-item"><a href="/en/shop/?cat=MacBook#cmnsx-products" class="cat-link">
          <div class="cat-media"><img src="/shop/assets/img/cats/macbook.png" alt="MacBook" loading="lazy" decoding="async"></div>
          <div class="cat-label">MacBook</div>
        </a></li>
      <li class="cat-item"><a href="/en/shop/?cat=iMac#cmnsx-products" class="cat-link">
          <div class="cat-media"><img src="/shop/assets/img/cats/imac.png" alt="iMac" loading="lazy" decoding="async"></div>
          <div class="cat-label">iMac</div>
        </a></li>
      <li class="cat-item"><a href="/en/shop/?cat=iPhone#cmnsx-products" class="cat-link">
          <div class="cat-media"><img src="/shop/assets/img/cats/iphone.png" alt="iPhone" loading="lazy" decoding="async"></div>
          <div class="cat-label">iPhone</div>
        </a></li>
      <li class="cat-item"><a href="/en/shop/?cat=iPad#cmnsx-products" class="cat-link">
          <div class="cat-media"><img src="/shop/assets/img/cats/ipad.png" alt="iPad" loading="lazy" decoding="async"></div>
          <div class="cat-label">iPad</div>
        </a></li>
      <li class="cat-item"><a href="/en/shop/?cat=Watch#cmnsx-products" class="cat-link">
          <div class="cat-media"><img src="/shop/assets/img/cats/watch.png" alt="Apple Watch" loading="lazy" decoding="async"></div>
          <div class="cat-label">Apple Watch</div>
        </a></li>
      <li class="cat-item"><a href="/en/shop/?cat=AirPods#cmnsx-products" class="cat-link">
          <div class="cat-media"><img src="/shop/assets/img/cats/airpods.png" alt="AirPods" loading="lazy" decoding="async"></div>
          <div class="cat-label">AirPods</div>
        </a></li>
      <li class="cat-item"><a href="/en/shop/?cat=Accessories#cmnsx-products" class="cat-link">
          <div class="cat-media"><img src="/shop/assets/img/cats/accessories.png" alt="Accessories" loading="lazy" decoding="async"></div>
          <div class="cat-label">Accessories</div>
        </a></li>
    </ul>
  </section>

  <?php
  $labelRam   = $ram_min !== null ? "RAM: ≥ {$ram_min}GB" : "RAM";
  $labelSsd   = $ssd_min !== null ? "SSD: ≥ {$ssd_min}GB" : "SSD";
  $labelYear  = "Year";
  if ($year_min !== null && $year_max !== null)      $labelYear = ($year_min === $year_max) ? "Year: {$year_min}" : "Year: {$year_min}-{$year_max}";
  elseif ($year_min !== null)                       $labelYear = "Year: ≥ {$year_min}";
  elseif ($year_max !== null)                       $labelYear = "Year: ≤ {$year_max}";
  $labelColor = $color !== '' ? "Color: {$color}" : "Color";
  ?>
  <section class="filter-bar" aria-label="Product filter dropdowns">
    <div class="fb-row">
      <div class="fb-dd"><button type="button" class="fb-chip<?= $ram_min !== null ? ' is-active' : '' ?>" aria-haspopup="true" aria-expanded="false"><span class="material-symbols-rounded" aria-hidden="true">memory</span><?= h($labelRam) ?><span class="material-symbols-rounded fb-caret" aria-hidden="true">expand_more</span></button>
        <div class="fb-menu" role="menu"><a role="menuitem" class="fb-item<?= $ram_min === null ? ' is-selected' : '' ?>" href="<?= h(build_url(['ram_min' => null, 'page' => 1])) ?>#cmnsx-products">All</a><?php foreach ($facets['ram'] as $r): $val = (int)$r['val'];
                                                                                                                                                                                                    $href = h(build_url(['ram_min' => $val, 'page' => 1])) . '#cmnsx-products';
                                                                                                                                                                                                    $sel = ($ram_min !== null && (int)$ram_min === $val) ? ' is-selected' : ''; ?><a role="menuitem" class="fb-item<?= $sel ?>" href="<?= $href ?>">≥ <?= $val ?> GB <span class="fb-count">(<?= (int)$r['c'] ?>)</span></a><?php endforeach; ?></div>
      </div>
      <div class="fb-dd"><button type="button" class="fb-chip<?= $ssd_min !== null ? ' is-active' : '' ?>" aria-haspopup="true" aria-expanded="false"><span class="material-symbols-rounded" aria-hidden="true">hard_drive</span><?= h($labelSsd) ?><span class="material-symbols-rounded fb-caret" aria-hidden="true">expand_more</span></button>
        <div class="fb-menu" role="menu"><a role="menuitem" class="fb-item<?= $ssd_min === null ? ' is-selected' : '' ?>" href="<?= h(build_url(['ssd_min' => null, 'page' => 1])) ?>#cmnsx-products">All</a><?php foreach ($facets['ssd'] as $r): $val = (int)$r['val'];
                                                                                                                                                                                                    $href = h(build_url(['ssd_min' => $val, 'page' => 1])) . '#cmnsx-products';
                                                                                                                                                                                                    $sel = ($ssd_min !== null && (int)$ssd_min === $val) ? ' is-selected' : ''; ?><a role="menuitem" class="fb-item<?= $sel ?>" href="<?= $href ?>">≥ <?= $val ?> GB <span class="fb-count">(<?= (int)$r['c'] ?>)</span></a><?php endforeach; ?></div>
      </div>
      <div class="fb-dd"><button type="button" class="fb-chip<?= ($year_min !== null || $year_max !== null) ? ' is-active' : '' ?>" aria-haspopup="true" aria-expanded="false"><span class="material-symbols-rounded" aria-hidden="true">calendar_month</span><?= h($labelYear) ?><span class="material-symbols-rounded fb-caret" aria-hidden="true">expand_more</span></button>
        <div class="fb-menu" role="menu"><a role="menuitem" class="fb-item<?= ($year_min === null && $year_max === null) ? ' is-selected' : '' ?>" href="<?= h(build_url(['year_min' => null, 'year_max' => null, 'page' => 1])) ?>#cmnsx-products">All</a><?php foreach ($facets['year'] as $r): $val = (int)$r['val'];
                                                                                                                                                                                                                                              $href = h(build_url(['year_min' => $val, 'year_max' => $val, 'page' => 1])) . '#cmnsx-products';
                                                                                                                                                                                                                                              $sel = ($year_min !== null && $year_max !== null && (int)$year_min === $val && (int)$year_max === $val) ? ' is-selected' : ''; ?><a role="menuitem" class="fb-item<?= $sel ?>" href="<?= $href ?>"><?= $val ?> <span class="fb-count">(<?= (int)$r['c'] ?>)</span></a><?php endforeach; ?><?php if (count($facets['year']) > 0 && $facets['year'][0]['val'] >= 2020): ?><a role="menuitem" class="fb-item<?= ($year_min === 2020 && $year_max === null) ? ' is-selected' : '' ?>" href="<?= h(build_url(['year_min' => 2020, 'year_max' => null, 'page' => 1])) ?>#cmnsx-products">2020 & Newer</a><?php endif; ?></div>
      </div>
      <div class="fb-dd"><button type="button" class="fb-chip<?= $color !== '' ? ' is-active' : '' ?>" aria-haspopup="true" aria-expanded="false"><span class="material-symbols-rounded" aria-hidden="true">palette</span><?= h($labelColor) ?><span class="material-symbols-rounded fb-caret" aria-hidden="true">expand_more</span></button>
        <div class="fb-menu" role="menu"><a role="menuitem" class="fb-item<?= $color === '' ? ' is-selected' : '' ?>" href="<?= h(build_url(['color' => null, 'page' => 1])) ?>#cmnsx-products">All</a><?php foreach ($facets['color'] as $r): if (!$r['val']) continue;
                                                                                                                                                                                              $val = (string)$r['val'];
                                                                                                                                                                                              $href = h(build_url(['color' => $val, 'page' => 1])) . '#cmnsx-products';
                                                                                                                                                                                              $sel = ($color !== '' && $color === $val) ? ' is-selected' : ''; ?><a role="menuitem" class="fb-item<?= $sel ?>" href="<?= $href ?>"><?= h($val) ?> <span class="fb-count">(<?= (int)$r['c'] ?>)</span></a><?php endforeach; ?></div>
      </div>
      <div class="fb-spacer"></div>
      <a class="fb-clear" href="/en/shop/#cmnsx-products"><span class="material-symbols-rounded" aria-hidden="true">filter_alt_off</span> Clear All</a>
    </div>
  </section>

  <?= $__products_html ?>

  <script src="/shop/assets/js/hero.js" defer></script>
  <script>
    function toggleSidebar() {
      document.getElementById('sidebar').classList.toggle('open');
      document.getElementById('sidebar-overlay').classList.toggle('show');
      document.getElementById('hamburger').classList.toggle('open');
    }

    function toggleSidebarDropdown(btn) {
      btn.closest('.sidebar-dropdown').classList.toggle('open');
    }
    (function() {
      const nav = document.querySelector('.navbar');

      function onScroll() {
        if (window.scrollY > 30) nav.classList.add('scrolled');
        else nav.classList.remove('scrolled');
      }
      window.addEventListener('scroll', onScroll);
      onScroll();
    })();

    // Smooth partial reload
    (function() {
      var root = document.getElementById('cmnsx-products');
      if (!root) return;

      function loadProducts(url, push) {
        var u = new URL(url, location.origin);
        u.hash = '';
        u.searchParams.set('ajax', '1');
        root.style.opacity = '0.4';
        fetch(u, {
            headers: {
              'X-Requested-With': 'fetch'
            }
          })
          .then(r => r.text()).then(function(html) {
            root.outerHTML = html;
            var newRoot = document.getElementById('cmnsx-products');
            if (newRoot) {
              root = newRoot;
              root.style.opacity = '1';
              if (push) history.pushState(null, '', url);
              root.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
              });
            }
          }).catch(function() {
            root.style.opacity = '1';
            location.href = url;
          });
      }
      document.addEventListener('click', function(e) {
        var a = e.target.closest('.cmnsx-pager a');
        if (!a) return;
        e.preventDefault();
        loadProducts(a.href, true);
      });
      document.addEventListener('click', function(e) {
        var a = e.target.closest('.filter-bar a');
        if (!a) return;
        document.querySelectorAll('.fb-dd.is-open').forEach(dd => dd.classList.remove('is-open'));
        e.preventDefault();
        loadProducts(a.href, true);
      });
      document.addEventListener('click', function(e) {
        var a = e.target.closest('a[href^="/en/shop/"]');
        if (!a) return;
        if (!/#cmnsx-products$/.test(a.getAttribute('href'))) return;
        e.preventDefault();
        loadProducts(a.href, true);
      });
      document.querySelectorAll('form.search-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
          e.preventDefault();
          var params = new URLSearchParams(new FormData(form));
          var url = (form.getAttribute('action') || '/en/shop/') + '?' + params.toString() + '#cmnsx-products';
          loadProducts(url, true);
        });
      });
      window.addEventListener('popstate', function() {
        loadProducts(location.href, false);
      });
    })();

    // Floating dropdown positioner
    (function() {
      const row = document.querySelector('.fb-row');

      function placeMenu(dd) {
        const btn = dd.querySelector('.fb-chip');
        const menu = dd.querySelector('.fb-menu');
        if (!btn || !menu) return;
        const r = btn.getBoundingClientRect();
        const vw = window.innerWidth;
        const vh = window.innerHeight;
        const gap = 8;
        menu.style.position = 'fixed';
        menu.style.minWidth = Math.max(r.width, 200) + 'px';
        menu.style.visibility = 'hidden';
        menu.style.display = 'block';
        const mw = menu.offsetWidth;
        menu.style.display = '';
        menu.style.visibility = '';
        let left = r.left;
        if (left + mw > vw - 12) left = Math.max(12, vw - mw - 12);
        if (left < 12) left = 12;
        const top = Math.min(vh - 12, r.bottom + gap);
        const maxH = Math.max(160, vh - top - 12);
        menu.style.left = left + 'px';
        menu.style.top = top + 'px';
        menu.style.maxHeight = maxH + 'px';
        menu.style.overflow = 'auto';
        menu.style.zIndex = 1000;
        if (row) {
          row.style.overflowY = 'visible';
          row.style.overflowX = 'auto';
        }
      }

      function openDD(dd) {
        document.querySelectorAll('.fb-dd.is-open').forEach(el => {
          if (el !== dd) el.classList.remove('is-open');
        });
        dd.classList.add('is-open');
        dd.querySelector('.fb-chip')?.setAttribute('aria-expanded', 'true');
        placeMenu(dd);
      }

      function closeAll() {
        document.querySelectorAll('.fb-dd.is-open').forEach(dd => {
          dd.classList.remove('is-open');
          dd.querySelector('.fb-chip')?.setAttribute('aria-expanded', 'false');
        });
        if (row) {
          row.style.overflowY = 'visible';
          row.style.overflowX = 'auto';
        }
      }
      document.addEventListener('click', function(e) {
        const chip = e.target.closest('.fb-chip');
        const inMenu = e.target.closest('.fb-menu');
        if (chip) {
          e.preventDefault();
          const dd = chip.closest('.fb-dd');
          dd.classList.contains('is-open') ? closeAll() : openDD(dd);
          return;
        }
        if (inMenu) return;
        closeAll();
      });
      document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeAll();
      });
      window.addEventListener('scroll', closeAll, {
        passive: true
      });
      window.addEventListener('resize', function() {
        const dd = document.querySelector('.fb-dd.is-open');
        if (dd) placeMenu(dd);
      });
      document.addEventListener('click', function(e) {
        const a = e.target.closest('.filter-bar .fb-menu a');
        if (!a) return;
        closeAll();
      });
    })();
  </script>

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
          <p>Scan to add <b>LINE Official @cmnsfixmac</b>. Send slip, check stock, or arrange pickup.</p>
          <a class="rcp-btn line small" href="https://line.me/R/ti/p/@cmns" target="_blank" rel="noopener"><span class="material-symbols-rounded">chat_bubble</span> Open LINE</a>
        </div>
      </div>
    </div>
    <footer class="rcp-foot">
      <button type="button" id="rcp-copy" class="rcp-btn ghost"><span class="material-symbols-rounded">content_copy</span> Copy Summary</button>
      <a id="rcp-line" class="rcp-btn line" href="https://line.me/R/ti/p/@cmns" target="_blank" rel="noopener"><span class="material-symbols-rounded">send</span> Send Details to LINE</a>
      <button type="button" id="rcp-print" class="rcp-btn"><span class="material-symbols-rounded">print</span> Print / Save as PDF</button>
    </footer>
  </aside>

  <script>
    (function() {
      const $ = (s, ctx = document) => ctx.querySelector(s);
      const LS_KEY = 'cmnsx_cart';
      const fmt = n => '฿' + (Number(n) || 0).toLocaleString('th-TH', {
        maximumFractionDigits: 0
      });

      function loadCart() {
        try {
          return JSON.parse(localStorage.getItem(LS_KEY)) || []
        } catch (e) {
          return []
        }
      }

      function saveCart(c) {
        localStorage.setItem(LS_KEY, JSON.stringify(c));
      }

      function cartCount() {
        return loadCart().reduce((s, it) => s + (Number(it.qty) || 1), 0);
      }

      function cartTotal() {
        return loadCart().reduce((s, it) => s + (Number(it.price) || 0) * (Number(it.qty) || 1), 0);
      }

      function upsertItem({
        id,
        name,
        price,
        img,
        url
      }, qty = 1) {
        const cart = loadCart();
        const i = cart.findIndex(it => String(it.id) === String(id));
        if (i > -1) cart[i].qty = Math.min(99, (Number(cart[i].qty) || 1) + qty);
        else cart.push({
          id,
          name,
          price: Number(price) || 0,
          img,
          url,
          qty: Math.max(1, qty)
        });
        saveCart(cart);
        render();
      }

      function setQty(id, qty) {
        const cart = loadCart();
        const i = cart.findIndex(it => String(it.id) === String(id));
        if (i > -1) {
          cart[i].qty = Math.max(1, Math.min(99, Number(qty) || 1));
          saveCart(cart);
          render();
        }
      }

      function removeItem(id) {
        saveCart(loadCart().filter(it => String(it.id) !== String(id)));
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
        navCart = document.querySelector('.nav-cart');
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
      navCart?.addEventListener('click', e => {
        e.preventDefault();
        openCart();
      });
      $('.mc-close').addEventListener('click', closeCart);
      overlay.addEventListener('click', closeCart);
      document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeCart();
      });

      function escapeHtml(s) {
        return String(s || '').replace(/[&<>"]/g, c => ({
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;'
        } [c]));
      }

      function render() {
        const cart = loadCart();
        const badge = document.querySelector('.nav-cart .cart-count');
        if (badge) badge.textContent = String(cartCount());
        emptyEl.style.display = cart.length ? 'none' : 'block';
        listEl.innerHTML = '';
        cart.forEach(it => {
          const li = document.createElement('li');
          li.className = 'mc-item';
          li.innerHTML = `
        <img class="mc-thumb" src="${it.img || '/assets/img/placeholder.jpg'}" alt="" onerror="this.onerror=null;this.src='/assets/img/placeholder.jpg';">
        <div>
          <div class="mc-title"><a href="${it.url||'#'}">${escapeHtml(it.name||'Product')}</a></div>
          <div class="mc-meta">
            <div class="mc-qty" data-id="${it.id}">
              <button type="button" class="mc-minus" aria-label="Decrease quantity"><span class="material-symbols-rounded">remove</span></button>
              <input type="text" class="mc-input" value="${Number(it.qty)||1}" inputmode="numeric" pattern="[0-9]*" />
              <button type="button" class="mc-plus" aria-label="Increase quantity"><span class="material-symbols-rounded">add</span></button>
            </div>
            <div class="mc-price">${fmt(it.price)}</div>
          </div>
        </div>
        <button type="button" class="mc-remove" data-id="${it.id}" aria-label="Remove"><span class="material-symbols-rounded">delete</span></button>
      `;
          listEl.appendChild(li);
        });
        totalEl.textContent = fmt(cartTotal());
      }

      const cartIcon = document.querySelector('.nav-cart');

      function badgeBump() {
        const b = document.querySelector('.nav-cart .cart-count');
        if (!b) return;
        b.classList.remove('pop');
        void b.offsetWidth;
        b.classList.add('pop');
      }

      function showToast(msg) {
        const t = document.createElement('div');
        t.className = 'toast';
        t.textContent = msg || 'Added to cart';
        document.body.appendChild(t);
        requestAnimationFrame(() => t.classList.add('show'));
        setTimeout(() => {
          t.classList.remove('show');
          setTimeout(() => t.remove(), 250);
        }, 1600);
      }

      function flyToCart(fromEl) {
        if (!cartIcon) return;
        const card = fromEl.closest('.cmnsx-card');
        const img = card?.querySelector('.cmnsx-thumb img, .cmnsx-thumb .cmnsx-thumb-icon');
        if (!img) return;
        const rectFrom = img.getBoundingClientRect();
        const rectTo = cartIcon.getBoundingClientRect();
        if (!img.getAttribute('src')) {
          const ghost = document.createElement('div');
          ghost.className = 'fly-img';
          ghost.innerHTML = '<span class="material-symbols-rounded">image</span>';
          ghost.style.left = (rectFrom.left + rectFrom.width / 2 - 32) + 'px';
          ghost.style.top = (rectFrom.top + rectFrom.height / 2 - 32) + 'px';
          ghost.style.display = 'grid';
          ghost.style.placeItems = 'center';
          document.body.appendChild(ghost);
          const dx = (rectTo.left + rectTo.width / 2) - (rectFrom.left + rectFrom.width / 2);
          const dy = (rectTo.top + rectTo.height / 2) - (rectFrom.top + rectFrom.height / 2);
          requestAnimationFrame(() => {
            ghost.style.transform = `translate(${dx}px, ${dy}px) scale(.35)`;
            ghost.style.opacity = '0.2';
          });
          setTimeout(() => ghost.remove(), 650);
          return;
        }
        const ghost = document.createElement('img');
        ghost.className = 'fly-img';
        ghost.src = img.getAttribute('src');
        ghost.style.left = (rectFrom.left + rectFrom.width / 2 - 32) + 'px';
        ghost.style.top = (rectFrom.top + rectFrom.height / 2 - 32) + 'px';
        document.body.appendChild(ghost);
        const dx = (rectTo.left + rectTo.width / 2) - (rectFrom.left + rectFrom.width / 2);
        const dy = (rectTo.top + rectTo.height / 2) - (rectFrom.top + rectFrom.height / 2);
        requestAnimationFrame(() => {
          ghost.style.transform = `translate(${dx}px, ${dy}px) scale(.35)`;
          ghost.style.opacity = '0.2';
        });
        setTimeout(() => ghost.remove(), 650);
      }

      document.addEventListener('click', function(e) {
        const btn = e.target.closest('.card-cart-btn');
        if (!btn) return;
        e.preventDefault();
        btn.disabled = true;
        const card = btn.closest('.cmnsx-card');
        const payload = {
          id: btn.dataset.id,
          name: btn.dataset.name || card?.querySelector('.cmnsx-name')?.textContent?.trim(),
          price: btn.dataset.price || card?.querySelector('.cmnsx-price-now')?.textContent?.replace(/[^\d]/g, ''),
          img: btn.dataset.img,
          url: btn.dataset.url || card?.querySelector('.cmnsx-link')?.getAttribute('href')
        };
        payload.price = Number(payload.price) || 0;
        upsertItem(payload, 1);
        try {
          navigator.vibrate && navigator.vibrate(30);
        } catch (e) {}
        btn.classList.add('added');
        setTimeout(() => {
          btn.classList.remove('added');
          btn.disabled = false;
        }, 320);
        flyToCart(btn);
        badgeBump();
        showToast('Added to cart');
      });

document.getElementById('mc-list').addEventListener('click',function(e){
    const minus=e.target.closest('.mc-minus'); const plus=e.target.closest('.mc-plus'); const remove=e.target.closest('.mc-remove');
    if(minus||plus){ /* ... (โค้ดเพิ่มลดจำนวน) ... */ return; }
    
    // แก้ตรงนี้
    if(remove){ 
      const id=remove.dataset.id; 
      if(id) {
        // เพิ่มแค่บรรทัด if(confirm(...)) นี่แหละ
        // (มึงจะเปลี่ยนข้อความเป็นไทยก็ได้นะ "ยืนยันการลบ?" แล้วแต่)
        if(confirm('Are you sure you want to remove this item?')) {
          removeItem(id); 
        }
      }
      return; 
    }
  });
      document.getElementById('mc-list').addEventListener('input', function(e) {
        const inp = e.target.closest('.mc-input');
        if (!inp) return;
        const wrap = e.target.closest('.mc-qty');
        const id = wrap?.dataset.id;
        if (!id) return;
        const q = Math.max(1, Math.min(99, Number(inp.value.replace(/[^\d]/g, '')) || 1));
        setQty(id, q);
      });

      const bd = document.getElementById('receipt-backdrop');
      const md = document.getElementById('receipt-modal');
      const rList = document.getElementById('rcp-list');
      const rSub = document.getElementById('rcp-subtotal');
      const rShip = document.getElementById('rcp-ship');
      const rTot = document.getElementById('rcp-total');
      const rClose = md.querySelector('.rcp-close');
      const rCopy = document.getElementById('rcp-copy');
      const rPrint = document.getElementById('rcp-print');
      const rLine = document.getElementById('rcp-line');
      const SHIPPING = 0;

      function showReceipt() {
        bd.style.display = 'block';
        md.style.display = 'grid';
      }

      function hideReceipt() {
        bd.style.display = 'none';
        md.style.display = 'none';
      }

      function buildSummaryText(cart, total) {
        const rows = cart.map(it => `• ${it.name||'Product'} x ${it.qty} = ${fmt((Number(it.price)||0)*(Number(it.qty)||1))}`);
        rows.push(`\nGrand Total: ${fmt(total)}`);
        rows.push(`\nContact: LINE @cmnsfixmac`);
        return rows.join('\n');
      }

      function openReceipt() {
        const cart = loadCart();
        if (!cart.length) {
          alert('Your cart is empty');
          return;
        }
        rList.innerHTML = '';
        cart.forEach(it => {
          const li = document.createElement('li');
          li.className = 'rcp-item';
          li.innerHTML = `
      <img class="rcp-thumb" src="${it.img || '/assets/img/placeholder.jpg'}" alt="" onerror="this.onerror=null;this.src='/assets/img/placeholder.jpg';">
      <div><div class="rcp-title">${escapeHtml(it.name||'Product')}</div><div class="rcp-meta">Quantity: ${Number(it.qty)||1}</div></div>
      <div class="rcp-price">${fmt((Number(it.price)||0)*(Number(it.qty)||1))}</div>`;
          rList.appendChild(li);
        });
        const sub = cart.reduce((s, it) => s + (Number(it.price) || 0) * (Number(it.qty) || 1), 0);
        const ship = SHIPPING;
        const grand = sub + ship;
        rSub.textContent = fmt(sub);
        rShip.textContent = ship ? fmt(ship) : 'Free';
        rTot.textContent = fmt(grand);
        const text = buildSummaryText(cart, grand);
        rCopy.onclick = async () => {
          try {
            await navigator.clipboard.writeText(text);
            rCopy.innerHTML = '<span class="material-symbols-rounded">check</span> Copied!';
            setTimeout(() => rCopy.innerHTML = '<span class="material-symbols-rounded">content_copy</span> Copy Summary', 1200);
          } catch (e) {
            alert('Could not copy');
          }
        };
        rLine.href = 'https://line.me/R/ti/p/@cmns';
        rPrint.onclick = () => window.print();
        showReceipt();
      }
      document.getElementById('mc-clear').addEventListener('click', function() {
        if (confirm('Clear entire cart?')) clearCart();
      });
      document.getElementById('mc-checkout').addEventListener('click', function(e) {
        e.preventDefault();
        openReceipt();
      });
      bd.addEventListener('click', hideReceipt);
      rClose.addEventListener('click', hideReceipt);
      document.addEventListener('keydown', e => {
        if (e.key === 'Escape') hideReceipt();
      });

      render();
    })();
  </script>

  <div id="promo-backdrop" aria-hidden="true" hidden></div>
  <aside id="promo-modal" role="dialog" aria-modal="true" aria-labelledby="promo-title" hidden>
    <div class="promo-card" role="document">
      <button type="button" class="promo-close" aria-label="Close promotion"><span class="material-symbols-rounded" aria-hidden="true">close</span></button>
      <div class="promo-product-container">
        <div class="promo-loader">
          <div class="spinner"></div><span>Loading products...</span>
        </div>
        <ul id="promo-product-list" class="promo-product-list"></ul>
      </div>
      <div class="promo-body">
        <h3 id="promo-title">Recommended For You!</h3>
        <p>Quality used MacBook / iPhone / iPad with warranty and extras. Browse products or add us on LINE to chat.</p>
        <div class="promo-actions">
          <a href="/en/shop/#cmnsx-products" class="promo-btn primary"><span class="material-symbols-rounded" aria-hidden="true">shopping_bag</span> Shop Now</a>
          <a href="https://line.me/R/ti/p/@cmns" target="_blank" rel="noopener" class="promo-btn line"><span class="material-symbols-rounded" aria-hidden="true">chat_bubble</span> Add LINE @cmns</a>
        </div>
        <label class="promo-nomore"><input type="checkbox" id="promo-never"> Don't show this again</label>
      </div>
    </div>
  </aside>
  <script>
    (function() {
      const KEY_SEEN_AT = 'promo_seen_at_v1',
        KEY_NEVER = 'promo_never_v1',
        DAY = 24 * 60 * 60 * 1000,
        COOLDOWN_DAYS = 7;
      const bd = document.getElementById('promo-backdrop'),
        md = document.getElementById('promo-modal'),
        closeBtn = md?.querySelector('.promo-close');
      const neverCb = document.getElementById('promo-never'),
        listEl = document.getElementById('promo-product-list'),
        loaderEl = md?.querySelector('.promo-loader');
      if (!bd || !md || !listEl || !loaderEl) return;
      let isLoaded = false;
      const API_URL = '/shop/api_random_products.php?limit=4'; // ใช้ API เดิมได้

      function hasNever() {
        return localStorage.getItem(KEY_NEVER) === '1';
      }

      function lastSeen() {
        return Number(localStorage.getItem(KEY_SEEN_AT) || 0);
      }

      function shouldShow() {
        if (hasNever()) return false;
        const seen = lastSeen();
        return !seen || (Date.now() - seen) > COOLDOWN_DAYS * DAY;
      }

      function lockScroll(on) {
        document.documentElement.style.overflow = on ? 'hidden' : '';
        document.body.style.overflow = on ? 'hidden' : '';
      }

      async function fetchProducts() {
        if (isLoaded) return;
        isLoaded = true;
        loaderEl.classList.remove('is-hidden');
        listEl.innerHTML = '';
        try {
          const res = await fetch(API_URL);
          if (!res.ok) throw new Error('net');
          const data = await res.json();
          if (!data.success || !Array.isArray(data.items) || !data.items.length) throw new Error('empty');
          data.items.forEach(item => {
            const li = document.createElement('li');
            li.className = 'promo-product-item';
            const name = (item.name || '').replace(/"/g, '&quot;').replace(/</g, '&lt;');
            const price = item.price_fmt || '';
            let url = item.url || '#';
            if (url.startsWith('/shop/')) url = '/en' + url;
            const fallback = "<div class='promo-product-icon'><span class='material-symbols-rounded' aria-hidden='true'>image</span></div>";
            let media = item.img ? `<img src="${item.img}" alt="${name}" loading="lazy" decoding="async" onerror="this.onerror=null; this.outerHTML='${fallback.replace(/'/g,'&#39;')}';">` : fallback;
            li.innerHTML = `<a href="${url}">${media}<div class="promo-product-name">${name}</div><div class="promo-product-price">${price}</div></a>`;
            listEl.appendChild(li);
          });
          loaderEl.classList.add('is-hidden');
        } catch (e) {
          loaderEl.innerHTML = '<span>Could not load products.</span>';
          isLoaded = false;
        }
      }

      function openPromo() {
        bd.hidden = false;
        md.hidden = false;
        requestAnimationFrame(() => {
          bd.classList.add('show');
          md.classList.add('show');
          lockScroll(true);
          closeBtn?.focus();
        });
        fetchProducts();
      }

      function closePromo(persist = true) {
        bd.classList.remove('show');
        md.classList.remove('show');
        lockScroll(false);
        setTimeout(() => {
          bd.hidden = true;
          md.hidden = true;
          listEl.innerHTML = '';
          loaderEl.classList.remove('is-hidden');
          loaderEl.innerHTML = '<div class="spinner"></div><span>Loading products...</span>';
          isLoaded = false;
        }, 260);
        if (persist) localStorage.setItem(KEY_SEEN_AT, String(Date.now()));
        if (neverCb?.checked) localStorage.setItem(KEY_NEVER, '1');
      }
      window.addEventListener('DOMContentLoaded', () => {
        const force = new URLSearchParams(location.search).get('promo') === 'show';
        if (force) {
          openPromo();
          return;
        }
        if (shouldShow()) setTimeout(openPromo, 1000);
        else {
          bd.hidden = true;
          md.hidden = true;
          bd.classList.remove('show');
          md.classList.remove('show');
          lockScroll(false);
        }
      });
      closeBtn?.addEventListener('click', () => closePromo(true));
      bd.addEventListener('click', () => closePromo(true));
      document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && !md.hidden) closePromo(false);
      });
    })();
  </script>

  <footer class="cmns-footer">
    <div class="footer-container">
      <div class="footer-grid">
        <div class="footer-col" id="f-brand">
          <a href="/en/shop/" class="f-logo"><img src="/assets/img/Logo1.png" alt="CMNS FixMac Logo"></a>
          <p>Chiang Mai's #1 shop for quality used Apple products and repairs.</p>
          <div class="f-socials">
            <a href="https://line.me/R/ti/p/@cmns" target="_blank" rel="noopener" aria-label="LINE"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" fill="currentColor">
                <path d="M224 336.1c-63.6 0-115.3-51.6-115.3-115.3S160.4 105.6 224 105.6s115.3 51.6 115.3 115.3-51.7 115.2-115.3 115.2zm0-207c-50.5 0-91.7 41.2-91.7 91.7s41.2 91.7 91.7 91.7 91.7-41.2 91.7-91.7-41.2-91.7-91.7-91.7zM448 220.7c0-101.9-82.8-184.6-184.8-184.6S78.4 118.8 78.4 220.7c0 82.2 53.7 151.6 125.7 174.4 6.9 2.2 11.2 8.9 11.2 16.2v.2c0 10.9-11.2 19.8-22.1 19.8-11.1 0-22.1-8.9-22.1-19.8 0-11.3-9.2-20.5-20.5-20.5-11.3 0-20.5 9.2-20.5 20.5 0 33.3 27 60.3 60.3 60.3s60.3-27 60.3-60.3v-.2c0-7.3 4.3-14 11.2-16.2 72-22.8 125.7-92.2 125.7-174.4zm-48.7 0c0 75.2-61.1 136.3-136.3 136.3s-136.3-61.1-136.3-136.3 61.1-136.3 136.3-136.3 136.3 61.1 136.3 136.3zM161.2 214.2c0-3.3-2.7-6-6-6s-6 2.7-6 6v13.1c0 3.3 2.7 6 6 6s6-2.7 6-6v-13.1zm51.4 0c0-3.3-2.7-6-6-6s-6 2.7-6 6v13.1c0 3.3 2.7 6 6 6s6-2.7 6-6v-13.1zm51.3 0c0-3.3-2.7-6-6-6s-6 2.7-6 6v13.1c0 3.3 2.7 6 6 6s6-2.7 6-6v-13.1z" />
              </svg></a>
            <a href="https://www.facebook.com/cmnsfixmac" target="_blank" rel="noopener" aria-label="Facebook"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="currentColor">
                <path d="M504 256C504 119 393 8 256 8S8 119 8 256c0 123.78 90.69 226.38 209.25 245V327.69h-63V256h63v-54.64c0-62.15 37-96.48 93.67-96.48 27.14 0 55.52 4.84 55.52 4.84v61h-31.28c-30.8 0-40.41 19.12-40.41 38.73V256h68.78l-11 71.69h-57.78V501C413.31 482.38 504 379.78 504 256z" />
              </svg></a>
            <a href="https://www.instagram.com/cmnsfixmac" target="_blank" rel="noopener" aria-label="Instagram"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" fill="currentColor">
                <path d="M224.1 141c-63.6 0-114.9 51.3-114.9 114.9s51.3 114.9 114.9 114.9S339 319.5 339 255.9 287.7 141 224.1 141zm0 189.6c-41.1 0-74.7-33.5-74.7-74.7s33.5-74.7 74.7-74.7 74.7 33.5 74.7 74.7-33.6 74.7-74.7 74.7zm146.4-194.3c0 14.9-12 26.8-26.8 26.8-14.9 0-26.8-12-26.8-26.8s12-26.8 26.8-26.8 26.8 12 26.8 26.8zm76.1 27.2c-1.7-35.9-9.9-67.7-36.2-93.9-26.2-26.2-58-34.4-93.9-36.2-37.2-2.1-147.9-2.1-185.1 0-35.9 1.7-67.7 9.9-93.9 36.2-26.2 26.2-34.4 58-36.2 93.9-2.1 37.2-2.1 147.9 0 185.1 1.7 35.9 9.9 67.7 36.2 93.9 26.2 26.2 58 34.4 93.9 36.2 37.2 2.1 147.9 2.1 185.1 0 35.9-1.7 67.7-9.9 93.9-36.2 26.2-26.2 34.4-58 36.2-93.9 2.1-37.2 2.1-147.8 0-185.1z" />
              </svg></a>
            <a href="https://www.tiktok.com/@cmnsfixmac" target="_blank" rel="noopener" aria-label="TikTok"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" fill="currentColor">
                <path d="M448,209.91a210.06,210.06,0,0,1-122.77-39.25V349.38A162.55,162.55,0,1,1,185,188.31V278.2a74.62,74.62,0,1,0,52.23,71.18V0l88,0a121.18,121.18,0,0,0,1.86,22.17h0A122.18,122.18,0,0,0,381,102.39a121.43,121.43,0,0,0,67,20.14Z" />
              </svg></a>
          </div>
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
            <li><span class="material-symbols-rounded" aria-hidden="true">location_on</span><a href="https://maps.app.goo.gl/r6f1sHhfa8mzxZXD9" target="_blank" rel="noopener">Our Store (Chiang Mai)</a></li>
            <li><span class="material-symbols-rounded" aria-hidden="true">call</span><a href="tel:0841511684">084-151-1684</a></li>
            <li><span class="material-symbols-rounded" aria-hidden="true">chat_bubble</span><a href="https://line.me/R/ti/p/@cmns" target="_blank" rel="noopener">LINE: @cmns</a></li>
            <li><span class="material-symbols-rounded" aria-hidden="true">mail</span><a href="mailto:info@cmnsfixmac.com">info@cmnsfixmac.com</a></li>
          </ul>
        </div>
      </div>
      <div class="footer-bottom">
        <p>© <?= date('Y') ?> CMNS FixMac. All rights reserved.</p>
      </div>
    </div>
  </footer>

</body>

</html>