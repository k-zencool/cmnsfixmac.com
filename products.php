<?php
require 'includes/db.php';

if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
  $stmt = $pdo->query("SELECT * FROM products WHERE status = 1 ORDER BY RAND() LIMIT 4");
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) :
?>
    <a href="product-detail.php?id=<?= $row['id'] ?>" class="product-card">
      <img src="uploads/<?= htmlspecialchars($row['main_image']) ?>" alt="<?= htmlspecialchars($row['name']) ?>"loading="lazy">
      <div class="product-info">
        <p class="category"><?= htmlspecialchars($row['category']) ?></p>
        <h3><?= htmlspecialchars($row['name']) ?></h3>
        <p class="price"><?= number_format($row['price'], 0) ?> บาท</p>
      </div>
    </a>
<?php
  endwhile;
  exit;
}
?>


<?php
// ดึงสินค้าล่าสุดจากฐานข้อมูล (4 ชิ้น สำหรับ Structured Data)
$stmt = $pdo->prepare("SELECT * FROM products WHERE status = 1 ORDER BY created_at DESC LIMIT 4");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<head>
  <meta charset="UTF-8">
  <title>ขาย MacBook มือสอง iPhone มือสอง iPad มือสอง ที่เชียงใหม่ | CMNS FixMac</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="จำหน่าย MacBook มือสอง iPhone มือสอง iPad มือสอง ของแท้ ราคาถูก เชียงใหม่ รับประกันสินค้า พร้อมบริการหลังการขาย โดยทีมงาน CMNS FixMac มืออาชีพ">
  <meta name="keywords" content="MacBook มือสอง, iPhone มือสอง, iPad มือสอง, ขาย MacBook เชียงใหม่, ขาย iPhone เชียงใหม่, ขาย iPad เชียงใหม่, CMNS FixMac">
  <meta name="author" content="CMNS FixMac">

  <!-- Open Graph / Facebook -->
  <meta property="og:type" content="website">
  <meta property="og:title" content="ขาย MacBook มือสอง iPhone มือสอง iPad มือสอง ที่เชียงใหม่ | CMNS FixMac">
  <meta property="og:description" content="ซื้อ MacBook มือสอง สภาพดี ราคาย่อมเยา ได้ที่ CMNS FixMac เชียงใหม่">
  <meta property="og:image" content="https://cmnsfixmac.com/assets/img/og-banner.jpg">
  <meta property="og:url" content="https://cmnsfixmac.com/products.php">
  <meta property="og:site_name" content="CMNS FixMac">

  <!-- Twitter -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="ขาย MacBook มือสอง iPhone มือสอง iPad มือสอง ที่เชียงใหม่ | CMNS FixMac">
  <meta name="twitter:description" content="ซื้อ MacBook มือสอง สภาพดี ราคาย่อมเยา ได้ที่ CMNS FixMac เชียงใหม่">
  <meta name="twitter:image" content="https://cmnsfixmac.com/assets/img/og-banner.jpg">

  <!-- Canonical -->
  <link rel="canonical" href="https://cmnsfixmac.com/products.php" />
  <link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png" />
  <link rel="stylesheet" href="assets/css/products-style.css">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
  <link rel="stylesheet" href="/assets/css/footer-style.css">

  <!-- 🔍 Structured Data: Store -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "Store",
    "name": "CMNS FixMac",
    "image": "https://cmnsfixmac.com/assets/img/og-banner.jpg",
    "@id": "https://cmnsfixmac.com/",
    "url": "https://cmnsfixmac.com/products.php",
    "telephone": "+66 84 151 1684",
    "address": {
      "@type": "PostalAddress",
      "streetAddress": "482 หมู่ 8 ถ.เชียงใหม่-หางดง ต.แม่เหียะ อ.เมือง",
      "addressLocality": "เชียงใหม่",
      "postalCode": "50100",
      "addressCountry": "TH"
    },
    "description": "ขาย MacBook มือสอง iPhone มือสอง iPad มือสอง ในเชียงใหม่ ราคาถูก สภาพดี มีรับประกัน"
  }
  </script>

  <!-- 🛍️ Structured Data: Product จากฐานข้อมูล -->
  <script type="application/ld+json">
  <?= json_encode([
    "@context" => "https://schema.org",
    "@graph" => array_map(function($product) {
      return [
        "@type" => "Product",
        "name" => htmlspecialchars($product['name']),
        "image" => "https://cmnsfixmac.com/uploads/" . htmlspecialchars($product['main_image']),
        "description" => htmlspecialchars($product['category']) . " มือสอง สภาพดี ราคาถูก โดย CMNS FixMac",
        "sku" => "SKU-" . $product['id'],
        "offers" => [
          "@type" => "Offer",
          "priceCurrency" => "THB",
          "price" => number_format($product['price'], 0, '.', ''),
          "availability" => "https://schema.org/InStock",
          "itemCondition" => "https://schema.org/UsedCondition",
          "url" => "https://cmnsfixmac.com/product-detail.php?id=" . $product['id']
        ]
      ];
    }, $products)
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
  </script>
</head>



<body>

<?php include_once 'includes/header.php'; ?> 
<div class="container">


  <!-- 🍎 Apple Categories -->
  <section class="apple-product-categories">
    <h2>ผลิตภัณฑ์ของ <strong>Apple</strong> ทั้งหมด</h2>
    <div class="category-list">
  <a href="#section-macbook" class="category-item">
    <img src="assets/img/macbook.png" loading="lazy"alt="MacBook"><p class="title">MacBook</p>
  </a>
  <a href="#section-imac" class="category-item">
    <img src="assets/img/mac.png" loading="lazy"alt="iMac"><p class="title">iMac</p>
  </a>
  <a href="#section-iphone" class="category-item">
    <img src="assets/img/iphone.png" loading="lazy"alt="iPhone"><p class="title">iPhone</p>
  </a>
  <a href="#section-ipad" class="category-item">
    <img src="assets/img/ipad.png" loading="lazy"alt="iPad"><p class="title">iPad</p>
  </a>
  <a href="#section-watch" class="category-item">
    <img src="assets/img/watch.png" loading="lazy"alt="Apple Watch"><p class="title">Apple Watch</p>
  </a>
  <a href="#section-airpods" class="category-item">
    <img src="assets/img/airpods.png" loading="lazy"alt="AirPods"><p class="title">AirPods</p>
  </a>
  <a href="#section-accessories" class="category-item">
    <img src="assets/img/accessories.png" loading="lazy"alt="Accessories"><p class="title">Accessories</p>
  </a>
</div>

  </section>
  <div class="section-divider"></div>


  <div class="category-filter">
    <button class="filter-btn" data-category="all">ทั้งหมด</button>
    <button class="filter-btn" data-category="MacBook">MacBook</button>
    <button class="filter-btn" data-category="iPhone">iPhone</button>
    <button class="filter-btn" data-category="iPad">iPad</button>
  </div>


  <!-- 🔁 Section: สินค้าแนะนำ -->
  <section class="product-grid-section">
    <div class="product-grid-header">
      <h2>สินค้าแนะนำ</h2>
      <button id="refresh-btn" class="refresh-btn">เปลี่ยนสินค้า</button>
    </div>

    <div class="product-wrapper">
      <div id="random-products" class="product-row">
        <!-- JS สินค้าจะมาใส่ตรงนี้ -->
      </div>
    </div>
  </section>

  <?php
  $stmt = $pdo->prepare("SELECT * FROM products WHERE category = 'MacBook' AND status = 1 ORDER BY created_at DESC");
  $stmt->execute();
  $macbookProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (count($macbookProducts) > 0):
?>
<!-- 🔻 Section: MacBook -->
<section id="section-macbook" class="product-grid-section">
  <div class="product-grid-header">
    <h2>MacBook</h2>
    <button class="refresh-btn" onclick="toggleSection(this, 'macbook-products')">ดูทั้งหมด</button>
  </div>

  <div class="product-wrapper">
    <div id="macbook-products" class="product-row">
      <?php
        $index = 1;
        foreach ($macbookProducts as $row):
      ?>
        <a href="product-detail.php?id=<?= $row['id'] ?>" class="product-card<?= $index > 4 ? ' hidden-product' : '' ?>">
          <img src="uploads/<?= htmlspecialchars($row['main_image']) ?>" alt="<?= htmlspecialchars($row['name']) ?>" loading="lazy">
          <div class="product-info">
            <p class="category"><?= htmlspecialchars($row['category']) ?></p>
            <h3><?= htmlspecialchars($row['name']) ?></h3>
            <p class="price"><?= number_format($row['price'], 0) ?> บาท</p>
          </div>
        </a>
      <?php $index++; endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php
  $stmt = $pdo->prepare("SELECT * FROM products WHERE category = 'iMac' AND status = 1 ORDER BY created_at DESC");
  $stmt->execute();
  $imacProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (count($imacProducts) > 0): ?>
  <!-- 🔻 Section: iMac -->
  <section id="section-imac" class="product-grid-section">
    <div class="product-grid-header">
      <h2>iMac</h2>
      <button class="refresh-btn" onclick="toggleSection(this, 'imac-products')">ดูทั้งหมด</button>
    </div>

    <div class="product-wrapper">
      <div id="imac-products" class="product-row">
        <?php foreach ($imacProducts as $index => $row): ?>
          <a href="product-detail.php?id=<?= $row['id'] ?>" class="product-card<?= $index > 3 ? ' hidden-product' : '' ?>">
            <img src="uploads/<?= htmlspecialchars($row['main_image']) ?>" alt="<?= htmlspecialchars($row['name']) ?>"loading="lazy">
            <div class="product-info">
              <p class="category"><?= htmlspecialchars($row['category']) ?></p>
              <h3><?= htmlspecialchars($row['name']) ?></h3>
              <p class="price"><?= number_format($row['price'], 0) ?> บาท</p>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php
  $stmt = $pdo->prepare("SELECT * FROM products WHERE category = 'iPhone' AND status = 1 ORDER BY created_at DESC");
  $stmt->execute();
  $iphoneProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (count($iphoneProducts) > 0): ?>
  <!-- 🔻 Section: iPhone -->
  <section id="section-iphone" class="product-grid-section">
    <div class="product-grid-header">
      <h2>iPhone</h2>
      <button class="refresh-btn" onclick="toggleSection(this, 'iphone-products')">ดูทั้งหมด</button>
    </div>

    <div class="product-wrapper">
      <div id="iphone-products" class="product-row">
        <?php foreach ($iphoneProducts as $index => $row): ?>
          <a href="product-detail.php?id=<?= $row['id'] ?>" class="product-card<?= $index > 3 ? ' hidden-product' : '' ?>">
            <img src="uploads/<?= htmlspecialchars($row['main_image']) ?>" alt="<?= htmlspecialchars($row['name']) ?>"loading="lazy">
            <div class="product-info">
              <p class="category"><?= htmlspecialchars($row['category']) ?></p>
              <h3><?= htmlspecialchars($row['name']) ?></h3>
              <p class="price"><?= number_format($row['price'], 0) ?> บาท</p>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php
  $stmt = $pdo->prepare("SELECT * FROM products WHERE category = 'iPad' AND status = 1 ORDER BY created_at DESC");
  $stmt->execute();
  $ipadProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (count($ipadProducts) > 0): ?>
  <!-- 🔻 Section: iPad -->
  <section id="section-ipad" class="product-grid-section">
    <div class="product-grid-header">
      <h2>iPad</h2>
      <button class="refresh-btn" onclick="toggleSection(this, 'ipad-products')">ดูทั้งหมด</button>
    </div>

    <div class="product-wrapper">
      <div id="ipad-products" class="product-row">
        <?php foreach ($ipadProducts as $index => $row): ?>
          <a href="product-detail.php?id=<?= $row['id'] ?>" class="product-card<?= $index > 3 ? ' hidden-product' : '' ?>">
            <img src="uploads/<?= htmlspecialchars($row['main_image']) ?>" alt="<?= htmlspecialchars($row['name']) ?>"loading="lazy">
            <div class="product-info">
              <p class="category"><?= htmlspecialchars($row['category']) ?></p>
              <h3><?= htmlspecialchars($row['name']) ?></h3>
              <p class="price"><?= number_format($row['price'], 0) ?> บาท</p>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php
  $stmt = $pdo->prepare("SELECT * FROM products WHERE category = 'Apple Watch' AND status = 1 ORDER BY created_at DESC");
  $stmt->execute();
  $watchProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (count($watchProducts) > 0): ?>
  <!-- 🔻 Section: Apple Watch -->
  <section id="section-watch" class="product-grid-section">
    <div class="product-grid-header">
      <h2>Apple Watch</h2>
      <button class="watch-toggle-btn" onclick="toggleSection(this, 'watch-products')">ดูทั้งหมด</button>
    </div>

    <div class="product-wrapper">
      <div id="watch-products" class="product-row">
        <?php foreach ($watchProducts as $index => $row): ?>
          <a href="product-detail.php?id=<?= $row['id'] ?>" class="product-card<?= $index > 3 ? ' hidden-product' : '' ?>">
            <img src="uploads/<?= htmlspecialchars($row['main_image']) ?>" alt="<?= htmlspecialchars($row['name']) ?>"loading="lazy">
            <div class="product-info">
              <p class="category"><?= htmlspecialchars($row['category']) ?></p>
              <h3><?= htmlspecialchars($row['name']) ?></h3>
              <p class="price"><?= number_format($row['price'], 0) ?> บาท</p>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php
  $stmt = $pdo->prepare("SELECT * FROM products WHERE category = 'AirPods' AND status = 1 ORDER BY created_at DESC");
  $stmt->execute();
  $airpodsProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (count($airpodsProducts) > 0): ?>
  <!-- 🔻 Section: AirPods -->
  <section id="section-airpods" class="product-grid-section">
    <div class="product-grid-header">
      <h2>AirPods</h2>
      <button class="refresh-btn" onclick="toggleSection(this, 'airpods-products')">ดูทั้งหมด</button>
    </div>

    <div class="product-wrapper">
      <div id="airpods-products" class="product-row">
        <?php foreach ($airpodsProducts as $index => $row): ?>
          <a href="product-detail.php?id=<?= $row['id'] ?>" class="product-card<?= $index > 3 ? ' hidden-product' : '' ?>">
            <img src="uploads/<?= htmlspecialchars($row['main_image']) ?>" alt="<?= htmlspecialchars($row['name']) ?>"loading="lazy">
            <div class="product-info">
              <p class="category"><?= htmlspecialchars($row['category']) ?></p>
              <h3><?= htmlspecialchars($row['name']) ?></h3>
              <p class="price"><?= number_format($row['price'], 0) ?> บาท</p>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php
  $stmt = $pdo->prepare("SELECT * FROM products WHERE category = 'Accessories' AND status = 1 ORDER BY created_at DESC");
  $stmt->execute();
  $accessoryProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (count($accessoryProducts) > 0): ?>
  <!-- 🔻 Section: Accessories -->
  <section id="section-accessories" class="product-grid-section">
    <div class="product-grid-header">
      <h2>Accessories</h2>
      <button class="refresh-btn" onclick="toggleSection(this, 'accessory-products')">ดูทั้งหมด</button>
    </div>

    <div class="product-wrapper">
      <div id="accessory-products" class="product-row">
        <?php foreach ($accessoryProducts as $index => $row): ?>
          <a href="product-detail.php?id=<?= $row['id'] ?>" class="product-card<?= $index > 3 ? ' hidden-product' : '' ?>">
            <img src="uploads/<?= htmlspecialchars($row['main_image']) ?>" alt="<?= htmlspecialchars($row['name']) ?>"loading="lazy">
            <div class="product-info">
              <p class="category"><?= htmlspecialchars($row['category']) ?></p>
              <h3><?= htmlspecialchars($row['name']) ?></h3>
              <p class="price"><?= number_format($row['price'], 0) ?> บาท</p>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

</div>

<?php include_once 'includes/footer.php'; ?>



<script>
  let intervalID;

  function loadRandomProducts() {
    const container = document.getElementById('random-products');

    container.classList.add('fade-out');

    setTimeout(() => {
      fetch('products.php?ajax=1')
        .then(response => response.text())
        .then(html => {
          container.innerHTML = html;
          container.classList.remove('fade-out');
          container.classList.add('fade-in');
          setTimeout(() => container.classList.remove('fade-in'), 300);
        });
    }, 300);
  }

  function startAutoRefresh() {
    intervalID = setInterval(loadRandomProducts, 5000);
  }

  function stopAutoRefresh() {
    clearInterval(intervalID);
  }

  // โหลดครั้งแรก
  loadRandomProducts();
  startAutoRefresh();

  // ปุ่ม "เปลี่ยนสินค้า"
  document.getElementById('refresh-btn').addEventListener('click', loadRandomProducts);

  // หยุดออโต้เมื่อ hover
  const productZone = document.getElementById('random-products');
  productZone.addEventListener('mouseenter', stopAutoRefresh);
  productZone.addEventListener('mouseleave', startAutoRefresh);

  document.querySelectorAll('.filter-btn').forEach(btn => {
  btn.addEventListener('click', function () {
    const category = this.dataset.category;

    // กำหนด class active
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    this.classList.add('active');

    fetch(`products.php?ajax=1&category=${encodeURIComponent(category)}`)
      .then(res => res.text())
      .then(html => {
        const container = document.getElementById('random-products');
        container.innerHTML = html;
      });
  });
});

function toggleSection(button, sectionId) {
  const section = document.getElementById(sectionId);
  const hiddenItems = section.querySelectorAll('.hidden-product');
  const isCollapsed = hiddenItems[0]?.style.display !== 'flex';

  hiddenItems.forEach(el => {
    el.style.display = isCollapsed ? 'flex' : 'none';
  });

  button.textContent = isCollapsed ? 'ย่อรายการ' : 'ดูทั้งหมด';
}

// ซ่อนสินค้า MacBook ที่เกิน 4 ชิ้นตอนโหลดหน้า
document.addEventListener('DOMContentLoaded', () => {
  const macbookSection = document.getElementById('macbook-products');
  if (macbookSection) {
    const hiddenItems = macbookSection.querySelectorAll('.product-card:nth-child(n+5)');
    hiddenItems.forEach(el => el.classList.add('hidden-product'));
  }
});



</script>


<script src="assets/js/preload-images.js"></script>

</body>
</html>
