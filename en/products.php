<?php
// Assuming this file is /en/products.php
require '../includes/db.php'; // Path changed

// Define which language columns to use on this page
$name_col = 'name_en';
$details_col = 'productdetails_en';

// --- English AJAX Handler ---
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
  $category_ajax = filter_input(INPUT_GET, 'category', FILTER_SANITIZE_SPECIAL_CHARS);
  $query_ajax = "SELECT id, {$name_col} AS name, category, price, main_image FROM products WHERE status = 1 AND ({$name_col} IS NOT NULL AND {$name_col} != '')";
  $params_ajax = [];

  if (!empty($category_ajax) && $category_ajax !== 'all') {
    $query_ajax .= " AND LOWER(category) = LOWER(?)";
    $params_ajax[] = $category_ajax;
  }
  $query_ajax .= " ORDER BY RAND() LIMIT 4";

  $stmt_ajax = $pdo->prepare($query_ajax);
  $stmt_ajax->execute($params_ajax);

  while ($row = $stmt_ajax->fetch(PDO::FETCH_ASSOC)) :
    $product_name_en = htmlspecialchars($row['name'] ?? 'Product');
    $alt_text_en = "Image of " . $product_name_en;
?>
    <a href="product-detail.php?id=<?= $row['id'] ?>" class="product-card">
      <img src="../uploads/<?= htmlspecialchars($row['main_image'] ?? 'placeholder.png') ?>" alt="<?= $alt_text_en ?>" loading="lazy">
      <div class="product-info">
        <p class="category"><?= htmlspecialchars($row['category'] ?? '') ?></p>
        <h3><?= $product_name_en ?></h3>
        <p class="price">THB <?= number_format($row['price'] ?? 0, 0) ?></p>
      </div>
    </a>
<?php
  endwhile;
  exit;
}
// --- End AJAX Handler ---

// Fetch latest products for Structured Data (English context)
$stmt_sd = $pdo->prepare("SELECT id, name_en, category, price, main_image, productdetails_en FROM products WHERE status = 1 AND (name_en IS NOT NULL AND name_en != '') ORDER BY created_at DESC LIMIT 4");
$stmt_sd->execute();
$products_for_sd = $stmt_sd->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Used MacBooks, iPhones, iPads for Sale in Chiang Mai | CMNS FixMac</title>

  <link rel="alternate" hreflang="th" href="https://cmnsfixmac.com/products.php" />
  <link rel="alternate" hreflang="en" href="https://cmnsfixmac.com/en/products.php" />
  <link rel="alternate" hreflang="x-default" href="https://cmnsfixmac.com/en/products.php" />
  
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Shop for quality used MacBooks, iPhones, and iPads in Chiang Mai. CMNS FixMac offers genuine pre-owned Apple products at great prices with warranty and after-sales service.">
  <meta name="keywords" content="Used MacBook Chiang Mai, Used iPhone Chiang Mai, Used iPad Chiang Mai, Secondhand Apple Chiang Mai, CMNS FixMac Shop">
  <meta name="author" content="CMNS FixMac">

  <meta property="og:type" content="website">
  <meta property="og:title" content="Used MacBooks, iPhones, iPads for Sale in Chiang Mai | CMNS FixMac">
  <meta property="og:description" content="Find great deals on quality used Apple products in Chiang Mai at CMNS FixMac.">
  <meta property="og:image" content="https://cmnsfixmac.com/assets/img/og-banner.jpg">
  <meta property="og:url" content="https://cmnsfixmac.com/en/products.php">
  <meta property="og:site_name" content="CMNS FixMac">
  <meta property="og:locale" content="en_US">


  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Used MacBooks, iPhones, iPads for Sale in Chiang Mai | CMNS FixMac">
  <meta name="twitter:description" content="Find great deals on quality used Apple products in Chiang Mai at CMNS FixMac.">
  <meta name="twitter:image" content="https://cmnsfixmac.com/assets/img/og-banner.jpg">

  <link rel="canonical" href="https://cmnsfixmac.com/en/products.php" />
  <link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png" />
  <link rel="stylesheet" href="../assets/css/products-style.css">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/footer-style.css">
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-3WXK9GWN7C"></script>

  

  <script>
    window.dataLayer = window.dataLayer || [];

    function gtag() {
      dataLayer.push(arguments);
    }
    gtag('js', new Date());
    gtag('config', 'G-3WXK9GWN7C');
  </script>

  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Store",
      "name": "CMNS FixMac",
      "image": "https://cmnsfixmac.com/assets/img/og-banner.jpg",
      "@id": "https://cmnsfixmac.com/en/",
      "url": "https://cmnsfixmac.com/en/products.php",
      "telephone": "+66 84 151 1684",
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "482 Moo 8, Chiang Mai-Hang Dong Rd, Mae Hia, Mueang",
        "addressLocality": "Chiang Mai",
        "postalCode": "50100",
        "addressCountry": "TH"
      },
      "description": "Sales of used MacBooks, iPhones, and iPads in Chiang Mai. Affordable prices, good condition, with warranty."
    }
  </script>

  <script type="application/ld+json">
    <?= json_encode([
      "@context" => "https://schema.org",
      "@graph" => array_map(function ($product) {
        return [
          "@type" => "Product",
          "name" => htmlspecialchars($product['name_en'] ?? 'Used Apple Product'), // Use English name
          "image" => "https://cmnsfixmac.com/uploads/" . htmlspecialchars($product['main_image'] ?? 'placeholder.png'),
          "description" => htmlspecialchars($product['productdetails_en'] ?? ('Used ' . ($product['category'] ?? 'Apple Product') . ' in good condition, for sale by CMNS FixMac.')), // Use English details
          "sku" => "SKU-" . ($product['id'] ?? '0'),
          "offers" => [
            "@type" => "Offer",
            "priceCurrency" => "THB",
            "price" => number_format($product['price'] ?? 0, 0, '.', ''),
            "availability" => "https://schema.org/InStock",
            "itemCondition" => "https://schema.org/UsedCondition",
            "url" => "https://cmnsfixmac.com/en/product-detail.php?id=" . ($product['id'] ?? '0') // Link to English product detail
          ]
        ];
      }, $products_for_sd)
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
  </script>
</head>

<body>

  <?php include_once '../includes/header_en.php'; ?>
  <div class="container">

    <section class="apple-product-categories">
      <h2>All <strong>Apple</strong> Products</h2>
      <div class="category-list">
        <a href="#section-macbook" class="category-item"><img src="../assets/img/macbook.png" loading="lazy" alt="Used MacBooks">
          <p class="title">MacBook</p>
        </a>
        <a href="#section-imac" class="category-item"><img src="../assets/img/mac.png" loading="lazy" alt="Used iMacs">
          <p class="title">iMac</p>
        </a>
        <a href="#section-iphone" class="category-item"><img src="../assets/img/iphone.png" loading="lazy" alt="Used iPhones">
          <p class="title">iPhone</p>
        </a>
        <a href="#section-ipad" class="category-item"><img src="../assets/img/ipad.png" loading="lazy" alt="Used iPads">
          <p class="title">iPad</p>
        </a>
        <a href="#section-watch" class="category-item"><img src="../assets/img/watch.png" loading="lazy" alt="Used Apple Watches">
          <p class="title">Apple Watch</p>
        </a>
        <a href="#section-airpods" class="category-item"><img src="../assets/img/airpods.png" loading="lazy" alt="Used AirPods">
          <p class="title">AirPods</p>
        </a>
        <a href="#section-accessories" class="category-item"><img src="../assets/img/accessories.png" loading="lazy" alt="Used Accessories">
          <p class="title">Accessories</p>
        </a>
      </div>
    </section>
    <div class="section-divider"></div>

    <div class="category-filter">
      <button class="filter-btn active" data-category="all">All</button> <button class="filter-btn" data-category="MacBook">MacBook</button>
      <button class="filter-btn" data-category="iPhone">iPhone</button>
      <button class="filter-btn" data-category="iPad">iPad</button>
    </div>

    <section class="product-grid-section">
      <div class="product-grid-header">
        <h2>Recommended Products</h2> <button id="refresh-btn" class="refresh-btn">Refresh Items</button>
      </div>
      <div class="product-wrapper">
        <div id="random-products" class="product-row">
        </div>
      </div>
    </section>

    <?php
    // Define categories to loop through
    $categories_en = [
      "MacBook" => "MacBook",
      "iMac" => "iMac",
      "iPhone" => "iPhone",
      "iPad" => "iPad",
      "Apple Watch" => "Apple Watch",
      "AirPods" => "AirPods",
      "Accessories" => "Accessories"
    ];

    // Function to display product cards to avoid repetition
    function display_product_card_en($product)
    {
      $product_name_display = htmlspecialchars($product['name'] ?? 'Apple Product');
      $alt_text_display = "Image of " . $product_name_display;
    ?>
      <a href="product-detail.php?id=<?= $product['id'] ?>" class="product-card">
        <img src="../uploads/<?= htmlspecialchars($product['main_image'] ?? 'placeholder.png') ?>" alt="<?= $alt_text_display ?>" loading="lazy">
        <div class="product-info">
          <p class="category"><?= htmlspecialchars($product['category'] ?? '') ?></p>
          <h3><?= $product_name_display ?></h3>
          <p class="price">THB <?= number_format($product['price'] ?? 0, 0) ?></p>
        </div>
      </a>
      <?php
    }

    foreach ($categories_en as $db_category_name => $display_name_en) :
      $stmt_cat = $pdo->prepare("SELECT id, {$name_col} AS name, category, price, main_image FROM products WHERE category = ? AND status = 1 AND ({$name_col} IS NOT NULL AND {$name_col} != '') ORDER BY created_at DESC");
      $stmt_cat->execute([$db_category_name]);
      $category_products = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

      if (count($category_products) > 0):
        $section_id_slug = strtolower(str_replace(' ', '-', $db_category_name));
      ?>
        <section id="section-<?= $section_id_slug ?>" class="product-grid-section">
          <div class="product-grid-header">
            <h2><?= $display_name_en ?></h2>
            <button class="refresh-btn" onclick="toggleSection(this, '<?= $section_id_slug ?>-products')">View All</button>
          </div>
          <div class="product-wrapper">
            <div id="<?= $section_id_slug ?>-products" class="product-row">
              <?php foreach ($category_products as $index => $product_item): ?>
                <div class="product-card-container<?= $index >= 4 ? ' hidden-product' : '' ?>">
                  <?php display_product_card_en($product_item); ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </section>
    <?php
      endif;
    endforeach;
    ?>
  </div> <?php include_once '../includes/footer_en.php'; ?>

  <script>
    let intervalID;

    function loadRandomProducts() {
      const container = document.getElementById('random-products');
      if (!container) return;
      container.classList.add('fade-out');
      setTimeout(() => {
        fetch('products.php?ajax=1') // This will call en/products.php?ajax=1
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
      if (intervalID) clearInterval(intervalID);
      intervalID = setInterval(loadRandomProducts, 5000);
    }

    function stopAutoRefresh() {
      clearInterval(intervalID);
    }

    const randomProductZone = document.getElementById('random-products');
    if (randomProductZone) {
      loadRandomProducts();
      startAutoRefresh();
      document.getElementById('refresh-btn').addEventListener('click', loadRandomProducts);
      randomProductZone.addEventListener('mouseenter', stopAutoRefresh);
      randomProductZone.addEventListener('mouseleave', startAutoRefresh);
    }

    document.querySelectorAll('.filter-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const category = this.dataset.category;
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        stopAutoRefresh();
        // Fetch for the recommended section based on filter
        fetch(`products.php?ajax=1&category=${encodeURIComponent(category)}`)
          .then(res => res.text())
          .then(html => {
            const container = document.getElementById('random-products');
            if (container) container.innerHTML = html;
          });
      });
    });

    function toggleSection(button, sectionId) {
      const section = document.getElementById(sectionId);
      if (!section) return;
      const hiddenItems = section.querySelectorAll('.hidden-product');
      const isNowVisible = button.textContent === 'Collapse';

      hiddenItems.forEach(el => {
        el.style.display = isNowVisible ? 'none' : 'flex'; // Use 'flex' or 'block' depending on your product card style
      });

      button.textContent = isNowVisible ? 'View All' : 'Collapse'; // Translated
    }

    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('.product-row').forEach(productRow => {
        const productCards = productRow.querySelectorAll('.product-card-container');
        if (productCards.length > 0) {
          productCards.forEach((card, index) => {
            if (index >= 4) {
              card.style.display = 'none';
              card.classList.add('hidden-product');
            }
          });
        }
      });
    });
  </script>

  <script src="../assets/js/preload-images.js"></script>
</body>

</html>