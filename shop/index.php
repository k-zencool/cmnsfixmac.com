<?php
require_once __DIR__ . '/../includes/db.php';

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function getv($k, $d = null) { return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }
function build_url($patch) {
    $qs = array_merge($_GET, $patch);
    foreach ($qs as $k => $v) { if ($v === '' || $v === null) unset($qs[$k]); }
    if (isset($qs['page']) && (int)$qs['page'] === 1) unset($qs['page']);
    $q = http_build_query($qs);
    return '/shop/' . ($q ? '?' . $q : '');
}

$en_version_url = 'https://cmnsfixmac.com/en' . $_SERVER['REQUEST_URI'];
$en_version_url = str_replace('/index.php', '/', $en_version_url);

/* ---------- Filters ---------- */
$q        = getv('q', '');
$cat      = getv('cat', '');
$min      = getv('min', '') !== '' ? (float)getv('min') : null;
$max      = getv('max', '') !== '' ? (float)getv('max') : null;
$sort     = getv('sort', 'new');
$page     = max(1, (int)getv('page', 1));
$pp       = min(60, max(12, (int)getv('pp', 24)));
$off      = ($page - 1) * $pp;

// kept for URL backward-compat (filter-bar HTML uses them), no longer drives queries
$ram_min  = getv('ram_min', '')  !== '' ? (int)getv('ram_min')  : null;
$ssd_min  = getv('ssd_min', '')  !== '' ? (int)getv('ssd_min')  : null;
$year_min = getv('year_min', '') !== '' ? (int)getv('year_min') : null;
$year_max = getv('year_max', '') !== '' ? (int)getv('year_max') : null;
$color    = getv('color', '');

/* ---------- Base WHERE ---------- */
$where  = ["sl.status = 'published'", "inv.status != 'SOLD'"];
$params = [];
if ($q !== '') {
    $where[] = "(sl.title LIKE :q OR inv.name LIKE :q)";
    $params[':q'] = "%$q%";
}
if ($cat !== '') {
    $where[] = "sc.name = :cat";
    $params[':cat'] = $cat;
}
if ($min !== null) { $where[] = "sl.price >= :min"; $params[':min'] = $min; }
if ($max !== null) { $where[] = "sl.price <= :max"; $params[':max'] = $max; }
$WHERE_BASE = 'WHERE ' . implode(' AND ', $where);

/* ---------- ORDER ---------- */
$ORDER = 'ORDER BY sl.created_at DESC, sl.id DESC';
if ($sort === 'price_asc')  $ORDER = 'ORDER BY sl.price ASC, sl.id DESC';
if ($sort === 'price_desc') $ORDER = 'ORDER BY sl.price DESC, sl.id DESC';

/* ---------- Items ---------- */
$sqlItems = "
    SELECT SQL_CALC_FOUND_ROWS
        sl.id,
        COALESCE(sl.title, inv.name)        AS name,
        sc.name                              AS category,
        sl.price,
        sl.price_original                    AS price_old,
        COALESCE(sl.cover_image, inv.image)  AS main_image,
        1                                    AS in_stock,
        1                                    AS stock_qty,
        sl.created_at
    FROM shop_listings sl
    JOIN inventory inv ON inv.id = sl.inventory_id
    JOIN shop_categories sc ON sc.id = sl.category_id
    $WHERE_BASE
    $ORDER
    LIMIT :lim OFFSET :off
";
$st = $pdo->prepare($sqlItems);
foreach ($params as $k => $v) $st->bindValue($k, $v);
$st->bindValue(':lim', $pp, PDO::PARAM_INT);
$st->bindValue(':off', $off, PDO::PARAM_INT);
$st->execute();
$items = $st->fetchAll(PDO::FETCH_ASSOC);
$total = (int)$pdo->query("SELECT FOUND_ROWS()")->fetchColumn();

/* ---------- Facets (EAV removed — category counts in sidebar only) ---------- */
$facets = ['ram' => [], 'ssd' => [], 'year' => [], 'color' => []];

/* ---------- Sold Items ---------- */
$sqlSold = "
    SELECT sl.id,
           COALESCE(sl.title, inv.name)        AS name,
           sc.name                              AS category,
           sl.price,
           COALESCE(sl.cover_image, inv.image)  AS main_image
    FROM shop_listings sl
    JOIN inventory inv ON inv.id = sl.inventory_id
    JOIN shop_categories sc ON sc.id = sl.category_id
    WHERE sl.status = 'sold'
      AND COALESCE(sl.cover_image, inv.image) IS NOT NULL
    ORDER BY sl.updated_at DESC
    LIMIT 4
";
$soldItems = $pdo->query($sqlSold)->fetchAll(PDO::FETCH_ASSOC);

/* ---------- Active filter chips ---------- */
$activeFilters = [];
if ($q !== '')     $activeFilters[] = ['label' => "ค้นหา: \"".h($q)."\"",  'url' => build_url(['q'   => null, 'page' => 1])];
if ($cat !== '')   $activeFilters[] = ['label' => "หมวด: ".h($cat),         'url' => build_url(['cat' => null, 'page' => 1])];
if ($min !== null) $activeFilters[] = ['label' => "ราคา: ≥ ".h($min),       'url' => build_url(['min' => null, 'page' => 1])];
if ($max !== null) $activeFilters[] = ['label' => "ราคา: ≤ ".h($max),       'url' => build_url(['max' => null, 'page' => 1])];

ob_start();
?>
<section id="cmnsx-products" class="cmnsx-products" aria-label="สินค้าทั้งหมด">

  <?php if (!empty($activeFilters)): ?>
    <div class="cmnsx-active-filters" aria-label="ตัวกรองที่ใช้งาน">
      <span class="cmnsx-af-title">ตัวกรอง:</span>
      <ul class="cmnsx-af-list">
        <?php foreach ($activeFilters as $f): ?>
          <li class="cmnsx-af-pill">
            <a href="<?= h($f['url']) ?>#cmnsx-products" class="cmnsx-af-link" aria-label="ลบตัวกรอง <?= h($f['label']) ?>">
              <?= h($f['label']) ?> <span class="material-symbols-rounded" aria-hidden="true">cancel</span>
            </a>
          </li>
        <?php endforeach; ?>
        <li class="cmnsx-af-clear">
          <a href="/shop/#cmnsx-products" class="cmnsx-af-link clear-all">
            <span class="material-symbols-rounded" aria-hidden="true">delete_sweep</span> ล้างทั้งหมด
          </a>
        </li>
      </ul>
    </div>
  <?php endif; ?>

  <h2 class="cmnsx-title">สินค้าทั้งหมด (<?= number_format($total) ?>)</h2>
  <?php $fallback_icon_html = '<div class="cmnsx-thumb-icon"><span class="material-symbols-rounded" aria-hidden="true">image</span></div>'; ?>

  <?php if (empty($items)): ?>
    <p class="cmnsx-empty">ไม่พบสินค้า ลองล้างตัวกรอง</p>
  <?php else: ?>
    <ul class="cmnsx-grid">
      <?php
      foreach ($items as $row):
        $url   = '/shop/product-detail.php?id=' . (int)$row['id'];
        $img = trim((string)($row['main_image'] ?? ''));
        if ($img !== '' && substr($img, 0, 1) !== '/' && !preg_match('~^https?://~', $img)) {
          $img = '/' . ltrim($img, '/');
        }
        $name  = $row['name']; // title (ไทย)
        $cat   = $row['category'] ?: 'อื่นๆ'; // brand
        $price = (float)$row['price'];
        $old   = (float)($row['price_old'] ?? 0);
        $disc  = $old > $price ? ($old - $price) : 0;
        $pct   = $old > 0 ? round($disc / $old * 100) : 0;
        $qty   = (int)($row['stock_qty'] ?? 0);
        $in_stock = (int)($row['in_stock'] ?? 0);
        $low   = $in_stock === 1 && $qty > 0 && $qty <= 1;
      ?>
        <li class="cmnsx-card">
          <?php if ($disc > 0): ?>
            <div class="cmnsx-badge">ลด <?= number_format($disc, 0) ?> ฿<?= $pct ? " (-$pct%)" : "" ?></div>
          <?php endif; ?>

          <a href="<?= h($url) ?>" class="cmnsx-thumb">
            <?php if ($img === ''): ?>
              <?= $fallback_icon_html ?>
            <?php else: ?>
              <?php $onerror_js = "this.onerror=null; this.outerHTML='" . addslashes($fallback_icon_html) . "';"; ?>
              <img src="<?= h($img) ?>" alt="<?= h($name) ?>" class="cmnsx-img" loading="lazy" decoding="async" onerror="<?= h($onerror_js) ?>">
            <?php endif; ?>
          </a>
          <div class="cmnsx-info">
            <div class="cmnsx-cat"><?= h($cat) ?></div>
            <h3 class="cmnsx-name"><a href="<?= h($url) ?>" class="cmnsx-link"><?= h($name) ?></a></h3>
            <?php if ($low): ?>
              <div class="cmnsx-low">• สินค้าใกล้หมดแล้ว</div>
            <?php endif; ?>
            <div class="cmnsx-price">
              <span class="cmnsx-price-now">฿<?= number_format($price, 0) ?></span>
              <?php if ($disc > 0): ?>
                <span class="cmnsx-price-old">฿<?= number_format($old, 0) ?></span>
              <?php endif; ?>
              <button class="card-cart-btn cart-on-price" type="button" aria-label="ใส่ตะกร้า"
                data-id="<?= (int)$row['id'] ?>" data-name="<?= h($name) ?>" data-price="<?= (float)$price ?>" data-img="<?= h($img) ?>" data-url="<?= h($url) ?>">
                <span class="material-symbols-rounded" aria-hidden="true">add_shopping_cart</span>
              </button>
            </div>
            <?php $line_url = 'https://line.me/R/ti/p/@cmns'; ?>
            <a class="btn-line-full" href="<?= h($line_url) ?>" target="_blank" rel="noopener">
              <span class="material-symbols-rounded" aria-hidden="true">chat_bubble</span>
              สั่งผ่าน LINE
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
          $first = 1; $last = max(1, (int)$pages); $prev = max($first, $page - 1); $next = min($last, $page + 1);
          $mk = fn($p) => h(build_url(['page' => $p])) . '#cmnsx-products';
          ?>
          <li class="cmnsx-pager-item cmnsx-pager-nav <?= $page <= $first ? 'is-disabled' : '' ?>">
            <?= $page > $first ? '<a href="'.$mk($first).'" aria-label="หน้าแรก">«</a>' : '<span aria-disabled="true">«</span>' ?>
          </li>
          <li class="cmnsx-pager-item cmnsx-pager-nav <?= $page <= $first ? 'is-disabled' : '' ?>">
            <?= $page > $first ? '<a href="'.$mk($prev).'" aria-label="ก่อนหน้า">‹</a>' : '<span aria-disabled="true">‹</span>' ?>
          </li>
          <?php
          $links = []; $window = 2;
          if ($pages > 1) { $links[1] = 1; for ($i = max(2, $page - $window); $i <= min($pages - 1, $page + $window); $i++) { $links[$i] = $i; } $links[$pages] = $pages; }
          $withEllipsis = []; $lastLinkNum = 0;
          foreach ($links as $pageNum) { if ($lastLinkNum !== 0 && $pageNum > $lastLinkNum + 1) { $withEllipsis[] = '...'; } $withEllipsis[] = $pageNum; $lastLinkNum = $pageNum; }
          ?>
          <?php foreach ($withEllipsis as $p): ?>
            <li class="cmnsx-pager-item">
              <?php if ($p === '...'): ?><span class="cmnsx-pager-dots">...</span>
              <?php elseif ($p === $page): ?><span class="cmnsx-pager-current" aria-current="page"><?= $p ?></span>
              <?php else: ?><a href="<?= $mk($p) ?>" class="cmnsx-pager-link"><?= $p ?></a>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
          <li class="cmnsx-pager-item cmnsx-pager-nav <?= $page >= $last ? 'is-disabled' : '' ?>">
            <?= $page < $last ? '<a href="'.$mk($next).'" aria-label="ถัดไป">›</a>' : '<span aria-disabled="true">›</span>' ?>
          </li>
          <li class="cmnsx-pager-item cmnsx-pager-nav <?= $page >= $last ? 'is-disabled' : '' ?>">
            <?= $page < $last ? '<a href="'.$mk($last).'" aria-label="หน้าสุดท้าย">»</a>' : '<span aria-disabled="true">»</span>' ?>
          </li>
        </ul>
      </nav>
    <?php endif; ?>
  <?php endif; ?>

  <?php if (!empty($soldItems)): ?>
    <div class="cmnsx-sold-section" style="margin-top: 40px; border-top: 1px solid #eee; padding-top: 20px;">
      <h3 class="cmnsx-title" style="font-size: 1.2rem; color: #666; margin-bottom: 15px; display:flex; align-items:center; gap:8px;">
        <span class="material-symbols-rounded" aria-hidden="true">history</span> สินค้าที่จำหน่ายแล้ว (Sold Out)
      </h3>
      <ul class="cmnsx-grid">
        <?php foreach ($soldItems as $row):
          // ไม่ต้องมีลิงก์หรือถ้ามีก็กดไม่ได้
          $img = trim((string)($row['main_image'] ?? ''));
          if ($img !== '' && substr($img, 0, 1) !== '/' && !preg_match('~^https?://~', $img)) {
            $img = '/' . ltrim($img, '/');
          }
          $name  = $row['name'];
          $cat   = $row['category'] ?: 'อื่นๆ';
          $price = (float)$row['price'];
        ?>
          <li class="cmnsx-card is-sold" style="opacity: 0.7; filter: grayscale(100%); pointer-events: none; position: relative;">
            <div class="cmnsx-thumb">
              <?php if ($img === ''): ?>
                <?= $fallback_icon_html ?>
              <?php else: ?>
                <img src="<?= h($img) ?>" alt="<?= h($name) ?>" class="cmnsx-img" loading="lazy">
              <?php endif; ?>
              <div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); 
                          background:rgba(0,0,0,0.65); color:#fff; padding:6px 16px; border-radius:4px; 
                          font-weight:700; font-size:16px; border:1px solid #fff; z-index:2; text-transform: uppercase;">
                Sold Out
              </div>
            </div>
            <div class="cmnsx-info">
              <div class="cmnsx-cat"><?= h($cat) ?></div>
              <h3 class="cmnsx-name" style="color:#555;"><?= h($name) ?></h3>
              <div class="cmnsx-price">
                <span class="cmnsx-price-now" style="color:#888;">฿<?= number_format($price, 0) ?></span>
              </div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
  </section>
<?php
$__products_html = ob_get_clean();

/* ---------- AJAX short-circuit ---------- */
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
  header('Content-Type: text/html; charset=UTF-8');
  echo $__products_html;
  exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CMNS FixMac | ร้านขาย Apple มือสอง เชียงใหม่ – MacBook, iPhone, iPad ,มือสองคุณภาพดี พร้อมรับประกัน</title>
  <meta name="description" content="ร้านขาย MacBook มือสอง, iPhone มือสอง, iPad มือสอง, Apple Watch มือสอง สภาพดี พร้อมรับประกันหลังการขาย จัดส่งทั่วไทย มีหน้าร้านที่เชียงใหม่ โดย CMNS FixMac">
  <?php $current_th_url = 'https://cmnsfixmac.com' . str_replace('/index.php', '/', $_SERVER['REQUEST_URI']); ?>
  <link rel="canonical" href="<?= h($current_th_url) ?>" />
  <link rel="alternate" hreflang="th" href="<?= h($current_th_url) ?>" />
  <link rel="alternate" hreflang="en" href="<?= h($en_version_url) ?>" />
  <link rel="alternate" hreflang="x-default" href="<?= h($current_th_url) ?>" />
  <link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png" />
  <script src="/assets/js/theme.js"></script>
  <link rel="stylesheet" href="/assets/css/design-tokens.css?v=1">
  <link rel="stylesheet" href="/assets/css/shop/shop-style.css">
  <link rel="stylesheet" href="/assets/css/shop/hero.css">
  <link rel="stylesheet" href="/assets/css/shop/cart-receipt.css">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />

  <style>
    .cmnsx-active-filters { display: flex; align-items: flex-start; flex-wrap: wrap; gap: 8px; margin-bottom: 20px; padding: 12px; background: #f9f9f9; border-radius: 8px; }
    .cmnsx-af-title { font-weight: 500; font-size: 15px; margin-right: 8px; line-height: 28px; }
    .cmnsx-af-list { display: flex; flex-wrap: wrap; gap: 8px; list-style: none; margin: 0; padding: 0; }
    .cmnsx-af-pill, .cmnsx-af-clear { margin: 0; }
    .cmnsx-af-link {
      display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; font-size: 14px;
      color: #333; background: #fff; border: 1px solid #ddd; border-radius: 16px; text-decoration: none;
      transition: all 0.2s ease;
    }
    .cmnsx-af-link .material-symbols-rounded { font-size: 16px; }
    .cmnsx-af-link:hover { background: #f0f0f0; border-color: #bbb; }
    .cmnsx-af-link.clear-all { color: #d9534f; border-color: #d9534f; background: #fff; }
    .cmnsx-af-link.clear-all:hover { background: #fdf2f2; }
    .cmnsx-af-link.clear-all .material-symbols-rounded { font-size: 18px; }
  </style>
  </head>
<body>

  <header class="navbar navbar-top">
    <div class="nav-container">
      <div class="nav-logo">
        <a href="/shop/"><img src="/assets/img/Logo1.png" alt="CMNS FixMac Logo"></a>
      </div>
      <div class="menu-desktop-only">
        <nav class="menu">
          <a href="/" class="highlight-home"><span class="material-symbols-rounded">home</span> หน้าแรก</a>
          <a href="/shop/" class="active"><span class="material-symbols-rounded">storefront</span> ร้านค้า</a>
          <a href="/works/"><span class="material-symbols-rounded">construction</span> ผลงาน</a>
          <a href="/articles/"><span class="material-symbols-rounded">description</span> บทความ</a>
          <a href="/buyback/"><span class="material-symbols-rounded">laptop_mac</span> รับซื้อเครื่อง</a>
          <a href="/warranty/"><span class="material-symbols-rounded">verified</span> ตรวจสอบประกัน</a>
        </nav>
      </div>
      <div class="nav-actions">
        <form class="nav-search search-form" action="/shop/" method="get" role="search">
          <input type="text" name="q" value="<?= h($q) ?>" placeholder="ค้นหาสินค้า...">
          <button type="submit" aria-label="ค้นหา"><span class="material-symbols-rounded">search</span></button>
        </form>
        <a href="#!" class="nav-cart" aria-label="ตะกร้า"><span class="material-symbols-rounded">shopping_cart</span><span class="cart-count">0</span></a>
        <a href="<?= h($en_version_url) ?>" class="language-switch-btn" title="Switch to English"><span class="material-symbols-rounded">language</span> EN</a>
        <button id="hamburger" class="hamburger" type="button" onclick="toggleSidebar()" aria-label="เมนู"><span></span><span></span><span></span></button>
      </div>
      <div id="sidebar" class="sidebar">
        <div class="sidebar-header">
          <a href="/"><img src="/assets/img/Logo1.png" alt="CMNS FixMac Logo" style="height:36px; margin-bottom:16px;"></a>
          <span class="close-btn" onclick="toggleSidebar()">✕</span>
        </div>
        <nav class="sidebar-menu">
          <a href="/" class="highlight-home"><span class="material-symbols-rounded">home</span> หน้าแรก</a>
          <a href="/works/"><span class="material-symbols-rounded">construction</span> ผลงาน</a>
          <a href="/shop/" class="active"><span class="material-symbols-rounded">storefront</span> ร้านค้า</a>
          <a href="/articles/"><span class="material-symbols-rounded">description</span> บทความ</a>
          <a href="/buyback/"><span class="material-symbols-rounded">laptop_mac</span> รับซื้อเครื่อง</a>
          <a href="/warranty/"><span class="material-symbols-rounded">verified</span> ตรวจสอบประกัน</a>
          <a href="tel:0841511684"><span class="material-symbols-rounded">call</span> โทรเลย</a>
          <a href="<?= h($en_version_url) ?>" class="language-switch-btn" title="Switch to English"><span class="material-symbols-rounded">language</span> EN</a>
        </nav>
        <div class="sidebar-dropdown">
          <button class="dropdown-toggle" onclick="toggleSidebarDropdown(this)"><span class="material-symbols-rounded">smart_toy</span> ทดสอบอุปกรณ์<span class="material-symbols-rounded dropdown-icon">expand_more</span></button>
          <div class="dropdown-submenu">
            <a href="/tester/monitor-tester/"><span class="material-symbols-rounded">monitor</span> หน้าจอ</a>
            <a href="/tester/keyboard-tester/"><span class="material-symbols-rounded">keyboard</span> คีย์บอร์ด</a>
            <a href="/tester/microphone-tester/"><span class="material-symbols-rounded">mic</span> ไมค์</a>
            <a href="/tester/camera-tester/"><span class="material-symbols-rounded">photo_camera</span> กล้อง</a>
            <a href="/tester/sounds-tester/"><span class="material-symbols-rounded">volume_up</span> ลำโพง</a>
            <a href="/tester/touchscreen-tester/"><span class="material-symbols-rounded">touch_app</span> ตรวจทัชสกรีน</a>
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
            <h2>MacBook Pro มือสอง</h2><p>สภาพสวย ประกันครบ ราคาพิเศษ</p>
            <a href="/shop/?cat=MacBook#cmnsx-products" class="btn-hero">ช้อปเลย</a>
          </div>
        </article>
        <article class="hero-slide">
          <img src="https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?q=80&w=2000&auto=format&fit=crop" alt="iPhone">
          <div class="hero-caption">
            <h2>iPhone ราคาดี</h2><p>รับประกันใช้งานได้จริง คุ้มค่า</p>
            <a href="/shop/?cat=iPhone#cmnsx-products" class="btn-hero">ดู iPhone</a>
          </div>
        </article>
        <article class="hero-slide">
          <img src="https://images.unsplash.com/photo-1542751110-97427bbecf20?q=80&w=2000&auto=format&fit=crop" alt="iPad">
          <div class="hero-caption">
            <h2>iPad พร้อมใช้งาน</h2><p>เหมาะกับการเรียนและทำงาน</p>
            <a href="/shop/?cat=iPad#cmnsx-products" class="btn-hero">ดู iPad</a>
          </div>
        </article>
      </div>
      <button class="hero-nav prev" aria-label="ก่อนหน้า">‹</button>
      <button class="hero-nav next" aria-label="ถัดไป">›</button>
      <div class="hero-dots" aria-label="Slide indicators"></div>
    </div>
    <aside class="hero-right">
      <div class="promo-card">
        <img src="https://images.unsplash.com/photo-1542751110-97427bbecf20?q=80&w=2000&auto=format&fit=crop" alt="โปรโมชั่น">
        <div class="promo-caption">
          <h3>โปรโมชั่นพิเศษ</h3><p>ลดสูงสุด 30% สำหรับ MacBook</p>
          <a href="/shop/?promo=mb#cmnsx-products" class="btn-hero btn-ghost">ดูโปร</a>
        </div>
      </div>
    </aside>
  </section>

  <section class="seo-hero">
    <div class="seo-inner">
      <h1>ร้านค้า Apple มือสอง เชียงใหม่ – MacBook, iPhone, iPad คุณภาพดี</h1>
      <p>บริการซื้อ–ขาย <strong>MacBook มือสอง</strong>, <strong>iPhone มือสอง</strong>, <strong>iPad มือสอง</strong> และอุปกรณ์ Apple แท้ ราคาพิเศษ พร้อมรับประกันหลังการขาย มีหน้าร้านเชียงใหม่ และจัดส่งทั่วประเทศ</p>
    </div>
  </section>

  <section class="cat-gallery" aria-label="เลือกตามหมวด">
    <h2 class="sec-title">เลือกตามหมวด</h2>
    <ul class="cat-grid">
      <li class="cat-item"><a href="/shop/?cat=MacBook#cmnsx-products" class="cat-link"><div class="cat-media"><img src="/shop/assets/img/cats/macbook.png" alt="MacBook" loading="lazy" decoding="async"></div><div class="cat-label">MacBook</div></a></li>
      <li class="cat-item"><a href="/shop/?cat=iMac#cmnsx-products" class="cat-link"><div class="cat-media"><img src="/shop/assets/img/cats/imac.png" alt="iMac" loading="lazy" decoding="async"></div><div class="cat-label">iMac</div></a></li>
      <li class="cat-item"><a href="/shop/?cat=iPhone#cmnsx-products" class="cat-link"><div class="cat-media"><img src="/shop/assets/img/cats/iphone.png" alt="iPhone" loading="lazy" decoding="async"></div><div class="cat-label">iPhone</div></a></li>
      <li class="cat-item"><a href="/shop/?cat=iPad#cmnsx-products" class="cat-link"><div class="cat-media"><img src="/shop/assets/img/cats/ipad.png" alt="iPad" loading="lazy" decoding="async"></div><div class="cat-label">iPad</div></a></li>
      <li class="cat-item"><a href="/shop/?cat=Watch#cmnsx-products" class="cat-link"><div class="cat-media"><img src="/shop/assets/img/cats/watch.png" alt="Apple Watch" loading="lazy" decoding="async"></div><div class="cat-label">Apple Watch</div></a></li>
      <li class="cat-item"><a href="/shop/?cat=AirPods#cmnsx-products" class="cat-link"><div class="cat-media"><img src="/shop/assets/img/cats/airpods.png" alt="AirPods" loading="lazy" decoding="async"></div><div class="cat-label">AirPods</div></a></li>
      <li class="cat-item"><a href="/shop/?cat=Accessories#cmnsx-products" class="cat-link"><div class="cat-media"><img src="/shop/assets/img/cats/accessories.png" alt="Accessories" loading="lazy" decoding="async"></div><div class="cat-label">Accessories</div></a></li>
    </ul>
  </section>

  <?php
  $labelRam   = $ram_min  !== null ? "RAM: ≥ {$ram_min}GB"  : "RAM";
  $labelSsd   = $ssd_min  !== null ? "SSD: ≥ {$ssd_min}GB"  : "SSD";
  $labelYear  = "ปี";
  if ($year_min !== null && $year_max !== null) {
    $labelYear = ($year_min === $year_max) ? "ปี: {$year_min}" : "ปี: {$year_min}-{$year_max}";
  } elseif ($year_min !== null) {
    $labelYear = "ปี: ≥ {$year_min}";
  } elseif ($year_max !== null) {
    $labelYear = "ปี: ≤ {$year_max}";
  }
  $labelColor = $color    !== ''   ? "สี: {$color}"         : "สี";
  ?>
  <section class="filter-bar" aria-label="ฟิลเตอร์สินค้าแบบดรอปดาว">
    <div class="fb-row">
      <div class="fb-dd"><button type="button" class="fb-chip<?= $ram_min !== null ? ' is-active' : '' ?>" aria-haspopup="true" aria-expanded="false"><span class="material-symbols-rounded" aria-hidden="true">memory</span><?= h($labelRam) ?><span class="material-symbols-rounded fb-caret" aria-hidden="true">expand_more</span></button><div class="fb-menu" role="menu"><a role="menuitem" class="fb-item<?= $ram_min === null ? ' is-selected' : '' ?>" href="<?= h(build_url(['ram_min' => null, 'page' => 1])) ?>#cmnsx-products">ทั้งหมด</a><?php foreach ($facets['ram'] as $r): $val = (int)$r['val']; $href = h(build_url(['ram_min' => $val, 'page' => 1])) . '#cmnsx-products'; $sel = ($ram_min !== null && (int)$ram_min === $val) ? ' is-selected' : ''; ?><a role="menuitem" class="fb-item<?= $sel ?>" href="<?= $href ?>">≥ <?= $val ?> GB <span class="fb-count">(<?= (int)$r['c'] ?>)</span></a><?php endforeach; ?></div></div>
      <div class="fb-dd"><button type="button" class="fb-chip<?= $ssd_min !== null ? ' is-active' : '' ?>" aria-haspopup="true" aria-expanded="false"><span class="material-symbols-rounded" aria-hidden="true">hard_drive</span><?= h($labelSsd) ?><span class="material-symbols-rounded fb-caret" aria-hidden="true">expand_more</span></button><div class="fb-menu" role="menu"><a role="menuitem" class="fb-item<?= $ssd_min === null ? ' is-selected' : '' ?>" href="<?= h(build_url(['ssd_min' => null, 'page' => 1])) ?>#cmnsx-products">ทั้งหมด</a><?php foreach ($facets['ssd'] as $r): $val = (int)$r['val']; $href = h(build_url(['ssd_min' => $val, 'page' => 1])) . '#cmnsx-products'; $sel = ($ssd_min !== null && (int)$ssd_min === $val) ? ' is-selected' : ''; ?><a role="menuitem" class="fb-item<?= $sel ?>" href="<?= $href ?>">≥ <?= $val ?> GB <span class="fb-count">(<?= (int)$r['c'] ?>)</span></a><?php endforeach; ?></div></div>
      <div class="fb-dd"><button type="button" class="fb-chip<?= ($year_min !== null || $year_max !== null) ? ' is-active' : '' ?>" aria-haspopup="true" aria-expanded="false"><span class="material-symbols-rounded" aria-hidden="true">calendar_month</span><?= h($labelYear) ?><span class="material-symbols-rounded fb-caret" aria-hidden="true">expand_more</span></button><div class="fb-menu" role="menu"><a role="menuitem" class="fb-item<?= ($year_min === null && $year_max === null) ? ' is-selected' : '' ?>" href="<?= h(build_url(['year_min' => null, 'year_max' => null, 'page' => 1])) ?>#cmnsx-products">ทั้งหมด</a><?php foreach ($facets['year'] as $r): $val = (int)$r['val']; $href = h(build_url(['year_min' => $val, 'year_max' => $val, 'page' => 1])) . '#cmnsx-products'; $sel = ($year_min !== null && $year_max !== null && (int)$year_min === $val && (int)$year_max === $val) ? ' is-selected' : ''; ?><a role="menuitem" class="fb-item<?= $sel ?>" href="<?= $href ?>"><?= $val ?> <span class="fb-count">(<?= (int)$r['c'] ?>)</span></a><?php endforeach; ?><?php if (count($facets['year']) > 0 && $facets['year'][0]['val'] >= 2020): ?><a role="menuitem" class="fb-item<?= ($year_min === 2020 && $year_max === null) ? ' is-selected' : '' ?>" href="<?= h(build_url(['year_min' => 2020, 'year_max' => null, 'page' => 1])) ?>#cmnsx-products">2020 ขึ้นไป</a><?php endif; ?></div></div>
      <div class="fb-dd"><button type="button" class="fb-chip<?= $color !== '' ? ' is-active' : '' ?>" aria-haspopup="true" aria-expanded="false"><span class="material-symbols-rounded" aria-hidden="true">palette</span><?= h($labelColor) ?><span class="material-symbols-rounded fb-caret" aria-hidden="true">expand_more</span></button><div class="fb-menu" role="menu"><a role="menuitem" class="fb-item<?= $color === '' ? ' is-selected' : '' ?>" href="<?= h(build_url(['color' => null, 'page' => 1])) ?>#cmnsx-products">ทั้งหมด</a><?php foreach ($facets['color'] as $r): if (!$r['val']) continue; $val = (string)$r['val']; $href = h(build_url(['color' => $val, 'page' => 1])) . '#cmnsx-products'; $sel = ($color !== '' && $color === $val) ? ' is-selected' : ''; ?><a role="menuitem" class="fb-item<?= $sel ?>" href="<?= $href ?>"><?= h($val) ?> <span class="fb-count">(<?= (int)$r['c'] ?>)</span></a><?php endforeach; ?></div></div>
      <div class="fb-spacer"></div>
      <a class="fb-clear" href="/shop/#cmnsx-products" role="button"><span class="material-symbols-rounded" aria-hidden="true">filter_alt_off</span> ล้างทั้งหมด</a>
    </div>
  </section>

  <?= $__products_html ?>

  <script src="/shop/assets/js/hero.js" defer></script>

  <script>
    // ===== Global escapeHtml (ใช้ร่วมกันทุกที่) =====
    // [FIXED] อุดรูรั่ว XSS
// ===== Global escapeHtml (แก้ไขแล้ว) =====
    function escapeHtml(s) {
      return String(s || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }
    function toggleSidebar() {
      document.getElementById('sidebar').classList.toggle('open');
      document.getElementById('sidebar-overlay').classList.toggle('show');
      document.getElementById('hamburger').classList.toggle('open');
    }
    function toggleSidebarDropdown(btn) { btn.closest('.sidebar-dropdown').classList.toggle('open'); }
    (function handleNavbarScroll() {
      const nav = document.querySelector('.navbar');
      function onScroll() {
        if (window.scrollY > 30) nav.classList.add('scrolled'); else nav.classList.remove('scrolled');
      }
      window.addEventListener('scroll', onScroll); onScroll();
    })();

    // ===== Smooth partial reload (กึ่ง-AJAX) =====
    (function() {
      var root = document.getElementById('cmnsx-products'); if (!root) return;
      function loadProducts(url, push) {
        var u = new URL(url, location.origin); u.hash = ''; u.searchParams.set('ajax', '1');
        root.style.opacity = '0.4';
        fetch(u, { headers: { 'X-Requested-With': 'fetch' } })
          .then(r => r.text())
          .then(function(html) {
            root.outerHTML = html; var newRoot = document.getElementById('cmnsx-products');
            if (newRoot) { root = newRoot; root.style.opacity = '1'; if (push) history.pushState(null, '', url); root.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
          })
          .catch(function() { root.style.opacity = '1'; location.href = url; });
      }
      document.addEventListener('click', function(e) { var a = e.target.closest('.cmnsx-pager a'); if (!a) return; e.preventDefault(); loadProducts(a.href, true); });
      // [ADDED] เพิ่ม listener สำหรับ .cmnsx-af-link
      document.addEventListener('click', function(e) { var a = e.target.closest('.filter-bar a, .cmnsx-af-link'); if (!a) return; document.querySelectorAll('.fb-dd.is-open').forEach(dd => dd.classList.remove('is-open')); e.preventDefault(); loadProducts(a.href, true); });
      document.addEventListener('click', function(e) {
        var a = e.target.closest('a[href^="/shop/"]'); if (!a) return;
        if (!/#cmnsx-products$/.test(a.getAttribute('href'))) return;
        e.preventDefault(); loadProducts(a.href, true);
      });
      document.querySelectorAll('form.search-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
          e.preventDefault();
          var params = new URLSearchParams(new FormData(form));
          var url = (form.getAttribute('action') || '/shop/') + '?' + params.toString() + '#cmnsx-products';
          loadProducts(url, true);
        });
      });
      window.addEventListener('popstate', function() { loadProducts(location.href, false); });
    })();

    // ===== Floating dropdown (fixed placement) =====
    (function() {
      const row = document.querySelector('.fb-row');
      function placeMenu(dd) {
        const btn = dd.querySelector('.fb-chip'); const menu = dd.querySelector('.fb-menu'); if (!btn || !menu) return;
        const r = btn.getBoundingClientRect(); const vw = window.innerWidth; const vh = window.innerHeight; const gap = 8;
        menu.style.position = 'fixed';
        menu.style.minWidth = Math.max(r.width, 200) + 'px';
        menu.style.visibility = 'hidden'; menu.style.display = 'block';
        const mw = menu.offsetWidth; menu.style.display = ''; menu.style.visibility = '';
        let left = r.left; if (left + mw > vw - 12) left = Math.max(12, vw - mw - 12); if (left < 12) left = 12;
        const top = Math.min(vh - 12, r.bottom + gap); const maxH = Math.max(160, vh - top - 12);
        menu.style.left = left + 'px'; menu.style.top = top + 'px'; menu.style.maxHeight = maxH + 'px'; menu.style.overflow = 'auto'; menu.style.zIndex = 1000;
        if (row) { row.style.overflowY = 'visible'; row.style.overflowX = 'auto'; }
      }
      function openDD(dd) {
        document.querySelectorAll('.fb-dd.is-open').forEach(el => { if (el !== dd) el.classList.remove('is-open'); });
        dd.classList.add('is-open'); const btn = dd.querySelector('.fb-chip'); if (btn) btn.setAttribute('aria-expanded', 'true'); placeMenu(dd);
      }
      function closeAll() {
        document.querySelectorAll('.fb-dd.is-open').forEach(dd => {
          dd.classList.remove('is-open'); const b = dd.querySelector('.fb-chip'); if (b) b.setAttribute('aria-expanded', 'false');
        });
        if (row) { row.style.overflowY = 'visible'; row.style.overflowX = 'auto'; }
      }
      document.addEventListener('click', function(e) {
        const chip = e.target.closest('.fb-chip'); const inMenu = e.target.closest('.fb-menu');
        if (chip) { e.preventDefault(); const dd = chip.closest('.fb-dd'); dd.classList.contains('is-open') ? closeAll() : openDD(dd); return; }
        if (inMenu) return; closeAll();
      });
      document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAll(); });
      window.addEventListener('scroll', closeAll, { passive: true });
      window.addEventListener('resize', function() { const dd = document.querySelector('.fb-dd.is-open'); if (dd) placeMenu(dd); });
      document.addEventListener('click', function(e) { const a = e.target.closest('.filter-bar .fb-menu a'); if (!a) return; closeAll(); });
    })();
  </script>

  <div id="mini-cart-overlay" style="display:none;"></div>
  <aside id="mini-cart" aria-label="ตะกร้าสินค้า" style="display:none;" tabindex="-1">
    <header class="mc-head"><h3>ตะกร้าสินค้า</h3><button type="button" class="mc-close" aria-label="ปิดตะกร้า"><span class="material-symbols-rounded">close</span></button></header>
    <div class="mc-body"><ul class="mc-list" id="mc-list"></ul><div class="mc-empty" id="mc-empty">ยังไม่มีสินค้าในตะกร้า</div></div>
    <footer class="mc-foot"><div class="mc-sum"><span>รวมทั้งหมด</span><strong id="mc-total">฿0</strong></div><div class="mc-actions"><button type="button" id="mc-clear" class="mc-btn ghost"><span class="material-symbols-rounded">delete</span> ล้างตะกร้า</button><a href="#!" id="mc-checkout" class="mc-btn primary"><span class="material-symbols-rounded">receipt_long</span> สรุปรายการ</a></div></footer>
  </aside>
  <div id="receipt-backdrop" aria-hidden="true" style="display:none;"></div>
  <aside id="receipt-modal" role="dialog" aria-modal="true" aria-labelledby="receipt-title" style="display:none;">
    <header class="rcp-head"><h3 id="receipt-title">สรุปรายการ</h3><button type="button" class="rcp-close" aria-label="ปิดป็อปอัพ"><span class="material-symbols-rounded">close</span></button></header>
    <div class="rcp-body">
      <ul id="rcp-list" class="rcp-list"></ul>
      <div class="rcp-sum">
        <div class="row"><span>ยอดรวมสินค้า</span><strong id="rcp-subtotal">฿0</strong></div>
        <div class="row"><span>ค่าจัดส่ง</span><strong id="rcp-ship">ฟรี</strong></div>
        <div class="split"></div>
        <div class="row grand"><span>ยอดสุทธิ</span><strong id="rcp-total">฿0</strong></div>
      </div>
      <div class="rcp-qr">
        <div class="qr-box"><img src="/shop/assets/img/line.png" alt="LINE QR @cmnsfixmac" loading="lazy" decoding="async"></div>
        <div class="qr-text">
          <h4>ชำระเงิน & ติดต่อ</h4>
          <p>สแกน QR เพื่อแอด <b>LINE Official @cmnsfixmac</b> แล้วส่งสลิป/สอบถามสต็อก หรือนัดรับได้ทันที</p>
          <a class="rcp-btn line small" href="https://line.me/R/ti/p/@cmns" target="_blank" rel="noopener"><span class="material-symbols-rounded">chat_bubble</span> เปิด LINE</a>
        </div>
      </div>
    </div>
    <footer class="rcp-foot">
      <button type="button" id="rcp-copy" class="rcp-btn ghost"><span class="material-symbols-rounded">content_copy</span> คัดลอกรายการ</button>
      <a id="rcp-line" class="rcp-btn line" href="#" target="_blank" rel="noopener"><span class="material-symbols-rounded">send</span> ส่งรายละเอียดไป LINE</a>
      <button type="button" id="rcp-print" class="rcp-btn"><span class="material-symbols-rounded">print</span> พิมพ์ / บันทึกเป็น PDF</button>
    </footer>
  </aside>

  <script>
    (function() {
      // ===== Cart (localStorage) + Smooth drawer + animations =====
      const $ = (s, ctx = document) => ctx.querySelector(s);
      const LS_KEY = 'cmnsx_cart';
      const fmt = n => '฿' + (Number(n) || 0).toLocaleString('th-TH',{maximumFractionDigits:0});

      function loadCart(){ try{ return JSON.parse(localStorage.getItem(LS_KEY)) || [] }catch(e){ return [] } }
      function saveCart(cart){ localStorage.setItem(LS_KEY, JSON.stringify(cart)); }
      function cartCount(){ return loadCart().reduce((s, it) => s + (Number(it.qty) || 1), 0); }
      function cartTotal(){ return loadCart().reduce((s, it) => s + (Number(it.price) || 0) * (Number(it.qty) || 1), 0); }

      function upsertItem({id,name,price,img,url}, qty=1){
        const cart=loadCart();
        const i=cart.findIndex(it=>String(it.id)===String(id));
        if(i>-1)cart[i].qty=Math.min(99,(Number(cart[i].qty)||1)+qty);
        else cart.push({id,name,price:Number(price)||0,img,url,qty:Math.max(1,qty)});
        saveCart(cart); render();
      }
      function setQty(id,qty){
        const cart=loadCart();
        const i=cart.findIndex(it=>String(it.id)===String(id));
        if(i>-1){cart[i].qty=Math.max(1,Math.min(99,Number(qty)||1)); saveCart(cart); render();}
      }
      function removeItem(id){ saveCart(loadCart().filter(it=>String(it.id)!==String(id))); render(); }
      function clearCart(){ saveCart([]); render(); }

      const overlay=$('#mini-cart-overlay'),drawer=$('#mini-cart'),listEl=$('#mc-list'),emptyEl=$('#mc-empty'),totalEl=$('#mc-total'),cartBadge=$('.nav-cart .cart-count'),navCart=$('.nav-cart');

      window.openCart=function(){ overlay.style.display='block'; drawer.style.display='flex'; document.body.classList.add('mc-open'); drawer.focus(); };
      window.closeCart=function(){ document.body.classList.remove('mc-open'); setTimeout(()=>{ overlay.style.display='none'; drawer.style.display='none'; },320); };

      if (navCart) {
        navCart.addEventListener('click', e => { e.preventDefault(); openCart(); });
        navCart.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openCart(); } });
      }
      $('.mc-close').addEventListener('click', closeCart);
      overlay.addEventListener('click', closeCart);
      document.addEventListener('keydown', e => { if (e.key === 'Escape') closeCart(); });

      function render() {
        const cart = loadCart();
        if (cartBadge) cartBadge.textContent = String(cartCount());

        emptyEl.style.display = cart.length ? 'none' : 'block';
        listEl.innerHTML = '';
        cart.forEach(it => {
          const li = document.createElement('li');
          li.className = 'mc-item';
          const safeName = escapeHtml(it.name || 'สินค้า'); // [FIXED] ใช้ escapeHtml ที่แก้แล้ว
          li.innerHTML = `
        <img class="mc-thumb" src="${it.img || '/assets/img/placeholder.jpg'}" alt="" onerror="this.onerror=null;this.src='/assets/img/placeholder.jpg';">
        <div>
          <div class="mc-title"><a href="${it.url||'#'}">${safeName}</a></div>
          <div class="mc-meta">
            <div class="mc-qty" data-id="${it.id}">
              <button type="button" class="mc-minus" aria-label="ลดจำนวน"><span class="material-symbols-rounded">remove</span></button>
              <input type="text" class="mc-input" value="${Number(it.qty)||1}" inputmode="numeric" pattern="[0-9]*" />
              <button type="button" class="mc-plus" aria-label="เพิ่มจำนวน"><span class="material-symbols-rounded">add</span></button>
            </div>
            <div class="mc-price">${fmt(it.price)}</div>
          </div>
        </div>
        <button type="button" class="mc-remove" data-id="${it.id}" aria-label="ลบ"><span class="material-symbols-rounded">delete</span></button>
      `;
          listEl.appendChild(li);
        });
        totalEl.textContent = fmt(cartTotal());
      }

      // ===== Animations: fly to cart + toast + badge bump =====
      const cartIcon = document.querySelector('.nav-cart');

      function badgeBump() {
        const b = document.querySelector('.nav-cart .cart-count');
        if (!b) return;
        b.classList.remove('pop'); void b.offsetWidth; b.classList.add('pop');
      }

      function showToast(msg) {
        const t = document.createElement('div');
        t.className = 'toast';
        t.textContent = msg || 'เพิ่มลงตะกร้าแล้ว';
        document.body.appendChild(t);
        requestAnimationFrame(() => t.classList.add('show'));
        setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 250); }, 1600);
      }

      function flyToCart(fromEl) {
        if (!cartIcon) return;
        const card = fromEl.closest('.cmnsx-card');
        const img = card?.querySelector('.cmnsx-thumb img, .cmnsx-thumb .cmnsx-thumb-icon');
        if (!img) return;
        const rectFrom = img.getBoundingClientRect();
        const rectTo = cartIcon.getBoundingClientRect();

        // icon div fallback
        if (!img.getAttribute('src')) {
          const ghost = document.createElement('div');
          ghost.className = 'fly-img';
          ghost.innerHTML = '<span class="material-symbols-rounded">image</span>';
          ghost.style.left = (rectFrom.left + rectFrom.width / 2 - 32) + 'px';
          ghost.style.top  = (rectFrom.top  + rectFrom.height / 2 - 32) + 'px';
          ghost.style.display = 'grid'; ghost.style.placeItems = 'center';
          document.body.appendChild(ghost);
          const dx = (rectTo.left + rectTo.width / 2) - (rectFrom.left + rectFrom.width / 2);
          const dy = (rectTo.top  + rectTo.height/ 2) - (rectFrom.top  + rectFrom.height/ 2);
          requestAnimationFrame(() => { ghost.style.transform = `translate(${dx}px, ${dy}px) scale(.35)`; ghost.style.opacity = '0.2'; });
          setTimeout(() => ghost.remove(), 650);
          return;
        }

        const ghost = document.createElement('img');
        ghost.className = 'fly-img';
        ghost.src = img.getAttribute('src');
        ghost.style.left = (rectFrom.left + rectFrom.width / 2 - 32) + 'px';
        ghost.style.top  = (rectFrom.top  + rectFrom.height / 2 - 32) + 'px';
        document.body.appendChild(ghost);
        const dx = (rectTo.left + rectTo.width / 2) - (rectFrom.left + rectFrom.width / 2);
        const dy = (rectTo.top  + rectTo.height/ 2) - (rectFrom.top  + rectFrom.height/ 2);
        requestAnimationFrame(() => { ghost.style.transform = `translate(${dx}px, ${dy}px) scale(.35)`; ghost.style.opacity = '0.2'; });
        setTimeout(() => ghost.remove(), 650);
      }

      // Add-to-cart (รวม animation)
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
          url: btn.dataset.url || card?.querySelector('.cmnsx-link')?.getAttribute('href'),
        };
        payload.price = Number(payload.price) || 0;
        upsertItem(payload, 1);

        try { navigator.vibrate && navigator.vibrate(30); } catch (e) {}
        btn.classList.add('added');
        setTimeout(() => { btn.classList.remove('added'); btn.disabled = false; }, 320);

        flyToCart(btn);
        badgeBump();
        showToast('เพิ่มลงตะกร้าแล้ว');
      });

      // Qty +/- and remove
      document.getElementById('mc-list').addEventListener('click', function(e) {
        const minus = e.target.closest('.mc-minus');
        const plus = e.target.closest('.mc-plus');
        const remove = e.target.closest('.mc-remove');

        if (minus || plus) {
          const wrap = e.target.closest('.mc-qty');
          const id = wrap?.dataset.id;
          const inp = wrap?.querySelector('.mc-input');
          if (!id || !inp) return;
          let q = Number(inp.value) || 1;
          q = minus ? Math.max(1, q - 1) : Math.min(99, q + 1);
          setQty(id, q);
          return;
        }
        if (remove) {
          const id = remove.dataset.id;
          if (id) removeItem(id);
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

      // ====== Receipt Popup ======
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

      function showReceipt() { bd.style.display = 'block'; md.style.display = 'grid'; }
      function hideReceipt() { bd.style.display = 'none'; md.style.display = 'none'; }

      function buildSummaryText(cart, total) {
        const rows = cart.map(it => `• ${it.name || 'สินค้า'} x ${it.qty} = ${fmt((Number(it.price)||0)*(Number(it.qty)||1))}`);
        rows.push(`\nรวมทั้งหมดยอดสุทธิ: ${fmt(total)}`);
        rows.push(`\nติดต่อ: LINE @cmnsfixmac`);
        return rows.join('\n');
      }

      function openReceipt() {
        const cart = loadCart();
        if (!cart.length) { alert('ตะกร้่าว่าง'); return; }

        rList.innerHTML = '';
        cart.forEach(it => {
          const li = document.createElement('li');
          li.className = 'rcp-item';
          const safeName = escapeHtml(it.name || 'สินค้า'); // [FIXED] ใช้ escapeHtml ที่แก้แล้ว
          li.innerHTML = `
        <img class="rcp-thumb" src="${it.img || '/assets/img/placeholder.jpg'}" alt="" onerror="this.onerror=null;this.src='/assets/img/placeholder.jpg';">
        <div>
          <div class="rcp-title">${safeName}</div>
          <div class="rcp-meta">จำนวน: ${Number(it.qty)||1}</div>
        </div>
        <div class="rcp-price">${fmt((Number(it.price)||0)*(Number(it.qty)||1))}</div>
      `;
          rList.appendChild(li);
        });

        const sub = cart.reduce((s, it) => s + (Number(it.price) || 0) * (Number(it.qty) || 1), 0);
        const ship = SHIPPING;
        const grand = sub + ship;

        rSub.textContent = fmt(sub);
        rShip.textContent = ship ? fmt(ship) : 'ฟรี';
        rTot.textContent = fmt(grand);

        const text = buildSummaryText(cart, grand);
        rCopy.onclick = async () => {
          try {
            await navigator.clipboard.writeText(text);
            rCopy.innerHTML = '<span class="material-symbols-rounded">check</span> คัดลอกแล้ว';
            setTimeout(() => rCopy.innerHTML = '<span class="material-symbols-rounded">content_copy</span> คัดลอกรายการ', 1200);
          } catch (e) { alert('คัดลอกไม่สำเร็จ'); }
        };
        rLine.href = 'https://line.me/R/ti/p/@cmns';
        rPrint.onclick = () => window.print();

        showReceipt();
      }

      document.getElementById('mc-clear').addEventListener('click', function() {
        if (confirm('ล้างตะกร้าทั้งหมด?')) clearCart();
      });
      document.getElementById('mc-checkout').addEventListener('click', function(e) {
        e.preventDefault(); openReceipt();
      });

      bd.addEventListener('click', hideReceipt);
      rClose.addEventListener('click', hideReceipt);
      document.addEventListener('keydown', e => { if (e.key === 'Escape') hideReceipt(); });

      // init
      render();
    })();
  </script>

  <div id="promo-backdrop" aria-hidden="true" hidden></div>

  <aside id="promo-modal" role="dialog" aria-modal="true" aria-labelledby="promo-title" hidden>
    <div class="promo-card" role="document">
      <button type="button" class="promo-close" aria-label="ปิดโปรโมชั่น">
        <span class="material-symbols-rounded" aria-hidden="true">close</span>
      </button>

      <div class="promo-product-container">
        <div class="promo-loader">
          <div class="spinner"></div>
          <span>กำลังโหลดสินค้า...</span>
        </div>
        <ul id="promo-product-list" class="promo-product-list"></ul>
      </div>
      <div class="promo-body">
        <h3 id="promo-title">สินค้าแนะนำเฉพาะคุณ!</h3>
        <p>MacBook / iPhone / iPad มือสองคัดสภาพดี รับประกัน พร้อมของแถม — กดดูสินค้าหรือแอดไลน์เพื่อคุยกับแอดมินได้เลย</p>

        <div class="promo-actions">
          <a href="/shop/#cmnsx-products" class="promo-btn primary">
            <span class="material-symbols-rounded" aria-hidden="true">shopping_bag</span> ช้อปเลย
          </a>
          <a href="https://line.me/R/ti/p/@cmns" target="_blank" rel="noopener" class="promo-btn line">
            <span class="material-symbols-rounded" aria-hidden="true">chat_bubble</span> แอด LINE @cmns
          </a>
        </div>

        <label class="promo-nomore">
          <input type="checkbox" id="promo-never"> ไม่ต้องแสดงอีก
        </label>
      </div>
    </div>
  </aside>

  <script>
    (function() {
      const KEY_SEEN_AT = 'promo_seen_at_v1';
      const KEY_NEVER = 'promo_never_v1';
      const DAY = 24 * 60 * 60 * 1000;
      const COOLDOWN_DAYS = 7;

      const bd = document.getElementById('promo-backdrop');
      const md = document.getElementById('promo-modal');
      const closeBtn = md?.querySelector('.promo-close');
      const neverCb = document.getElementById('promo-never');
      const listEl = document.getElementById('promo-product-list');
      const loaderEl = md?.querySelector('.promo-loader');

      let isPromoLoaded = false;
      const API_URL = '/shop/api_random_products.php?limit=4'; // ปรับ path ตามจริงได้

      if (!bd || !md || !listEl || !loaderEl) return;

      function hasNever() { return localStorage.getItem(KEY_NEVER) === '1'; }
      function lastSeen() { return Number(localStorage.getItem(KEY_SEEN_AT) || 0); }
      function shouldShow() {
        if (hasNever()) return false;
        const seen = lastSeen();
        return !seen || (Date.now() - seen) > COOLDOWN_DAYS * DAY;
      }
      function lockScroll(lock) {
        document.documentElement.style.overflow = lock ? 'hidden' : '';
        document.body.style.overflow = lock ? 'hidden' : '';
      }

      // ========== FIXED: โหลดแบบสร้าง DOM และกัน XSS ==========
      async function fetchPromoProducts() {
        if (isPromoLoaded) return;
        isPromoLoaded = true;

        loaderEl.classList.remove('is-hidden');
        listEl.innerHTML = '';

        try {
          const response = await fetch(API_URL, { headers: { 'X-Requested-With': 'fetch' } });
          if (!response.ok) throw new Error('Network error');

          const data = await response.json();
          if (!data.success || !Array.isArray(data.items) || data.items.length === 0) {
            throw new Error('API returned no items');
          }

          for (const item of data.items) {
            const li = document.createElement('li');
            li.className = 'promo-product-item';

            const nameSafe = escapeHtml(item.name || ''); // [FIXED] ใช้ escapeHtml ที่แก้แล้ว
            const priceSafe = escapeHtml(item.price_fmt || ''); // [FIXED] ใช้ escapeHtml ที่แก้แล้ว
            const urlSafe = typeof item.url === 'string' ? item.url : '#';
            const imgSafe = typeof item.img === 'string' ? item.img : '';

            // สร้าง fallback icon แบบปลอดภัย
            const fallback = document.createElement('div');
            fallback.className = 'promo-product-icon';
            fallback.innerHTML = '<span class="material-symbols-rounded" aria-hidden="true">image</span>';

            // สร้าง media element
            let mediaEl;
            if (imgSafe) {
              mediaEl = document.createElement('img');
              mediaEl.loading = 'lazy';
              mediaEl.decoding = 'async';
              mediaEl.src = imgSafe;
              mediaEl.alt = nameSafe;
              mediaEl.onerror = function() {
                this.replaceWith(fallback);
              };
            } else {
              mediaEl = fallback;
            }

            // ประกอบ DOM
            const a = document.createElement('a');
            a.href = urlSafe;

            const nameEl = document.createElement('div');
            nameEl.className = 'promo-product-name';
            nameEl.textContent = item.name || ''; // .textContent ปลอดภัยจาก XSS อยู่แล้ว

            const priceEl = document.createElement('div');
            priceEl.className = 'promo-product-price';
            priceEl.textContent = item.price_fmt || ''; // .textContent ปลอดภัยจาก XSS อยู่แล้ว

            a.appendChild(mediaEl);
            a.appendChild(nameEl);
            a.appendChild(priceEl);
            li.appendChild(a);
            listEl.appendChild(li);
          }

          loaderEl.classList.add('is-hidden');
        } catch (err) {
          console.error('Failed to load promo products:', err);
          loaderEl.innerHTML = '<span>ไม่สามารถโหลดสินค้าได้</span>';
          isPromoLoaded = false;
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
        fetchPromoProducts();
      }

      function closePromo(persistSeen = true) {
        bd.classList.remove('show');
        md.classList.remove('show');
        lockScroll(false);

        setTimeout(() => {
          bd.hidden = true;
          md.hidden = true;
          listEl.innerHTML = '';
          loaderEl.classList.remove('is-hidden');
          loaderEl.innerHTML = '<div class="spinner"></div><span>กำลังโหลดสินค้า...</span>';
          isPromoLoaded = false;
        }, 260);

        if (persistSeen) localStorage.setItem(KEY_SEEN_AT, String(Date.now()));
        if (neverCb?.checked) localStorage.setItem(KEY_NEVER, '1');
      }

      window.addEventListener('DOMContentLoaded', () => {
        const forceShow = new URLSearchParams(location.search).get('promo') === 'show';
        if (forceShow) { openPromo(); return; }
        if (shouldShow()) {
          setTimeout(openPromo, 1000);
        } else {
          bd.hidden = true; md.hidden = true;
          bd.classList.remove('show'); md.classList.remove('show');
          lockScroll(false);
        }
      });

      closeBtn?.addEventListener('click', () => closePromo(true));
      bd.addEventListener('click', () => closePromo(true));
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !md.hidden) closePromo(false);
      });
    })();
  </script>

  <footer class="cmns-footer">
    <div class="footer-container">
      <div class="footer-grid">
        <div class="footer-col" id="f-brand">
          <a href="/shop/" class="f-logo"><img src="/assets/img/Logo1.png" alt="CMNS FixMac Logo"></a>
          <p>ร้านขายและซ่อม Apple มือสอง คุณภาพดีอันดับ 1 ในเชียงใหม่</p>
          <div class="f-socials">
            <a href="https://line.me/R/ti/p/@cmns" target="_blank" rel="noopener" aria-label="LINE"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" fill="currentColor"><path d="M224 336.1c-63.6 0-115.3-51.6-115.3-115.3S160.4 105.6 224 105.6s115.3 51.6 115.3 115.3-51.7 115.2-115.3 115.2zm0-207c-50.5 0-91.7 41.2-91.7 91.7s41.2 91.7 91.7 91.7 91.7-41.2 91.7-91.7-41.2-91.7-91.7-91.7zM448 220.7c0-101.9-82.8-184.6-184.8-184.6S78.4 118.8 78.4 220.7c0 82.2 53.7 151.6 125.7 174.4 6.9 2.2 11.2 8.9 11.2 16.2v.2c0 10.9-11.2 19.8-22.1 19.8-11.1 0-22.1-8.9-22.1-19.8 0-11.3-9.2-20.5-20.5-20.5-11.3 0-20.5 9.2-20.5 20.5 0 33.3 27 60.3 60.3 60.3s60.3-27 60.3-60.3v-.2c0-7.3 4.3-14 11.2-16.2 72-22.8 125.7-92.2 125.7-174.4zm-48.7 0c0 75.2-61.1 136.3-136.3 136.3s-136.3-61.1-136.3-136.3 61.1-136.3 136.3-136.3 136.3 61.1 136.3 136.3zM161.2 214.2c0-3.3-2.7-6-6-6s-6 2.7-6 6v13.1c0 3.3 2.7 6 6 6s6-2.7 6-6v-13.1zm51.4 0c0-3.3-2.7-6-6-6s-6 2.7-6 6v13.1c0 3.3 2.7 6 6 6s6-2.7 6-6v-13.1zm51.3 0c0-3.3-2.7-6-6-6s-6 2.7-6 6v13.1c0 3.3 2.7 6 6 6s6-2.7 6-6v-13.1z" /></svg></a>
            <a href="https://www.facebook.com/cmnsfixmac" target="_blank" rel="noopener" aria-label="Facebook"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="currentColor"><path d="M504 256C504 119 393 8 256 8S8 119 8 256c0 123.78 90.69 226.38 209.25 245V327.69h-63V256h63v-54.64c0-62.15 37-96.48 93.67-96.48 27.14 0 55.52 4.84 55.52 4.84v61h-31.28c-30.8 0-40.41 19.12-40.41 38.73V256h68.78l-11 71.69h-57.78V501C413.31 482.38 504 379.78 504 256z" /></svg></a>
            <a href="https://www.instagram.com/cmnsfixmac" target="_blank" rel="noopener" aria-label="Instagram"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" fill="currentColor"><path d="M224.1 141c-63.6 0-114.9 51.3-114.9 114.9s51.3 114.9 114.9 114.9S339 319.5 339 255.9 287.7 141 224.1 141zm0 189.6c-41.1 0-74.7-33.5-74.7-74.7s33.5-74.7 74.7-74.7 74.7 33.5 74.7 74.7-33.6 74.7-74.7 74.7zm146.4-194.3c0 14.9-12 26.8-26.8 26.8-14.9 0-26.8-12-26.8-26.8s12-26.8 26.8-26.8 26.8 12 26.8 26.8zm76.1 27.2c-1.7-35.9-9.9-67.7-36.2-93.9-26.2-26.2-58-34.4-93.9-36.2-37.2-2.1-147.9-2.1-185.1 0-35.9 1.7-67.7 9.9-93.9 36.2-26.2 26.2-34.4 58-36.2 93.9-2.1 37.2-2.1 147.9 0 185.1 1.7 35.9 9.9 67.7 36.2 93.9 26.2 26.2 58 34.4 93.9 36.2 37.2 2.1 147.9 2.1 185.1 0 35.9-1.7 67.7-9.9 93.9-36.2 26.2-26.2 34.4-58 36.2-93.9 2.1-37.2 2.1-147.8 0-185.1zM416 331c-1.5 28.4-8.2 51.5-31.3 74.6-23.1 23.1-46.2 29.8-74.6 31.3-36.9 1.8-140.3 1.8-177.2 0-28.4-1.5-51.5-8.2-74.6-31.3-23.1-23.1-29.8-46.2-31.3-74.6-1.8-36.9-1.8-140.3 0-177.2 1.5-28.4 8.2-51.5 31.3-74.6 23.1-23.1 46.2-29.8 74.6-31.3 36.9-1.8 140.3 1.8 177.2 0 28.4-1.5 51.5-8.2 74.6 31.3 23.1 23.1 29.8 46.2 31.3 74.6 1.8 36.9 1.8 140.3 0 177.2z" /></svg></a>
            <a href="https://www.tiktok.com/@cmnsfixmac" target="_blank" rel="noopener" aria-label="TikTok"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" fill="currentColor"><path d="M448,209.91a210.06,210.06,0,0,1-122.77-39.25V349.38A162.55,162.55,0,1,1,185,188.31V278.2a74.62,74.62,0,1,0,52.23,71.18V0l88,0a121.18,121.18,0,0,0,1.86,22.17h0A122.18,122.18,0,0,0,381,102.39a121.43,121.43,0,0,0,67,20.14Z" /></svg></a>
          </div>
        </div>

        <div class="footer-col">
          <h4>บริการของเรา</h4>
          <ul>
            <li><a href="/shop/">ร้านค้า (สินค้ามือสอง)</a></li>
            <li><a href="/works/">ผลงานซ่อม</a></li>
            <li><a href="/buyback/">รับซื้อเครื่อง</a></li>
            <li><a href="/warranty/">ตรวจสอบประกัน</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h4>ช่วยเหลือ</h4>
          <ul>
            <li><a href="/articles/">บทความ</a></li>

          </ul>
        </div>

        <div class="footer-col">
          <h4>ติดต่อเรา</h4>
          <ul class="f-contact">
            <li>
              <span class="material-symbols-rounded" aria-hidden="true">location_on</span>
              <a href="https://maps.app.goo.gl/r6f1sHhfa8mzxZXD9" target="_blank" rel="noopener">หน้าร้าน (เชียงใหม่)</a>
            </li>
            <li>
              <span class="material-symbols-rounded" aria-hidden="true">call</span>
              <a href="tel:0841511684">084-151-1684</a>
            </li>
            <li>
              <span class="material-symbols-rounded" aria-hidden="true">chat_bubble</span>
              <a href="https://line.me/R/ti/p/@cmns" target="_blank" rel="noopener">LINE: @cmns</a>
            </li>
            <li>
              <span class="material-symbols-rounded" aria-hidden="true">mail</span>
              <a href="mailto:info@cmnsfixmac.com">info@cmnsfixmac.com</a>
            </li>
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