<?php
require_once __DIR__ . '/../includes/db.php';

/* ---------- Helpers ---------- */
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function getv($k,$d=null){ return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }
function build_url($patch){
  $qs = array_merge($_GET, $patch);
  foreach($qs as $k=>$v){ if($v==='' || $v===null) unset($qs[$k]); }
  $q = http_build_query($qs);
  return '/shop/'.($q ? '?'.$q : '');
}

/* ---------- Lang link ---------- */
$en_version_url = 'https://cmnsfixmac.com/en' . $_SERVER['REQUEST_URI'];
$en_version_url = str_replace('/index.php', '/', $en_version_url);

/* ---------- Filters ---------- */
$q        = getv('q','');
$cat      = getv('cat','');
$min      = getv('min','') !== '' ? (float)getv('min') : null;
$max      = getv('max','') !== '' ? (float)getv('max') : null;

$ram_min  = getv('ram_min','')  !== '' ? (int)getv('ram_min')  : null;
$ssd_min  = getv('ssd_min','')  !== '' ? (int)getv('ssd_min')  : null;
$year_min = getv('year_min','') !== '' ? (int)getv('year_min') : null;
$year_max = getv('year_max','') !== '' ? (int)getv('year_max') : null;
$color    = getv('color','');

$sort     = getv('sort','new'); // new|price_asc|price_desc
$page     = max(1, (int)getv('page',1));
$pp       = min(60, max(12, (int)getv('pp',24)));
$off      = ($page-1)*$pp;

/* ---------- Base WHERE ---------- */
$where  = ["l.status='published'","l.in_stock=1"];
$params = [];
if($q!==''){   $where[] = "l.title LIKE :q"; $params[':q']="%$q%"; }
if($cat!==''){ $where[] = "l.category = :cat"; $params[':cat']=$cat; }
if($min!==null){ $where[] = "l.price >= :min"; $params[':min']=$min; }
if($max!==null){ $where[] = "l.price <= :max"; $params[':max']=$max; }
$WHERE_BASE = 'WHERE '.implode(' AND ', $where);

/* ---------- Base IDs for facets ---------- */
$sqlBaseIds = "SELECT l.id FROM listings l $WHERE_BASE";
$st = $pdo->prepare($sqlBaseIds); $st->execute($params);
$baseIds = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
$idsList = $baseIds ? implode(',', $baseIds) : '0';

/* ---------- Facets ---------- */
$attrMap = $pdo->query("SELECT key_name,id FROM attrs WHERE key_name IN ('ram_gb','ssd_gb','year','color')")
               ->fetchAll(PDO::FETCH_KEY_PAIR);
$facets = ['ram'=>[], 'ssd'=>[], 'year'=>[], 'color'=>[]];

if ($baseIds){
  if (!empty($attrMap['ram_gb'])){
    $st = $pdo->prepare("SELECT v.value_int val, COUNT(*) c FROM listing_attr_values v
                         WHERE v.attr_id=:aid AND v.listing_id IN ($idsList)
                         GROUP BY v.value_int ORDER BY v.value_int");
    $st->execute([':aid'=>$attrMap['ram_gb']]); $facets['ram'] = $st->fetchAll(PDO::FETCH_ASSOC);
  }
  if (!empty($attrMap['ssd_gb'])){
    $st = $pdo->prepare("SELECT v.value_int val, COUNT(*) c FROM listing_attr_values v
                         WHERE v.attr_id=:aid AND v.listing_id IN ($idsList)
                         GROUP BY v.value_int ORDER BY v.value_int");
    $st->execute([':aid'=>$attrMap['ssd_gb']]); $facets['ssd'] = $st->fetchAll(PDO::FETCH_ASSOC);
  }
  if (!empty($attrMap['year'])){
    $st = $pdo->prepare("SELECT v.value_int val, COUNT(*) c FROM listing_attr_values v
                         WHERE v.attr_id=:aid AND v.listing_id IN ($idsList)
                         GROUP BY v.value_int ORDER BY v.value_int DESC");
    $st->execute([':aid'=>$attrMap['year']]); $facets['year'] = $st->fetchAll(PDO::FETCH_ASSOC);
  }
  if (!empty($attrMap['color'])){
    $st = $pdo->prepare("SELECT v.value_string val, COUNT(*) c FROM listing_attr_values v
                         WHERE v.attr_id=:aid AND v.listing_id IN ($idsList)
                         GROUP BY v.value_string ORDER BY v.value_string");
    $st->execute([':aid'=>$attrMap['color']]); $facets['color'] = $st->fetchAll(PDO::FETCH_ASSOC);
  }
}

/* ---------- Items WHERE (with chosen facets) ---------- */
$whereItems  = $where; $paramsItems = $params;
if($ram_min!==null && !empty($attrMap['ram_gb'])){
  $whereItems[]  = "EXISTS (SELECT 1 FROM listing_attr_values v
                            WHERE v.listing_id=l.id AND v.attr_id=:ram_aid AND v.value_int >= :ram_min)";
  $paramsItems[':ram_aid']=(int)$attrMap['ram_gb']; $paramsItems[':ram_min']=$ram_min;
}
if($ssd_min!==null && !empty($attrMap['ssd_gb'])){
  $whereItems[]  = "EXISTS (SELECT 1 FROM listing_attr_values v
                            WHERE v.listing_id=l.id AND v.attr_id=:ssd_aid AND v.value_int >= :ssd_min)";
  $paramsItems[':ssd_aid']=(int)$attrMap['ssd_gb']; $paramsItems[':ssd_min']=$ssd_min;
}
if($year_min!==null && !empty($attrMap['year'])){
  $whereItems[]  = "EXISTS (SELECT 1 FROM listing_attr_values v
                            WHERE v.listing_id=l.id AND v.attr_id=:y_aid AND v.value_int >= :ymin)";
  $paramsItems[':y_aid']=(int)$attrMap['year']; $paramsItems[':ymin']=$year_min;
}
if($year_max!==null && !empty($attrMap['year'])){
  $whereItems[]  = "EXISTS (SELECT 1 FROM listing_attr_values v
                            WHERE v.listing_id=l.id AND v.attr_id=:y2_aid AND v.value_int <= :ymax)";
  $paramsItems[':y2_aid']=(int)$attrMap['year']; $paramsItems[':ymax']=$year_max;
}
if($color!=='' && !empty($attrMap['color'])){
  $whereItems[]  = "EXISTS (SELECT 1 FROM listing_attr_values v
                            WHERE v.listing_id=l.id AND v.attr_id=:c_aid AND v.value_string = :color)";
  $paramsItems[':c_aid']=(int)$attrMap['color']; $paramsItems[':color']=$color;
}
$WHERE_ITEMS = 'WHERE '.implode(' AND ', $whereItems);

/* ---------- ORDER ---------- */
$ORDER = 'ORDER BY l.created_at DESC, l.id DESC';
if($sort==='price_asc')  $ORDER = 'ORDER BY l.price ASC, l.id DESC';
if($sort==='price_desc') $ORDER = 'ORDER BY l.price DESC, l.id DESC';

/* ---------- COUNT ---------- */
$st = $pdo->prepare("SELECT COUNT(*) FROM listings l $WHERE_ITEMS");
$st->execute($paramsItems); $total = (int)$st->fetchColumn();

/* ---------- ITEMS ---------- */
$sqlItems = "SELECT l.id, l.title name, l.category, l.price, l.price_old, l.stock_qty, l.created_at, l.main_image
             FROM listings l
             $WHERE_ITEMS
             $ORDER
             LIMIT :lim OFFSET :off";
$st = $pdo->prepare($sqlItems);
foreach($paramsItems as $k=>$v) $st->bindValue($k,$v);
$st->bindValue(':lim',$pp,PDO::PARAM_INT);
$st->bindValue(':off',$off,PDO::PARAM_INT);
$st->execute();
$items = $st->fetchAll(PDO::FETCH_ASSOC);

/* ---------- Render product section into buffer (AJAX-ready) ---------- */
ob_start();
?>
<section id="cmnsx-products" class="cmnsx-products" aria-label="สินค้าทั้งหมด">
  <h2 class="cmnsx-title">สินค้าทั้งหมด (<?= number_format($total) ?>)</h2>

  <?php if (empty($items)): ?>
    <p class="cmnsx-empty">ไม่พบสินค้า ลองล้างตัวกรอง</p>
  <?php else: ?>
    <ul class="cmnsx-grid">
      <?php foreach($items as $row):
        $url   = '/product-detail.php?id='.(int)$row['id'];
        $img = trim((string)$row['main_image']);
        if ($img !== '' && substr($img,0,1) !== '/' && !preg_match('~^https?://~',$img)) { $img = '/'.ltrim($img,'/'); }
        if ($img === '') $img = '/assets/img/placeholder.jpg';
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
        <?php if ($disc > 0): ?>
          <div class="cmnsx-badge">ลด <?= number_format($disc,0) ?> ฿<?= $pct ? " (-$pct%)" : "" ?></div>
        <?php endif; ?>

        <a href="<?= h($url) ?>" class="cmnsx-thumb">
          <img
            src="<?= h($img) ?>"
            alt="<?= h($name) ?>"
            class="cmnsx-img"
            loading="lazy"
            decoding="async"
            onerror="this.onerror=null;this.src='/assets/img/placeholder.jpg';">
        </a>

                <div class="cmnsx-info">
          <div class="cmnsx-cat"><?= h($cat) ?></div>
          <h3 class="cmnsx-name"><a href="<?= h($url) ?>" class="cmnsx-link"><?= h($name) ?></a></h3>

          <?php if ($low): ?>
            <div class="cmnsx-low">• สินค้าใกล้หมดแล้ว</div>
          <?php endif; ?>

          <!-- แถวราคา + ปุ่มตะกร้าไอคอนชิดขวา -->
          <div class="cmnsx-price">
            <span class="cmnsx-price-now">฿<?= number_format($price,0) ?></span>
            <?php if ($disc > 0): ?>
              <span class="cmnsx-price-old">฿<?= number_format($old,0) ?></span>
            <?php endif; ?>

            <!-- ปุ่มตะกร้า -->
            <button
              class="card-cart-btn cart-on-price"
              type="button"
              aria-label="ใส่ตะกร้า"
              data-id="<?= (int)$row['id'] ?>"
              data-name="<?= h($name) ?>"
              data-price="<?= (float)$price ?>"
              data-img="<?= h($img) ?>"
              data-url="<?= h($url) ?>"
            >
              <span class="material-symbols-rounded" aria-hidden="true">add_shopping_cart</span>
            </button>
          </div>

          <?php
            // ลิงก์ LINE แบบคงที่ ไม่แนบข้อความใดๆ
            $line_url = 'https://line.me/R/ti/p/@cmns';
          ?>
          <a class="btn-line-full" href="<?= h($line_url) ?>" target="_blank" rel="noopener">
            <span class="material-symbols-rounded" aria-hidden="true">chat_bubble</span>
            สั่งผ่าน LINE
          </a>
        </div>

      </li>
      <?php endforeach; ?>
    </ul>

    <?php $pages = (int)ceil($total / $pp); if ($pages > 1): ?>
      <nav class="cmnsx-pager" aria-label="pagination">
        <ul class="cmnsx-pager-list">
          <?php
            $first=1; $last=max(1,(int)$pages); $prev=max($first,$page-1); $next=min($last,$page+1);
            $mk = function($p){ return h(build_url(['page'=>$p])) . '#cmnsx-products'; };
          ?>
          <?php if ($page > $first): ?>
            <li class="cmnsx-pager-item cmnsx-pager-nav"><a href="<?= $mk($first) ?>" aria-label="หน้าแรก">«</a></li>
            <li class="cmnsx-pager-item cmnsx-pager-nav"><a href="<?= $mk($prev) ?>" aria-label="ก่อนหน้า">‹</a></li>
          <?php else: ?>
            <li class="cmnsx-pager-item cmnsx-pager-nav is-disabled" aria-disabled="true"><span>«</span></li>
            <li class="cmnsx-pager-item cmnsx-pager-nav is-disabled" aria-disabled="true"><span>‹</span></li>
          <?php endif; ?>

          <?php for ($i=1; $i<=$pages; $i++): ?>
            <li class="cmnsx-pager-item">
              <?php if ($i === $page): ?>
                <span class="cmnsx-pager-current"><?= $i ?></span>
              <?php else: ?>
                <a href="<?= $mk($i) ?>" class="cmnsx-pager-link"><?= $i ?></a>
              <?php endif; ?>
            </li>
          <?php endfor; ?>

          <?php if ($page < $last): ?>
            <li class="cmnsx-pager-item cmnsx-pager-nav"><a href="<?= $mk($next) ?>" aria-label="ถัดไป">›</a></li>
            <li class="cmnsx-pager-item cmnsx-pager-nav"><a href="<?= $mk($last) ?>" aria-label="หน้าสุดท้าย">»</a></li>
          <?php else: ?>
            <li class="cmnsx-pager-item cmnsx-pager-nav is-disabled" aria-disabled="true"><span>›</span></li>
            <li class="cmnsx-pager-item cmnsx-pager-nav is-disabled" aria-disabled="true"><span>»</span></li>
          <?php endif; ?>
        </ul>
      </nav>
    <?php endif; ?>
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

  <!-- SEO -->
  <meta name="description" content="ร้านขาย MacBook มือสอง, iPhone มือสอง, iPad มือสอง, Apple Watch มือสอง สภาพดี พร้อมรับประกันหลังการขาย จัดส่งทั่วไทย มีหน้าร้านที่เชียงใหม่ โดย CMNS FixMac">
  <link rel="canonical" href="https://cmnsfixmac.com/shop/" />

  <!-- Favicon -->
  <link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png" />

  <!-- Styles -->
  <link rel="stylesheet" href="assets/css/shop-style.css">
  <link rel="stylesheet" href="/shop/assets/css/hero.css">
  <link rel="stylesheet" href="/shop/assets/css/cart-receipt.css">
  <link rel="stylesheet" href="/shop/assets/css/promo-modal.css">

  <!-- Icons -->
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
</head>

<body>

<!-- ========================== NAVBAR ========================== -->
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

      <a href="<?= h($en_version_url) ?>" class="language-switch-btn" title="Switch to English">
        <span class="material-symbols-rounded">language</span> EN
      </a>

      <button id="hamburger" class="hamburger" type="button" onclick="toggleSidebar()" aria-label="เมนู">
        <span></span><span></span><span></span>
      </button>
    </div>

    <!-- Sidebar -->
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
          $en_version_url_sidebar = 'https://cmnsfixmac.com/en' . $_SERVER['REQUEST_URI'];
          $en_version_url_sidebar = str_replace('/index.php', '/', $en_version_url_sidebar);
        ?>
        <a href="<?= h($en_version_url_sidebar) ?>" class="language-switch-btn" title="Switch to English">
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

<!-- ======================== HERO ===================== -->
<section id="hero-split" class="hero-split">
  <div class="hero-left">
    <div class="hero-track">
      <article class="hero-slide is-active">
        <img src="https://images.unsplash.com/photo-1517336714731-489689fd1ca8?q=80&w=2000&auto=format&fit=crop" alt="MacBook">
        <div class="hero-caption">
          <h2>MacBook Pro มือสอง</h2>
          <p>สภาพสวย ประกันครบ ราคาพิเศษ</p>
          <a href="/shop/?cat=MacBook#cmnsx-products" class="btn-hero">ช้อปเลย</a>
        </div>
      </article>
      <article class="hero-slide">
        <img src="https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?q=80&w=2000&auto=format&fit=crop" alt="iPhone">
        <div class="hero-caption">
          <h2>iPhone ราคาดี</h2>
          <p>รับประกันใช้งานได้จริง คุ้มค่า</p>
          <a href="/shop/?cat=iPhone#cmnsx-products" class="btn-hero">ดู iPhone</a>
        </div>
      </article>
      <article class="hero-slide">
        <img src="https://images.unsplash.com/photo-1542751110-97427bbecf20?q=80&w=2000&auto=format&fit=crop" alt="iPad">
        <div class="hero-caption">
          <h2>iPad พร้อมใช้งาน</h2>
          <p>เหมาะกับการเรียนและทำงาน</p>
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
        <h3>โปรโมชั่นพิเศษ</h3>
        <p>ลดสูงสุด 30% สำหรับ MacBook</p>
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

<!-- ========== QUICK CATEGORIES ========== -->
<section class="cat-gallery" aria-label="เลือกตามหมวด">
  <h2 class="sec-title">เลือกตามหมวด</h2>
  <ul class="cat-grid">
    <li class="cat-item"><a href="/shop/?cat=MacBook#cmnsx-products" class="cat-link"><div class="cat-media"><img src="assets/img/cats/macbook.png" alt="MacBook" loading="lazy" decoding="async"></div><div class="cat-label">MacBook</div></a></li>
    <li class="cat-item"><a href="/shop/?cat=iMac#cmnsx-products" class="cat-link"><div class="cat-media"><img src="assets/img/cats/imac.png" alt="iMac" loading="lazy" decoding="async"></div><div class="cat-label">iMac</div></a></li>
    <li class="cat-item"><a href="/shop/?cat=iPhone#cmnsx-products" class="cat-link"><div class="cat-media"><img src="assets/img/cats/iphone.png" alt="iPhone" loading="lazy" decoding="async"></div><div class="cat-label">iPhone</div></a></li>
    <li class="cat-item"><a href="/shop/?cat=iPad#cmnsx-products" class="cat-link"><div class="cat-media"><img src="assets/img/cats/ipad.png" alt="iPad" loading="lazy" decoding="async"></div><div class="cat-label">iPad</div></a></li>
    <li class="cat-item"><a href="/shop/?cat=Watch#cmnsx-products" class="cat-link"><div class="cat-media"><img src="assets/img/cats/watch.png" alt="Apple Watch" loading="lazy" decoding="async"></div><div class="cat-label">Apple Watch</div></a></li>
    <li class="cat-item"><a href="/shop/?cat=AirPods#cmnsx-products" class="cat-link"><div class="cat-media"><img src="assets/img/cats/airpods.png" alt="AirPods" loading="lazy" decoding="async"></div><div class="cat-label">AirPods</div></a></li>
    <li class="cat-item"><a href="/shop/?cat=Accessories#cmnsx-products" class="cat-link"><div class="cat-media"><img src="assets/img/cats/accessories.png" alt="Accessories" loading="lazy" decoding="async"></div><div class="cat-label">Accessories</div></a></li>
  </ul>
</section>

<!-- ========== FILTER BAR (single-line dropdowns) ========== -->
<?php
  $labelRam   = $ram_min  !== null ? "RAM: ≥ {$ram_min}GB"  : "RAM";
  $labelSsd   = $ssd_min  !== null ? "SSD: ≥ {$ssd_min}GB"  : "SSD";
  $labelYear  = $year_min !== null ? "ปี: ≥ {$year_min}"    : "ปี";
  $labelColor = $color    !== ''   ? "สี: {$color}"         : "สี";
?>
<section class="filter-bar" aria-label="ฟิลเตอร์สินค้าแบบดรอปดาว">
  <div class="fb-row">
    <!-- RAM -->
    <div class="fb-dd">
      <button type="button" class="fb-chip<?= $ram_min!==null?' is-active':'' ?>" aria-haspopup="true" aria-expanded="false">
        <span class="material-symbols-rounded" aria-hidden="true">memory</span><?= h($labelRam) ?>
        <span class="material-symbols-rounded fb-caret" aria-hidden="true">expand_more</span>
      </button>
      <div class="fb-menu" role="menu">
        <a role="menuitem" class="fb-item<?= $ram_min===null?' is-selected':'' ?>" href="<?= h(build_url(['ram_min'=>null,'page'=>1])) ?>#cmnsx-products">ทั้งหมด</a>
        <?php foreach($facets['ram'] as $r):
          $val=(int)$r['val']; $href=h(build_url(['ram_min'=>$val, 'page'=>1])) . '#cmnsx-products';
          $sel = ($ram_min!==null && (int)$ram_min===$val) ? ' is-selected':'';
        ?>
          <a role="menuitem" class="fb-item<?= $sel ?>" href="<?= $href ?>"><?= $val ?> GB <span class="fb-count">(<?= (int)$r['c'] ?>)</span></a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- SSD -->
    <div class="fb-dd">
      <button type="button" class="fb-chip<?= $ssd_min!==null?' is-active':'' ?>" aria-haspopup="true" aria-expanded="false">
        <span class="material-symbols-rounded" aria-hidden="true">hard_drive</span><?= h($labelSsd) ?>
        <span class="material-symbols-rounded fb-caret" aria-hidden="true">expand_more</span>
      </button>
      <div class="fb-menu" role="menu">
        <a role="menuitem" class="fb-item<?= $ssd_min===null?' is-selected':'' ?>" href="<?= h(build_url(['ssd_min'=>null,'page'=>1])) ?>#cmnsx-products">ทั้งหมด</a>
        <?php foreach($facets['ssd'] as $r):
          $val=(int)$r['val']; $href=h(build_url(['ssd_min'=>$val, 'page'=>1])) . '#cmnsx-products';
          $sel = ($ssd_min!==null && (int)$ssd_min===$val) ? ' is-selected':'';
        ?>
          <a role="menuitem" class="fb-item<?= $sel ?>" href="<?= $href ?>"><?= $val ?> GB <span class="fb-count">(<?= (int)$r['c'] ?>)</span></a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ปี -->
    <div class="fb-dd">
      <button type="button" class="fb-chip<?= $year_min!==null?' is-active':'' ?>" aria-haspopup="true" aria-expanded="false">
        <span class="material-symbols-rounded" aria-hidden="true">calendar_month</span><?= h($labelYear) ?>
        <span class="material-symbols-rounded fb-caret" aria-hidden="true">expand_more</span>
      </button>
      <div class="fb-menu" role="menu">
        <a role="menuitem" class="fb-item<?= $year_min===null?' is-selected':'' ?>" href="<?= h(build_url(['year_min'=>null,'year_max'=>null,'page'=>1])) ?>#cmnsx-products">ทั้งหมด</a>
        <?php foreach($facets['year'] as $r):
          $val=(int)$r['val']; $href=h(build_url(['year_min'=>$val, 'page'=>1])) . '#cmnsx-products';
          $sel = ($year_min!==null && (int)$year_min===$val) ? ' is-selected':'';
        ?>
          <a role="menuitem" class="fb-item<?= $sel ?>" href="<?= $href ?>">≥ <?= $val ?> <span class="fb-count">(<?= (int)$r['c'] ?>)</span></a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- สี -->
    <div class="fb-dd">
      <button type="button" class="fb-chip<?= $color!==''?' is-active':'' ?>" aria-haspopup="true" aria-expanded="false">
        <span class="material-symbols-rounded" aria-hidden="true">palette</span><?= h($labelColor) ?>
        <span class="material-symbols-rounded fb-caret" aria-hidden="true">expand_more</span>
      </button>
      <div class="fb-menu" role="menu">
        <a role="menuitem" class="fb-item<?= $color===''?' is-selected':'' ?>" href="<?= h(build_url(['color'=>null,'page'=>1])) ?>#cmnsx-products">ทั้งหมด</a>
        <?php foreach($facets['color'] as $r):
          if(!$r['val']) continue; $val=(string)$r['val'];
          $href=h(build_url(['color'=>$val, 'page'=>1])) . '#cmnsx-products';
          $sel = ($color!=='' && $color===$val) ? ' is-selected':'';
        ?>
          <a role="menuitem" class="fb-item<?= $sel ?>" href="<?= $href ?>"><?= h($val) ?> <span class="fb-count">(<?= (int)$r['c'] ?>)</span></a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="fb-spacer"></div>
    <a class="fb-clear" href="/shop/#cmnsx-products"><span class="material-symbols-rounded" aria-hidden="true">filter_alt_off</span> ล้างทั้งหมด</a>
  </div>
</section>

<!-- ======================== PRODUCT LIST (AJAX-ready) ======================== -->
<?= $__products_html ?>

<script src="/shop/assets/js/hero.js" defer></script>
<script src="assets/js/hero-slider.js"></script>
<script>
  function toggleSidebar(){
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebar-overlay').classList.toggle('show');
    document.getElementById('hamburger').classList.toggle('open');
  }
  function toggleSidebarDropdown(btn){
    btn.closest('.sidebar-dropdown').classList.toggle('open');
  }
  (function handleNavbarScroll(){
    const nav = document.querySelector('.navbar');
    function onScroll(){ if (window.scrollY > 30) nav.classList.add('scrolled'); else nav.classList.remove('scrolled'); }
    window.addEventListener('scroll', onScroll); onScroll();
  })();

  // ===== Smooth partial reload (กึ่ง-AJAX) =====
  (function(){
    var root = document.getElementById('cmnsx-products');
    if (!root) return;

    function loadProducts(url, push){
      var u = new URL(url, location.origin);
      u.hash = '';
      u.searchParams.set('ajax','1');
      root.style.opacity = '0.4';
      fetch(u, { headers: { 'X-Requested-With':'fetch' }})
        .then(r=>r.text())
        .then(function(html){
          root.outerHTML = html;
          var newRoot = document.getElementById('cmnsx-products');
          if (newRoot) {
            root = newRoot;
            root.style.opacity = '1';
            if (push) history.pushState(null, '', url);
            root.scrollIntoView({ behavior:'smooth', block:'start' });
          }
        })
        .catch(function(){
          root.style.opacity = '1';
          location.href = url;
        });
    }

    // ดักเพจเนชัน
    document.addEventListener('click', function(e){
      var a = e.target.closest('.cmnsx-pager a');
      if (!a) return;
      e.preventDefault();
      loadProducts(a.href, true);
    });

    // ดักตัวกรอง + ปุ่มล้าง
    document.addEventListener('click', function(e){
      var a = e.target.closest('.filter-bar a');
      if (!a) return;
      document.querySelectorAll('.fb-dd.is-open').forEach(dd => dd.classList.remove('is-open'));
      e.preventDefault();
      loadProducts(a.href, true);
    });

    // ลิงก์ที่ลงท้ายด้วย #cmnsx-products
    document.addEventListener('click', function(e){
      var a = e.target.closest('a[href^="/shop/"]');
      if (!a) return;
      if (!/#cmnsx-products$/.test(a.getAttribute('href'))) return;
      e.preventDefault();
      loadProducts(a.href, true);
    });

    // ฟอร์มค้นหา
    document.querySelectorAll('form.search-form').forEach(function(form){
      form.addEventListener('submit', function(e){
        e.preventDefault();
        var params = new URLSearchParams(new FormData(form));
        var url = (form.getAttribute('action') || '/shop/') + '?' + params.toString() + '#cmnsx-products';
        loadProducts(url, true);
      });
    });

    // รองรับ Back/Forward
    window.addEventListener('popstate', function(){
      loadProducts(location.href, false);
    });
  })();

  // ===== Floating dropdown (fixed placement) =====
  (function(){
    const row = document.querySelector('.fb-row');

    function placeMenu(dd){
      const btn  = dd.querySelector('.fb-chip');
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

      const top  = Math.min(vh - 12, r.bottom + gap);
      const maxH = Math.max(160, vh - top - 12);

      menu.style.left = left + 'px';
      menu.style.top  = top + 'px';
      menu.style.maxHeight = maxH + 'px';
      menu.style.overflow = 'auto';
      menu.style.zIndex = 1000;
      if (row){ row.style.overflowY = 'visible'; row.style.overflowX = 'auto'; }
    }

    function openDD(dd){
      document.querySelectorAll('.fb-dd.is-open').forEach(el=>{
        if (el!==dd) el.classList.remove('is-open');
      });
      dd.classList.add('is-open');
      const btn = dd.querySelector('.fb-chip');
      if (btn) btn.setAttribute('aria-expanded','true');
      placeMenu(dd);
    }

    function closeAll(){
      document.querySelectorAll('.fb-dd.is-open').forEach(dd=>{
        dd.classList.remove('is-open');
        const b=dd.querySelector('.fb-chip'); if(b) b.setAttribute('aria-expanded','false');
      });
      if (row){ row.style.overflowY = 'visible'; row.style.overflowX = 'auto'; }
    }

    document.addEventListener('click', function(e){
      const chip = e.target.closest('.fb-chip');
      const inMenu = e.target.closest('.fb-menu');
      if (chip){ e.preventDefault(); const dd = chip.closest('.fb-dd'); dd.classList.contains('is-open') ? closeAll() : openDD(dd); return; }
      if (inMenu) return;
      closeAll();
    });
    document.addEventListener('keydown', e=>{ if (e.key==='Escape') closeAll(); });
    window.addEventListener('scroll', closeAll, {passive:true});
    window.addEventListener('resize', function(){
      const dd = document.querySelector('.fb-dd.is-open');
      if (dd) placeMenu(dd);
    });
    document.addEventListener('click', function(e){
      const a = e.target.closest('.filter-bar .fb-menu a'); if (!a) return; closeAll();
    });
  })();
</script>

<!-- ======================= MINI CART (DRAWER, localStorage) ======================= -->
<div id="mini-cart-overlay" style="display:none;"></div>
<aside id="mini-cart" aria-label="ตะกร้าสินค้า" style="display:none;" tabindex="-1">
  <header class="mc-head">
    <h3>ตะกร้าสินค้า</h3>
    <button type="button" class="mc-close" aria-label="ปิดตะกร้า">
      <span class="material-symbols-rounded">close</span>
    </button>
  </header>

  <div class="mc-body">
    <ul class="mc-list" id="mc-list"></ul>
    <div class="mc-empty" id="mc-empty">ยังไม่มีสินค้าในตะกร้า</div>
  </div>

  <footer class="mc-foot">
    <div class="mc-sum">
      <span>รวมทั้งหมด</span>
      <strong id="mc-total">฿0</strong>
    </div>
    <div class="mc-actions">
      <button type="button" id="mc-clear" class="mc-btn ghost">
        <span class="material-symbols-rounded">delete</span> ล้างตะกร้า
      </button>
      <a href="#!" id="mc-checkout" class="mc-btn primary">
        <span class="material-symbols-rounded">receipt_long</span> สรุปรายการ
      </a>
    </div>
  </footer>
</aside>

<!-- ===== Receipt Popup (Summary Modal) ===== -->
<div id="receipt-backdrop" aria-hidden="true" style="display:none;"></div>
<aside id="receipt-modal" role="dialog" aria-modal="true" aria-labelledby="receipt-title" style="display:none;">
  <header class="rcp-head">
    <h3 id="receipt-title">สรุปรายการ</h3>
    <button type="button" class="rcp-close" aria-label="ปิดป็อปอัพ">
      <span class="material-symbols-rounded">close</span>
    </button>
  </header>

  <div class="rcp-body">
    <ul id="rcp-list" class="rcp-list"></ul>

    <div class="rcp-sum">
      <div class="row"><span>ยอดรวมสินค้า</span><strong id="rcp-subtotal">฿0</strong></div>
      <div class="row"><span>ค่าจัดส่ง</span><strong id="rcp-ship">ฟรี</strong></div>
      <div class="split"></div>
      <div class="row grand"><span>ยอดสุทธิ</span><strong id="rcp-total">฿0</strong></div>
    </div>

    <!-- QR LINE -->
    <div class="rcp-qr">
      <div class="qr-box">
        <img src="assets/img/line.png" alt="LINE QR @cmnsfixmac" loading="lazy" decoding="async">
      </div>
      <div class="qr-text">
        <h4>ชำระเงิน & ติดต่อ</h4>
        <p>สแกน QR เพื่อแอด <b>LINE Official @cmnsfixmac</b> แล้วส่งสลิป/สอบถามสต็อก หรือนัดรับได้ทันที</p>
        <a class="rcp-btn line small" href="https://line.me/R/ti/p/@cmns" target="_blank" rel="noopener">
          <span class="material-symbols-rounded">chat_bubble</span> เปิด LINE
        </a>
      </div>
    </div>
  </div>

  <footer class="rcp-foot">
    <button type="button" id="rcp-copy" class="rcp-btn ghost">
      <span class="material-symbols-rounded">content_copy</span> คัดลอกรายการ
    </button>
    <a id="rcp-line" class="rcp-btn line" href="#" target="_blank" rel="noopener">
      <span class="material-symbols-rounded">send</span> ส่งรายละเอียดไป LINE
    </a>
    <button type="button" id="rcp-print" class="rcp-btn">
      <span class="material-symbols-rounded">print</span> พิมพ์ / บันทึกเป็น PDF
    </button>
  </footer>
</aside>

<script>
(function(){
  // ===== Cart (localStorage) + Smooth drawer + animations =====
  const $  = (s,ctx=document)=>ctx.querySelector(s);
  const $$ = (s,ctx=document)=>Array.from(ctx.querySelectorAll(s));
  const LS_KEY = 'cmnsx_cart';
  const fmt = n => '฿' + (Number(n)||0).toLocaleString('th-TH', {maximumFractionDigits:0});

  function loadCart(){ try{ return JSON.parse(localStorage.getItem(LS_KEY)) || []; }catch(e){ return []; } }
  function saveCart(cart){ localStorage.setItem(LS_KEY, JSON.stringify(cart)); }
  function cartCount(){ return loadCart().reduce((s,it)=>s+(Number(it.qty)||1),0); }
  function cartTotal(){ return loadCart().reduce((s,it)=>s+(Number(it.price)||0)*(Number(it.qty)||1),0); }

  function upsertItem({id,name,price,img,url}, qty=1){
    const cart = loadCart();
    const i = cart.findIndex(it => String(it.id)===String(id));
    if (i>-1){ cart[i].qty = Math.min(99, (Number(cart[i].qty)||1) + qty); }
    else { cart.push({id, name, price:Number(price)||0, img, url, qty:Math.max(1,qty)}); }
    saveCart(cart);
    render();
  }
  function setQty(id, qty){
    const cart = loadCart();
    const i = cart.findIndex(it => String(it.id)===String(id));
    if (i>-1){ cart[i].qty = Math.max(1, Math.min(99, Number(qty)||1)); saveCart(cart); render(); }
  }
  function removeItem(id){ saveCart(loadCart().filter(it=>String(it.id)!==String(id))); render(); }
  function clearCart(){ saveCart([]); render(); }

  // Drawer elements
  const overlay  = $('#mini-cart-overlay');
  const drawer   = $('#mini-cart');
  const listEl   = $('#mc-list');
  const emptyEl  = $('#mc-empty');
  const totalEl  = $('#mc-total');
  const checkout = $('#mc-checkout');
  const cartBadge= document.querySelector('.nav-cart .cart-count');
  const navCart  = document.querySelector('.nav-cart');

  // Smooth open/close
  window.openCart = function(){
    overlay.style.display = 'block';
    drawer.style.display  = 'flex';
    document.body.classList.add('mc-open');
    drawer.focus();
  };
  window.closeCart = function(){
    document.body.classList.remove('mc-open');
    setTimeout(()=>{ overlay.style.display='none'; drawer.style.display='none'; }, 320);
  };

  if (navCart){
    navCart.addEventListener('click', e=>{ e.preventDefault(); openCart(); });
    navCart.addEventListener('keydown', e=>{ if (e.key==='Enter'||e.key===' '){ e.preventDefault(); openCart(); }});
  }
  $('.mc-close').addEventListener('click', closeCart);
  overlay.addEventListener('click', closeCart);
  document.addEventListener('keydown', e=>{ if (e.key==='Escape') closeCart(); });

  function escapeHtml(s){ return String(s||'').replace(/[&<>"]/g, c=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c])); }

  function render(){
    const cart = loadCart();
    if (cartBadge) cartBadge.textContent = String(cartCount());

    emptyEl.style.display = cart.length ? 'none' : 'block';
    listEl.innerHTML = '';
    cart.forEach(it=>{
      const li = document.createElement('li');
      li.className = 'mc-item';
      li.innerHTML = `
        <img class="mc-thumb" src="${it.img || '/assets/img/placeholder.jpg'}" alt="">
        <div>
          <div class="mc-title"><a href="${it.url||'#'}">${escapeHtml(it.name||'สินค้า')}</a></div>
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
  function badgeBump(){
    const b = document.querySelector('.nav-cart .cart-count');
    if (!b) return;
    b.classList.remove('pop'); void b.offsetWidth; b.classList.add('pop');
  }
  function showToast(msg){
    const t = document.createElement('div');
    t.className = 'toast';
    t.textContent = msg || 'เพิ่มลงตะกร้าแล้ว';
    document.body.appendChild(t);
    requestAnimationFrame(()=> t.classList.add('show'));
    setTimeout(()=>{ t.classList.remove('show'); setTimeout(()=>t.remove(), 250); }, 1600);
  }
  function flyToCart(fromEl){
    if (!cartIcon) return;
    const card = fromEl.closest('.cmnsx-card');
    const img  = card?.querySelector('.cmnsx-thumb img') || card?.querySelector('img');
    const src  = img?.getAttribute('src'); if (!src) return;
    const rectFrom = img.getBoundingClientRect();
    const rectTo   = cartIcon.getBoundingClientRect();
    const ghost = document.createElement('img');
    ghost.className = 'fly-img'; ghost.src = src;
    ghost.style.left = (rectFrom.left + rectFrom.width/2 - 32) + 'px';
    ghost.style.top  = (rectFrom.top + rectFrom.height/2 - 32) + 'px';
    document.body.appendChild(ghost);
    const dx = (rectTo.left + rectTo.width/2) - (rectFrom.left + rectFrom.width/2);
    const dy = (rectTo.top + rectTo.height/2)  - (rectFrom.top + rectFrom.height/2);
    requestAnimationFrame(()=>{ ghost.style.transform = `translate(${dx}px, ${dy}px) scale(.35)`; ghost.style.opacity = '0.2'; });
    setTimeout(()=> ghost.remove(), 650);
  }

  // Add-to-cart (รวม animation)
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.card-cart-btn');
    if (!btn) return;
    e.preventDefault();

    btn.disabled = true;
    const payload = {
      id:   btn.dataset.id,
      name: btn.dataset.name || btn.closest('.cmnsx-card')?.querySelector('.cmnsx-name')?.textContent?.trim(),
      price: btn.dataset.price || btn.closest('.cmnsx-card')?.querySelector('.cmnsx-price-now')?.textContent?.replace(/[^\d]/g,''),
      img:  btn.dataset.img || btn.closest('.cmnsx-card')?.querySelector('img')?.getAttribute('src'),
      url:  btn.dataset.url || btn.closest('.cmnsx-card')?.querySelector('.cmnsx-link')?.getAttribute('href'),
    };
    payload.price = Number(payload.price)||0;
    upsertItem(payload, 1);

    try{ navigator.vibrate && navigator.vibrate(30); }catch(e){}
    btn.classList.add('added');
    setTimeout(()=>{ btn.classList.remove('added'); btn.disabled=false; }, 320);

    flyToCart(btn);
    badgeBump();
    showToast('เพิ่มลงตะกร้าแล้ว');
  });

  // Qty +/- and remove
  document.getElementById('mc-list').addEventListener('click', function(e){
    const minus = e.target.closest('.mc-minus');
    const plus  = e.target.closest('.mc-plus');
    const remove= e.target.closest('.mc-remove');

    if (minus || plus){
      const wrap = e.target.closest('.mc-qty');
      const id   = wrap?.dataset.id;
      const inp  = wrap?.querySelector('.mc-input');
      if (!id || !inp) return;
      let q = Number(inp.value)||1;
      q = minus ? Math.max(1, q-1) : Math.min(99, q+1);
      setQty(id, q);
      return;
    }
    if (remove){
      const id = remove.dataset.id;
      if (id) removeItem(id);
      return;
    }
  });
  document.getElementById('mc-list').addEventListener('input', function(e){
    const inp = e.target.closest('.mc-input'); if (!inp) return;
    const wrap= e.target.closest('.mc-qty'); const id = wrap?.dataset.id;
    if (!id) return;
    const q = Math.max(1, Math.min(99, Number(inp.value.replace(/[^\d]/g,''))||1));
    setQty(id, q);
  });

  // ====== Receipt Popup ======
  const bd = document.getElementById('receipt-backdrop');
  const md = document.getElementById('receipt-modal');
  const rList = document.getElementById('rcp-list');
  const rSub  = document.getElementById('rcp-subtotal');
  const rShip = document.getElementById('rcp-ship');
  const rTot  = document.getElementById('rcp-total');
  const rClose= md.querySelector('.rcp-close');
  const rCopy = document.getElementById('rcp-copy');
  const rPrint= document.getElementById('rcp-print');
  const rLine = document.getElementById('rcp-line');
  const SHIPPING = 0;

  function showReceipt(){ bd.style.display='block'; md.style.display='grid'; }
  function hideReceipt(){ bd.style.display='none'; md.style.display='none'; }

  function buildSummaryText(cart, total){
    const rows = cart.map(it => `• ${it.name} x ${it.qty} = ${fmt((Number(it.price)||0)*(Number(it.qty)||1))}`);
    rows.push(`\nรวมทั้งหมดยอดสุทธิ: ${fmt(total)}`);
    rows.push(`\nติดต่อ: LINE @cmnsfixmac`);
    return rows.join('\n');
    }

  function openReceipt(){
    const cart = loadCart();
    if (!cart.length){ alert('ตะกร้่าว่าง'); return; }

    rList.innerHTML = '';
    cart.forEach(it=>{
      const li = document.createElement('li');
      li.className = 'rcp-item';
      li.innerHTML = `
        <img class="rcp-thumb" src="${it.img || '/assets/img/placeholder.jpg'}" alt="">
        <div>
          <div class="rcp-title">${(it.name||'สินค้า').replace(/[&<>"]/g,s=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[s]))}</div>
          <div class="rcp-meta">จำนวน: ${Number(it.qty)||1}</div>
        </div>
        <div class="rcp-price">${fmt((Number(it.price)||0)*(Number(it.qty)||1))}</div>
      `;
      rList.appendChild(li);
    });

    const sub = cart.reduce((s,it)=> s + (Number(it.price)||0)*(Number(it.qty)||1), 0);
    const ship = SHIPPING;
    const grand = sub + ship;

    rSub.textContent = fmt(sub);
    rShip.textContent = ship ? fmt(ship) : 'ฟรี';
    rTot.textContent = fmt(grand);

    const text = buildSummaryText(cart, grand);
    rCopy.onclick = async ()=> {
      try { await navigator.clipboard.writeText(text); rCopy.innerHTML='<span class="material-symbols-rounded">check</span> คัดลอกแล้ว'; setTimeout(()=>rCopy.innerHTML='<span class="material-symbols-rounded">content_copy</span> คัดลอกรายการ',1200); }
      catch(e){ alert('คัดลอกไม่สำเร็จ'); }
    };
    rLine.href = 'https://line.me/R/ti/p/@cmns'
    rPrint.onclick = ()=> window.print();

    showReceipt();
  }

  document.getElementById('mc-clear').addEventListener('click', function(){
    if (confirm('ล้างตะกร้าทั้งหมด?')) clearCart();
  });
  document.getElementById('mc-checkout').addEventListener('click', function(e){
    e.preventDefault();
    openReceipt();
  });

  bd.addEventListener('click', hideReceipt);
  rClose.addEventListener('click', hideReceipt);
  document.addEventListener('keydown', e=>{ if (e.key==='Escape') hideReceipt(); });

  // init
  render();
})();
</script>


<!-- ===== PROMO POPUP (first-visit) ===== -->
<!-- ===== PROMO POPUP (first-visit) ===== -->
<div id="promo-backdrop" aria-hidden="true" hidden></div>

<aside id="promo-modal" role="dialog" aria-modal="true" aria-labelledby="promo-title" hidden>
  <div class="promo-card" role="document">
    <button type="button" class="promo-close" aria-label="ปิดโปรโมชั่น">
      <span class="material-symbols-rounded" aria-hidden="true">close</span>
    </button>

    <div class="promo-media">
      <!-- เปลี่ยนรูปโปรโมชันได้ตามต้องการ -->
      <img
        src="/assets/img/promo/launch.jpg"
        alt="โปรโมชั่นพิเศษจาก CMNS FixMac"
        onerror="this.closest('.promo-media').classList.add('is-hidden')"
      >
      <div class="promo-badge">โปรพิเศษ</div>
    </div>

    <div class="promo-body">
      <h3 id="promo-title">ลดพิเศษเฉพาะสัปดาห์นี้!</h3>
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
        <input type="checkbox" id="promo-never"> ไม่ต้องแสดงอีก (ซ่อนไว้ยาวๆ)
      </label>
    </div>
  </div>
</aside>
<!-- ===== /PROMO POPUP ===== -->
<!-- --- SAFE CSS for promo popup --- -->
<style>
  /* ซ่อนแน่นอนเมื่อมี [hidden] */
  #promo-backdrop[hidden],
  #promo-modal[hidden] { display: none !important; }

  /* Backdrop: ไม่รับคลิกจนกว่าจะ .show */
  #promo-backdrop{
    position: fixed; inset: 0;
    background: rgba(0,0,0,.55);
    opacity: 0; pointer-events: none;
    transition: opacity .25s ease;
    z-index: 9996;
  }
  #promo-backdrop.show{ opacity: 1; pointer-events: auto; }

  /* Modal: ไม่รับคลิก/โฟกัสจนกว่าจะ .show */
  #promo-modal{
    position: fixed; inset: 0;
    display: grid; place-items: center;
    padding: 16px;
    opacity: 0; pointer-events: none;
    transition: opacity .25s ease, transform .25s ease;
    z-index: 9997;
  }
  #promo-modal.show{ opacity: 1; pointer-events: auto; }

  /* การ์ดด้านใน */
  #promo-modal .promo-card{
    width: min(960px, 96vw);
    max-height: 90vh; overflow: auto;
    background: #fff; border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0,0,0,.25);
    transform: translateY(12px);
    transition: transform .25s ease;
    position: relative;
  }
  #promo-modal.show .promo-card{ transform: translateY(0); }

  .promo-media{ position: relative; overflow: hidden; border-radius: 16px 16px 0 0; }
  .promo-media img{ width: 100%; height: auto; display: block; }
  .promo-media.is-hidden{ display:none; }

  .promo-badge{
    position: absolute; top: 10px; left: 10px;
    background: #111; color: #fff; font-weight: 900;
    border-radius: 999px; padding: 6px 10px; font-size: .9rem;
    box-shadow: 0 6px 20px rgba(0,0,0,.25);
  }

  .promo-close{
    position: absolute; top: 10px; right: 10px;
    width: 36px; height: 36px; border: 0;
    border-radius: 10px; background: rgba(0,0,0,.6);
    color: #fff; cursor: pointer;
    display: grid; place-items: center;
  }

  .promo-body{ padding: 16px; }
  .promo-actions{ display:flex; gap:10px; flex-wrap:wrap; margin:12px 0; }
  .promo-btn{
    display:inline-flex; align-items:center; gap:8px;
    height:44px; padding:0 14px; border-radius:12px;
    text-decoration:none; font-weight:900; border:1px solid transparent;
  }
  .promo-btn.primary{ background:#111; color:#fff; }
  .promo-btn.line{ background:#06C755; color:#fff; border-color:#06C755; }
  .promo-nomore{ display:flex; align-items:center; gap:8px; color:#444; }

  @media (max-width: 640px){
    #promo-modal .promo-card{ width: 100%; max-height: 92vh; }
  }
</style>

<!-- --- SAFE JS for promo popup --- -->
<script>
(function(){
  const KEY_SEEN_AT = 'promo_seen_at_v1';
  const KEY_NEVER   = 'promo_never_v1';
  const DAY = 24*60*60*1000;
  const COOLDOWN_DAYS = 7;

  const bd = document.getElementById('promo-backdrop');
  const md = document.getElementById('promo-modal');
  const closeBtn = md?.querySelector('.promo-close');
  const neverCb  = document.getElementById('promo-never');

  // ป้องกันบั๊ค: ถ้าไม่มี element ให้จบ
  if(!bd || !md) return;

  function hasNever(){ return localStorage.getItem(KEY_NEVER) === '1'; }
  function lastSeen(){ return Number(localStorage.getItem(KEY_SEEN_AT) || 0); }
  function shouldShow(){
    if (hasNever()) return false;
    const seen = lastSeen();
    return !seen || (Date.now() - seen) > COOLDOWN_DAYS * DAY;
  }

  function lockScroll(lock){
    document.documentElement.style.overflow = lock ? 'hidden' : '';
    document.body.style.overflow = lock ? 'hidden' : '';
  }

  function openPromo(){
    // เปิดแบบปลอดภัย: เอา hidden ออกก่อน แล้วค่อย add .show (เพื่อให้ transition ทำงาน)
    bd.hidden = false; md.hidden = false;
    requestAnimationFrame(()=>{
      bd.classList.add('show');
      md.classList.add('show');
      lockScroll(true);
      closeBtn?.focus();
    });
  }

  function closePromo(persistSeen = true){
    bd.classList.remove('show');
    md.classList.remove('show');
    // ปิดการรับคลิกทันที
    lockScroll(false);
    // รอ transition แล้วค่อย hidden
    setTimeout(()=>{ bd.hidden = true; md.hidden = true; }, 260);
    if (persistSeen) localStorage.setItem(KEY_SEEN_AT, String(Date.now()));
    if (neverCb?.checked) localStorage.setItem(KEY_NEVER, '1');
  }

  // เปิดเมื่อหน้าโหลด (เฉพาะครั้งแรก/ครบกำหนด) + dev override (?promo=show)
  window.addEventListener('DOMContentLoaded', ()=>{
    const forceShow = new URLSearchParams(location.search).get('promo') === 'show';
    if (forceShow){
      openPromo();
      return;
    }

    if (shouldShow()){
      openPromo();
    } else {
      // เผื่อเคยค้าง: บังคับซ่อน
      bd.hidden = true; md.hidden = true;
      bd.classList.remove('show'); md.classList.remove('show');
      lockScroll(false);
    }
  });

  // ปิดด้วยปุ่ม/คลิกฉาก/กด ESC
  closeBtn?.addEventListener('click', ()=> closePromo(true));
  bd.addEventListener('click', ()=> closePromo(true));
  document.addEventListener('keydown', (e)=>{
    if (e.key === 'Escape' && !md.hidden) closePromo(false);
  });
})();
</script>






</body>
</html>
