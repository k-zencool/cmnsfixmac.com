<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// ดึงสินค้าแนะนำมาโชว์หน้าแรก
$stmt = $pdo->query("SELECT id, name, price, main_image, category 
                     FROM products 
                     WHERE status=1 
                     ORDER BY created_at DESC LIMIT 8");
$featured = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>CMNS FixMac – ขาย MacBook iPhone iPad มือสอง เชียงใหม่</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link rel="stylesheet" href="assets/css/products-style.css">
  <link rel="stylesheet" href="assets/css/footer-style.css">
  <link rel="shortcut icon" href="assets/img/favicon1.png">
</head>
<body>
  <?php include 'includes/header.php'; ?>

  <!-- HERO -->
  <section class="hero">
    <div class="container">
      <div>
        <h1>ซื้อ–ขาย MacBook, iPhone, iPad มือสอง</h1>
        <p>ราคาย่อมเยา มีประกันหลังการขาย บริการโดยทีมงานมืออาชีพ</p>
        <a href="products.php" class="btn btn-primary">ดูสินค้าทั้งหมด</a>
      </div>
      <div>
        <img src="assets/img/og-banner.jpg" alt="CMNS FixMac" />
      </div>
    </div>
  </section>

  <!-- FEATURED PRODUCTS -->
  <section class="container">
    <h2>สินค้าแนะนำ</h2>
    <div class="grid">
      <?php foreach($featured as $p): ?>
        <article class="product-card">
          <a href="product-detail.php?id=<?= (int)$p['id'] ?>" class="thumb">
            <img src="uploads/<?= htmlspecialchars($p['main_image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
          </a>
          <div class="product-info">
            <p class="category"><?= htmlspecialchars($p['category']) ?></p>
            <h3 class="name"><?= htmlspecialchars($p['name']) ?></h3>
            <p class="price"><?= number_format($p['price'],0) ?> บาท</p>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <div style="margin-top:16px">
      <a href="products.php" class="btn">ดูสินค้าทั้งหมด</a>
    </div>
  </section>

  <?php include 'includes/footer.php'; ?>
</body>
</html>
