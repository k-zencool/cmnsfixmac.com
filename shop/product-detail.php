<?php
require_once __DIR__ . '/../includes/db.php';

// !!! กูเพิ่มตรงนี้ให้ มึงจะได้ไม่ต้องไปยุ่งกับ db.php !!!
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
$q = getv('q', ''); // <-- กูนแอบเพิ่มตรงนี้ กัน error ตรงช่อง search


/* ---------- Get Product ID ---------- */
$id = max(0, (int)getv('id', 0));

/* ---------- Init Vars ---------- */
$product = null;
$attrs = [];
$all_images = [];
$related_items = [];
$error = '';
$tags = []; // For storing tags

/* ---------- Fetch Data (Logic เดิมของมึง) ---------- */
if ($id > 0) {
  // 1. Fetch Main Product
  $sqlProduct = "
    SELECT l.*,
           JSON_UNQUOTE(JSON_EXTRACT(l.attrs, '$.description')) as description,
           JSON_UNQUOTE(JSON_EXTRACT(l.attrs, '$.meta_description')) as meta_description,
           JSON_UNQUOTE(JSON_EXTRACT(l.attrs, '$.tags')) as tags_json
    FROM listings l
    WHERE l.id = :id AND l.status = 'published'
  ";
  $st = $pdo->prepare($sqlProduct);
  $st->execute([':id' => $id]);
  $product = $st->fetch(PDO::FETCH_ASSOC);

  if ($product) {
    // 2. Fetch Attributes
    $sqlAttrs = "
      SELECT a.key_name, v.value_string, v.value_int
      FROM listing_attr_values v
      JOIN attrs a ON a.id = v.attr_id
      WHERE v.listing_id = :id
      ORDER BY a.id
    ";
    $st = $pdo->prepare($sqlAttrs);
    $st->execute([':id' => $id]);
    $attrs = $st->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch Gallery Images
    $sqlGallery = "
      SELECT url FROM listing_images
      WHERE listing_id = :id ORDER BY sort_order
    ";
    $st = $pdo->prepare($sqlGallery);
    $st->execute([':id' => $id]);
    $gallery_images = $st->fetchAll(PDO::FETCH_COLUMN);

    // Build final image list
    $all_images = [];
    if (!empty($product['main_image'])) {
      $all_images[] = $product['main_image'];
    }
    foreach ($gallery_images as $img) {
      if (!empty($img) && !in_array($img, $all_images)) {
        $all_images[] = $img;
      }
    }
    if (empty($all_images)) {
      $all_images[] = '';
    }

    // 4. Fetch Related Products
    $sqlRelated = "
      SELECT l.id, l.title name, l.brand as category, l.price, l.price_old, l.stock_qty, l.created_at, l.main_image
      FROM listings l
      WHERE l.brand = :brand
        AND l.id != :id
        AND l.status = 'published'
        AND l.in_stock = 1
      ORDER BY RAND()
      LIMIT 4
    ";
    $st = $pdo->prepare($sqlRelated);
    $st->execute([':brand' => $product['brand'], ':id' => $id]);
    $related_items = $st->fetchAll(PDO::FETCH_ASSOC);

    // 5. Prepare Tags
    if (!empty($product['brand'])) {
      $tags[] = $product['brand'];
    }
    if (!empty($product['tags_json'])) {
      $json_tags = json_decode($product['tags_json'], true);
      if (is_array($json_tags)) {
        $tags = array_merge($tags, $json_tags);
      }
    }
    foreach ($attrs as $attr) {
      if ($attr['key_name'] === 'year' && !empty($attr['value_int'])) {
        $tags[] = (string)$attr['value_int'];
        break;
      }
    }
    $tags = array_unique(array_filter($tags));
  } else {
    $error = "ไม่พบสินค้าที่ตรงกัน";
  }
} else {
  $error = "ID สินค้าไม่ถูกต้อง";
}

if ($error) {
  http_response_code(404);
}
/* ---------- Lang link (แก้ให้ใช้ BASE_URL) ---------- */
$request_uri = $_SERVER['REQUEST_URI'];
if (!$product) {
  $request_uri = '/shop/';
}
$en_version_url = rtrim(BASE_URL, '/') . '/en' . $request_uri;
$en_version_url = str_replace('/index.php', '/', $en_version_url);

/* ---------- SEO & Page Vars (แก้ให้ใช้ BASE_URL) ---------- */
$page_title = "ไม่พบสินค้า";
$meta_description = "ไม่พบสินค้าที่คุณค้นหา กรุณาลองใหม่ หรือกลับไปที่หน้าสินค้ารวม";
$og_image = BASE_URL . "/assets/img/Logo1.png"; // <-- ใช้ BASE_URL
$name = ''; // [!!] กูแก้ตรงนี้: ประกาศให้มีค่าว่างไว้ก่อน [!!]

if ($product) {
  // [!!] กูแก้ตรงนี้: ย้าย $name มาข้างใน if [!!]
  $name = $product['title'];
  $clean_desc = trim(preg_replace('/\s+/', ' ', strip_tags($product['description'] ?? '')));
  $page_title = h($name); // ใช้ $name
  $meta_description = h($product['meta_description'] ?: mb_substr($clean_desc, 0, 155));
  $main_img_path = $all_images[0] ?? '';
  if ($main_img_path && substr($main_img_path, 0, 1) === '/') {
    $og_image = BASE_URL . h($main_img_path); // <-- ใช้ BASE_URL
  }
}
// [!!] กูแก้ตรงนี้: ลบ else ทิ้ง [!!]
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title><?= $page_title ?> | CMNS FixMac</title>
  <meta name="description" content="<?= $meta_description ?>">

  <?php // (แก้ให้ใช้ BASE_URL) 
  ?>
  <?php if ($product): ?>
    <link rel="canonical" href="<?= BASE_URL ?>/shop/product-detail.php?id=<?= $id ?>" />
  <?php else: ?>
    <link rel="canonical" href="<?= BASE_URL ?>/shop/" />
  <?php endif; ?>

  <meta property="og:title" content="<?= $page_title ?> | CMNS FixMac" />
  <meta property="og:description" content="<?= $meta_description ?>" />
  <meta property="og:image" content="<?= $og_image ?>" />
  <?php if ($product): ?>
    <meta property="og:url" content="<?= BASE_URL ?>/shop/product-detail.php?id=<?= $id ?>" />
    <meta property="og:type" content="product" />
  <?php endif; ?>

  <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/img/favicon1.png" /> <?php // <-- ใช้ BASE_URL 
                                                                              ?>

  <?php // (แก้ Path ให้เป็น Absolute Path ให้หมด จะได้ไม่พัง) 
  ?>
  <link rel="stylesheet" href="/shop/assets/css/shop-style.css">
  <link rel="stylesheet" href="/shop/assets/css/hero.css">
  <link rel="stylesheet" href="/shop/assets/css/cart-receipt.css">
  <link rel="stylesheet" href="/shop/assets/css/product-detail.css">

  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />

  <?php
  // --- Generate Keywords (Logic เดิม) ---
  $keywords = [];
  if ($product) {
    $keywords[] = h($product['title']);
    $keywords[] = h($product['brand']);
    $keywords[] = 'มือสอง';
    $keywords[] = 'เชียงใหม่';
    $keywords[] = 'CMNS FixMac';
    $title_parts = explode(' ', $product['title']);
    foreach ($title_parts as $part) {
      $part = trim($part, ',./\\()[]{}<>?!"\'');
      if (mb_strlen($part) > 2 && !is_numeric($part)) {
        $keywords[] = h($part);
      }
    }
    foreach ($attrs as $attr) {
      $value = $attr['value_string'] ?: $attr['value_int'];
      if ($value) {
        $keywords[] = h($value);
      }
    }
    if (!in_array(h($product['brand']), $keywords)) {
      $keywords[] = h($product['brand']);
    }
    $keywords[] = 'Apple';
    $keywords = array_unique(array_filter($keywords));
    $keyword_string = implode(', ', array_slice($keywords, 0, 20));
  } else {
    $keyword_string = 'Apple, มือสอง, เชียงใหม่, MacBook, iPhone, iPad, ซ่อม, ขาย';
  }
  ?>
  <meta name="keywords" content="<?= $keyword_string ?>">

</head>

<body>

  <?php // <--! START: HEADER/NAVBAR SECTION (กูยัดใส่ให้แล้ว) !--> 
  ?>
  <header class="navbar navbar-top">
    <div class="nav-container">
      <div class="nav-logo">
        <a href="/shop/"><img src="/assets/img/Logo1.png" alt="CMNS FixMac Logo"></a>
      </div>
      <div class="menu-desktop-only">
        <nav class="menu">
          <a href="/" class="highlight-home"><span class="material-symbols-rounded">home</span> หน้าแรก</a>
          <a href="/shop/"><span class="material-symbols-rounded">storefront</span> ร้านค้า</a>
          <a href="/works.php"><span class="material-symbols-rounded">construction</span> ผลงาน</a>
          <a href="/articles.php"><span class="material-symbols-rounded">description</span> บทความ</a>
          <a href="/buyback.php"><span class="material-symbols-rounded">laptop_mac</span> รับซื้อเครื่อง</a>
          <a href="/warranty.php"><span class="material-symbols-rounded">verified</span> ตรวจสอบประกัน</a>
        </nav>
      </div>
      <div class="nav-actions">
        <form class="nav-search search-form" action="/shop/" method="get" role="search">
          <input type="text" name="q" value="<?= h($q) ?>" placeholder="ค้นหาสินค้า...">
          <button type="submit" aria-label="ค้นหา"><span class="material-symbols-rounded">search</span></button>
        </form>

        <a href="#!" class="nav-cart" aria-label="ตะกร้า">
          <span class="material-symbols-rounded">shopping_cart</span>
          <span class="cart-count">0</span>
        </a>

        <?php // $en_version_url ถูก define ไว้ที่ต้นไฟล์นี้แล้ว 
        ?>
        <a href="<?= h($en_version_url) ?>" class="language-switch-btn" title="Switch to English">
          <span class="material-symbols-rounded">language</span> EN
        </a>

        <button id="hamburger" class="hamburger" type="button" onclick="toggleSidebar()" aria-label="เมนู">
          <span></span><span></span><span></span>
        </button>
      </div>

      <div id="sidebar" class="sidebar">
        <div class="sidebar-header">
          <a href="/"><img src="/assets/img/Logo1.png" alt="CMNS FixMac Logo" style="height:36px; margin-bottom:16px;"></a>
          <span class="close-btn" onclick="toggleSidebar()">✕</span>
        </div>

        <nav class="sidebar-menu">
          <a href="/" class="highlight-home"><span class="material-symbols-rounded">home</span> หน้าแรก</a>
          <a href="/works.php"><span class="material-symbols-rounded">construction</span> ผลงาน</a>
          <a href="/shop/"><span class="material-symbols-rounded">storefront</span> ร้านค้า</a>
          <a href="/articles.php"><span class="material-symbols-rounded">description</span> บทความ</a>
          <a href="/buyback.php"><span class="material-symbols-rounded">laptop_mac</span> รับซื้อเครื่อง</a>
          <a href="/warranty.php"><span class="material-symbols-rounded">verified</span> ตรวจสอบประกัน</a>
          <a href="tel:0841511684"><span class="material-symbols-rounded">call</span> โทรเลย</a>
          <?php
          // $en_version_url ถูก define ไว้ที่ต้นไฟล์นี้แล้ว
          // แต่เราใช้ตัวแปรเดิมซ้ำได้เลย ไม่ต้องประกาศ $en_version_url_sidebar อีก
          ?>
          <a href="<?= h($en_version_url) ?>" class="language-switch-btn" title="Switch to English">
            <span class="material-symbols-rounded">language</span> EN
          </a>
        </nav>

        <div class="sidebar-dropdown">
          <button class="dropdown-toggle" onclick="toggleSidebarDropdown(this)">
            <span class="material-symbols-rounded">smart_toy</span> ทดสอบอุปกรณ์
            <span class="material-symbols-rounded dropdown-icon">expand_more</span>
          </button>
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
  <?php // <--! END: HEADER/NAVBAR SECTION !--> 
  ?>





  <main class="pdp-container">


    <nav class="pdp-breadcrumb" aria-label="breadcrumb">
      <ol class="breadcrumb-list">
        <li>
          <a href="/" title="หน้าแรก">
            <span class="material-symbols-rounded" aria-hidden="true">home</span>
          </a>
        </li>
        <li>
          <span class="breadcrumb-sep" aria-hidden="true">&gt;</span>
          <a href="/shop/">ร้านค้า</a>
        </li>

        <?php // ถ้ามีแบรนด์ (หมวดหมู่) และมันไม่ error 
        ?>
        <?php if ($product && !empty($product['brand'])): ?>
          <li>
            <span class="breadcrumb-sep" aria-hidden="true">&gt;</span>
            <a href="/shop/?cat=<?= urlencode($product['brand']) ?>"><?= h($product['brand']) ?></a>
          </li>
        <?php endif; ?>

        <?php // ถ้ามีสินค้า (อยู่หน้าสินค้า) 
        ?>
        <?php if ($product): ?>
          <li>
            <span class="breadcrumb-sep" aria-hidden="true">&gt;</span>
            <?php // ใช้ $name ที่มึงประกาศไว้ต้นไฟล์ได้เลย 
            ?>
            <?php // กูตัดคำให้ กันมันยาวเกิน 
            ?>
            <span class="breadcrumb-current" aria-current="page"><?= h(mb_strimwidth($name, 0, 50, "...")) ?></span>
          </li>
        <?php endif; ?>

      </ol>
    </nav>
    <?php if ($error): ?>
      <div class="pdp-error">
        <h1><?= h($error) ?></h1>
        <p>ไม่พบสินค้าที่คุณค้นหา หรือสินค้านี้อาจจะถูกลบไปแล้ว</p>
        <a href="/shop/" class="btn-line-full" style="display:inline-flex; width:auto; padding: 0 20px;"><span class="material-symbols-rounded">storefront</span> กลับไปหน้าสินค้าทั้งหมด</a>
      </div>

    <?php elseif ($product): ?>
      <?php
      // --- Prepare vars (Logic เดิม) ---
      $url   = '/shop/product-detail.php?id=' . (int)$product['id'];
      $img   = $all_images[0];
      $name  = $product['title'];
      $brand = $product['brand'] ?: 'ไม่ระบุแบรนด์';
      $sku   = $product['sku'] ?? null;
      $price = (float)$product['price'];
      $old   = (float)($product['price_old'] ?? 0);
      $disc  = $old > $price ? ($old - $price) : 0;
      $pct   = $old > 0 ? round($disc / $old * 100) : 0;
      $qty   = (int)($product['stock_qty'] ?? 0);
      $in_stock = $product['in_stock'] && $qty > 0;

      $stock_class = 'out-stock';
      $stock_text = 'สินค้าหมด';
      if ($in_stock) {
        if ($qty <= 2) {
          $stock_class = 'low-stock';
          $stock_text = "เหลือ $qty ชิ้น";
        } else {
          $stock_class = 'in-stock';
          $stock_text = 'มีสินค้า';
        }
      }

      // Icons (Logic เดิม)
      $fallback_icon_html = '<div class="pdp-gallery-icon"><span class="material-symbols-rounded" aria-hidden="true">image</span></div>';
      $thumb_icon_html = '<div class="pdp-gallery-icon"><span class="material-symbols-rounded" aria-hidden="true">image</span></div>';

      // Function to render image (Logic เดิม)
      function render_pdp_image($src, $alt, $class, $icon_html)
      {
        $alt = h($alt);
        if (empty($src)) {
          return $icon_html;
        }
        if (substr($src, 0, 1) !== '/' && !preg_match('~^https?://~', $src)) {
          $src = '/' . ltrim($src, '/');
        }
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
              <?php foreach ($all_images as $i => $thumb_src): ?>
                <?php
                if (!empty($thumb_src) && substr($thumb_src, 0, 1) !== '/' && !preg_match('~^https?://~', $thumb_src)) {
                  $thumb_src = '/' . ltrim($thumb_src, '/');
                }
                ?>
                <div class="pdp-thumb-item <?= $i === 0 ? 'is-active' : '' ?>" data-index="<?= $i ?>" data-full-src="<?= h($thumb_src) ?>">
                  <?= render_pdp_image($thumb_src, "Thumbnail $i", 'pdp-thumb-img', $thumb_icon_html) ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="pdp-info">
          <div class="pdp-cat"><?= h($brand) ?></div>
          <h1 class="pdp-title"><?= h($name) ?></h1>
          <div class="pdp-meta">
            <span>แบรนด์: <?= h($brand) ?></span>
            <?php if ($sku): ?><span>SKU: <?= h($sku) ?></span><?php endif; ?>
          </div>

          <div class="pdp-sub-actions">
            <button type="button"><span class="material-symbols-rounded">favorite</span> เพิ่มเป็นรายการโปรด</button>
            <a href="#"><span class="material-symbols-rounded">share</span> แชร์</a>
          </div>

          <div class="pdp-price-box">
            <span class="pdp-price-now">฿<?= number_format($price, 0) ?></span>
            <?php if ($disc > 0): ?>
              <span class="pdp-price-old">฿<?= number_format($old, 0) ?></span>
              <span class="pdp-discount-badge">ลด <?= number_format($disc, 0) ?> ฿ (-<?= $pct ?>%)</span>
            <?php endif; ?>
          </div>

          <div class="pdp-qty-selector">
            <label for="pdp-qty">จำนวน:</label>
            <div class="pdp-qty-input-wrap">
              <button type="button" class="pdp-qty-btn minus" aria-label="ลดจำนวน">-</button>
              <input type="number" id="pdp-qty" class="pdp-qty-input" value="1" min="1" max="<?= $in_stock ? $qty : 1 ?>" aria-label="จำนวน">
              <button type="button" class="pdp-qty-btn plus" aria-label="เพิ่มจำนวน">+</button>
            </div>
            <span class="pdp-stock <?= $stock_class ?>" style="margin-left: 10px; margin-bottom: 0;">(<?= $stock_text ?>)</span>
          </div>

          <div class="pdp-actions">
            <?php if ($in_stock): ?>
              <button class="pdp-add-to-cart-btn card-cart-btn" type="button" aria-label="ใส่ตะกร้า" data-id="<?= (int)$product['id'] ?>" data-name="<?= h($name) ?>" data-price="<?= (float)$price ?>" data-img="<?= h($img) ?>" data-url="<?= h($url) ?>">
                <span class="material-symbols-rounded" aria-hidden="true">add_shopping_cart</span> เพิ่มลงตะกร้า
              </button>
            <?php else: ?>
              <button class="pdp-add-to-cart-btn" type="button" style="background: #aaa; color:#fff; cursor: pointer;"><span class="material-symbols-rounded">notifications</span> แจ้งเตือนเมื่อมีสินค้า</button>
            <?php endif; ?>
          </div>

          <?php if ($tags): ?>
            <div class="pdp-tags">
              <?php foreach ($tags as $tag): ?>
                <a href="/shop/?q=<?= urlencode($tag) ?>" class="pdp-tag-item">#<?= h($tag) ?></a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

        </div>
      </div>
      <div class="pdp-tabs">
        <nav class="pdp-tab-nav" role="tablist">
          <?php if (!empty($product['description'])): ?>
            <button class="pdp-tab-btn is-active" id="tab-desc-btn" role="tab" aria-selected="true" aria-controls="tab-desc-content">รายละเอียดสินค้า</button>
          <?php endif; ?>
          <?php if ($attrs): ?>
            <button class="pdp-tab-btn <?= empty($product['description']) ? 'is-active' : '' ?>" id="tab-spec-btn" role="tab" aria-selected="<?= empty($product['description']) ? 'true' : 'false' ?>" aria-controls="tab-spec-content">คุณสมบัติ / สเปค</button>
          <?php endif; ?>
        </nav>

        <?php if (!empty($product['description'])): ?>
          <div class="pdp-tab-content is-active pdp-description" id="tab-desc-content" role="tabpanel" aria-labelledby="tab-desc-btn">
            <?= $product['description'] // <--- เอา h() ออกแล้ว! 
            ?>
          </div>
        <?php endif; ?>

        <?php if ($attrs): ?>
          <div class="pdp-tab-content <?= empty($product['description']) ? 'is-active' : '' ?>" id="tab-spec-content" role="tabpanel" aria-labelledby="tab-spec-btn">
            <table class="pdp-specs-table">
              <tbody>
                <?php
                $key_to_label_map = ['ram_gb' => 'RAM', 'ssd_gb' => 'ความจุ SSD', 'year' => 'ปี', 'color' => 'สี', 'model' => 'รุ่น', 'cpu' => 'ชิป', 'gpu' => 'การ์ดจอ'];
                foreach ($attrs as $attr):
                  $key = $attr['key_name'];
                  $label = $key_to_label_map[$key] ?? ucwords(str_replace('_', ' ', $key));
                ?>
                  <tr>
                    <th><?= h($label) ?></th>
                    <td><?= h($attr['value_string'] ?: $attr['value_int']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>


      <?php if ($related_items): ?>
        <div class="pdp-related-wrapper">
          <h2 class="pdp-section-title">สินค้าที่เกี่ยวข้อง</h2>
          <section class="cmnsx-products" style="padding: 0; max-width: none;">
            <ul class="cmnsx-grid">
              <?php
              // (ใช้ $fallback_icon_html ตัวเดิมที่ define ไว้ข้างบนได้)
              foreach ($related_items as $row):
                $url   = '/shop/product-detail.php?id=' . (int)$row['id'];
                $img = trim((string)$row['main_image']);
                if ($img !== '' && substr($img, 0, 1) !== '/' && !preg_match('~^https?://~', $img)) {
                  $img = '/' . ltrim($img, '/');
                }
                $name  = $row['name'];
                $cat   = $row['category'] ?: 'อื่นๆ';
                $price = (float)$row['price'];
                $old   = (float)($row['price_old'] ?? 0);
                $disc  = $old > $price ? ($old - $price) : 0;
                $pct   = $old > 0 ? round($disc / $old * 100) : 0;
                $qty   = (int)($row['stock_qty'] ?? 0);
                $low   = $qty > 0 && $qty <= 1;
              ?>
                <li class="cmnsx-card">
                  <?php if ($disc > 0): ?><div class="cmnsx-badge">ลด <?= number_format($disc, 0) ?> ฿<?= $pct ? " (-$pct%)" : "" ?></div><?php endif; ?>
                  <a href="<?= h($url) ?>" class="cmnsx-thumb">
                    <?php if ($img === ''): echo $fallback_icon_html;
                    else: // ใช้ตัวแปรเดิม
                      $onerror_js = "this.onerror=null; this.outerHTML='" . addslashes($fallback_icon_html) . "';"; ?>
                      <img src="<?= h($img) ?>" alt="<?= h($name) ?>" class="cmnsx-img" loading="lazy" decoding="async" onerror="<?= h($onerror_js) ?>">
                    <?php endif; ?>
                  </a>
                  <div class="cmnsx-info">
                    <div class="cmnsx-cat"><?= h($cat) ?></div>
                    <h3 class="cmnsx-name"><a href="<?= h($url) ?>" class="cmnsx-link"><?= h($name) ?></a></h3>
                    <?php if ($low): ?><div class="cmnsx-low">• สินค้าใกล้หมดแล้ว</div><?php endif; ?>
                    <div class="cmnsx-price">
                      <span class="cmnsx-price-now">฿<?= number_format($price, 0) ?></span>
                      <?php if ($disc > 0): ?><span class="cmnsx-price-old">฿<?= number_format($old, 0) ?></span><?php endif; ?>
                      <button class="card-cart-btn cart-on-price" type="button" aria-label="ใส่ตะกร้า" data-id="<?= (int)$row['id'] ?>" data-name="<?= h($name) ?>" data-price="<?= (float)$price ?>" data-img="<?= h($img) ?>" data-url="<?= h($url) ?>">
                        <span class="material-symbols-rounded" aria-hidden="true">add_shopping_cart</span>
                      </button>
                    </div>
                    <?php $line_url = 'https://line.me/R/ti/p/@cmns'; ?>
                    <a class="btn-line-full" href="<?= h($line_url) ?>" target="_blank" rel="noopener"><span class="material-symbols-rounded" aria-hidden="true">chat_bubble</span> สั่งผ่าน LINE</a>
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
  <aside id="mini-cart" aria-label="ตะกร้าสินค้า" style="display:none;" tabindex="-1">
    <header class="mc-head">
      <h3>ตะกร้าสินค้า</h3><button type="button" class="mc-close" aria-label="ปิดตะกร้า"><span class="material-symbols-rounded">close</span></button>
    </header>
    <div class="mc-body">
      <ul class="mc-list" id="mc-list"></ul>
      <div class="mc-empty" id="mc-empty">ยังไม่มีสินค้าในตะกร้า</div>
    </div>
    <footer class="mc-foot">
      <div class="mc-sum"><span>รวมทั้งหมด</span><strong id="mc-total">฿0</strong></div>
      <div class="mc-actions"><button type="button" id="mc-clear" class="mc-btn ghost"><span class="material-symbols-rounded">delete</span> ล้างตะกร้า</button><a href="#!" id="mc-checkout" class="mc-btn primary"><span class="material-symbols-rounded">receipt_long</span> สรุปรายการ</a></div>
    </footer>
  </aside>
  <div id="receipt-backdrop" aria-hidden="true" style="display:none;"></div>
  <aside id="receipt-modal" role="dialog" aria-modal="true" aria-labelledby="receipt-title" style="display:none;">
    <header class="rcp-head">
      <h3 id="receipt-title">สรุปรายการ</h3><button type="button" class="rcp-close" aria-label="ปิดป็อปอัพ"><span class="material-symbols-rounded">close</span></button>
    </header>
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
          <p>สแกน QR เพื่อแอด <b>LINE Official @cmnsfixmac</b> แล้วส่งสลิป/สอบถามสต็อก หรือนัดรับได้ทันที</p><a class="rcp-btn line small" href="https://line.me/R/ti/p/@cmns" target="_blank" rel="noopener"><span class="material-symbols-rounded">chat_bubble</span> เปิด LINE</a>
        </div>
      </div>
    </div>
    <footer class="rcp-foot"><button type="button" id="rcp-copy" class="rcp-btn ghost"><span class="material-symbols-rounded">content_copy</span> คัดลอกรายการ</button><a id="rcp-line" class="rcp-btn line" href="#" target="_blank" rel="noopener"><span class="material-symbols-rounded">send</span> ส่งรายละเอียดไป LINE</a><button type="button" id="rcp-print" class="rcp-btn"><span class="material-symbols-rounded">print</span> พิมพ์ / บันทึกเป็น PDF</button></footer>
  </aside>


  <script src="/shop/assets/js/hero.js" defer></script> <?php // For Navbar? 
                                                        ?>

  <script>
    // Navbar/Sidebar JS (ถ้ามึงจะ include header แยก ก็ย้าย JS ส่วนนี้ไปด้วย)
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

    // Product Gallery JS (!!! กูแก้ตรงนี้ !!!)
    (function() {
      const mainWrapper = document.getElementById('pdp-main-image-wrapper');
      const thumbs = document.querySelectorAll('.pdp-thumb-item');
      if (!mainWrapper || !thumbs.length) return;

      // --- กูแก้ตรงนี้: ให้มันอ่าน fallback icon จาก HTML ที่มึง render ไว้เลย ---
      const mainIcon = mainWrapper.querySelector('.pdp-gallery-icon');
      const fallbackIconHtml = mainIcon ? mainIcon.outerHTML : '<div class="pdp-gallery-icon"><span class="material-symbols-rounded" aria-hidden="true">image</span></div>';

      const images = Array.from(thumbs).map(t => t.dataset.fullSrc);
      let currentIndex = 0;

      function updateMainImage(index) {
        currentIndex = index;
        const fullSrc = images[currentIndex];

        // --- กูแก้ตรงนี้: ให้มันอ่าน alt text จาก h1 (pdp-title) ไม่ต้องใช้ PHP ---
        const altEl = document.querySelector('.pdp-title');
        const alt = altEl ? altEl.textContent : 'Product Image';

        const prevBtnHTML = images.length > 1 ? '<button class="pdp-gallery-nav prev" aria-label="Previous image"><span class="material-symbols-rounded">arrow_back_ios</span></button>' : '';
        const nextBtnHTML = images.length > 1 ? '<button class="pdp-gallery-nav next" aria-label="Next image"><span class="material-symbols-rounded">arrow_forward_ios</span></button>' : '';


        if (!fullSrc || fullSrc === '') {
          mainWrapper.innerHTML = fallbackIconHtml + prevBtnHTML + nextBtnHTML;
        } else {
          const onerrorJs = "this.onerror=null; this.outerHTML='" + fallbackIconHtml.replace(/'/g, "\\'") + "';";
          mainWrapper.innerHTML = `
          <img src="${fullSrc}" alt="${alt.replace(/"/g, '&quot;')}" class="pdp-main-img" loading="lazy" decoding="async" onerror="${onerrorJs.replace(/"/g, '&quot;')}">
          ${prevBtnHTML} ${nextBtnHTML}
        `;
        }

        thumbs.forEach((t, i) => {
          t.classList.toggle('is-active', i === currentIndex);
        });

        // Re-attach listeners
        const newPrev = mainWrapper.querySelector('.pdp-gallery-nav.prev');
        const newNext = mainWrapper.querySelector('.pdp-gallery-nav.next');
        if (newPrev) newPrev.addEventListener('click', showPrev);
        if (newNext) newNext.addEventListener('click', showNext);
      }

      thumbs.forEach(thumb => {
        thumb.addEventListener('click', function(e) {
          e.preventDefault();
          updateMainImage(parseInt(thumb.dataset.index, 10));
        });
      });

      function showPrev() {
        const newIndex = (currentIndex - 1 + images.length) % images.length;
        updateMainImage(newIndex);
      }

      function showNext() {
        const newIndex = (currentIndex + 1) % images.length;
        updateMainImage(newIndex);
      }

      // --- กูแก้ Initial load ให้มันฉลาดขึ้น ---
      const firstActiveThumb = document.querySelector('.pdp-thumb-item.is-active');
      if (firstActiveThumb) {
        updateMainImage(parseInt(firstActiveThumb.dataset.index, 10));
      } else if (images.length > 0) {
        updateMainImage(0);
      }

    })();

    // Quantity Selector JS (เหมือนเดิม)
    (function() {
      const qtyInput = document.getElementById('pdp-qty');
      const minusBtn = document.querySelector('.pdp-qty-btn.minus');
      const plusBtn = document.querySelector('.pdp-qty-btn.plus');
      if (!qtyInput || !minusBtn || !plusBtn) return;

      const maxQty = parseInt(qtyInput.max, 10) || 1;

      minusBtn.addEventListener('click', () => {
        let currentVal = parseInt(qtyInput.value, 10);
        if (currentVal > 1) {
          qtyInput.value = currentVal - 1;
        }
      });
      plusBtn.addEventListener('click', () => {
        let currentVal = parseInt(qtyInput.value, 10);
        if (currentVal < maxQty) {
          qtyInput.value = currentVal + 1;
        }
      });
      qtyInput.addEventListener('change', () => {
        let currentVal = parseInt(qtyInput.value, 10);
        if (isNaN(currentVal) || currentVal < 1) {
          qtyInput.value = 1;
        } else if (currentVal > maxQty) {
          qtyInput.value = maxQty;
        }
      });
    })();

    // Tabs JS (เหมือนเดิม)
    (function() {
      const tabButtons = document.querySelectorAll('.pdp-tab-btn');
      const tabContents = document.querySelectorAll('.pdp-tab-content');
      if (!tabButtons.length || !tabContents.length) return;

      tabButtons.forEach(button => {
        button.addEventListener('click', () => {
          tabContents.forEach(content => content.classList.remove('is-active'));
          tabButtons.forEach(btn => {
            btn.classList.remove('is-active');
            btn.setAttribute('aria-selected', 'false');
          });
          const targetContentId = button.getAttribute('aria-controls');
          const targetContent = document.getElementById(targetContentId);
          if (targetContent) {
            targetContent.classList.add('is-active');
          }
          button.classList.add('is-active');
          button.setAttribute('aria-selected', 'true');
        });
      });
    })();

    // Cart JS (Minified - เหมือนเดิมเป๊ะ)
    (function() {
      const $ = s => document.querySelector(s);
      const $$ = s => Array.from(document.querySelectorAll(s));
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

      function saveCart(t) {
        localStorage.setItem(LS_KEY, JSON.stringify(t))
      }

      function cartCount() {
        return loadCart().reduce((t, e) => t + (Number(e.qty) || 1), 0)
      }

      function cartTotal() {
        return loadCart().reduce((t, e) => t + (Number(e.price) || 0) * (Number(e.qty) || 1), 0)
      }

      function upsertItem({
        id: t,
        name: e,
        price: r,
        img: o,
        url: c
      }, n = 1) {
        const i = loadCart();
        const s = i.findIndex(e => String(e.id) === String(t));
        s > -1 ? i[s].qty = Math.min(99, (Number(i[s].qty) || 1) + n) : i.push({
          id: t,
          name: e,
          price: Number(r) || 0,
          img: o,
          url: c,
          qty: Math.max(1, n)
        });
        saveCart(i);
        render()
      }

      function setQty(t, e) {
        const r = loadCart();
        const o = r.findIndex(e => String(e.id) === String(t));
        o > -1 && (r[o].qty = Math.max(1, Math.min(99, Number(e) || 1)), saveCart(r), render())
      }

      function removeItem(t) {
        saveCart(loadCart().filter(e => String(e.id) !== String(t)));
        render()
      }

      function clearCart() {
        saveCart([]);
        render()
      }
      const overlay = $('#mini-cart-overlay');
      const drawer = $('#mini-cart');
      const listEl = $('#mc-list');
      const emptyEl = $('#mc-empty');
      const totalEl = $('#mc-total');
      const checkout = $('#mc-checkout');
      const cartBadge = $('.nav-cart .cart-count');
      const navCart = $('.nav-cart');
      window.openCart = function() {
        overlay.style.display = 'block';
        drawer.style.display = 'flex';
        document.body.classList.add('mc-open');
        drawer.focus()
      };
      window.closeCart = function() {
        document.body.classList.remove('mc-open');
        setTimeout(() => {
          overlay.style.display = 'none';
          drawer.style.display = 'none'
        }, 320)
      };
      navCart && (navCart.addEventListener('click', t => {
        t.preventDefault();
        openCart()
      }), navCart.addEventListener('keydown', t => {
        'Enter' !== t.key && ' ' !== t.key || (t.preventDefault(), openCart())
      }));
      $('.mc-close')?.addEventListener('click', closeCart);
      overlay?.addEventListener('click', closeCart);
      document.addEventListener('keydown', t => {
        'Escape' === t.key && !$('#receipt-modal')?.hidden && hideReceipt() || 'Escape' === t.key && !drawer?.hidden && closeCart()
      });

      function escapeHtml(t) {
        return String(t || '').replace(/[&<>"]/g, t => ({
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;'
        } [t]))
      }

      function render() {
        const t = loadCart();
        cartBadge && (cartBadge.textContent = String(cartCount()));
        if (emptyEl) emptyEl.style.display = t.length ? 'none' : 'block';
        if (listEl) listEl.innerHTML = '';
        t.forEach(t => {
          const e = document.createElement('li');
          e.className = 'mc-item';
          let r = t.img || '/assets/img/placeholder.jpg';
          r && !r.startsWith('/') && !r.startsWith('http') && (r = '/assets/img/placeholder.jpg');
          e.innerHTML = `\n          <img class="mc-thumb" src="${r}" alt="" onerror="this.onerror=null;this.src='/assets/img/placeholder.jpg';">\n          <div>\n            <div class="mc-title"><a href="${t.url||'#'}">${escapeHtml(t.name||'สินค้า')}</a></div>\n            <div class="mc-meta">\n              <div class="mc-qty" data-id="${t.id}">\n                <button type="button" class="mc-minus" aria-label="ลดจำนวน"><span class="material-symbols-rounded">remove</span></button>\n                <input type="text" class="mc-input" value="${Number(t.qty)||1}" inputmode="numeric" pattern="[0-9]*" />\n                <button type="button" class="mc-plus" aria-label="เพิ่มจำนวน"><span class="material-symbols-rounded">add</span></button>\n              </div>\n              <div class="mc-price">${fmt(t.price)}</div>\n            </div>\n          </div>\n          <button type="button" class="mc-remove" data-id="${t.id}" aria-label="ลบ"><span class="material-symbols-rounded">delete</span></button>\n        `;
          listEl?.appendChild(e)
        });
        if (totalEl) totalEl.textContent = fmt(cartTotal())
      }
      const cartIcon = $('.nav-cart');

      function badgeBump() {
        const t = $('.nav-cart .cart-count');
        t && (t.classList.remove('pop'), void t.offsetWidth, t.classList.add('pop'))
      }

      function showToast(t) {
        const e = document.createElement('div');
        e.className = 'toast';
        e.textContent = t || 'เพิ่มลงตะกร้าแล้ว';
        document.body.appendChild(e);
        requestAnimationFrame(() => e.classList.add('show'));
        setTimeout(() => {
          e.classList.remove('show');
          setTimeout(() => e.remove(), 250)
        }, 1600)
      }

      function flyToCart(t) {
        if (!cartIcon) return;
        let e, r;
        const o = t.closest('.cmnsx-card');
        if (o) e = o?.querySelector('.cmnsx-thumb img, .cmnsx-thumb .cmnsx-thumb-icon');
        else {
          const t = $('#pdp-main-image-wrapper');
          e = t?.querySelector('img, .pdp-gallery-icon')
        }
        if (!e) return;
        r = e.getBoundingClientRect();
        let c = e.getAttribute('src');
        const n = cartIcon.getBoundingClientRect();
        const i = (n.left + n.width / 2) - (r.left + r.width / 2);
        const s = (n.top + n.height / 2) - (r.top + r.height / 2);
        if (!c) {
          const t = document.createElement('div');
          t.className = 'fly-img';
          t.innerHTML = '<span class="material-symbols-rounded">image</span>';
          t.style.left = r.left + r.width / 2 - 32 + 'px';
          t.style.top = r.top + r.height / 2 - 32 + 'px';
          t.style.background = '#f0f0f0';
          t.style.color = '#888';
          t.style.display = 'grid';
          t.style.placeItems = 'center';
          t.style.borderRadius = '8px';
          t.style.overflow = 'hidden';
          document.body.appendChild(t);
          requestAnimationFrame(() => {
            t.style.transform = `translate(${i}px, ${s}px) scale(.35)`;
            t.style.opacity = '0.2'
          });
          setTimeout(() => t.remove(), 650);
          return
        }
        const a = document.createElement('img');
        a.className = 'fly-img';
        a.src = c;
        a.style.left = r.left + r.width / 2 - 32 + 'px';
        a.style.top = r.top + r.height / 2 - 32 + 'px';
        document.body.appendChild(a);
        requestAnimationFrame(() => {
          a.style.transform = `translate(${i}px, ${s}px) scale(.35)`;
          a.style.opacity = '0.2'
        });
        setTimeout(() => a.remove(), 650)
      }
      document.addEventListener('click', function(t) {
        const e = t.target.closest('.card-cart-btn');
        if (!e) return;
        t.preventDefault();
        e.disabled = !0;
        const r = e.closest('.cmnsx-card');
        let o = 1;
        if (!r) {
          const t = $('#pdp-qty');
          o = parseInt(t?.value, 10) || 1
        }
        const c = {
          id: e.dataset.id,
          name: e.dataset.name,
          price: e.dataset.price,
          img: e.dataset.img,
          url: e.dataset.url
        };
        c.price = Number(c.price) || 0;
        upsertItem(c, o);
        try {
          navigator.vibrate && navigator.vibrate(30)
        } catch (t) {}
        e.classList.add('added');
        setTimeout(() => {
          e.classList.remove('added');
          e.disabled = !1
        }, 320);
        flyToCart(e);
        badgeBump();
        showToast('เพิ่มลงตะกร้าแล้ว')
      });
      $('#mc-list')?.addEventListener('click', function(t) {
        const e = t.target.closest('.mc-minus');
        const r = t.target.closest('.mc-plus');
        const o = t.target.closest('.mc-remove');
        if (e || r) {
          const c = t.target.closest('.mc-qty');
          const n = c?.dataset.id;
          const i = c?.querySelector('.mc-input');
          if (!n || !i) return;
          let s = Number(i.value) || 1;
          s = e ? Math.max(1, s - 1) : Math.min(99, s + 1);
          setQty(n, s);
          return
        }
        if (o) {
          const t = o.dataset.id;
          t && removeItem(t);
          return
        }
      });
      $('#mc-list')?.addEventListener('input', function(t) {
        const e = t.target.closest('.mc-input');
        if (!e) return;
        const r = t.target.closest('.mc-qty');
        const o = r?.dataset.id;
        if (!o) return;
        const c = Math.max(1, Math.min(99, Number(e.value.replace(/[^\d]/g, '')) || 1));
        setQty(o, c)
      });
      const bd = $('#receipt-backdrop');
      const md = $('#receipt-modal');
      const rList = $('#rcp-list');
      const rSub = $('#rcp-subtotal');
      const rShip = $('#rcp-ship');
      const rTot = $('#rcp-total');
      const rClose = md?.querySelector('.rcp-close');
      const rCopy = $('#rcp-copy');
      const rPrint = $('#rcp-print');
      const rLine = $('#rcp-line');
      const SHIPPING = 0;

      function showReceipt() {
        if (bd && md) bd.style.display = 'block', md.style.display = 'grid'
      }

      function hideReceipt() {
        if (bd && md) bd.style.display = 'none', md.style.display = 'none'
      }

      function buildSummaryText(t, e) {
        const r = t.map(t => `• ${t.name} x ${t.qty} = ${fmt((Number(t.price)||0)*(Number(t.qty)||1))}`);
        r.push(`\nรวมทั้งหมดยอดสุทธิ: ${fmt(e)}`);
        r.push('\nติดต่อ: LINE @cmnsfixmac');
        return r.join('\n')
      }

      function openReceipt() {
        const t = loadCart();
        if (!t.length) return void alert('ตะกร้่าว่าง');
        if (rList) rList.innerHTML = '';
        t.forEach(t => {
          const e = document.createElement('li');
          e.className = 'rcp-item';
          let r = t.img || '/assets/img/placeholder.jpg';
          r && !r.startsWith('/') && !r.startsWith('http') && (r = '/assets/img/placeholder.jpg');
          e.innerHTML = `\n          <img class="rcp-thumb" src="${r}" alt="" onerror="this.onerror=null;this.src='/assets/img/placeholder.jpg';">\n          <div>\n            <div class="rcp-title">${(t.name||'สินค้า').replace(/[&<>"]/g,t=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[t]))}</div>\n            <div class="rcp-meta">จำนวน: ${Number(t.qty)||1}</div>\
          </div>\n          <div class="rcp-price">${fmt((Number(t.price)||0)*(Number(t.qty)||1))}</div>\n        `;
          rList?.appendChild(e)
        });
        const e = t.reduce((t, e) => t + (Number(e.price) || 0) * (Number(e.qty) || 1), 0);
        const r = SHIPPING;
        const o = e + r;
        if (rSub) rSub.textContent = fmt(e);
        if (rShip) rShip.textContent = r ? fmt(r) : 'ฟรี';
        if (rTot) rTot.textContent = fmt(o);
        const c = buildSummaryText(t, o);
        if (rCopy) rCopy.onclick = async () => {
          try {
            await navigator.clipboard.writeText(c);
            rCopy.innerHTML = '<span class="material-symbols-rounded">check</span> คัดลอกแล้ว';
            setTimeout(() => rCopy.innerHTML = '<span class="material-symbols-rounded">content_copy</span> คัดลอกรายการ', 1200)
          } catch (t) {
            alert('คัดลอกไม่สำเร็จ')
          }
        };
        if (rLine) rLine.href = 'https://line.me/R/ti/p/@cmns';
        if (rPrint) rPrint.onclick = () => window.print();
        showReceipt()
      }
      $('#mc-clear')?.addEventListener('click', function() {
        confirm('ล้างตะกร้าทั้งหมด?') && clearCart()
      });
      $('#mc-checkout')?.addEventListener('click', function(t) {
        t.preventDefault();
        openReceipt()
      });
      bd?.addEventListener('click', hideReceipt);
      rClose?.addEventListener('click', hideReceipt);
      render();
    })();
  </script>

  <?php // <--! START: FOOTER SECTION (กูยัดใส่ให้แล้ว) !--> 
  ?>
  <footer class="cmns-footer">
    <div class="footer-container">
      <div class="footer-grid">
        <div class="footer-col" id="f-brand">
          <a href="/shop/" class="f-logo">
            <img src="/assets/img/Logo1.png" alt="CMNS FixMac Logo">
          </a>
          <p>ร้านขายและซ่อม Apple มือสอง คุณภาพดีอันดับ 1 ในเชียงใหม่</p>
          <div class="f-socials">
            <a href="https://line.me/R/ti/p/@cmns" target="_blank" rel="noopener" aria-label="LINE">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" fill="currentColor">
                <path d="M224 336.1c-63.6 0-115.3-51.6-115.3-115.3S160.4 105.6 224 105.6s115.3 51.6 115.3 115.3-51.7 115.2-115.3 115.2zm0-207c-50.5 0-91.7 41.2-91.7 91.7s41.2 91.7 91.7 91.7 91.7-41.2 91.7-91.7-41.2-91.7-91.7-91.7zM448 220.7c0-101.9-82.8-184.6-184.8-184.6S78.4 118.8 78.4 220.7c0 82.2 53.7 151.6 125.7 174.4 6.9 2.2 11.2 8.9 11.2 16.2v.2c0 10.9-11.2 19.8-22.1 19.8-11.1 0-22.1-8.9-22.1-19.8 0-11.3-9.2-20.5-20.5-20.5-11.3 0-20.5 9.2-20.5 20.5 0 33.3 27 60.3 60.3 60.3s60.3-27 60.3-60.3v-.2c0-7.3 4.3-14 11.2-16.2 72-22.8 125.7-92.2 125.7-174.4zm-48.7 0c0 75.2-61.1 136.3-136.3 136.3s-136.3-61.1-136.3-136.3 61.1-136.3 136.3-136.3 136.3 61.1 136.3 136.3zM161.2 214.2c0-3.3-2.7-6-6-6s-6 2.7-6 6v13.1c0 3.3 2.7 6 6 6s6-2.7 6-6v-13.1zm51.4 0c0-3.3-2.7-6-6-6s-6 2.7-6 6v13.1c0 3.3 2.7 6 6 6s6-2.7 6-6v-13.1zm51.3 0c0-3.3-2.7-6-6-6s-6 2.7-6 6v13.1c0 3.3 2.7 6 6 6s6-2.7 6-6v-13.1z" />
              </svg>
            </a>
            <a href="https://www.facebook.com/cmnsfixmac" target="_blank" rel="noopener" aria-label="Facebook">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="currentColor">
                <path d="M504 256C504 119 393 8 256 8S8 119 8 256c0 123.78 90.69 226.38 209.25 245V327.69h-63V256h63v-54.64c0-62.15 37-96.48 93.67-96.48 27.14 0 55.52 4.84 55.52 4.84v61h-31.28c-30.8 0-40.41 19.12-40.41 38.73V256h68.78l-11 71.69h-57.78V501C413.31 482.38 504 379.78 504 256z" />
              </svg>
            </a>
            <a href="https://www.instagram.com/cmnsfixmac" target="_blank" rel="noopener" aria-label="Instagram">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" fill="currentColor">
                <path d="M224.1 141c-63.6 0-114.9 51.3-114.9 114.9s51.3 114.9 114.9 114.9S339 319.5 339 255.9 287.7 141 224.1 141zm0 189.6c-41.1 0-74.7-33.5-74.7-74.7s33.5-74.7 74.7-74.7 74.7 33.5 74.7 74.7-33.6 74.7-74.7 74.7zm146.4-194.3c0 14.9-12 26.8-26.8 26.8-14.9 0-26.8-12-26.8-26.8s12-26.8 26.8-26.8 26.8 12 26.8 26.8zm76.1 27.2c-1.7-35.9-9.9-67.7-36.2-93.9-26.2-26.2-58-34.4-93.9-36.2-37.2-2.1-147.9-2.1-185.1 0-35.9 1.7-67.7 9.9-93.9 36.2-26.2 26.2-34.4 58-36.2 93.9-2.1 37.2-2.1 147.9 0 185.1 1.7 35.9 9.9 67.7 36.2 93.9 26.2 26.2 58 34.4 93.9 36.2 37.2 2.1 147.9 2.1 185.1 0 35.9-1.7 67.7-9.9 93.9-36.2 26.2-26.2 34.4-58 36.2-93.9 2.1-37.2 2.1-147.8 0-185.1zM416 331c-1.5 28.4-8.2 51.5-31.3 74.6-23.1 23.1-46.2 29.8-74.6 31.3-36.9 1.8-140.3 1.8-177.2 0-28.4-1.5-51.5-8.2-74.6-31.3-23.1-23.1-29.8-46.2-31.3-74.6-1.8-36.9-1.8-140.3 0-177.2 1.5-28.4 8.2-51.5 31.3-74.6 23.1-23.1 46.2-29.8 74.6-31.3 36.9-1.8 140.3-1.8 177.2 0 28.4 1.5 51.5 8.2 74.6 31.3 23.1 23.1 29.8 46.2 31.3 74.6 1.8 36.9 1.8 140.3 0 177.2z" />
              </svg>
            </a>
            <a href="https://www.tiktok.com/@cmnsfixmac" target="_blank" rel="noopener" aria-label="TikTok">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" fill="currentColor">
                <path d="M448,209.91a210.06,210.06,0,0,1-122.77-39.25V349.38A162.55,162.55,0,1,1,185,188.31V278.2a74.62,74.62,0,1,0,52.23,71.18V0l88,0a121.18,121.18,0,0,0,1.86,22.17h0A122.18,122.18,0,0,0,381,102.39a121.43,121.43,0,0,0,67,20.14Z" />
              </svg>
            </a>
          </div>
        </div>

        <div class="footer-col">
          <h4>บริการของเรา</h4>
          <ul>
            <li><a href="/shop/">ร้านค้า (สินค้ามือสอง)</a></li>
            <li><a href="/works.php">ผลงานซ่อม</a></li>
            <li><a href="/buyback.php">รับซื้อเครื่อง</a></li>
            <li><a href="/warranty.php">ตรวจสอบประกัน</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h4>ช่วยเหลือ</h4>
          <ul>
            <li><a href="/articles.php">บทความ</a></li>

            
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
  <?php // <--! END: FOOTER SECTION !--> 
  ?>


</body>

</html>