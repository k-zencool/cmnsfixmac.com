<?php
require_once __DIR__ . '/../includes/db.php';

defined('BASE_URL') or define('BASE_URL', 'https://cmnsfixmac.com');
const LINE_ID = '@cmns';

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function getv($k, $d = null) { return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }
function img_path($raw) {
    $img = trim((string)($raw ?? ''));
    if ($img !== '' && $img[0] !== '/' && !preg_match('~^https?://~', $img)) $img = '/' . ltrim($img, '/');
    return $img;
}

$id = max(0, (int)getv('id', 0));
$product = null; $all_images = []; $specs = []; $related = []; $error = '';

if ($id > 0) {
    $st = $pdo->prepare("
        SELECT sl.id, sl.slug, sl.category_id,
               COALESCE(sl.title, inv.name)        AS title,
               sc.name                             AS brand,
               sl.price, sl.price_original         AS price_old,
               sl.description,
               COALESCE(sl.cover_image, inv.image) AS main_image,
               IF(inv.status = 'SOLD', 0, 1)       AS in_stock,
               inv.sku, inv.serial_number, inv.color, inv.condition_grade,
               inv.cpu_spec, inv.ram_spec, inv.storage_spec, inv.gpu_spec,
               inv.battery_health, inv.store_warranty_days, inv.condition_note
        FROM shop_listings sl
        JOIN inventory inv      ON inv.id = sl.inventory_id
        JOIN shop_categories sc ON sc.id = sl.category_id
        WHERE sl.id = :id AND sl.status = 'published'
    ");
    $st->execute([':id' => $id]);
    $product = $st->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        $g = $pdo->prepare("SELECT url FROM shop_images WHERE listing_id = ? ORDER BY sort_order ASC");
        $g->execute([$id]);
        $gallery = $g->fetchAll(PDO::FETCH_COLUMN);
        $all_images = array_values(array_unique(array_filter(
            array_map('img_path', array_merge([$product['main_image']], $gallery))
        )));

        $specMap = [
            'ชิป / CPU'   => $product['cpu_spec'],
            'RAM'         => $product['ram_spec'],
            'ความจุ'      => $product['storage_spec'],
            'การ์ดจอ'     => $product['gpu_spec'],
            'สี'          => $product['color'],
            'สภาพเครื่อง' => !empty($product['condition_grade']) ? 'Grade ' . $product['condition_grade'] : null,
            'สุขภาพแบต'   => !empty($product['battery_health']) ? $product['battery_health'] . '%' : null,
            'ประกันร้าน'  => !empty($product['store_warranty_days']) ? $product['store_warranty_days'] . ' วัน' : null,
            'Serial'      => $product['serial_number'],
        ];
        foreach ($specMap as $k => $v) { if ($v !== null && $v !== '') $specs[$k] = $v; }

        $r = $pdo->prepare("
            SELECT sl2.id, COALESCE(sl2.title, inv2.name) AS name, sc2.name AS category,
                   sl2.price, sl2.price_original AS price_old,
                   COALESCE(sl2.cover_image, inv2.image) AS main_image
            FROM shop_listings sl2
            JOIN inventory inv2      ON inv2.id = sl2.inventory_id
            JOIN shop_categories sc2 ON sc2.id = sl2.category_id
            WHERE sl2.category_id = :cat AND sl2.id != :id
              AND sl2.status = 'published' AND inv2.status != 'SOLD'
            ORDER BY RAND() LIMIT 4
        ");
        $r->execute([':cat' => $product['category_id'], ':id' => $id]);
        $related = $r->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $error = 'ไม่พบสินค้าที่ตรงกัน';
    }
} else {
    $error = 'ID สินค้าไม่ถูกต้อง';
}

if ($error) {
    http_response_code(404);
    $page_title = 'ไม่พบสินค้า — CMNS FixMac';
    include_once __DIR__ . '/../includes/header.php';
    ?>
    <main>
      <section class="sv-section">
        <div class="sv-container">
          <div class="shop-empty" style="max-width:560px;margin:40px auto;">
            <span class="material-symbols-rounded">search_off</span>
            <h3><?= h($error) ?></h3>
            <p>สินค้าอาจถูกขายไปแล้ว หรือลิงก์ไม่ถูกต้อง</p>
            <a href="/shop/" class="btn btn-accent"><span class="material-symbols-rounded">storefront</span> กลับไปหน้าร้านค้า</a>
          </div>
        </div>
      </section>
    </main>
    <?php
    include_once __DIR__ . '/../includes/footer.php';
    echo '</body></html>';
    exit;
}

/* ---------- Product present ---------- */
$canonical = BASE_URL . '/shop/product-detail.php?id=' . $id;
$price = (float)$product['price'];
$old   = (float)($product['price_old'] ?? 0);
$disc  = $old > $price ? $old - $price : 0;
$pct   = $old > 0 ? round($disc / $old * 100) : 0;
$clean_desc = trim(preg_replace('/\s+/', ' ', strip_tags($product['description'] ?? '')));
$meta_desc  = mb_substr($clean_desc, 0, 155) ?: ($product['title'] . ' มือสองคุณภาพดี พร้อมรับประกันร้าน');
$og_image   = !empty($all_images[0]) && $all_images[0][0] === '/' ? BASE_URL . $all_images[0] : BASE_URL . '/assets/img/Logo1.png';

$line_msg = "สนใจสินค้า: {$product['title']} (฿" . number_format($price) . ")\n$canonical";
$line_url = 'https://line.me/R/oaMessage/' . LINE_ID . '/?' . rawurlencode($line_msg);

$page_title = h($product['title']) . ' | ร้านค้า CMNS FixMac';
$page_css   = ['/assets/css/shop/shop.css?v=8', 'https://unpkg.com/aos@2.3.4/dist/aos.css'];

ob_start(); ?>
<meta name="description" content="<?= h($meta_desc) ?>">
<meta name="robots" content="index, follow">
<link rel="canonical" href="<?= h($canonical) ?>">
<link rel="alternate" hreflang="th" href="<?= h($canonical) ?>">
<link rel="alternate" hreflang="en" href="<?= BASE_URL ?>/en/shop/product-detail.php?id=<?= $id ?>">
<link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png">
<meta property="og:type" content="product">
<meta property="og:title" content="<?= h($product['title']) ?>">
<meta property="og:description" content="<?= h($meta_desc) ?>">
<meta property="og:image" content="<?= h($og_image) ?>">
<meta property="og:url" content="<?= h($canonical) ?>">
<meta property="og:locale" content="th_TH">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= h($product['title']) ?>">
<meta name="twitter:description" content="<?= h($meta_desc) ?>">
<meta name="twitter:image" content="<?= h($og_image) ?>">
<meta property="product:price:amount" content="<?= $price ?>">
<meta property="product:price:currency" content="THB">
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@graph'   => [
        ['@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'หน้าแรก',  'item' => BASE_URL . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'ร้านค้า',   'item' => BASE_URL . '/shop/'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $product['brand'], 'item' => BASE_URL . '/shop/?cat=' . urlencode($product['brand'])],
            ['@type' => 'ListItem', 'position' => 4, 'name' => $product['title'], 'item' => $canonical],
        ]],
        ['@type' => 'Product',
         'name' => $product['title'], 'description' => $meta_desc, 'image' => $og_image,
         'sku' => $product['sku'] ?: ('SL' . $id),
         'brand' => ['@type' => 'Brand', 'name' => 'Apple'],
         'offers' => [
             '@type' => 'Offer', 'priceCurrency' => 'THB', 'price' => $price,
             'availability' => $product['in_stock'] ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
             'url' => $canonical, 'itemCondition' => 'https://schema.org/UsedCondition',
             'seller' => ['@type' => 'Organization', 'name' => 'CMNS FixMac'],
         ],
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php
$page_head_extra = ob_get_clean();

include_once __DIR__ . '/../includes/header.php';
?>

<main>
<div class="pd-wrap">
  <div class="sv-container">

    <nav class="pd-breadcrumb" aria-label="breadcrumb">
      <a href="/">หน้าแรก</a><span class="material-symbols-rounded">chevron_right</span>
      <a href="/shop/">ร้านค้า</a><span class="material-symbols-rounded">chevron_right</span>
      <a href="/shop/?cat=<?= h(urlencode($product['brand'])) ?>"><?= h($product['brand']) ?></a><span class="material-symbols-rounded">chevron_right</span>
      <span><?= h($product['title']) ?></span>
    </nav>

    <div class="pd-grid" data-aos="fade-up">

      <!-- Gallery -->
      <div class="pd-gallery">
        <div class="pd-main-img">
          <?php if (!empty($all_images)): ?>
            <img id="pd-main" src="<?= h($all_images[0]) ?>" alt="<?= h($product['title']) ?>">
          <?php else: ?>
            <div class="pd-main-noimg"><span class="material-symbols-rounded">image</span></div>
          <?php endif; ?>
        </div>
        <?php if (count($all_images) > 1): ?>
        <div class="pd-thumbs">
          <?php foreach ($all_images as $i => $im): ?>
          <div class="pd-thumb<?= $i === 0 ? ' active' : '' ?>" onclick="pdSwap(this,'<?= h($im) ?>')">
            <img src="<?= h($im) ?>" alt="<?= h($product['title']) ?> รูปที่ <?= $i + 1 ?>" loading="lazy">
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Info -->
      <div class="pd-info">
        <span class="pd-cat"><?= h($product['brand']) ?></span>
        <h1 class="pd-title"><?= h($product['title']) ?></h1>
        <div class="pd-price-row">
          <span class="pd-price">฿<?= number_format($price) ?></span>
          <?php if ($old > $price): ?>
            <span class="pd-old">฿<?= number_format($old) ?></span>
            <span class="pd-discount">-<?= $pct ?>%</span>
          <?php endif; ?>
        </div>
        <?php if ($product['in_stock']): ?>
          <div class="pd-stock in"><span class="material-symbols-rounded">check_circle</span> พร้อมขาย</div>
        <?php else: ?>
          <div class="pd-stock out"><span class="material-symbols-rounded">cancel</span> ขายแล้ว</div>
        <?php endif; ?>

        <?php if ($specs): ?>
        <ul class="pd-specs">
          <?php foreach ($specs as $k => $v): ?>
          <li><span class="pd-spec-key"><?= h($k) ?></span><span class="pd-spec-val"><?= h($v) ?></span></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <div class="pd-cta">
          <a href="<?= h($line_url) ?>" target="_blank" rel="noopener" class="btn btn-line btn-block">
            <img src="/assets/img/line-icon.png" alt="LINE" width="18" height="18"> สอบถาม / สั่งซื้อผ่าน LINE
          </a>
          <div class="pd-cta-row">
            <a href="tel:0841511684" class="btn btn-ghost"><span class="material-symbols-rounded">call</span> โทรสอบถาม</a>
            <a href="https://www.facebook.com/CmnsShop" target="_blank" rel="noopener" class="btn btn-ghost"><span class="material-symbols-rounded">thumb_up</span> Messenger</a>
          </div>
          <p class="pd-note">เครื่องคัดเกรด ตรวจสภาพก่อนส่งมอบ · นัดรับเชียงใหม่หรือส่งทั่วประเทศ · กดปุ่ม LINE แล้วชื่อรุ่นจะถูกแนบไปให้อัตโนมัติ</p>
        </div>
      </div>

    </div>

    <?php if (trim((string)$product['description']) !== ''): ?>
    <section class="pd-desc-section">
      <h2>รายละเอียดสินค้า</h2>
      <div class="pd-desc"><?= $product['description'] ?></div>
    </section>
    <?php endif; ?>

    <?php if ($related): ?>
    <section class="pd-related">
      <h2>สินค้าใกล้เคียง</h2>
      <ul class="shop-grid">
        <?php foreach ($related as $row):
          $rurl = '/shop/product-detail.php?id=' . (int)$row['id'];
          $rimg = img_path($row['main_image']);
          $rp = (float)$row['price']; $ro = (float)($row['price_old'] ?? 0);
        ?>
        <li class="shop-card">
          <a class="shop-card-link" href="<?= h($rurl) ?>">
            <div class="shop-card-media">
              <?php if ($rimg !== ''): ?><img src="<?= h($rimg) ?>" alt="<?= h($row['name']) ?>" loading="lazy"><?php else: ?><div class="shop-card-noimg"><span class="material-symbols-rounded">image</span></div><?php endif; ?>
            </div>
            <div class="shop-card-body">
              <span class="shop-card-cat"><?= h($row['category']) ?></span>
              <h3 class="shop-card-title"><?= h($row['name']) ?></h3>
              <div class="shop-card-price-row">
                <span class="shop-card-price">฿<?= number_format($rp) ?></span>
                <?php if ($ro > $rp): ?><span class="shop-card-old">฿<?= number_format($ro) ?></span><?php endif; ?>
              </div>
            </div>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>

  </div>
</div>
</main>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
<script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
<script>
AOS.init({ duration: 700, once: true, offset: 60 });
function pdSwap(el, src) {
  var main = document.getElementById('pd-main');
  if (main) main.src = src;
  document.querySelectorAll('.pd-thumb').forEach(function(t){ t.classList.remove('active'); });
  el.classList.add('active');
}
</script>
</body>
</html>
