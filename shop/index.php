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
function cat_icon($name) {
    $map = [
        'MacBook' => 'laptop_mac', 'iPhone' => 'smartphone', 'iPad' => 'tablet_mac',
        'iMac / Mac mini' => 'desktop_mac', 'AirPods / Apple Watch' => 'watch',
        'อะไหล่' => 'memory', 'อื่นๆ' => 'category',
    ];
    return $map[$name] ?? 'category';
}
function img_path($raw) {
    $img = trim((string)($raw ?? ''));
    if ($img !== '' && $img[0] !== '/' && !preg_match('~^https?://~', $img)) $img = '/' . ltrim($img, '/');
    return $img;
}

/* ---------- Filters ---------- */
$q    = getv('q', '');
$cat  = getv('cat', '');
$sort = getv('sort', 'new');
$view = getv('view', '') === 'sold' ? 'sold' : 'available';   // active zone (tab)
$page = max(1, (int)getv('page', 1));
$pp   = 32;
$off  = ($page - 1) * $pp;

/* ---------- Categories (for chips) ---------- */
$categories = $pdo->query("SELECT id, name FROM shop_categories ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

/* ---------- Zone base filters (mutually exclusive) ---------- */
$availBase = "(sl.status = 'published' AND inv.status != 'SOLD')";
$soldBase  = "(sl.status = 'sold' OR inv.status = 'SOLD')";

/* ---------- Shared q/cat filter (applies to whichever zone is active) ---------- */
$filterSql    = '';
$filterParams = [];
if ($q !== '')   { $filterSql .= " AND (sl.title LIKE :q OR inv.name LIKE :q)"; $filterParams[':q'] = "%$q%"; }
if ($cat !== '') { $filterSql .= " AND sc.name = :cat"; $filterParams[':cat'] = $cat; }

/* ---------- Tab counts (same q/cat filter so badges match the results) ---------- */
$countSql = "
    SELECT SUM($availBase) AS avail, SUM($soldBase) AS sold
    FROM shop_listings sl
    JOIN inventory inv      ON inv.id = sl.inventory_id
    JOIN shop_categories sc ON sc.id = sl.category_id
    WHERE 1=1 $filterSql";
$cst = $pdo->prepare($countSql);
foreach ($filterParams as $k => $v) $cst->bindValue($k, $v);
$cst->execute();
$counts     = $cst->fetch(PDO::FETCH_ASSOC);
$availCount = (int)$counts['avail'];
$soldCount  = (int)$counts['sold'];

/* ---------- Active zone selection ---------- */
$base  = $view === 'sold' ? $soldBase : $availBase;
$total = $view === 'sold' ? $soldCount : $availCount;
$pages = max(1, (int)ceil($total / $pp));

$ORDER = 'ORDER BY sl.created_at DESC, sl.id DESC';
if ($view === 'sold')       $ORDER = 'ORDER BY sl.updated_at DESC, sl.id DESC';  // most recently sold first
if ($sort === 'price_asc')  $ORDER = 'ORDER BY sl.price ASC, sl.id DESC';
if ($sort === 'price_desc') $ORDER = 'ORDER BY sl.price DESC, sl.id DESC';

/* ---------- Items ---------- */
$sql = "
    SELECT
        sl.id,
        COALESCE(sl.title, inv.name)        AS name,
        sc.name                             AS category,
        sl.price,
        sl.price_original                   AS price_old,
        COALESCE(sl.cover_image, inv.image) AS main_image
    FROM shop_listings sl
    JOIN inventory inv      ON inv.id = sl.inventory_id
    JOIN shop_categories sc ON sc.id = sl.category_id
    WHERE $base $filterSql
    $ORDER
    LIMIT :lim OFFSET :off";
$st = $pdo->prepare($sql);
foreach ($filterParams as $k => $v) $st->bindValue($k, $v);
$st->bindValue(':lim', $pp, PDO::PARAM_INT);
$st->bindValue(':off', $off, PDO::PARAM_INT);
$st->execute();
$items = $st->fetchAll(PDO::FETCH_ASSOC);

/* ---------- Hot deals (top discounts) for featured bento ---------- */
$dealItems = $pdo->query("
    SELECT sl.id,
           COALESCE(sl.title, inv.name)        AS name,
           sc.name                             AS category,
           sl.price,
           sl.price_original                   AS price_old,
           COALESCE(sl.cover_image, inv.image) AS main_image,
           ROUND((sl.price_original - sl.price) / sl.price_original * 100) AS pct
    FROM shop_listings sl
    JOIN inventory inv      ON inv.id = sl.inventory_id
    JOIN shop_categories sc ON sc.id = sl.category_id
    WHERE sl.status = 'published'
      AND inv.status != 'SOLD'
      AND sl.price_original > sl.price
    ORDER BY pct DESC, (sl.price_original - sl.price) DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

/* ---------- Active filters ---------- */
$activeFilters = [];
if ($q !== '')   $activeFilters[] = ['label' => 'ค้นหา: "' . $q . '"', 'url' => build_url(['q' => null, 'page' => 1])];
if ($cat !== '') $activeFilters[] = ['label' => 'หมวด: ' . $cat,        'url' => build_url(['cat' => null, 'page' => 1])];

$page_title = 'ร้านค้า — Mac & iPhone มือสองคัดเกรด พร้อมรับประกัน | CMNS FixMac';
$meta_desc  = 'ร้านค้า CMNS FixMac — MacBook, iMac, iPhone, iPad มือสองคัดเกรด ตรวจสภาพละเอียด พร้อมรับประกันร้าน นัดรับเชียงใหม่หรือส่งทั่วประเทศ สอบถาม/สั่งซื้อผ่าน LINE ได้เลย';
$canonical  = 'https://cmnsfixmac.com/shop/' . ($cat !== '' ? '?cat=' . urlencode($cat) : '');
$page_css   = ['/assets/css/shop/shop.css?v=24', 'https://unpkg.com/aos@2.3.4/dist/aos.css'];

/* Build ItemList schema from current page items */
$itemListEl = [];
foreach ($items as $i => $row) {
    $itemListEl[] = [
        '@type' => 'ListItem', 'position' => $i + 1,
        'url'   => 'https://cmnsfixmac.com/shop/product-detail.php?id=' . (int)$row['id'],
        'name'  => $row['name'],
    ];
}
$breadcrumbEl = [
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'หน้าแรก', 'item' => 'https://cmnsfixmac.com/'],
    ['@type' => 'ListItem', 'position' => 2, 'name' => 'ร้านค้า',  'item' => 'https://cmnsfixmac.com/shop/'],
];
if ($cat !== '') $breadcrumbEl[] = ['@type' => 'ListItem', 'position' => 3, 'name' => $cat, 'item' => $canonical];

ob_start(); ?>
<meta name="description" content="<?= h($meta_desc) ?>">
<meta name="keywords" content="ร้านขาย Mac มือสอง เชียงใหม่, MacBook มือสอง, iPhone มือสอง, iPad มือสอง, iMac มือสอง, Mac มือสองคัดเกรด, ร้าน Apple มือสอง">
<meta name="robots" content="<?= $view === 'sold' ? 'noindex, follow' : 'index, follow' ?>">
<link rel="canonical" href="<?= h($canonical) ?>">
<link rel="alternate" hreflang="th" href="https://cmnsfixmac.com/shop/">
<link rel="alternate" hreflang="en" href="https://cmnsfixmac.com/en/shop/">
<link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= h($page_title) ?>">
<meta property="og:description" content="<?= h($meta_desc) ?>">
<meta property="og:image" content="https://cmnsfixmac.com/assets/img/Logo1.png">
<meta property="og:url" content="<?= h($canonical) ?>">
<meta property="og:locale" content="th_TH">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= h($page_title) ?>">
<meta name="twitter:description" content="<?= h($meta_desc) ?>">
<meta name="twitter:image" content="https://cmnsfixmac.com/assets/img/Logo1.png">
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@graph'   => [
        ['@type' => 'BreadcrumbList', 'itemListElement' => $breadcrumbEl],
        ['@type' => 'CollectionPage',
         'name'  => $page_title,
         'description' => $meta_desc,
         'url'   => $canonical,
         'mainEntity' => ['@type' => 'ItemList', 'numberOfItems' => $total, 'itemListElement' => $itemListEl],
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php
$page_head_extra = ob_get_clean();

include_once __DIR__ . '/../includes/header.php';
?>

<main>

<!-- ═══════════ HERO (split) ═══════════ -->
<section class="shop-hero">
  <div class="shop-hero-orb shop-orb-1" aria-hidden="true"></div>
  <div class="shop-hero-orb shop-orb-2" aria-hidden="true"></div>

  <div class="shop-hero-wrap">

    <!-- Left: copy + search -->
    <div class="shop-hero-left" data-aos="fade-up">
      <span class="shop-hero-eyebrow"><span class="material-symbols-rounded">storefront</span> CMNS Mac · ร้านค้า</span>
      <h1 class="shop-hero-title">Mac &amp; iPhone มือสอง<br>คัดเกรด พร้อมรับประกัน</h1>
      <p class="shop-hero-lead">ตรวจสภาพละเอียดทุกเครื่องก่อนลงขาย สอบถาม/สั่งซื้อผ่าน LINE ได้เลย</p>

      <form class="shop-hero-search" method="get" action="/shop/">
        <span class="material-symbols-rounded">search</span>
        <input type="text" name="q" value="<?= h($q) ?>" placeholder="ค้นหารุ่น เช่น MacBook Air M2, iPhone 13…" aria-label="ค้นหาสินค้า">
        <?php if ($cat !== ''): ?><input type="hidden" name="cat" value="<?= h($cat) ?>"><?php endif; ?>
        <button type="submit">ค้นหา</button>
      </form>

      <div class="shop-hero-trust">
        <span class="hero-pill"><span class="material-symbols-rounded">verified_user</span> รับประกันร้าน</span>
        <span class="hero-pill"><span class="material-symbols-rounded">fact_check</span> ตรวจ 30+ จุด</span>
        <span class="hero-pill"><span class="material-symbols-rounded">local_shipping</span> ส่งทั่วไทย</span>
      </div>

      <div class="shop-hero-quick">
        <span class="hero-quick-label">ช้อปตามหมวด</span>
        <div class="hero-quick-row">
          <a href="/shop/?cat=MacBook#shop-products"><span class="material-symbols-rounded">laptop_mac</span> MacBook</a>
          <a href="/shop/?cat=iPhone#shop-products"><span class="material-symbols-rounded">smartphone</span> iPhone</a>
          <a href="/shop/?cat=iPad#shop-products"><span class="material-symbols-rounded">tablet_mac</span> iPad</a>
          <a href="/shop/?cat=<?= urlencode('iMac / Mac mini') ?>#shop-products"><span class="material-symbols-rounded">desktop_mac</span> iMac</a>
        </div>
      </div>
    </div>

    <!-- Right: device showcase -->
    <div class="shop-hero-right" data-aos="fade-up" data-aos-delay="100">
      <div class="shop-hero-stage">
        <div class="shop-glow" aria-hidden="true"></div>
        <img class="shop-dev shop-dev-mac"    src="/assets/img/shop/dev-macbook.png" alt="" aria-hidden="true" loading="lazy" decoding="async">
        <img class="shop-dev shop-dev-ipad"   src="/assets/img/shop/dev-ipad.png"    alt="" aria-hidden="true" loading="lazy" decoding="async">
        <img class="shop-dev shop-dev-iphone" src="/assets/img/shop/dev-iphone.png"  alt="" aria-hidden="true" loading="lazy" decoding="async">
      </div>
    </div>

  </div>
</section>

<!-- ═══════════ TOOLBAR ═══════════ -->
<div class="shop-toolbar<?= $q !== '' ? ' is-searching' : '' ?>" id="shopToolbar">
  <div class="shop-toolbar-inner">

    <!-- Search toggle (collapsed icon) -->
    <button type="button" class="shop-search-toggle" id="shopSearchToggle" aria-label="ค้นหาสินค้า" aria-expanded="<?= $q !== '' ? 'true' : 'false' ?>">
      <span class="material-symbols-rounded"><?= $q !== '' ? 'search' : 'search' ?></span>
      <?php if ($q !== ''): ?><span class="shop-search-dot" aria-hidden="true"></span><?php endif; ?>
    </button>

    <!-- Category chips (text-only, Apple-clean) -->
    <div class="shop-chips" role="tablist" aria-label="หมวดสินค้า">
      <a href="<?= h(build_url(['cat' => null, 'page' => 1])) ?>#shop-products" class="shop-chip<?= $cat === '' ? ' active' : '' ?>">ทั้งหมด</a>
      <?php foreach ($categories as $c): ?>
      <a href="<?= h(build_url(['cat' => $c['name'], 'page' => 1])) ?>#shop-products"
         class="shop-chip<?= $cat === $c['name'] ? ' active' : '' ?>"><?= h($c['name']) ?></a>
      <?php endforeach; ?>
    </div>

    <!-- Sort -->
    <div class="shop-sort">
      <select id="shop-sort-sel" aria-label="เรียงลำดับ" onchange="location.href=this.value">
        <option value="<?= h(build_url(['sort' => null, 'page' => 1])) ?>#shop-products"      <?= $sort === 'new' ? 'selected' : '' ?>>ใหม่ล่าสุด</option>
        <option value="<?= h(build_url(['sort' => 'price_asc', 'page' => 1])) ?>#shop-products"  <?= $sort === 'price_asc' ? 'selected' : '' ?>>ราคาต่ำ→สูง</option>
        <option value="<?= h(build_url(['sort' => 'price_desc', 'page' => 1])) ?>#shop-products" <?= $sort === 'price_desc' ? 'selected' : '' ?>>ราคาสูง→ต่ำ</option>
      </select>
    </div>

    <!-- Expanding search bar (overlays the row when open) -->
    <form class="shop-search-bar" method="get" action="/shop/" role="search">
      <span class="material-symbols-rounded shop-sb-icon">search</span>
      <input type="text" name="q" id="shopSearchInput"
             value="<?= h($q) ?>"
             placeholder="ค้นหารุ่น เช่น MacBook Air M2, iPhone 15…"
             aria-label="ค้นหาสินค้า" autocomplete="off">
      <?php if ($cat !== ''): ?><input type="hidden" name="cat" value="<?= h($cat) ?>"><?php endif; ?>
      <?php if ($sort !== 'new'): ?><input type="hidden" name="sort" value="<?= h($sort) ?>"><?php endif; ?>
      <button type="button" class="shop-sb-close" id="shopSearchClose" aria-label="ปิดการค้นหา">
        <span class="material-symbols-rounded">close</span>
      </button>
    </form>

  </div>
</div>

<!-- ═══════════ PRODUCTS ═══════════ -->
<section class="shop-products" id="shop-products">
  <div class="sv-container">

    <!-- Zone switch: Apple segmented control (พร้อมขาย / ขายแล้ว) -->
    <div class="shop-seg" data-active="<?= h($view) ?>" role="tablist" aria-label="โซนสินค้า">
      <span class="shop-seg-thumb" aria-hidden="true"></span>
      <a class="shop-seg-btn<?= $view === 'available' ? ' active' : '' ?>" role="tab" data-seg="available"
         aria-selected="<?= $view === 'available' ? 'true' : 'false' ?>"
         href="<?= h(build_url(['view' => null, 'page' => 1])) ?>#shop-products">
        <span class="material-symbols-rounded">storefront</span>
        <span class="shop-seg-text">พร้อมขาย</span>
        <span class="shop-seg-count"><?= number_format($availCount) ?></span>
      </a>
      <a class="shop-seg-btn<?= $view === 'sold' ? ' active' : '' ?>" role="tab" data-seg="sold"
         aria-selected="<?= $view === 'sold' ? 'true' : 'false' ?>"
         href="<?= h(build_url(['view' => 'sold', 'page' => 1])) ?>#shop-products">
        <span class="material-symbols-rounded">sell</span>
        <span class="shop-seg-text">ขายแล้ว</span>
        <span class="shop-seg-count"><?= number_format($soldCount) ?></span>
      </a>
    </div>

    <?php if ($activeFilters): ?>
    <div class="shop-active">
      <span class="af-title">ตัวกรอง:</span>
      <?php foreach ($activeFilters as $f): ?>
      <a class="shop-af-pill" href="<?= h($f['url']) ?>#shop-products"><?= h($f['label']) ?> <span class="material-symbols-rounded">close</span></a>
      <?php endforeach; ?>
      <a class="shop-af-pill clear-all" href="<?= h(build_url(['q' => null, 'cat' => null, 'page' => 1])) ?>#shop-products"><span class="material-symbols-rounded">delete_sweep</span> ล้างทั้งหมด</a>
    </div>
    <?php endif; ?>

    <?php $isSold = $view === 'sold'; ?>
    <h2 class="shop-count"><?= $isSold ? 'ขายไปแล้ว' : 'สินค้าพร้อมขาย' ?> <span>(<?= number_format($total) ?> ชิ้น)</span></h2>

    <?php if (empty($items)): ?>
    <div class="shop-empty">
      <span class="material-symbols-rounded"><?= $isSold ? 'sell' : 'inventory_2' ?></span>
      <?php if ($isSold): ?>
      <h3>ยังไม่มีรายการที่ขายแล้วในหมวดนี้</h3>
      <p>ลองสลับไปดูโซน "พร้อมขาย" หรือทักมาถามรุ่นที่อยากได้</p>
      <?php else: ?>
      <h3>ยังไม่มีสินค้าในหมวดนี้</h3>
      <p>ลองล้างตัวกรอง หรือทักมาถามรุ่นที่อยากได้ เดี๋ยวเราหาให้</p>
      <?php endif; ?>
      <a href="https://line.me/R/ti/p/@cmns" target="_blank" rel="noopener" class="btn btn-line">
        <img src="/assets/img/line-icon.png" alt="LINE" width="18" height="18"> ทักหาเราใน LINE
      </a>
    </div>
    <?php else: ?>
    <ul class="shop-grid">
      <?php foreach ($items as $idx => $row):
        $url   = '/shop/product-detail.php?id=' . (int)$row['id'];
        $img   = img_path($row['main_image']);
        $price = (float)$row['price'];
        $old   = (float)($row['price_old'] ?? 0);
        $disc  = $old > $price ? $old - $price : 0;
        $pct   = $old > 0 ? round($disc / $old * 100) : 0;
        /* First two rows are above the fold: load them eagerly with high
           priority so they paint fast; the rest stay lazy. */
        $eager = $idx < 8;
      ?>
      <li class="shop-card<?= $isSold ? ' is-sold' : '' ?>">
        <a class="shop-card-link" href="<?= h($url) ?>">
          <div class="shop-card-media<?= $img === '' ? ' is-ready' : '' ?>">
            <?php if ($isSold): ?><span class="shop-sold-ribbon">SOLD</span>
            <?php elseif ($disc > 0): ?><span class="shop-badge">-<?= $pct ?>%</span><?php endif; ?>
            <?php if ($img !== ''): ?>
              <span class="shop-loader" aria-hidden="true"></span>
              <img src="<?= h($img) ?>" alt="<?= h($row['name']) ?>" width="400" height="400"
                   <?= $eager ? 'loading="eager" fetchpriority="high"' : 'loading="lazy" fetchpriority="low"' ?> decoding="async">
            <?php else: ?>
              <div class="shop-card-noimg"><span class="material-symbols-rounded">hide_image</span></div>
            <?php endif; ?>
          </div>
          <div class="shop-card-body">
            <span class="shop-card-cat"><?= h($row['category']) ?></span>
            <h3 class="shop-card-title"><?= h($row['name']) ?></h3>
            <div class="shop-card-price-row">
              <span class="shop-card-price">฿<?= number_format($price) ?></span>
              <?php if (!$isSold && $old > $price): ?><span class="shop-card-old">฿<?= number_format($old) ?></span><?php endif; ?>
            </div>
          </div>
        </a>
        <div class="shop-card-foot">
          <a class="btn btn-ghost" href="<?= h($url) ?>">ดูรายละเอียด <span class="material-symbols-rounded">arrow_forward</span></a>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>

    <?php if ($pages > 1): ?>
    <nav class="shop-pager" aria-label="หน้า">
      <a class="<?= $page <= 1 ? 'disabled' : '' ?>" href="<?= h(build_url(['page' => $page - 1])) ?>#shop-products"><span class="material-symbols-rounded">chevron_left</span></a>
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i == $page): ?>
          <span class="current"><?= $i ?></span>
        <?php else: ?>
          <a href="<?= h(build_url(['page' => $i])) ?>#shop-products"><?= $i ?></a>
        <?php endif; ?>
      <?php endfor; ?>
      <a class="<?= $page >= $pages ? 'disabled' : '' ?>" href="<?= h(build_url(['page' => $page + 1])) ?>#shop-products"><span class="material-symbols-rounded">chevron_right</span></a>
    </nav>
    <?php endif; ?>
    <?php endif; ?>

  </div>
</section>

<!-- ═══════════ HOT DEALS (asymmetric bento) ═══════════ -->
<?php if ($view === 'available' && count($dealItems) >= 5):
  $feat = $dealItems[0];
  $rest = array_slice($dealItems, 1, 4);
?>
<section class="sv-section shop-deals">
  <div class="sv-container">
    <div class="sv-section-head" data-aos="fade-up">
      <span class="section-label">ดีลเด็ด</span>
      <h2>ลดเยอะ คัดมาให้</h2>
      <p class="sv-desc">ของดีราคาพิเศษ มีจำนวนจำกัด ถูกใจรีบจองก่อนใคร</p>
    </div>

    <div class="shop-deals-bento">

      <!-- Feature deal (2×2) -->
      <?php $u = '/shop/product-detail.php?id=' . (int)$feat['id']; $im = img_path($feat['main_image']); ?>
      <a class="deal-card deal-feature" href="<?= h($u) ?>" data-aos="fade-up">
        <span class="deal-badge">-<?= (int)$feat['pct'] ?>%</span>
        <div class="deal-media<?= $im === '' ? ' is-ready' : '' ?>">
          <?php if ($im !== ''): ?><span class="shop-loader" aria-hidden="true"></span><img src="<?= h($im) ?>" alt="<?= h($feat['name']) ?>" width="600" height="600" loading="lazy" decoding="async"><?php else: ?><div class="shop-card-noimg"><span class="material-symbols-rounded">hide_image</span></div><?php endif; ?>
        </div>
        <div class="deal-info">
          <span class="deal-cat"><?= h($feat['category']) ?></span>
          <h3 class="deal-title"><?= h($feat['name']) ?></h3>
          <div class="deal-price-row">
            <span class="deal-price">฿<?= number_format((float)$feat['price']) ?></span>
            <span class="deal-old">฿<?= number_format((float)$feat['price_old']) ?></span>
          </div>
          <span class="deal-cta">ดูดีลนี้ <span class="material-symbols-rounded">arrow_forward</span></span>
        </div>
      </a>

      <!-- Small deals (1×1) -->
      <?php foreach ($rest as $i => $row):
        $u = '/shop/product-detail.php?id=' . (int)$row['id']; $im = img_path($row['main_image']); ?>
      <a class="deal-card deal-mini" href="<?= h($u) ?>" data-aos="fade-up" data-aos-delay="<?= ($i + 1) * 60 ?>">
        <span class="deal-badge deal-badge-sm">-<?= (int)$row['pct'] ?>%</span>
        <div class="deal-mini-media<?= $im === '' ? ' is-ready' : '' ?>">
          <?php if ($im !== ''): ?><span class="shop-loader" aria-hidden="true"></span><img src="<?= h($im) ?>" alt="<?= h($row['name']) ?>" width="300" height="300" loading="lazy" decoding="async"><?php else: ?><div class="shop-card-noimg"><span class="material-symbols-rounded">hide_image</span></div><?php endif; ?>
        </div>
        <div class="deal-mini-info">
          <span class="deal-cat"><?= h($row['category']) ?></span>
          <h4 class="deal-mini-title"><?= h($row['name']) ?></h4>
          <div class="deal-price-row">
            <span class="deal-price-sm">฿<?= number_format((float)$row['price']) ?></span>
            <span class="deal-old-sm">฿<?= number_format((float)$row['price_old']) ?></span>
          </div>
        </div>
      </a>
      <?php endforeach; ?>

    </div>
  </div>
</section>
<?php endif; ?>

<!-- ═══════════ WHY BUY ═══════════ -->
<section class="sv-section shop-why">
  <div class="sv-container">
    <div class="sv-section-head" data-aos="fade-up">
      <span class="section-label">ทำไมซื้อกับเรา</span>
      <h2>ซื้อมือสองกับ CMNS Mac อุ่นใจกว่า</h2>
      <p class="sv-desc">ทุกเครื่องผ่านมือช่างผู้เชี่ยวชาญ ตรวจเช็คละเอียดก่อนส่งมอบ</p>
    </div>
    <div class="shop-why-grid">
      <?php foreach ([
          ['fact_check',   'ตรวจสภาพ 30+ จุด',   'เช็คทุกฟังก์ชันโดยช่างก่อนลงขาย แจ้งตำหนิตามจริง'],
          ['verified',     'รับประกันร้าน',       'มีประกันหลังการขาย มีปัญหาเคลมได้ ไม่ทิ้งกัน'],
          ['swap_horiz',   'เทิร์น & รับซื้อคืน',  'เอาเครื่องเก่ามาเทิร์นลดราคา หรือขายคืนให้เราได้'],
          ['bolt',         'สอบถามไว ตอบเร็ว',    'ทักผ่าน LINE ส่งรูป/สรุปดีลจบไว นัดรับสะดวก'],
      ] as $i => [$icon, $title, $desc]): ?>
      <div class="shop-why-card" data-aos="fade-up" data-aos-delay="<?= ($i % 4) * 80 ?>">
        <div class="shop-why-ico"><span class="material-symbols-rounded"><?= $icon ?></span></div>
        <h3><?= $title ?></h3>
        <p><?= $desc ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══════════ CTA ═══════════ -->
<section class="shop-cta">
  <div class="sv-container">
    <div class="shop-cta-inner" data-aos="fade-up">
      <span class="shop-cta-eyebrow">ต้องการความช่วยเหลือ?</span>
      <h2 class="shop-cta-heading">ไม่เจอรุ่นที่ถูกใจ?</h2>
      <p class="shop-cta-sub">บอกรุ่น สเปค และงบที่อยากได้ เดี๋ยวเราหาให้ หรือมีของอยากขาย เรารับซื้อด้วย</p>
      <div class="shop-cta-btns">
        <a href="https://line.me/R/ti/p/@cmns" target="_blank" rel="noopener" class="btn btn-line">
          <img src="/assets/img/line-icon.png" alt="LINE" width="18" height="18"> ทักหาเราใน LINE
        </a>
        <a href="tel:0841511684" class="btn btn-cta-phone">
          <span class="material-symbols-rounded">call</span> 084-151-1684
        </a>
        <a href="/buyback/" class="btn btn-cta-ghost">
          <span class="material-symbols-rounded">sell</span> ขายเครื่องให้เรา
        </a>
      </div>
    </div>
  </div>
</section>

</main>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
<script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
<script>
AOS.init({ duration: 700, once: true, offset: 60 });

/* ── Image loading: smooth fade-in + broken-image fallback ──
   - on success  → fade the <img> in and stop the skeleton shimmer
   - on error/404 → drop the <img>, show a centered icon instead   */
(function () {
  var MIN_SPIN = 450;                 // keep the loader on screen at least this long

  function apply(img) {
    // Force one painted frame at opacity:0 before flipping to is-loaded,
    // otherwise cached/eager images snap in with no transition.
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        img.classList.add('is-loaded');
        if (img.parentNode) img.parentNode.classList.add('is-ready');
      });
    });
  }
  function reveal(img, t0) {
    // If the image resolved faster than MIN_SPIN, hold the loader a touch
    // longer so it's actually perceivable (esp. on cached/fast connections).
    var wait = MIN_SPIN - (performance.now() - t0);
    wait > 0 ? setTimeout(function () { apply(img); }, wait) : apply(img);
  }
  function fail(img) {
    var box = img.parentNode;
    if (!box) return;
    box.classList.add('is-ready');
    img.remove();
    if (!box.querySelector('.shop-card-noimg')) {
      var ph = document.createElement('div');
      ph.className = 'shop-card-noimg';
      ph.innerHTML = '<span class="material-symbols-rounded">broken_image</span>';
      box.appendChild(ph);
    }
  }
  /* Start the spinner timer the moment THIS card scrolls into view, so every
     card — not just the first rows — shows the loader before its image fades in. */
  function track(img) {
    var t0 = performance.now();
    if (img.complete) {
      img.naturalWidth > 0 ? reveal(img, t0) : fail(img);
    } else {
      img.addEventListener('load',  function () { reveal(img, t0); });
      img.addEventListener('error', function () { fail(img); });
    }
  }

  var imgs = document.querySelectorAll('.shop-card-media img, .deal-media img, .deal-mini-media img');

  if (!('IntersectionObserver' in window)) {   // graceful fallback: handle all now
    imgs.forEach(track);
    return;
  }
  var io = new IntersectionObserver(function (entries, obs) {
    entries.forEach(function (e) {
      if (!e.isIntersecting) return;
      obs.unobserve(e.target);
      track(e.target);
    });
  }, { rootMargin: '0px 0px 0px 0px' });
  imgs.forEach(function (img) { io.observe(img); });
})();

/* Count-up for the bento stat */
document.querySelectorAll('.counter').forEach(function (el) {
  var target = +el.getAttribute('data-count');
  var io = new IntersectionObserver(function (entries, obs) {
    if (!entries[0].isIntersecting) return;
    obs.disconnect();
    var step = Math.max(1, Math.ceil(target / 90));
    (function tick() {
      var cur = +el.innerText.replace(/,/g, '');
      if (cur < target) { el.innerText = Math.min(cur + step, target).toLocaleString(); requestAnimationFrame(tick); }
      else { el.innerText = target.toLocaleString(); }
    })();
  }, { threshold: 0.4 });
  io.observe(el);
});

/* ── Hero device showcase: subtle mouse parallax ── */
(function () {
  var stage = document.querySelector('.shop-hero-stage');
  if (!stage) return;
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  if (window.matchMedia('(hover: none)').matches) return;   /* skip touch */

  var hero = stage.closest('.shop-hero');
  var raf = null, tx = 0, ty = 0;

  function onMove(e) {
    var r = hero.getBoundingClientRect();
    var cx = (e.clientX - r.left) / r.width  - 0.5;   /* -0.5 … 0.5 */
    var cy = (e.clientY - r.top)  / r.height - 0.5;
    tx = cx * 22;   /* max px shift */
    ty = cy * 16;
    if (!raf) raf = requestAnimationFrame(apply);
  }
  function apply() {
    raf = null;
    stage.style.setProperty('--px', tx.toFixed(1) + 'px');
    stage.style.setProperty('--py', ty.toFixed(1) + 'px');
  }
  function reset() {
    stage.style.setProperty('--px', '0px');
    stage.style.setProperty('--py', '0px');
  }
  hero.addEventListener('mousemove', onMove);
  hero.addEventListener('mouseleave', reset);
})();

/* ── Toolbar: expanding search + sticky shadow ── */
(function () {
  var toolbar = document.getElementById('shopToolbar');
  if (!toolbar) return;
  var toggle  = document.getElementById('shopSearchToggle');
  var closeBt = document.getElementById('shopSearchClose');
  var input   = document.getElementById('shopSearchInput');

  function openSearch() {
    toolbar.classList.add('is-searching');
    toggle.setAttribute('aria-expanded', 'true');
    setTimeout(function () { input.focus(); input.select(); }, 60);
  }
  function closeSearch() {
    /* keep open if there is an active server-side query so it isn't lost */
    toolbar.classList.remove('is-searching');
    toggle.setAttribute('aria-expanded', 'false');
  }

  toggle.addEventListener('click', function () {
    toolbar.classList.contains('is-searching') ? closeSearch() : openSearch();
  });
  closeBt.addEventListener('click', closeSearch);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && toolbar.classList.contains('is-searching')) closeSearch();
  });

  /* sticky elevation when toolbar reaches its stuck position */
  var sentinel = document.createElement('div');
  toolbar.parentNode.insertBefore(sentinel, toolbar);
  new IntersectionObserver(function (entries) {
    toolbar.classList.toggle('is-stuck', !entries[0].isIntersecting);
  }, { rootMargin: '-' + (toolbar.offsetTop) + 'px 0px 0px 0px', threshold: 0 }).observe(sentinel);
})();

/* ── Zone segmented control: slide the thumb, then navigate ── */
(function () {
  var seg = document.querySelector('.shop-seg');
  if (!seg) return;
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  seg.querySelectorAll('.shop-seg-btn').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      if (btn.classList.contains('active')) return;          // already here
      if (reduce) return;                                    // let the link navigate instantly
      e.preventDefault();
      seg.setAttribute('data-active', btn.dataset.seg);      // slide the thumb now
      seg.querySelectorAll('.shop-seg-btn').forEach(function (b) {
        b.classList.toggle('active', b === btn);
        b.setAttribute('aria-selected', b === btn ? 'true' : 'false');
      });
      setTimeout(function () { window.location.href = btn.href; }, 220);
    });
  });
})();
</script>
</body>
</html>
