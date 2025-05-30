<?php
$page_title = "ซ่อม MacBook ทุกอาการ | ช่างผู้เชี่ยวชาญ | cmnsfixmac";
$page_description = "รับซ่อม MacBook ทุกรุ่น เช่น จอแตก แบตเสื่อม น้ำเข้า เครื่องเปิดไม่ติด โดยช่างผู้เชี่ยวชาญ มีประกันทุกงานซ่อม";
$page_keywords = "ซ่อม MacBook, MacBook เปิดไม่ติด, เปลี่ยนจอ MacBook, เปลี่ยนแบต MacBook, MacBook ชาร์จไม่เข้า";

?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- SEO -->
    <title><?= $page_title ?></title>
    <meta name="description" content="<?= $page_description ?>">
    <meta name="keywords" content="<?= $page_keywords ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://cmnsfixmac.com/macbook.php">

    <!-- Open Graph (Facebook, LINE) -->
    <meta property="og:title" content="<?= $page_title ?>">
    <meta property="og:description" content="<?= $page_description ?>">
    <meta property="og:type" content="website">
    <meta property="og:image" content="https://cmnsfixmac.com/assets/img/macbook-repair-og.jpg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:locale" content="th_TH">
    <meta property="og:url" content="https://cmnsfixmac.com/macbook.php">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= $page_title ?>">
    <meta name="twitter:description" content="<?= $page_description ?>">
    <meta name="twitter:image" content="https://cmnsfixmac.com/assets/img/macbook-repair-og.jpg">

    <!-- Favicon & Font -->
    <link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet">

    <!-- Stylesheets -->
    <link rel="stylesheet" href="../assets/css/footer-style.css">
    <link rel="stylesheet" href="assets/css/macbook-style.css">
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">

    <!-- JavaScript -->
    <script src="assets/js/script.js" defer></script>

    <!-- Schema.org JSON-LD -->
    <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "LocalBusiness",
    "name": "cmnsfixmac",
    "image": "https://cmnsfixmac.com/assets/img/macbook-repair-og.jpg",
    "description": "รับซ่อม MacBook ทุกรุ่น อาการจอแตก เปิดไม่ติด แบตเสื่อม น้ำเข้า โดยช่างผู้เชี่ยวชาญ",
    "url": "https://cmnsfixmac.com/macbook.php",
    "address": {
      "@type": "PostalAddress",
      "addressLocality": "เชียงใหม่",
      "addressCountry": "TH"
    },
    "openingHours": "Mo-Sa 09:00-19:00",
    "telephone": "084-151-1684"
  }
  </script>

    <script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "ซ่อม MacBook ราคาเท่าไหร่?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "ราคาซ่อม MacBook ขึ้นอยู่กับรุ่นและอาการ เช่น เปลี่ยนจอเริ่มต้นประมาณ 5,900 บาท เปลี่ยนแบตเริ่มที่ 2,900 บาท"
      }
    },
    {
      "@type": "Question",
      "name": "ใช้เวลาซ่อม MacBook นานไหม?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "งานซ่อมทั่วไปใช้เวลา 1–3 วัน โดยเราจะแจ้งลูกค้าก่อนทุกครั้งหากต้องรออะไหล่"
      }
    }
  ]
}
</script>

</head>

<body>
    <?php include_once '../includes/header.php'; ?>

    <main>
        <section class="hero-macbook">
            <div class="hero-overlay">
                <div class="hero-text" data-aos="fade-up">
                    <h1>บริการซ่อม MacBook ครบทุกอาการ โดยช่างผู้เชี่ยวชาญ</h1>
                    <p>MacBook ทุกรุ่น อะไหล่คุณภาพ มีรับประกันทุกรายการ</p>
                </div>
            </div>
        </section>

        <div class="floating-menu" data-aos="fade-up">
            <ul>
                <li><a href="/services/macbook.php"><span class="material-symbols-rounded">laptop_mac</span>
                        <div>ซ่อม MacBook</div>
                    </a></li>
                <li><a href="/services/imac.php"><span class="material-symbols-rounded">desktop_mac</span>
                        <div>ซ่อม iMac</div>
                    </a></li>
                <li><a href="/services/iphone.php"><span class="material-symbols-rounded">smartphone</span>
                        <div>ซ่อม iPhone</div>
                    </a></li>
                <li><a href="/services/ipad.php"><span class="material-symbols-rounded">tablet_mac</span>
                        <div>ซ่อม iPad</div>
                    </a></li>
                <li><a href="/services/apple-watch.php"><span class="material-symbols-rounded">watch</span>
                        <div>ซ่อม Apple Watch</div>
                    </a></li>
                <li><a href="/services/airpods.php"><span class="material-symbols-rounded">headphones</span>
                        <div>ซ่อม AirPods</div>
                    </a></li>
                <li><a href="/services/software.php"><span class="material-symbols-rounded">terminal</span>
                        <div>ลงโปรแกรม / OS</div>
                    </a></li>
            </ul>
        </div>

        <section class="macbook-services container">
            <h2 data-aos="fade-up">บริการซ่อม MacBook ครบวงจร</h2>
            <p class="section-desc" data-aos="fade-up" data-aos-delay="100">
                รับซ่อม MacBook ทุกรุ่น เช่น MacBook Air และ MacBook Pro ทั้งชิป Intel, M1, M2, M3
                โดยทีมช่างผู้เชี่ยวชาญ พร้อมอะไหล่แท้ มีการรับประกันทุกรายการซ่อม
                ลูกค้าสามารถตรวจสอบราคาซ่อม MacBook เบื้องต้นได้จากตารางด้านล่าง
                ไม่ว่าจะเป็นการเปลี่ยนจอ เปลี่ยนแบตเตอรี่ หรือซ่อมเมนบอร์ด เรามีราคาชัดเจน โปร่งใส ไม่บวกเพิ่มหน้างาน
            </p>


            <div class="service-grid">
                <div class="service-box" data-aos="fade-up" data-aos-delay="200">
                    <span class="material-symbols-rounded">display_settings</span>
                    <h3>ซ่อมจอ MacBook</h3>
                    <p>รับเปลี่ยนจอ Retina, Liquid Retina, Mini-LED สำหรับ MacBook ทุกรุ่น จอแตก จอลาย จอไม่ติด</p>
                </div>
                <div class="service-box" data-aos="fade-up" data-aos-delay="300">
                    <span class="material-symbols-rounded">battery_alert</span>
                    <h3>เปลี่ยนแบตเตอรี่</h3>
                    <p>แบตเสื่อม แบตบวม ชาร์จไม่เข้า เปลี่ยนแบตแท้สำหรับ MacBook Pro/Air พร้อมรับประกัน</p>
                </div>
                <div class="service-box" data-aos="fade-up" data-aos-delay="400">
                    <span class="material-symbols-rounded">memory</span>
                    <h3>ซ่อมเมนบอร์ด / เปิดไม่ติด</h3>
                    <p>MacBook เปิดไม่ติด เครื่องดับ น้ำเข้า ช็อต บอร์ดไหม้ เรามีอะไหล่พร้อมแก้ไขระดับชิ้น</p>
                </div>
                <div class="service-box" data-aos="fade-up" data-aos-delay="500">
                    <span class="material-symbols-rounded">terminal</span>
                    <h3>ลง macOS และโปรแกรม</h3>
                    <p>ลง OS ใหม่ แก้บูตช้า ลงโปรแกรมทำงานเฉพาะทาง Microsoft Office, Adobe, AutoCAD</p>
                </div>
            </div>
        </section>

        <section class="why-row container">
            <h2 data-aos="fade-up">ทำไมต้องซ่อมกับ cmnsfixmac?</h2>
            <div class="why-row-items" data-aos="fade-up" data-aos-delay="100">
                <div class="why-row-item"><span class="material-symbols-rounded">verified</span>
                    <p>รับประกันทุกงานซ่อม</p>
                </div>
                <div class="why-row-item"><span class="material-symbols-rounded">search</span>
                    <p>ตรวจเช็คฟรี ไม่มีค่าใช้จ่าย</p>
                </div>
                <div class="why-row-item"><span class="material-symbols-rounded">bolt</span>
                    <p>อะไหล่แท้ ซ่อมไว</p>
                </div>
                <div class="why-row-item"><span class="material-symbols-rounded">engineering</span>
                    <p>ช่างเฉพาะทาง</p>
                </div>
            </div>
        </section>

        <section class="macbook-price-tabs container">
            <h2 data-aos="fade-up">ตารางราคาซ่อม MacBook</h2>
            <div class="tab-buttons" data-aos="fade-up">
                <button class="tab-btn active" data-tab="display">เปลี่ยนจอ</button>
                <button class="tab-btn" data-tab="battery">เปลี่ยนแบตเตอรี่</button>
                <button class="tab-btn" data-tab="board">ซ่อมบอร์ด</button>
            </div>

            <div class="tab-contents">
                <?php
                $categories = ['display' => 'เปลี่ยนจอ', 'battery' => 'เปลี่ยนแบตเตอรี่', 'board' => 'ซ่อมบอร์ด'];
                require_once '../includes/db.php';
                foreach ($categories as $key => $title):
                    $stmt = $pdo->prepare("SELECT * FROM macbook_fix_pricing WHERE category = ?");
                    $stmt->execute([$key]);
                    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <div class="tab-content <?= $key === 'display' ? 'active' : '' ?>" id="<?= $key ?>">
                        <table class="fix-table">
                            <thead>
                                <tr>
                                    <th>รุ่น</th>
                                    <th>รายละเอียด</th>
                                    <th>ราคาโดยประมาณ</th>
                                </tr>
                            </thead>
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
            <h2 data-aos="fade-up">ตัวอย่างผลงานซ่อม MacBook</h2>
            <div class="fix-result-grid">
                <?php
                require_once __DIR__ . '/../includes/db.php';
                $stmt = $pdo->prepare("SELECT * FROM repairs WHERE LOWER(category) = 'macbook' ORDER BY created_at DESC LIMIT 6");
                $stmt->execute();
                while ($row = $stmt->fetch()):
                    $imagePath = (!empty($row['image']) && file_exists(__DIR__ . '/../uploads/' . $row['image']))
                        ? '../uploads/' . htmlspecialchars($row['image'])
                        : '../assets/img/placeholder.png';
                    ?>
                    <a href="/work-detail.php?id=<?= htmlspecialchars($row['id']) ?>" class="fix-result-box"
                        data-aos="fade-up">
                        <img src="<?= $imagePath ?>"
                            alt="ซ่อม MacBook - <?= htmlspecialchars($row['title']) ?> รุ่น <?= htmlspecialchars($row['model']) ?>"
                            loading="lazy">
                        <div class="fix-result-info">
                            <h3><?= htmlspecialchars($row['title']) ?></h3>
                            <p><?= htmlspecialchars($row['model']) ?></p>
                            <p class="views"><?= number_format($row['views']) ?> เข้าชม</p>
                        </div>
                    </a>
                <?php endwhile; ?>
            </div>
            <div class="view-all-link" data-aos="fade-up">
                <a href="/works.php?category=MacBook" class="btn-orange">ดูผลงานทั้งหมด</a>
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