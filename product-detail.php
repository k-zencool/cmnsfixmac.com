<?php
require 'includes/db.php';

if (!isset($_GET['id'])) {
  die("ไม่พบรหัสสินค้า");
}

$id = intval($_GET['id']);
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
  die("ไม่พบสินค้านี้");
}

$stmtImages = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ?");
$stmtImages->execute([$id]);
$images = $stmtImages->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($product['name']) ?> | รายละเอียดสินค้า</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
  <link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png" />
  <link rel="stylesheet" href="/assets/css/footer-style.css">

  <style>
    body {
      margin: 0;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: #f9f9f9;
      color: #111;
      padding-top: 10px;
    }
    .product-detail {
      max-width: 1100px;
      margin: 60px auto;
      display: flex;
      flex-wrap: wrap;
      gap: 40px;
      padding: 0 20px;
      padding-top: 60px;
    }
    .product-gallery {
      flex: 1 1 400px;
      display: flex;
      flex-direction: column;
      gap: 20px;
      align-items: center;
    }
    .product-gallery .main-image img {
      width: 100%;
      border-radius: 20px;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
    }
    .thumbnail-list {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      justify-content: center;
    }
    .thumbnail-list .thumb {
      width: 80px;
      height: 80px;
      border-radius: 12px;
      object-fit: cover;
      cursor: pointer;
      border: 2px solid transparent;
      transition: 0.2s ease;
    }
    .thumbnail-list .thumb:hover {
      border-color: #007aff;
      transform: scale(1.05);
    }
    .product-info {
      flex: 1 1 400px;
      display: flex;
      flex-direction: column;
    }
    .product-info h1 {
      font-size: 2rem;
      margin-bottom: 10px;
    }
    .product-info .category {
      font-size: 0.95rem;
      color: #777;
      margin-bottom: 10px;
    }
    .product-info .price {
      font-size: 1.5rem;
      color: #0a0;
      font-weight: bold;
      margin-bottom: 20px;
    }
    .order-btn {
      background: #22aa55;
      color: white;
      padding: 10px 16px;
      font-size: 1rem;
      font-weight: 600;
      border-radius: 8px;
      text-decoration: none;
      transition: background 0.3s ease;
      display: inline-block;
      text-align: center;
      margin: 10px 0;
      width: 100%;
    }
    .order-btn:hover {
      background: #1b8c44;
    }
    @media (max-width: 768px) {
      .product-detail {
        flex-direction: column;
        align-items: center;
      }
      .product-info {
        text-align: center;
      }
    }
    .payment-icons {
      margin-top: -48px;
      text-align: center;
    }
    .payment-img {
      max-width: 200px;
      height: auto;
      display: inline-block;
      vertical-align: top;
    }
    .breadcrumb-bar {
      font-size: 0.9rem;
      color: #444;
      margin-bottom: 10px;
      width: 100%;
      text-align: left;
      padding: 0 10px;
      box-sizing: border-box;
    }
    .breadcrumb-bar a {
      color: #111;
      font-weight: bold;
      text-decoration: none;
    }
    .breadcrumb-bar a:hover {
      text-decoration: underline;
    }
    .breadcrumb-separator {
      margin: 0 6px;
      color: #999;
    }
    .breadcrumb-current {
      color: #555;
    }
    .toggle-wrapper {
      position: relative;
      margin-bottom: 30px;
    }
    .desc-content {
      font-size: 1rem;
      line-height: 1.8;
      color: #333;
      max-height: 260px;
      overflow: hidden;
      transition: max-height 0.3s ease;
    }
    .desc-content.expanded {
      max-height: 1500px;
    }
    #toggleBtn {
      display: block;
      margin-top: 10px;
      font-size: 0.9rem;
      color:rgb(0, 0, 0) !important;
      text-align: center;
      cursor: pointer;
      background: none !important;
      border: none !important;
      padding: 0 !important;
      font-weight: normal;
      box-shadow: none !important;
    }
    #toggleBtn:hover {
      text-decoration: underline;
      color:rgba(0, 0, 0, 0.4) !important;
    }
  </style>
</head>
<body>

<?php include 'includes/header.php'; ?>

<div class="product-detail">
  <div class="product-gallery">
    <div class="breadcrumb-bar">
      <a href="products.php" class="breadcrumb-home">Home</a>
      <span class="breadcrumb-separator">›</span>
      <span class="breadcrumb-current"><?= htmlspecialchars($product['name']) ?></span>
    </div>
    <div class="main-image">
      <img id="mainPreview" src="uploads/<?= htmlspecialchars($product['main_image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
    </div>
    <div class="thumbnail-list">
      <img class="thumb" src="uploads/<?= htmlspecialchars($product['main_image']) ?>" onclick="previewImage(this.src)">
      <?php foreach ($images as $img): ?>
        <img class="thumb" src="uploads/<?= htmlspecialchars($img['image']) ?>" onclick="previewImage(this.src)">
      <?php endforeach; ?>
    </div>
    <a href="https://page.line.me/cmns" class="order-btn" target="_blank">สั่งซื้อผ่าน LINE</a>
    <div class="payment-icons">
      <img src="assets/img/payment-methods.png" alt="Payment Methods" class="payment-img">
    </div>
  </div>
  <div class="product-info">
    <h1><?= htmlspecialchars($product['name']) ?></h1>
    <div class="category">หมวดหมู่: <?= htmlspecialchars($product['category']) ?></div>
    <div class="price"><?= number_format($product['price'], 2) ?> บาท</div>
    <div class="desc toggle-wrapper">
      <div class="desc-content" id="descContent">
        <?= htmlspecialchars_decode($product['productdetails'] ?? '<em>ไม่มีรายละเอียดเพิ่มเติม</em>') ?>
      </div>
      <span id="toggleBtn" onclick="toggleDesc()">ดูเพิ่มเติม</span>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
  function previewImage(src) {
    document.getElementById('mainPreview').src = src;
  }
  function toggleDesc() {
    const content = document.getElementById('descContent');
    const btn = document.getElementById('toggleBtn');
    content.classList.toggle('expanded');
    btn.textContent = content.classList.contains('expanded') ? 'ย่อข้อความ' : 'ดูเพิ่มเติม';
  }
  window.addEventListener('DOMContentLoaded', () => {
    const content = document.getElementById('descContent');
    const btn = document.getElementById('toggleBtn');
    if (!content.textContent.trim()) {
      btn.style.display = 'none';
    } else {
      btn.style.display = 'block';
    }
  });
</script>

</body>
</html>
