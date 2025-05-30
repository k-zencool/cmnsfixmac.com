<?php
$page_title = "ซ่อม AirPods ทุกอาการ | ช่างผู้เชี่ยวชาญ | cmnsfixmac";
$page_description = "รับซ่อม AirPods ทุกอาการ เช่น แบตเสื่อม เคสชาร์จเสีย น้ำเข้า เสียงข้างเดียวไม่ดัง โดยช่างผู้เชี่ยวชาญ มีประกันทุกงานซ่อม";
$page_keywords = "ซ่อม AirPods, AirPods เสียงไม่ดัง, เปลี่ยนแบต AirPods, เคสชาร์จ AirPods พัง, ล้างน้ำเข้า AirPods";
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $page_title ?></title>
  <meta name="description" content="<?= $page_description ?>">
  <meta name="keywords" content="<?= $page_keywords ?>">
  <link rel="canonical" href="https://cmnsfixmac.com/airpods.php">
  <meta property="og:title" content="<?= $page_title ?>">
  <meta property="og:description" content="<?= $page_description ?>">
  <meta property="og:type" content="website">
  <meta property="og:image" content="https://cmnsfixmac.com/assets/img/airpods-repair-og.jpg">
  <meta property="og:url" content="https://cmnsfixmac.com/airpods.php">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= $page_title ?>">
  <meta name="twitter:description" content="<?= $page_description ?>">
  <meta name="twitter:image" content="https://cmnsfixmac.com/assets/img/airpods-repair-og.jpg">

  <link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/footer-style.css">
  <link rel="stylesheet" href="assets/css/airpods-style.css">
  <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
  <script src="assets/js/script.js" defer></script>
</head>
<body>
<?php include_once '../includes/header.php'; ?>

<main>
  <section class="hero-airpods">
    <div class="hero-overlay">
      <div class="hero-text" data-aos="fade-up">
        <h1>บริการซ่อม AirPods ทุกอาการ โดยช่างผู้เชี่ยวชาญ</h1>
        <p>เปลี่ยนแบต เคสพัง น้ำเข้า เสียงข้างเดียว มีอะไหล่พร้อมรับประกัน</p>
      </div>
    </div>
  </section>

  <div class="floating-menu" data-aos="fade-up">
    <ul>
      <li><a href="/services/macbook.php"><span class="material-symbols-rounded">laptop_mac</span><div>ซ่อม MacBook</div></a></li>
      <li><a href="/services/imac.php"><span class="material-symbols-rounded">desktop_mac</span><div>ซ่อม iMac</div></a></li>
      <li><a href="/services/iphone.php"><span class="material-symbols-rounded">smartphone</span><div>ซ่อม iPhone</div></a></li>
      <li><a href="/services/ipad.php"><span class="material-symbols-rounded">tablet_mac</span><div>ซ่อม iPad</div></a></li>
      <li><a href="/services/apple-watch.php"><span class="material-symbols-rounded">watch</span><div>ซ่อม Apple Watch</div></a></li>
      <li><a href="/services/airpods.php"><span class="material-symbols-rounded">headphones</span><div>ซ่อม AirPods</div></a></li>
      <li><a href="/services/software.php"><span class="material-symbols-rounded">terminal</span><div>ลงโปรแกรม / OS</div></a></li>
    </ul>
  </div>

  <section class="airpods-services container">
    <h2 data-aos="fade-up">บริการซ่อม AirPods ครบวงจร</h2>
    <div class="service-grid">
      <div class="service-box" data-aos="fade-up" data-aos-delay="200">
        <span class="material-symbols-rounded">battery_alert</span>
        <h3>เปลี่ยนแบตเตอรี่</h3>
        <p>แบตหมดไว แบตบวม ชาร์จไม่เข้า เปลี่ยนเฉพาะข้างที่เสียได้</p>
      </div>
      <div class="service-box" data-aos="fade-up" data-aos-delay="300">
        <span class="material-symbols-rounded">settings</span>
        <h3>ซ่อมเคสชาร์จ</h3>
        <p>เคสไม่ชาร์จ พอร์ตหลวม ฝาไม่ปิด ซ่อมหรือเปลี่ยนใหม่</p>
      </div>
      <div class="service-box" data-aos="fade-up" data-aos-delay="400">
        <span class="material-symbols-rounded">water_damage</span>
        <h3>ล้างน้ำเข้า</h3>
        <p>เสียงเบา เสียงขาด น้ำเข้าลำโพงหรือบอร์ด เราล้างและซ่อมให้ได้</p>
      </div>
    </div>
  </section>

  <section class="airpods-price-tabs container">
    <h2 data-aos="fade-up">ตารางราคาซ่อม AirPods</h2>
    <div class="tab-buttons" data-aos="fade-up">
      <button class="tab-btn active" data-tab="battery">แบตเตอรี่</button>
      <button class="tab-btn" data-tab="case">เคสชาร์จ</button>
      <button class="tab-btn" data-tab="water">น้ำเข้า</button>
    </div>
    <div class="tab-contents">
      <?php
      $categories = ['battery' => 'แบตเตอรี่', 'case' => 'เคสชาร์จ', 'water' => 'น้ำเข้า'];
      require_once '../includes/db.php';
      foreach ($categories as $key => $title):
        $stmt = $pdo->prepare("SELECT * FROM airpods_fix_pricing WHERE category = ?");
        $stmt->execute([$key]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
      ?>
      <div class="tab-content <?= $key === 'battery' ? 'active' : '' ?>" id="<?= $key ?>">
        <table class="fix-table">
          <thead><tr><th>รุ่น</th><th>รายละเอียด</th><th>ราคา</th></tr></thead>
          <tbody>
            <?php foreach ($results as $row): ?>
              <tr>
                <td><?= htmlspecialchars($row['model']) ?></td>
                <td><?= htmlspecialchars($row['detail']) ?></td>
                <td><?= htmlspecialchars($row['price']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="fix-result container">
    <h2 data-aos="fade-up">ตัวอย่างผลงานซ่อม AirPods</h2>
    <div class="fix-result-grid">
      <?php
      $stmt = $pdo->prepare("SELECT * FROM repairs WHERE LOWER(category) = 'airpods' ORDER BY created_at DESC LIMIT 6");
      $stmt->execute();
      while ($row = $stmt->fetch()):
        $imagePath = (!empty($row['image']) && file_exists(__DIR__ . '/../uploads/' . $row['image']))
            ? '../uploads/' . htmlspecialchars($row['image'])
            : '../assets/img/placeholder.png';
      ?>
      <a href="/work-detail.php?id=<?= htmlspecialchars($row['id']) ?>" class="fix-result-box" data-aos="fade-up">
        <img src="<?= $imagePath ?>" alt="ซ่อม AirPods - <?= htmlspecialchars($row['title']) ?>" loading="lazy">
        <div class="fix-result-info">
          <h3><?= htmlspecialchars($row['title']) ?></h3>
          <p><?= htmlspecialchars($row['model']) ?></p>
          <p class="views"><?= number_format($row['views']) ?> เข้าชม</p>
        </div>
      </a>
      <?php endwhile; ?>
    </div>

    <!-- ✅ เพิ่มปุ่มดูผลงานทั้งหมด -->
    <div class="view-all-link" data-aos="fade-up">
      <a href="/works.php?category=AirPods" class="btn-orange">ดูผลงานทั้งหมด</a>
    </div>
  </section>
</main>

<footer></footer>

<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
<script>
  AOS.init({ duration: 800, once: true });
  document.querySelectorAll(".tab-btn").forEach(btn => {
    btn.addEventListener("click", () => {
      document.querySelectorAll(".tab-btn").forEach(b => b.classList.remove("active"));
      document.querySelectorAll(".tab-content").forEach(c => c.classList.remove("active"));
      btn.classList.add("active");
      document.getElementById(btn.dataset.tab).classList.add("active");
    });
  });
</script>
<?php include_once '../includes/footer.php'; ?>
</body>
</html>
