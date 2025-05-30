<?php include 'includes/db.php'; ?>
<!-- เชื่อมต่อฐานข้อมูลจากไฟล์ db.php -->

<?php
// ดึงวิดีโอล่าสุด 3 รายการจากตาราง youtube_videos
$stmt = $pdo->query("SELECT * FROM youtube_videos ORDER BY created_at DESC LIMIT 3");
// แปลงผลลัพธ์ให้อยู่ในรูปแบบ array
$videos = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">

<head>
  <!-- ตั้งค่ารูปแบบภาษาและขนาดหน้าจอสำหรับมือถือ -->
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- ตั้งค่าข้อมูล SEO -->
  <title>ซ่อม MacBook เชียงใหม่ | ร้านซ่อม Apple โดยช่างผู้เชี่ยวชาญ - CMNS FixMac</title>
  <meta name="description"
    content="ร้านซ่อม MacBook เชียงใหม่ ซ่อม iPhone, iPad, iMac โดยช่างผู้เชี่ยวชาญ Apple ใช้อะไหล่แท้ รับประกันทุกงานซ่อม มีรีวิวลูกค้า">
  <meta name="keywords" content="ซ่อม MacBook, ร้านซ่อม Apple, เปลี่ยนจอ iPhone, ซ่อม Mac เชียงใหม่, FixMac">
  <meta name="author" content="CMNS FixMac - ซ่อม MacBook เชียงใหม่">
  <meta name="robots" content="index, follow">

  <!-- Open Graph / Facebook -->
  <meta property="og:title" content="ซ่อม MacBook เชียงใหม่ โดยช่างผู้เชี่ยวชาญ - CMNS FixMac">
  <meta property="og:description"
    content="บริการซ่อม Apple โดยช่างเฉพาะทาง มีประสบการณ์จริง อะไหล่แท้ ประเมินฟรี ที่เชียงใหม่">
  <meta property="og:image" content="https://cmnsfixmac.com/assets/img/og-cover.jpg">
  <meta property="og:url" content="https://cmnsfixmac.com/">
  <meta property="og:type" content="website">
  <meta property="og:locale" content="th_TH">

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="ซ่อม MacBook เชียงใหม่ | CMNS FixMac">
  <meta name="twitter:description"
    content="ช่างผู้เชี่ยวชาญ Apple ที่เชียงใหม่ รับประกันงานซ่อม ใช้อะไหล่แท้ พร้อมรีวิวจริงจากลูกค้า">
  <meta name="twitter:image" content="https://cmnsfixmac.com/assets/img/og-cover.jpg">

  <!-- ลิงก์ไฟล์ CSS และไลบรารีภายนอก -->
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/floating-buttons.css">
  <link rel="stylesheet" href="https://unpkg.com/aos@2.3.4/dist/aos.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
  <link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png" />
  <link rel="stylesheet" href="/assets/css/footer-style.css">

  <script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "ProfessionalService",
  "@id": "#business",
  "name": "ซ่อม MacBook เชียงใหม่ | CMNS FixMac",
  "image": "https://cmnsfixmac.com/assets/img/apple-logo.png",
  "url": "https://cmnsfixmac.com",
  "telephone": "+66-84-151-1684",
  "priceRange": "฿฿",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "482 หมู่ 8 หลังกาดวรุณ ถนนเชียงใหม่-หางดง ต.แม่เหียะ อ.เมือง",
    "addressLocality": "เชียงใหม่",
    "postalCode": "50100",
    "addressCountry": "TH"
  },
  "sameAs": [
    "https://www.facebook.com/CmnsShop",
    "https://www.youtube.com/@cmns-fixmac",
    "https://page.line.me/cmns",
    "https://www.tiktok.com/@cmns_fixmac"
  ]
}
</script>

  <script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Service",
  "serviceType": "ซ่อม MacBook เชียงใหม่",
  "provider": { "@id": "#business" },
  "description": "บริการซ่อม MacBook เช่น เปลี่ยนจอ เปลี่ยนแบต ซ่อมบอร์ด เครื่องดับ เปิดไม่ติด โดยช่างผู้เชี่ยวชาญ"
}
</script>

  <script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Service",
  "serviceType": "ซ่อม iMac เชียงใหม่",
  "provider": { "@id": "#business" },
  "description": "อัปเกรด SSD เพิ่มแรม ซ่อมจอ ซ่อมบอร์ด iMac ทุกรุ่น"
}
</script>

  <script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Service",
  "serviceType": "ซ่อม iPhone เชียงใหม่",
  "provider": { "@id": "#business" },
  "description": "รับซ่อม iPhone ทุกอาการ เช่น จอแตก แบตเสื่อม ไมค์ลำโพงมีปัญหา ซ่อมบอร์ด เครื่องเปิดไม่ติด"
}
</script>

  <script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Service",
  "serviceType": "ซ่อม iPad เชียงใหม่",
  "provider": { "@id": "#business" },
  "description": "บริการซ่อม iPad เช่น เปลี่ยนจอ ทัชเพี้ยน แบตเตอรี่เสื่อม ซ่อมบอร์ด จอไม่ติด ชาร์จไม่เข้า"
}
</script>

  <script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "ซ่อม MacBook ใช้เวลานานไหม?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "โดยปกติใช้เวลา 1-3 วัน ขึ้นอยู่กับอาการและอะไหล่"
      }
    },
    {
      "@type": "Question",
      "name": "สามารถส่งเครื่องมาซ่อมทางขนส่งได้ไหม?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "สามารถส่งทาง Grab, หรือ Kerry ได้"
      }
    }
  ]
}
</script>

  <script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Review",
  "reviewRating": {
    "@type": "Rating",
    "ratingValue": "5",
    "bestRating": "5"
  },
  "author": {
    "@type": "Person",
    "name": "ลูกค้าจาก Google"
  },
  "reviewBody": "งานเร็ว น้องพนักงานอธิบายให้ข้อมูลแนะนำดี ร้านหาง่าย อยู่ติดถนนในหมูบ้านวรุณนิเวศน์ มีที่จอดรถในร่ม",
  "itemReviewed": { "@id": "#business" }
}
</script>

  <script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "VideoObject",
  "name": "รีวิวร้านซ่อม MacBook เชียงใหม่ | CMNS FixMac",
  "description": "วิดีโอรีวิวร้านซ่อม MacBook และ Apple ที่เชียงใหม่โดยทีมช่างผู้เชี่ยวชาญจาก CMNS FixMac",
  "thumbnailUrl": "https://img.youtube.com/vi/IEutM7RYaYs/maxresdefault.jpg",
  "uploadDate": "2024-11-25",
  "contentUrl": "https://www.youtube.com/shorts/IEutM7RYaYs",
  "embedUrl": "https://www.youtube.com/embed/IEutM7RYaYs",
  "publisher": {
    "@type": "Organization",
    "name": "CMNS FixMac",
    "logo": {
      "@type": "ImageObject",
      "url": "https://cmnsfixmac.com/assets/img/apple-logo.png"
    }
  }
}
</script>
</head>

<body>


  <?php include_once 'includes/header.php'; ?>
  <!-- เรียก header ของเว็บ เช่น เมนูนำทาง -->



  <!-- ส่วนแนะนำบริการ (Hero Section) -->
  <section class="hero">
    <!-- คอนเทนต์ใน Hero จะแสดงด้วย animation จาก AOS -->
    <div class="hero-content" data-aos="fade-up">
      <h1>ซ่อม MacBook เชียงใหม่ โดยช่างผู้เชี่ยวชาญ Apple</h1>
      <h2>ครบวงจร ซ่อม เปลี่ยน อัพเกรด</h2>
      <p>
        อะไหล่แท้ บริการมาตรฐาน โดยช่างผู้เชี่ยวชาญ มีประสบการณ์กับผลิตภัณฑ์แอปเปิ้ลโดยตรง<br>
        เรามีสินค้าหลายเกรดให้เลือก ให้เหมาะสมกับงบประมาณ และความคุ้มค่าที่สุด
      </p>
      <a href="#work" class="btn">ดูผลงาน</a>
      <a href="#tools" class="btn">ทดสอบเครื่อง ก่อนมาร้าน</a>

    </div>
  </section>


  <!-- จุดเด่นบริการ 4 ด้าน -->
  <section class="feature-highlight-floating" data-aos="fade-up">
    <!-- แสดงไอคอน + ข้อความ เช่น ฟรีประเมิน, ส่งเครื่องได้, ช่างเชี่ยวชาญ -->
    <div class="feature-box">
      <span class="material-symbols-rounded">request_quote</span>
      <h3>ประเมินราคาก่อนซ่อม</h3>
      <p>ไม่มีค่าใช้จ่ายในการตรวจเช็ค</p>
    </div>
    <div class="feature-box">
      <span class="material-symbols-rounded">local_shipping</span>
      <h3>ส่งเครื่องมาตรวจเช็คได้</h3>
      <p>ผ่านขนส่ง หรือ Grab, lalamove</p>
    </div>
    <div class="feature-box">
      <span class="material-symbols-rounded">engineering</span>
      <h3>ช่างมีประสบการณ์</h3>
      <p>กับ Mac โดยตรง</p>
    </div>
    <div class="feature-box">
      <span class="material-symbols-rounded">autorenew</span>
      <h3>รับซื้อ - ขาย เทิร์น</h3>
      <p>รับซื้อเครื่องไม่ใช้ ขายเป็นอะไหล่</p>
    </div>
  </section>


  <!-- ผลงานซ่อมล่าสุด 2 รายการ -->
  <section class="section-work" id="work" data-aos="fade-up">
    <h2>ผลงานล่าสุด</h2>
    <div class="work-grid">
      <!-- วนลูปแสดงข้อมูลจากฐานข้อมูล table repairs -->
      <?php
      $stmt = $pdo->query("SELECT * FROM repairs ORDER BY created_at DESC LIMIT 2");
      while ($row = $stmt->fetch()) {
        echo '<div class="work-card">';
        echo '<img src="uploads/' . htmlspecialchars($row["image"]) . '" alt="' . htmlspecialchars($row["title"]) . '" loading="lazy">';
        echo '<h3>' . htmlspecialchars($row["title"]) . '</h3>';
        echo '<p>' . htmlspecialchars($row["model"]) . '</p>';
        echo '</div>';
      }
      ?>
    </div>
    <br>
    <a href="works.php" class="btn">ดูเพิ่มเติม</a>
  </section>

  <!-- รายการบริการทั้งหมดที่ร้านมี -->
  <section class="service-highlight">
    <h2>บริการของเรา</h2>
<div class="services-container">
  <a href="/services/macbook.php" class="service-box" style="text-decoration:none; color:inherit;">
    <img src="assets/img/icons/icon-macbook.png" loading="lazy" alt="ซ่อม MacBook เชียงใหม่" class="service-icon">
    <h3 style="color:#0D1A3E; text-decoration:none;">ซ่อม MacBook</h3>
    <p style="color:#0D1A3E; text-decoration:none;">ซ่อมจอ บอร์ด แบต และปัญหาเครื่องดับ เปิดไม่ติด</p>
  </a>

  <a href="/services/imac.php" class="service-box" style="text-decoration:none; color:inherit;">
    <img src="assets/img/icons/icon-imac.png" loading="lazy" alt="ซ่อม iMac เชียงใหม่" class="service-icon">
    <h3 style="color:#0D1A3E; text-decoration:none;">ซ่อม iMac</h3>
    <p style="color:#0D1A3E; text-decoration:none;">อัปเกรด SSD เพิ่มแรม ซ่อมจอ ซ่อมบอร์ด iMac ทุกรุ่น</p>
  </a>

  <a href="/services/iphone.php" class="service-box extra" style="text-decoration:none; color:inherit;">
    <img src="assets/img/icons/icon-iphone.png" loading="lazy" alt="ซ่อม iphone เชียงใหม่" class="service-icon">
    <h3 style="color:#0D1A3E; text-decoration:none;">ซ่อม iPad / iPhone</h3>
    <p style="color:#0D1A3E; text-decoration:none;">เปลี่ยนจอ แบตเตอรี่ ลำโพง กล้อง ซ่อมบอร์ดขั้นสูง</p>
  </a>

  <a href="/services/apple-watch.php" class="service-box extra" style="text-decoration:none; color:inherit;">
    <img src="assets/img/icons/icon-applewatch.png" loading="lazy" alt="ซ่อม applewatch เชียงใหม่" class="service-icon">
    <h3 style="color:#0D1A3E; text-decoration:none;">ซ่อม Apple Watch</h3>
    <p style="color:#0D1A3E; text-decoration:none;">เปลี่ยนจอ แบตเตอรี่ ซ่อมจอลอก จอแตก Apple Watch</p>
  </a>

  <a href="/services/airpods.php" class="service-box extra" style="text-decoration:none; color:inherit;">
    <img src="assets/img/icons/icon-airpods.png" loading="lazy" alt="ซ่อม airpods เชียงใหม่" class="service-icon">
    <h3 style="color:#0D1A3E; text-decoration:none;">ซ่อม AirPods</h3>
    <p style="color:#0D1A3E; text-decoration:none;">แก้ปัญหาแบตเสื่อม ไมค์เสีย ชาร์จไม่เข้า สำหรับ AirPods ทุกรุ่น</p>
  </a>

  <a href="/services/software.php" class="service-box extra" style="text-decoration:none; color:inherit;">
    <img src="assets/img/icons/icon-os.png" loading="lazy" alt="ลงโปรแกรมและ OS เชียงใหม่" class="service-icon">
    <h3 style="color:#0D1A3E; text-decoration:none;">ลงโปรแกรมและ OS</h3>
    <p style="color:#0D1A3E; text-decoration:none;">ลง macOS เวอร์ชันใหม่, โปรแกรมทำงาน, โปรแกรมเฉพาะทาง</p>
  </a>
</div>




    <button id="toggleBtn">ดูเพิ่มเติม</button>
  </section>

  <section class="section-diagnose" data-aos="fade-up" id="tools">
    <h2>เครื่องมือตรวจเช็คอาการเบื้องต้น </h2>
    <p>ลองทดสอบอุปกรณ์ของคุณก่อนเข้าร้าน ด้วยเครื่องมือออนไลน์ที่เราแนะนำ</p>

    <div class="diagnose-wrapper">
      <div class="diagnose-buttons" id="diagnose-tools">
        <a href="https://cmnsfixmac.com/tester/keyboard-tester/index.php" target="_blank" class="btn desktop-only">
          <span class="material-symbols-rounded">keyboard</span> ทดสอบคีย์บอร์ด
        </a>
        <a href="https://cmnsfixmac.com/tester/sounds-tester/index.php" target="_blank" class="btn">
          <span class="material-symbols-rounded">volume_up</span> ทดสอบลำโพง
        </a>
        <a href="https://cmnsfixmac.com/tester/monitor-tester/index.php" target="_blank" class="btn">
          <span class="material-symbols-rounded">monitor</span> ตรวจจอเสีย
        </a>
        <a href="https://cmnsfixmac.com/tester/camera-tester/index.php" target="_blank" class="btn">
          <span class="material-symbols-rounded">photo_camera</span> ตรวจกล้อง
        </a>
        <a href="https://cmnsfixmac.com/tester/microphone-tester/index.php" target="_blank" class="btn">
          <span class="material-symbols-rounded">mic</span> ตรวจไมค์
        </a>
        <a href="https://cmnsfixmac.com/tester/touchscreen-tester/index.php" target="_blank" class="btn mobile-only">
          <span class="material-symbols-rounded">touch_app</span> ตรวจทัชสกรีน
        </a>
      </div>
    </div>
    <button class="toggle-btn" onclick="toggleDiagnose()">ดูเพิ่มเติม</button>
  </section>



  <!-- สไลด์แสดงรูปบรรยากาศร้าน -->
  <section class="section-atmosphere" data-aos="fade-up">
    <h2>บรรยากาศร้านของเรา</h2>
    <div class="swiper atmosphereSwiper">
      <div class="swiper-wrapper">
        <div class="swiper-slide"><img src="assets/img/store1.webp" loading="lazy"
            alt="หน้าร้านซ่อม MacBook เชียงใหม่ - CMNS FixMac"></div>
        <div class="swiper-slide"><img src="assets/img/store2.webp" loading="lazy"
            alt="เคาน์เตอร์ต้อนรับร้าน CMNS FixMac เชียงใหม่"></div>
        <div class="swiper-slide"><img src="assets/img/store3.webp" loading="lazy"
            alt="บรรยากาศภายในร้านซ่อม MacBook เชียงใหม่"></div>
        <div class="swiper-slide"><img src="assets/img/store4.webp" loading="lazy" alt="ซ่อม MacBook เชียงใหม่ - CMNS">
        </div>
        <div class="swiper-slide"><img src="assets/img/store5.webp" loading="lazy"
            alt="อุปกรณ์ซ่อม Apple มาตรฐาน Apple แท้ในร้าน CMNS FixMac"></div>
        <div class="swiper-slide"><img src="assets/img/store6.webp" loading="lazy"
            alt="ซ่อม iPhone iPad MacBook ที่ร้าน CMNS Fixmac เชียงใหม่"></div>
        <div class="swiper-slide"><img src="assets/img/store7.webp" loading="lazy"
            alt="ลูกค้ามาใช้บริการร้านซ่อม MacBook เชียงใหม่"></div>
        <div class="swiper-slide"><img src="assets/img/store8.webp" loading="lazy"
            alt="บรรยากาศร้านซ่อม Apple เชียงใหม่ FixMac จากภายนอก"></div>
      </div>
      <div class="swiper-pagination"></div>
    </div>
  </section>

  <!-- ลิงก์ตรวจสอบอาการเบื้องต้นแบบออนไลน์ -->


  <!-- จุดขายหรือจุดแข็งของร้าน -->
  <section class="section-reasons" data-aos="fade-up">
    <h2>เหตุผลที่ควรเลือกใช้บริการกับเรา</h2>
    <div class="reasons-grid">

      <div class="reason-box">
        <span class="material-symbols-rounded">support_agent</span>
        <h3>วิเคราะห์อาการฟรี</h3>
        <p>ทางร้านยินดีให้บริการสอบถามปัญหาและอาการเสีย เพื่อวิเคราะห์ค่าใช้จ่ายให้การซ่อมให้ฟรี</p>
      </div>

      <div class="reason-box">
        <span class="material-symbols-rounded">build_circle</span>
        <h3>ใช้อะไหล่คุณภาพสูง</h3>
        <p>ใช้เครื่องมือที่ได้มาตรฐาน ใหม่ ทันสมัย ​​ใช้อะไหล่แท้ มีพร้อมบริการ และรับประกันอะไหล่ทุกชิ้น</p>
      </div>

      <div class="reason-box">
        <span class="material-symbols-rounded">engineering</span>
        <h3>ทีมช่างเชี่ยวชาญ</h3>
        <p>ทีมช่างของเรามีความเชี่ยวชาญในเรื่องการซ่อม MAC ทั้งฮาร์ดแวร์และซอฟต์แวร์ มั่นใจได้ในความเป็นมืออาชีพ</p>
      </div>

    </div>
  </section>

  <!-- วิดีโอ YouTube ดึงจากฐานข้อมูล -->
  <section class="video-section" id="youtube">
    <h2>วิดีโอรีวิว & เทคนิคจากช่าง</h2>

    <div class="video-grid">
      <?php foreach ($videos as $v): ?>
        <div class="video-card">
          <div class="youtube-lazy" data-id="<?= htmlspecialchars($v['video_id']) ?>">
            <img src="https://img.youtube.com/vi/<?= htmlspecialchars($v['video_id']) ?>/hqdefault.jpg"
              alt="<?= htmlspecialchars($v['title']) ?>">
            <div class="play-button"></div>
          </div>
          <h3><?= htmlspecialchars($v['title']); ?></h3>
          <p><?= nl2br(htmlspecialchars($v['description'])); ?></p>
        </div>
      <?php endforeach; ?>
    </div>


    <br>
    <div style="text-align: center;">
      <a href="https://www.youtube.com/@cmns-fixmac" class="btn">ดูวิดีโอทั้งหมด</a>
    </div>
  </section>



  <!-- รีวิวจาก Google ผ่าน Elfsight -->
  <section class="section-review" data-aos="fade-up">
    <h2>รีวิวจากลูกค้าบน Google</h2>

    <!-- Elfsight Google Reviews | แสดงรีวิว Google จริงแบบอัตโนมัติ -->
    <script src="https://static.elfsight.com/platform/platform.js" async></script>
    <div class="elfsight-app-9dc2caab-6860-424f-bf62-fa7f8b19d56a" data-elfsight-app-lazy></div>
  </section>



  <section class="section-team" data-aos="fade-up">
    <h2>ทีมช่างของเรา</h2>
    <!-- เริ่ม block team-grid ที่มี class tighter -->
    <div class="team-grid tighter">
      <div class="team-member">
        <img src="assets/img/tech1.webp" alt="ช่างแจ็ค ผู้เชี่ยวชาญซ่อม MacBook ที่เชียงใหม่ CMNS FixMac">
        <h3>ช่างแจ็ค</h3>
        <p>ผู้เชี่ยวชาญ Mac,MacBook มากกว่า 10 ปี</p>
      </div>
    </div>
    <!-- ส่วนที่ซ่อนแบบลื่น -->
    <div class="team-more" id="team-more">
      <div class="team-grid">
        <div class="team-member">
          <div class="img-hover-wrap">
            <img src="assets/img/tech2.webp" loading="lazy"
              alt="ช่างทัก ผู้เชี่ยวชาญระบบ macOS และซ่อม iMac ที่เชียงใหม่" class="default-img">
            <img src="assets/img/tech2-hover.webp" loading="lazy"
              alt="ช่างทัก กำลังทำงานซ่อมระบบ OS Apple ในร้านซ่อม MacBook เชียงใหม่" class="hover-img">
          </div>
          <h3>ช่างทัก</h3>
          <p>ผู้เชี่ยวชาญระบบ OS และซอฟต์แวร์</p>
        </div>
        <div class="team-member">
          <img src="assets/img/tech3.webp" loading="lazy"
            alt="ช่างแบงค์ นักศึกษาฝึกงานซ่อม iPhone iPad ที่ร้าน CMNS FixMac เชียงใหม่">
          <h3>แบงค์</h3>
          <p>นักศึกษาฝึกงาน</p>
        </div>
        <div class="team-member">
          <img src="assets/img/tech4.webp" loading="lazy"
            alt="ช่างไมค์ ฝึกงานด้านซ่อม MacBook และอุปกรณ์ Apple ที่เชียงใหม่">
          <h3>ไมค์</h3>
          <p>นักศึกษาฝึกงาน</p>
        </div>
        <div class="team-member">
          <img src="assets/img/tech5.webp" loading="lazy" alt="ช่างนัฐ ฝึกงานในทีมซ่อม Apple ที่ร้าน FixMac เชียงใหม่">
          <h3>นัฐ</h3>
          <p>นักศึกษาฝึกงาน</p>
        </div>
      </div>
    </div>



    <button class="btn show-more-team" id="toggle-team-btn">ดูทีมช่างทั้งหมด</button>
  </section>



  <!-- แผนที่ร้าน -->
  <section class="map-section" id="map" data-aos="fade-up">
    <h2> แผนที่ร้านของเรา</h2>
    <div class="map-container">
      <iframe
        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2757.0147940546653!2d98.96466192390933!3d18.75005733629581!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x30da3aa79be8e5db%3A0x1a948e6def350e!2z4LiL4LmI4Lit4LihIG1hYyDguYDguIrguLXguKLguIfguYPguKvguKHguYggKEZpeCBtYWMgQ2hpYW5nbWFpKQ!5e0!3m2!1sth!2sth!4v1745215403938!5m2!1sth!2sth"
        width="100%" height="300" style="border:0;" allowfullscreen="" loading="lazy"
        referrerpolicy="no-referrer-when-downgrade">
      </iframe>
    </div>
  </section>


  <!-- JS LIBRARY -->
  <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>

  <!-- JS CUSTOM -->
  <script src="assets/js/main.js"></script>
  <script src="assets/js/swiper-init.js"></script>
  <script src="assets/js/aos-init.js"></script>
  <script src="assets/js/script.js"></script>


  <?php include_once 'includes/floating-buttons.php'; ?>
  <script src="assets/js/floating-buttons.js"></script>

  <!-- Footer -->
  <?php include_once 'includes/footer.php'; ?>

  <script src="assets/js/lazy-youtube.js"></script>
  <script src="assets/js/preload-images.js"></script>

</body>

</html>