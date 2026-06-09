<?php
require_once __DIR__ . '/../../includes/db.php';

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function getv($k, $d = null) { return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }
function build_url($patch) {
    $qs = array_merge($_GET, $patch);
    foreach ($qs as $k => $v) { if ($v === '' || $v === null) unset($qs[$k]); }
    if (isset($qs['page']) && (int)$qs['page'] === 1) unset($qs['page']);
    $q = http_build_query($qs);
    return '/en/shop/' . ($q ? '?' . $q : '');
}
function cat_icon($name) {
    $map = [
        'MacBook' => 'laptop_mac', 'iPhone' => 'smartphone', 'iPad' => 'tablet_mac',
        'iMac / Mac mini' => 'desktop_mac', 'AirPods / Apple Watch' => 'watch',
        'อะไหล่' => 'memory', 'อื่นๆ' => 'category',
    ];
    return $map[$name] ?? 'category';
}
function cat_label($name) {
    $map = ['อะไหล่' => 'Parts', 'อื่นๆ' => 'Others'];
    return $map[$name] ?? $name;
}
function img_path($raw) {
    $img = trim((string)($raw ?? ''));
    if ($img !== '' && $img[0] !== '/' && !preg_match('~^https?://~', $img)) $img = '/' . ltrim($img, '/');
    return $img;
}

$q    = getv('q', '');
$cat  = getv('cat', '');
$sort = getv('sort', 'new');
$page = max(1, (int)getv('page', 1));
$pp   = 24;
$off  = ($page - 1) * $pp;

$categories = $pdo->query("SELECT id, name FROM shop_categories ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

$where  = ["sl.status = 'published'", "inv.status != 'SOLD'"];
$params = [];
if ($q !== '')   { $where[] = "(sl.title LIKE :q OR inv.name LIKE :q)"; $params[':q'] = "%$q%"; }
if ($cat !== '') { $where[] = "sc.name = :cat"; $params[':cat'] = $cat; }
$WHERE = 'WHERE ' . implode(' AND ', $where);

$ORDER = 'ORDER BY sl.created_at DESC, sl.id DESC';
if ($sort === 'price_asc')  $ORDER = 'ORDER BY sl.price ASC, sl.id DESC';
if ($sort === 'price_desc') $ORDER = 'ORDER BY sl.price DESC, sl.id DESC';

$sql = "
    SELECT SQL_CALC_FOUND_ROWS
        sl.id, COALESCE(sl.title, inv.name) AS name, sc.name AS category,
        sl.price, sl.price_original AS price_old,
        COALESCE(sl.cover_image, inv.image) AS main_image
    FROM shop_listings sl
    JOIN inventory inv      ON inv.id = sl.inventory_id
    JOIN shop_categories sc ON sc.id = sl.category_id
    $WHERE $ORDER LIMIT :lim OFFSET :off";
$st = $pdo->prepare($sql);
foreach ($params as $k => $v) $st->bindValue($k, $v);
$st->bindValue(':lim', $pp, PDO::PARAM_INT);
$st->bindValue(':off', $off, PDO::PARAM_INT);
$st->execute();
$items = $st->fetchAll(PDO::FETCH_ASSOC);
$total = (int)$pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
$pages = max(1, (int)ceil($total / $pp));

$soldItems = $pdo->query("
    SELECT COALESCE(sl.title, inv.name) AS name, sc.name AS category, sl.price,
           COALESCE(sl.cover_image, inv.image) AS main_image
    FROM shop_listings sl
    JOIN inventory inv      ON inv.id = sl.inventory_id
    JOIN shop_categories sc ON sc.id = sl.category_id
    WHERE sl.status = 'sold' AND COALESCE(sl.cover_image, inv.image) IS NOT NULL
    ORDER BY sl.updated_at DESC LIMIT 4
")->fetchAll(PDO::FETCH_ASSOC);

$activeFilters = [];
if ($q !== '')   $activeFilters[] = ['label' => 'Search: "' . $q . '"', 'url' => build_url(['q' => null, 'page' => 1])];
if ($cat !== '') $activeFilters[] = ['label' => 'Category: ' . cat_label($cat), 'url' => build_url(['cat' => null, 'page' => 1])];

$page_title = 'Shop — Used Mac & iPhone, Graded & Warrantied | CMNS FixMac';
$meta_desc  = 'CMNS FixMac shop — graded used MacBook, iMac, iPhone, iPad. Carefully inspected, backed by a shop warranty. Pickup in Chiang Mai or nationwide shipping. Ask or order via LINE.';
$canonical  = 'https://cmnsfixmac.com/en/shop/' . ($cat !== '' ? '?cat=' . urlencode($cat) : '');
$switch_to_lang_url = '/shop/' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '');
$page_css = ['/assets/css/shop/shop.css?v=8', 'https://unpkg.com/aos@2.3.4/dist/aos.css'];

$itemListEl = [];
foreach ($items as $i => $row) {
    $itemListEl[] = [
        '@type' => 'ListItem', 'position' => $i + 1,
        'url'   => 'https://cmnsfixmac.com/en/shop/product-detail.php?id=' . (int)$row['id'],
        'name'  => $row['name'],
    ];
}
$breadcrumbEl = [
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => 'https://cmnsfixmac.com/en/'],
    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Shop', 'item' => 'https://cmnsfixmac.com/en/shop/'],
];
if ($cat !== '') $breadcrumbEl[] = ['@type' => 'ListItem', 'position' => 3, 'name' => cat_label($cat), 'item' => $canonical];

ob_start(); ?>
<meta name="description" content="<?= h($meta_desc) ?>">
<meta name="keywords" content="used Mac Chiang Mai, used MacBook, used iPhone, used iPad, used iMac, refurbished Apple Thailand, second hand Mac">
<meta name="robots" content="index, follow">
<link rel="canonical" href="<?= h($canonical) ?>">
<link rel="alternate" hreflang="th" href="https://cmnsfixmac.com/shop/">
<link rel="alternate" hreflang="en" href="https://cmnsfixmac.com/en/shop/">
<link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= h($page_title) ?>">
<meta property="og:description" content="<?= h($meta_desc) ?>">
<meta property="og:image" content="https://cmnsfixmac.com/assets/img/Logo1.png">
<meta property="og:url" content="<?= h($canonical) ?>">
<meta property="og:locale" content="en_US">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= h($page_title) ?>">
<meta name="twitter:description" content="<?= h($meta_desc) ?>">
<meta name="twitter:image" content="https://cmnsfixmac.com/assets/img/Logo1.png">
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@graph'   => [
        ['@type' => 'BreadcrumbList', 'itemListElement' => $breadcrumbEl],
        ['@type' => 'CollectionPage',
         'name'  => $page_title, 'description' => $meta_desc, 'url' => $canonical,
         'mainEntity' => ['@type' => 'ItemList', 'numberOfItems' => $total, 'itemListElement' => $itemListEl],
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php
$page_head_extra = ob_get_clean();

include_once __DIR__ . '/../../includes/header_en.php';
?>

<main>

<!-- ═══════════ HERO ═══════════ -->
<section class="shop-hero">
  <div class="shop-hero-dots" aria-hidden="true"></div>
  <div class="shop-hero-orb shop-orb-1" aria-hidden="true"></div>
  <div class="shop-hero-orb shop-orb-2" aria-hidden="true"></div>
  <div class="shop-bento">

    <!-- Headline + search -->
    <div class="bento-tile bento-head" data-aos="fade-up">
      <span class="sv-eyebrow"><span class="material-symbols-rounded">storefront</span> CMNS Mac · Shop</span>
      <h1>Graded used Mac &amp; iPhone,<br>with warranty</h1>
      <p>Every device is carefully inspected before listing. Ask or order via LINE.</p>
      <form class="shop-search" method="get" action="/en/shop/">
        <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search a model, e.g. MacBook Air M2, iPhone 13…" aria-label="Search products">
        <?php if ($cat !== ''): ?><input type="hidden" name="cat" value="<?= h($cat) ?>"><?php endif; ?>
        <button type="submit" aria-label="Search"><span class="material-symbols-rounded">search</span></button>
      </form>
    </div>

    <!-- Device showcase -->
    <div class="bento-tile bento-show" data-aos="fade-up" data-aos-delay="80">
      <div class="shop-hero-stage">
        <div class="shop-glow" aria-hidden="true"></div>
        <div class="shop-hero-grid" aria-hidden="true"></div>
        <img class="shop-dev shop-dev-mac"    src="/assets/img/shop/dev-macbook.png" alt="" aria-hidden="true" loading="lazy" decoding="async">
        <img class="shop-dev shop-dev-ipad"   src="/assets/img/shop/dev-ipad.png"    alt="" aria-hidden="true" loading="lazy" decoding="async">
        <img class="shop-dev shop-dev-iphone" src="/assets/img/shop/dev-iphone.png"  alt="" aria-hidden="true" loading="lazy" decoding="async">
      </div>
    </div>

    <!-- Sold counter -->
    <div class="bento-tile bento-sold" data-aos="fade-up" data-aos-delay="120">
      <span class="material-symbols-rounded">sell</span>
      <span class="bento-num counter" data-count="1857">0</span>
      <span class="bento-label2">devices sold</span>
    </div>

    <!-- Categories -->
    <div class="bento-tile bento-cats" data-aos="fade-up" data-aos-delay="160">
      <span class="bento-clabel">Shop by category</span>
      <div class="bento-cat-row">
        <a class="bento-cat" href="/en/shop/?cat=MacBook#shop-products"><span class="material-symbols-rounded">laptop_mac</span> MacBook</a>
        <a class="bento-cat" href="/en/shop/?cat=iPhone#shop-products"><span class="material-symbols-rounded">smartphone</span> iPhone</a>
        <a class="bento-cat" href="/en/shop/?cat=iPad#shop-products"><span class="material-symbols-rounded">tablet_mac</span> iPad</a>
        <a class="bento-cat" href="/en/shop/?cat=<?= urlencode('iMac / Mac mini') ?>#shop-products"><span class="material-symbols-rounded">desktop_mac</span> iMac</a>
      </div>
    </div>

    <!-- Shipping -->
    <div class="bento-tile bento-ship" data-aos="fade-up" data-aos-delay="200">
      <span class="material-symbols-rounded">local_shipping</span>
      <strong>Nationwide</strong>
      <span>Pickup in Chiang Mai / ship via Kerry-Grab</span>
    </div>

  </div>
</section>

<!-- ═══════════ TOOLBAR ═══════════ -->
<div class="shop-toolbar">
  <div class="shop-toolbar-inner">
    <div class="shop-chips" role="tablist" aria-label="Categories">
      <a href="<?= h(build_url(['cat' => null, 'page' => 1])) ?>#shop-products" class="shop-chip<?= $cat === '' ? ' active' : '' ?>">
        <span class="material-symbols-rounded">apps</span> All
      </a>
      <?php foreach ($categories as $c): ?>
      <a href="<?= h(build_url(['cat' => $c['name'], 'page' => 1])) ?>#shop-products"
         class="shop-chip<?= $cat === $c['name'] ? ' active' : '' ?>">
        <span class="material-symbols-rounded"><?= cat_icon($c['name']) ?></span> <?= h(cat_label($c['name'])) ?>
      </a>
      <?php endforeach; ?>
    </div>
    <div class="shop-sort">
      <label for="shop-sort-sel">Sort:</label>
      <select id="shop-sort-sel" onchange="location.href=this.value">
        <option value="<?= h(build_url(['sort' => null, 'page' => 1])) ?>#shop-products"      <?= $sort === 'new' ? 'selected' : '' ?>>Newest</option>
        <option value="<?= h(build_url(['sort' => 'price_asc', 'page' => 1])) ?>#shop-products"  <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: low → high</option>
        <option value="<?= h(build_url(['sort' => 'price_desc', 'page' => 1])) ?>#shop-products" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: high → low</option>
      </select>
    </div>
  </div>
</div>

<!-- ═══════════ PRODUCTS ═══════════ -->
<section class="shop-products" id="shop-products">
  <div class="sv-container">

    <?php if ($activeFilters): ?>
    <div class="shop-active">
      <span class="af-title">Filters:</span>
      <?php foreach ($activeFilters as $f): ?>
      <a class="shop-af-pill" href="<?= h($f['url']) ?>#shop-products"><?= h($f['label']) ?> <span class="material-symbols-rounded">close</span></a>
      <?php endforeach; ?>
      <a class="shop-af-pill clear-all" href="/en/shop/#shop-products"><span class="material-symbols-rounded">delete_sweep</span> Clear all</a>
    </div>
    <?php endif; ?>

    <h2 class="shop-count">All products <span>(<?= number_format($total) ?> items)</span></h2>

    <?php if (empty($items)): ?>
    <div class="shop-empty">
      <span class="material-symbols-rounded">inventory_2</span>
      <h3>No products in this category yet</h3>
      <p>Try clearing the filters, or tell us the model you want — we'll find it for you.</p>
      <a href="https://line.me/R/ti/p/@cmns" target="_blank" rel="noopener" class="btn btn-line">
        <img src="/assets/img/line-icon.png" alt="LINE" width="18" height="18"> Chat with us on LINE
      </a>
    </div>
    <?php else: ?>
    <ul class="shop-grid">
      <?php foreach ($items as $row):
        $url   = '/en/shop/product-detail.php?id=' . (int)$row['id'];
        $img   = img_path($row['main_image']);
        $price = (float)$row['price'];
        $old   = (float)($row['price_old'] ?? 0);
        $disc  = $old > $price ? $old - $price : 0;
        $pct   = $old > 0 ? round($disc / $old * 100) : 0;
      ?>
      <li class="shop-card">
        <a class="shop-card-link" href="<?= h($url) ?>">
          <div class="shop-card-media">
            <?php if ($disc > 0): ?><span class="shop-badge">-<?= $pct ?>%</span><?php endif; ?>
            <?php if ($img !== ''): ?>
              <img src="<?= h($img) ?>" alt="<?= h($row['name']) ?>" loading="lazy" decoding="async">
            <?php else: ?>
              <div class="shop-card-noimg"><span class="material-symbols-rounded">image</span></div>
            <?php endif; ?>
          </div>
          <div class="shop-card-body">
            <span class="shop-card-cat"><?= h(cat_label($row['category'])) ?></span>
            <h3 class="shop-card-title"><?= h($row['name']) ?></h3>
            <div class="shop-card-price-row">
              <span class="shop-card-price">฿<?= number_format($price) ?></span>
              <?php if ($old > $price): ?><span class="shop-card-old">฿<?= number_format($old) ?></span><?php endif; ?>
            </div>
          </div>
        </a>
        <div class="shop-card-foot">
          <a class="btn btn-ghost" href="<?= h($url) ?>">View details <span class="material-symbols-rounded">arrow_forward</span></a>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>

    <?php if ($pages > 1): ?>
    <nav class="shop-pager" aria-label="Pagination">
      <a class="<?= $page <= 1 ? 'disabled' : '' ?>" href="<?= h(build_url(['page' => $page - 1])) ?>#shop-products"><span class="material-symbols-rounded">chevron_left</span></a>
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i == $page): ?><span class="current"><?= $i ?></span>
        <?php else: ?><a href="<?= h(build_url(['page' => $i])) ?>#shop-products"><?= $i ?></a><?php endif; ?>
      <?php endfor; ?>
      <a class="<?= $page >= $pages ? 'disabled' : '' ?>" href="<?= h(build_url(['page' => $page + 1])) ?>#shop-products"><span class="material-symbols-rounded">chevron_right</span></a>
    </nav>
    <?php endif; ?>
    <?php endif; ?>

  </div>
</section>

<!-- ═══════════ RECENTLY SOLD ═══════════ -->
<?php if ($soldItems): ?>
<section class="sv-section shop-sold">
  <div class="sv-container">
    <div class="sv-section-head">
      <span class="section-label">Sold</span>
      <h2>Recently sold devices</h2>
      <p class="sv-desc">Stock moves fast — see a model you like? Message us to reserve it first.</p>
    </div>
    <ul class="shop-grid">
      <?php foreach ($soldItems as $row): $img = img_path($row['main_image']); ?>
      <li class="shop-card">
        <div class="shop-card-link">
          <div class="shop-card-media">
            <?php if ($img !== ''): ?><img src="<?= h($img) ?>" alt="<?= h($row['name']) ?>" loading="lazy"><?php else: ?><div class="shop-card-noimg"><span class="material-symbols-rounded">image</span></div><?php endif; ?>
          </div>
          <div class="shop-card-body">
            <span class="shop-card-cat"><?= h(cat_label($row['category'])) ?></span>
            <h3 class="shop-card-title"><?= h($row['name']) ?></h3>
          </div>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<!-- ═══════════ WHY BUY ═══════════ -->
<section class="sv-section shop-why">
  <div class="sv-container">
    <div class="sv-section-head" data-aos="fade-up">
      <span class="section-label">Why buy from us</span>
      <h2>Buying used with CMNS Mac feels safer</h2>
      <p class="sv-desc">Every device passes through our technicians and is thoroughly checked before handover.</p>
    </div>
    <div class="shop-why-grid">
      <?php foreach ([
          ['fact_check', '30+ point inspection', 'Every function checked by a technician, flaws disclosed honestly.'],
          ['verified',   'Shop warranty',        'After-sales warranty — easy claims if anything goes wrong.'],
          ['swap_horiz', 'Trade-in & buyback',   'Trade your old device for a discount, or sell it back to us.'],
          ['bolt',       'Fast replies',         'Message us on LINE — quick quotes and easy pickup.'],
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
<section class="sv-cta">
  <div class="sv-container">
    <div class="sv-cta-inner" data-aos="fade-up">
      <span class="material-symbols-rounded sv-cta-icon">storefront</span>
      <h2>Can't find the right model?</h2>
      <p>Tell us the model and budget you want and we'll source a graded unit for you<br>— or sell us your device, we buy too.</p>
      <div class="sv-cta-btns">
        <a href="https://line.me/R/ti/p/@cmns" target="_blank" rel="noopener" class="btn btn-line">
          <img src="/assets/img/line-icon.png" alt="LINE" width="18" height="18"> Chat with us on LINE
        </a>
        <a href="tel:0841511684" class="btn btn-accent"><span class="material-symbols-rounded">call</span> 084-151-1684</a>
        <a href="/en/buyback/" class="btn btn-ghost"><span class="material-symbols-rounded">sell</span> Sell us your device</a>
      </div>
    </div>
  </div>
</section>

</main>

<?php include_once __DIR__ . '/../../includes/footer_en.php'; ?>
<script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
<script>
AOS.init({ duration: 700, once: true, offset: 60 });

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
</script>
</body>
</html>
