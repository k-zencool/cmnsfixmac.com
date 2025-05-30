<?php
$page_title = "ลงโปรแกรมและระบบปฏิบัติการ macOS | cmnsfixmac";
$page_description = "บริการลง macOS, Microsoft Office, Adobe, AutoCAD, ล้างเครื่อง แก้ไวรัส โดยช่างผู้เชี่ยวชาญ รองรับ MacBook, iMac ทุกเวอร์ชัน";
$page_keywords = "ลงโปรแกรม Mac, ลง macOS, ลง Office Mac, ลง Adobe Mac, ล้างเครื่อง Mac, แก้เครื่องช้า";
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $page_title ?></title>
  <meta name="description" content="<?= $page_description ?>">
  <meta name="keywords" content="<?= $page_keywords ?>">
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="https://cmnsfixmac.com/software.php">

  <!-- Open Graph -->
  <meta property="og:title" content="<?= $page_title ?>">
  <meta property="og:description" content="<?= $page_description ?>">
  <meta property="og:type" content="website">
  <meta property="og:image" content="https://cmnsfixmac.com/assets/img/software-og.jpg">
  <meta property="og:url" content="https://cmnsfixmac.com/software.php">

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= $page_title ?>">
  <meta name="twitter:description" content="<?= $page_description ?>">
  <meta name="twitter:image" content="https://cmnsfixmac.com/assets/img/software-og.jpg">

  <link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/footer-style.css">
  <link rel="stylesheet" href="assets/css/software-style.css">
  <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
  <script src="assets/js/script.js" defer></script>
</head>

<body>
<?php include_once '../includes/header.php'; ?>

<main>
  <section class="hero-software">
    <div class="hero-overlay">
      <div class="hero-text" data-aos="fade-up">
        <h1>บริการลงโปรแกรม / ระบบปฏิบัติการ macOS</h1>
        <p>รองรับทั้ง MacBook, iMac ทุกเวอร์ชัน โดยช่างเฉพาะทาง</p>
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

  <section class="software-services container">
    <h2 data-aos="fade-up">บริการลงโปรแกรมและระบบปฏิบัติการ</h2>
    <p class="section-desc" data-aos="fade-up" data-aos-delay="100">
      บริการสำหรับผู้ใช้ MacBook และ iMac ที่ต้องการลงโปรแกรมทำงานเฉพาะทาง หรือลง macOS ใหม่ พร้อมตั้งค่าให้พร้อมใช้งาน โดยไม่ล้างข้อมูล หรือสามารถเลือกฟอร์แมตใหม่ได้
    </p>

    <div class="service-grid">
      <div class="service-box" data-aos="fade-up" data-aos-delay="200">
        <span class="material-symbols-rounded">computer</span>
        <h3>ลง macOS ใหม่</h3>
        <p>แก้ปัญหาเครื่องช้า ติดรหัส ล้างเครื่องให้สะอาด ตั้งค่าพร้อมใช้งาน</p>
      </div>
      <div class="service-box" data-aos="fade-up" data-aos-delay="300">
        <span class="material-symbols-rounded">apps</span>
        <h3>ลง Microsoft Office / Adobe</h3>
        <p>Office 2021, Adobe Photoshop, Illustrator, AutoCAD รองรับงานเฉพาะทาง</p>
      </div>
      <div class="service-box" data-aos="fade-up" data-aos-delay="400">
        <span class="material-symbols-rounded">cleaning_services</span>
        <h3>ล้างเครื่อง แก้ไวรัส</h3>
        <p>ลบไวรัส ป๊อปอัป Safari, แก้บูตช้า เปิดช้า หน่วง</p>
      </div>
    </div>
  </section>

  <section class="software-price-tabs container">
    <h2 data-aos="fade-up">ตารางค่าบริการลงโปรแกรม</h2>
    <div class="tab-buttons" data-aos="fade-up">
      <button class="tab-btn active" data-tab="os">ลง macOS ใหม่</button>
      <button class="tab-btn" data-tab="apps">ลงโปรแกรมทำงาน</button>
      <button class="tab-btn" data-tab="clean">ล้างเครื่อง / แก้ไวรัส</button>
    </div>

    <div class="tab-contents">
      <?php
      $categories = ['os' => 'ลง macOS ใหม่', 'apps' => 'ลงโปรแกรมทำงาน', 'clean' => 'ล้างเครื่อง / แก้ไวรัส'];
      require_once '../includes/db.php';
      foreach ($categories as $key => $title):
          $stmt = $pdo->prepare("SELECT * FROM software_fix_pricing WHERE category = ?");
          $stmt->execute([$key]);
          $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
      ?>
      <div class="tab-content <?= $key === 'os' ? 'active' : '' ?>" id="<?= $key ?>">
        <table class="fix-table">
          <thead><tr><th>รุ่น</th><th>รายละเอียด</th><th>ราคาโดยประมาณ</th></tr></thead>
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
    <h2 data-aos="fade-up">ผลงานลงโปรแกรมล่าสุด</h2>
    <div class="fix-result-grid">
      <?php
      $stmt = $pdo->prepare("SELECT * FROM repairs WHERE LOWER(category) = 'software' ORDER BY created_at DESC LIMIT 6");
      $stmt->execute();
      while ($row = $stmt->fetch()):
        $imagePath = (!empty($row['image']) && file_exists(__DIR__ . '/../uploads/' . $row['image']))
            ? '../uploads/' . htmlspecialchars($row['image'])
            : '../assets/img/placeholder.png';
      ?>
      <a href="/work-detail.php?id=<?= htmlspecialchars($row['id']) ?>" class="fix-result-box" data-aos="fade-up">
        <img src="<?= $imagePath ?>" alt="ลงโปรแกรม - <?= htmlspecialchars($row['title']) ?> รุ่น <?= htmlspecialchars($row['model']) ?>" loading="lazy">
        <div class="fix-result-info">
          <h3><?= htmlspecialchars($row['title']) ?></h3>
          <p><?= htmlspecialchars($row['model']) ?></p>
          <p class="views"><?= number_format($row['views']) ?> เข้าชม</p>
        </div>
      </a>
      <?php endwhile; ?>
    </div>
    <div class="view-all-link" data-aos="fade-up">
      <a href="/works.php?category=software" class="btn-orange">ดูผลงานทั้งหมด</a>
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
