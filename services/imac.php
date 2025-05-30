<?php
$page_title = "ซ่อม iMac ทุกอาการ | ช่างผู้เชี่ยวชาญ | cmnsfixmac";
$page_description = "รับซ่อม iMac ทุกรุ่น เช่น จอเสีย เปิดไม่ติด ช้าผิดปกติ ลงโปรแกรมใหม่ โดยช่างผู้เชี่ยวชาญ พร้อมรับประกันงานซ่อม";
$page_keywords = "ซ่อม iMac, iMac เปิดไม่ติด, เปลี่ยนจอ iMac, อัพเกรด iMac, ซ่อมบอร์ด iMac";
?>

<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $page_title ?></title>
  <meta name="description" content="<?= $page_description ?>">
  <meta name="keywords" content="<?= $page_keywords ?>">
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="https://cmnsfixmac.com/imac.php">

  <!-- Open Graph -->
  <meta property="og:title" content="<?= $page_title ?>">
  <meta property="og:description" content="<?= $page_description ?>">
  <meta property="og:type" content="website">
  <meta property="og:image" content="https://cmnsfixmac.com/assets/img/imac-repair-og.jpg">
  <meta property="og:url" content="https://cmnsfixmac.com/imac.php">

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= $page_title ?>">
  <meta name="twitter:description" content="<?= $page_description ?>">
  <meta name="twitter:image" content="https://cmnsfixmac.com/assets/img/imac-repair-og.jpg">

  <link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/footer-style.css">
  <link rel="stylesheet" href="assets/css/imac-style.css">
  <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
  <script src="assets/js/script.js" defer></script>

  <!-- Schema.org -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "LocalBusiness",
    "name": "cmnsfixmac",
    "image": "https://cmnsfixmac.com/assets/img/imac-repair-og.jpg",
    "description": "รับซ่อม iMac ทุกรุ่น เช่นจอเสีย เปิดไม่ติด เครื่องค้าง ช้า อัปเกรด โดยช่างผู้เชี่ยวชาญ",
    "url": "https://cmnsfixmac.com/imac.php",
    "address": {
      "@type": "PostalAddress",
      "addressLocality": "เชียงใหม่",
      "addressCountry": "TH"
    },
    "openingHours": "Mo-Sa 09:00-19:00",
    "telephone": "084-151-1684"
  }
  </script>
</head>

<body>
<?php include_once '../includes/header.php'; ?>

<main>
  <section class="hero-imac">
    <div class="hero-overlay">
      <div class="hero-text" data-aos="fade-up">
        <h1>บริการซ่อม iMac ครบทุกอาการ โดยช่างผู้เชี่ยวชาญ</h1>
        <p>iMac ทุกรุ่น อะไหล่คุณภาพ มีรับประกันทุกรายการ</p>
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

  <section class="imac-services container">
    <h2 data-aos="fade-up">บริการซ่อม iMac ครบวงจร</h2>
    <p class="section-desc" data-aos="fade-up" data-aos-delay="100">
      รับซ่อม iMac ทุกรุ่น เช่น iMac 21.5", iMac 27", iMac M1, M3 อาการจอเสีย เปิดไม่ติด ช้า ค้าง ลง macOS ใหม่ หรืออัปเกรด SSD โดยทีมช่างมืออาชีพ
    </p>

    <div class="service-grid">
      <div class="service-box" data-aos="fade-up" data-aos-delay="200">
        <span class="material-symbols-rounded">display_settings</span>
        <h3>เปลี่ยนหน้าจอ iMac</h3>
        <p>หน้าจอดำ จอลาย แตก ภาพไม่ขึ้น เราเปลี่ยนจอพร้อม QC ครบ</p>
      </div>
      <div class="service-box" data-aos="fade-up" data-aos-delay="300">
        <span class="material-symbols-rounded">memory</span>
        <h3>อัปเกรด SSD / RAM</h3>
        <p>เครื่องช้า เปิดแอปนาน เพิ่ม SSD หรือ RAM เพื่อความเร็วทันใจ</p>
      </div>
      <div class="service-box" data-aos="fade-up" data-aos-delay="400">
        <span class="material-symbols-rounded">power</span>
        <h3>ซ่อมเปิดไม่ติด</h3>
        <p>iMac ไม่เปิด มีเสียงแต่ไม่มีภาพ เราเช็คบอร์ดและ PSU อย่างละเอียด</p>
      </div>
      <div class="service-box" data-aos="fade-up" data-aos-delay="500">
        <span class="material-symbols-rounded">terminal</span>
        <h3>ลง macOS และโปรแกรม</h3>
        <p>ลง macOS ใหม่, Office, Adobe, Final Cut, Logic Pro และอื่น ๆ</p>
      </div>
    </div>
  </section>

  <section class="why-row container">
    <h2 data-aos="fade-up">ทำไมต้องซ่อมกับ cmnsfixmac?</h2>
    <div class="why-row-items" data-aos="fade-up" data-aos-delay="100">
      <div class="why-row-item"><span class="material-symbols-rounded">verified</span><p>รับประกันทุกงานซ่อม</p></div>
      <div class="why-row-item"><span class="material-symbols-rounded">search</span><p>ตรวจเช็คฟรี ไม่มีค่าใช้จ่าย</p></div>
      <div class="why-row-item"><span class="material-symbols-rounded">bolt</span><p>อะไหล่แท้ ซ่อมไว</p></div>
      <div class="why-row-item"><span class="material-symbols-rounded">engineering</span><p>ช่างเฉพาะทาง</p></div>
    </div>
  </section>

  <section class="imac-price-tabs container">
    <h2 data-aos="fade-up">ตารางราคาซ่อม iMac</h2>
    <div class="tab-buttons" data-aos="fade-up">
      <button class="tab-btn active" data-tab="display">เปลี่ยนจอ</button>
      <button class="tab-btn" data-tab="upgrade">อัปเกรด SSD / RAM</button>
      <button class="tab-btn" data-tab="board">ซ่อมบอร์ด / เปิดไม่ติด</button>
    </div>
    <div class="tab-contents">
      <?php
      $categories = ['display' => 'เปลี่ยนจอ', 'upgrade' => 'อัปเกรด SSD / RAM', 'board' => 'ซ่อมบอร์ด'];
      require_once '../includes/db.php';
      foreach ($categories as $key => $title):
          $stmt = $pdo->prepare("SELECT * FROM imac_fix_pricing WHERE category = ?");
          $stmt->execute([$key]);
          $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
      ?>
        <div class="tab-content <?= $key === 'display' ? 'active' : '' ?>" id="<?= $key ?>">
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
    <h2 data-aos="fade-up">ตัวอย่างผลงานซ่อม iMac</h2>
    <div class="fix-result-grid">
      <?php
      $stmt = $pdo->prepare("SELECT * FROM repairs WHERE LOWER(category) = 'imac' ORDER BY created_at DESC LIMIT 6");
      $stmt->execute();
      while ($row = $stmt->fetch()):
          $imagePath = (!empty($row['image']) && file_exists(__DIR__ . '/../uploads/' . $row['image']))
              ? '../uploads/' . htmlspecialchars($row['image'])
              : '../assets/img/placeholder.png';
      ?>
        <a href="/work-detail.php?id=<?= htmlspecialchars($row['id']) ?>" class="fix-result-box" data-aos="fade-up">
          <img src="<?= $imagePath ?>" alt="ซ่อม iMac - <?= htmlspecialchars($row['title']) ?> รุ่น <?= htmlspecialchars($row['model']) ?>" loading="lazy">
          <div class="fix-result-info">
            <h3><?= htmlspecialchars($row['title']) ?></h3>
            <p><?= htmlspecialchars($row['model']) ?></p>
            <p class="views"><?= number_format($row['views']) ?> เข้าชม</p>
          </div>
        </a>
      <?php endwhile; ?>
    </div>
    <div class="view-all-link" data-aos="fade-up">
      <a href="/works.php?category=iMac" class="btn-orange">ดูผลงานทั้งหมด</a>
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
