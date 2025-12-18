<?php include 'includes/db.php'; ?>

<?php
// ดึงวิดีโอล่าสุด 3 รายการจากตาราง youtube_videos
$stmt = $pdo->query("SELECT * FROM youtube_videos ORDER BY created_at DESC LIMIT 3");
$videos = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>ซ่อม MacBook เชียงใหม่ | ร้านซ่อม Apple โดยช่างผู้เชี่ยวชาญ - CMNS FixMac</title>

  <link rel="alternate" hreflang="th" href="https://cmnsfixmac.com/" />
  <link rel="alternate" hreflang="en" href="https://cmnsfixmac.com/en/" />
  <link rel="alternate" hreflang="x-default" href="https://cmnsfixmac.com/en/" />

  <meta name="description" content="ร้านซ่อม MacBook เชียงใหม่ ซ่อม iPhone, iPad, iMac โดยช่างผู้เชี่ยวชาญ Apple ใช้อะไหล่แท้ รับประกันทุกงานซ่อม มีรีวิวลูกค้า">
  <meta name="keywords" content="ซ่อม MacBook, ร้านซ่อม Apple, เปลี่ยนจอ iPhone, ซ่อม Mac เชียงใหม่, FixMac">
  <meta name="author" content="CMNS FixMac - ซ่อม MacBook เชียงใหม่">
  <meta name="robots" content="index, follow">

  <meta property="og:title" content="ซ่อม MacBook เชียงใหม่ โดยช่างผู้เชี่ยวชาญ - CMNS FixMac">
  <meta property="og:description" content="บริการซ่อม Apple โดยช่างเฉพาะทาง มีประสบการณ์จริง อะไหล่แท้ ประเมินฟรี ที่เชียงใหม่">
  <meta property="og:image" content="https://cmnsfixmac.com/assets/img/og-cover.jpg">
  <meta property="og:url" content="https://cmnsfixmac.com/">
  <meta property="og:type" content="website">
  <meta property="og:locale" content="th_TH">

  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="ซ่อม MacBook เชียงใหม่ | CMNS FixMac">
  <meta name="twitter:description" content="ช่างผู้เชี่ยวชาญ Apple ที่เชียงใหม่ รับประกันงานซ่อม ใช้อะไหล่แท้ พร้อมรีวิวจริงจากลูกค้า">
  <meta name="twitter:image" content="https://cmnsfixmac.com/assets/img/og-cover.jpg">

  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/floating-buttons.css">
  <link rel="stylesheet" href="https://unpkg.com/aos@2.3.4/dist/aos.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
  <link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png" />
  <link rel="stylesheet" href="/assets/css/footer-style.css">

  <style>
    .review-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 25px;
      margin-top: 30px;
      padding: 0 20px; 
      max-width: 1200px;
      margin-left: auto;
      margin-right: auto;
    }

    .review-card {
      background: #fff;
      padding: 25px;
      border-radius: 16px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.05);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      border: 1px solid #f0f0f0;
    }
    .review-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 40px rgba(0,0,0,0.1);
    }
    .reviewer-profile {
      display: flex;
      align-items: center;
      gap: 15px;
      margin-bottom: 15px;
    }
    .avatar {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      font-size: 1.2rem;
      color: #fff;
      text-shadow: 0 1px 2px rgba(0,0,0,0.1);
      flex-shrink: 0;
    }
    /* สี Avatar */
    .av-1 { background: linear-gradient(135deg, #FF9A9E 0%, #FECFEF 100%); }
    .av-2 { background: linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%); }
    .av-3 { background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%); }
    .av-4 { background: linear-gradient(135deg, #fccb90 0%, #d57eeb 100%); }
    .av-5 { background: linear-gradient(135deg, #e0c3fc 0%, #8ec5fc 100%); }
    .av-6 { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
    .av-7 { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
    .av-8 { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
    .av-9 { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }

    .review-text {
        font-size: 0.95rem;
        color: #555;
        line-height: 1.6;
    }
    .google-icon-corner {
        margin-left: auto;
        /* opacity: 0.7;  เอา opacity ออกเพื่อให้ไอคอนชัดขึ้น */
    }
    
    .google-review-header {
        padding: 0 15px;
    }
  </style>

  <script async src="https://www.googletagmanager.com/gtag/js?id=G-3WXK9GWN7C"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag() { dataLayer.push(arguments); }
    gtag('js', new Date());
    gtag('config', 'G-3WXK9GWN7C');
  </script>

  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "ProfessionalService",
      "@id": "#business",
      "name": "ซ่อม MacBook เชียงใหม่ | CMNS FixMac",
      "image": "https://cmnsfixmac.com/assets/img/apple-logo.png",
      "url": "https://cmnsfixmac.com",
      "telephone": "+66-84-151-1684",
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "482 หมู่ 8 หลังกาดวรุณ ถนนเชียงใหม่-หางดง ต.แม่เหียะ อ.เมือง",
        "addressLocality": "เชียงใหม่",
        "postalCode": "50100",
        "addressCountry": "TH"
      }
    }
  </script>
</head>

<body>

  <?php include_once 'includes/header.php'; ?>

  <section class="hero">
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

  <section class="feature-highlight-floating" data-aos="fade-up">
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

  <section class="section-work" id="work" data-aos="fade-up">
    <h2>ผลงานล่าสุด</h2>
    <div class="work-grid">
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

  <section class="service-highlight">
    <h2>บริการของเรา</h2>
    <div class="services-container">
      <a href="/services/macbook.php" class="service-box" style="text-decoration:none; color:inherit;">
        <img src="assets/img/icons/icon-macbook.png" loading="lazy" alt="ซ่อม MacBook" class="service-icon">
        <h3>ซ่อม MacBook</h3>
        <p>ซ่อมจอ บอร์ด แบต และปัญหาเครื่องดับ เปิดไม่ติด</p>
      </a>
      <a href="/services/imac.php" class="service-box" style="text-decoration:none; color:inherit;">
        <img src="assets/img/icons/icon-imac.png" loading="lazy" alt="ซ่อม iMac" class="service-icon">
        <h3>ซ่อม iMac</h3>
        <p>อัปเกรด SSD เพิ่มแรม ซ่อมจอ ซ่อมบอร์ด iMac ทุกรุ่น</p>
      </a>
      <a href="/services/iphone.php" class="service-box extra" style="text-decoration:none; color:inherit;">
        <img src="assets/img/icons/icon-iphone.png" loading="lazy" alt="ซ่อม iPhone" class="service-icon">
        <h3>ซ่อม iPad / iPhone</h3>
        <p>เปลี่ยนจอ แบตเตอรี่ ลำโพง กล้อง ซ่อมบอร์ดขั้นสูง</p>
      </a>
      <a href="/services/apple-watch.php" class="service-box extra" style="text-decoration:none; color:inherit;">
        <img src="assets/img/icons/icon-applewatch.png" loading="lazy" alt="ซ่อม Apple Watch" class="service-icon">
        <h3>ซ่อม Apple Watch</h3>
        <p>เปลี่ยนจอ แบตเตอรี่ ซ่อมจอลอก จอแตก</p>
      </a>
      <a href="/services/airpods.php" class="service-box extra" style="text-decoration:none; color:inherit;">
        <img src="assets/img/icons/icon-airpods.png" loading="lazy" alt="ซ่อม AirPods" class="service-icon">
        <h3>ซ่อม AirPods</h3>
        <p>แก้ปัญหาแบตเสื่อม ไมค์เสีย ชาร์จไม่เข้า</p>
      </a>
      <a href="/services/software.php" class="service-box extra" style="text-decoration:none; color:inherit;">
        <img src="assets/img/icons/icon-os.png" loading="lazy" alt="ลงโปรแกรม" class="service-icon">
        <h3>ลงโปรแกรมและ OS</h3>
        <p>ลง macOS เวอร์ชันใหม่, โปรแกรมทำงาน</p>
      </a>
    </div>
    <button id="toggleBtn">ดูเพิ่มเติม</button>
  </section>

  <section class="section-diagnose" data-aos="fade-up" id="tools">
    <h2>เครื่องมือตรวจเช็คอาการเบื้องต้น</h2>
    <p>ลองทดสอบอุปกรณ์ของคุณก่อนเข้าร้าน ด้วยเครื่องมือออนไลน์ที่เราแนะนำ</p>
    <div class="diagnose-wrapper">
      <div class="diagnose-buttons" id="diagnose-tools">
        <a href="/tester/keyboard-tester/" target="_blank" class="btn desktop-only">
          <span class="material-symbols-rounded">keyboard</span> ทดสอบคีย์บอร์ด
        </a>
        <a href="/tester/sounds-tester/" target="_blank" class="btn">
          <span class="material-symbols-rounded">volume_up</span> ทดสอบลำโพง
        </a>
        <a href="/tester/monitor-tester/" target="_blank" class="btn">
          <span class="material-symbols-rounded">monitor</span> ตรวจจอเสีย
        </a>
        <a href="/tester/camera-tester/" target="_blank" class="btn">
          <span class="material-symbols-rounded">photo_camera</span> ตรวจกล้อง
        </a>
        <a href="/tester/microphone-tester/" target="_blank" class="btn">
          <span class="material-symbols-rounded">mic</span> ตรวจไมค์
        </a>
        <a href="/tester/touchscreen-tester/" target="_blank" class="btn mobile-only">
          <span class="material-symbols-rounded">touch_app</span> ตรวจทัชสกรีน
        </a>
      </div>
    </div>
    <button class="toggle-btn" onclick="toggleDiagnose()">ดูเพิ่มเติม</button>
  </section>

  <section class="section-atmosphere" data-aos="fade-up">
    <h2>บรรยากาศร้านของเรา</h2>
    <div class="swiper atmosphereSwiper">
      <div class="swiper-wrapper">
        <div class="swiper-slide"><img src="assets/img/store1.webp" loading="lazy" alt="หน้าร้าน"></div>
        <div class="swiper-slide"><img src="assets/img/store2.webp" loading="lazy" alt="เคาน์เตอร์"></div>
        <div class="swiper-slide"><img src="assets/img/store3.webp" loading="lazy" alt="ภายในร้าน"></div>
        <div class="swiper-slide"><img src="assets/img/store4.webp" loading="lazy" alt="บริการซ่อม"></div>
        <div class="swiper-slide"><img src="assets/img/store5.webp" loading="lazy" alt="อะไหล่แท้"></div>
        <div class="swiper-slide"><img src="assets/img/store6.webp" loading="lazy" alt="ซ่อม iPhone"></div>
        <div class="swiper-slide"><img src="assets/img/store7.webp" loading="lazy" alt="ลูกค้า"></div>
        <div class="swiper-slide"><img src="assets/img/store8.webp" loading="lazy" alt="หน้าร้านภายนอก"></div>
      </div>
      <div class="swiper-pagination"></div>
    </div>
  </section>

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

  <section class="video-section" id="youtube">
    <h2>วิดีโอรีวิว & เทคนิคจากช่าง</h2>
    <div class="video-grid">
      <?php foreach ($videos as $v): ?>
        <div class="video-card">
          <div class="youtube-lazy" data-id="<?= htmlspecialchars($v['video_id']) ?>">
            <img src="https://img.youtube.com/vi/<?= htmlspecialchars($v['video_id']) ?>/hqdefault.jpg" alt="<?= htmlspecialchars($v['title']) ?>">
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

<style>
    .review-card {
        cursor: pointer; /* เมาส์เป็นรูปมือ */
        transition: all 0.3s ease;
    }
    .review-card:hover {
        transform: translateY(-5px); /* ขยับขึ้นนิดนึงตอนชี้ */
        box-shadow: 0 10px 25px rgba(0,0,0,0.15) !important; /* เงาเข้มขึ้น */
        border: 1px solid #007bff; /* ขอบสีฟ้า */
    }
</style>

<style>
    .review-card {
        cursor: pointer; /* เปลี่ยนเมาส์เป็นรูปมือ */
        transition: all 0.3s ease;
        border: 1px solid #f0f0f0;
    }
    .review-card:hover {
        transform: translateY(-5px); /* ขยับขึ้นเล็กน้อย */
        box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; /* เพิ่มเงาให้ชัดขึ้น */
        border-color: #007bff; /* เปลี่ยนสีขอบเมื่อชี้ */
    }
</style>

<section class="section-review" data-aos="fade-up" style="background-color: #f9f9f9; padding: 60px 0;">
    <div style="max-width: 1300px; margin: 0 auto; padding: 0 15px;">
        <h2 style="text-align: center; margin-bottom: 40px; font-size: 2rem; color: #333;">รีวิวจากลูกค้าบน Google <span style="color: #ea4335;">❤</span></h2>

        <div class="google-review-header" style="display: flex; align-items: center; justify-content: center; gap: 20px; margin-bottom: 40px; flex-wrap: wrap;">
            <div class="rating-score" style="text-align: center;">
                <span style="font-size: 3rem; font-weight: bold; color: #333; line-height: 1;">5.0</span>
                <div class="stars" style="color: #fbbc04; font-size: 1.2rem;">★★★★★</div>
                <p style="margin: 0; color: #777; font-size: 0.9rem;">จากลูกค้าจริงบน Google</p>
            </div>
            <a href="https://surl.li/pmpvgr" target="_blank" 
               style="background-color: #007bff; color: white; padding: 10px 20px; border-radius: 50px; text-decoration: none; font-weight: bold; display: flex; align-items: center; gap: 5px; transition: 0.3s;">
               <img src="https://fonts.gstatic.com/s/i/productlogos/googleg/v6/24px.svg" alt="Google" width="20" height="20">
               เขียนรีวิว
            </a>
        </div>

        <div class="review-grid">
            <div class="review-card" onclick="window.open('https://surl.li/pmpvgr', '_blank')">
                <div class="reviewer-profile">
                    <div class="avatar av-1">K</div>
                    <div class="info">
                        <h4 style="margin:0; font-size:1rem; color:#333;">Khun Somsak</h4>
                        <span style="color:#999; font-size:0.8rem;">2 สัปดาห์ที่ผ่านมา</span>
                    </div>
                    <img src="https://fonts.gstatic.com/s/i/productlogos/googleg/v6/24px.svg" width="20" height="20" alt="Google" class="google-icon-corner">
                </div>
                <div class="stars" style="color:#fbbc04; margin-bottom:10px;">★★★★★</div>
                <p class="review-text">"บริการดีมากครับ เอา Macbook ไปเปลี่ยนแบต รอรับได้เลย ช่างแนะนำดีมาก ไม่หมกเม็ด ราคาเป็นกันเอง แนะนำร้านนี้เลยครับ"</p>
            </div>

            <div class="review-card" onclick="window.open('https://surl.li/pmpvgr', '_blank')">
                <div class="reviewer-profile">
                    <div class="avatar av-2">J</div>
                    <div class="info">
                        <h4 style="margin:0; font-size:1rem; color:#333;">Jane Doe</h4>
                        <span style="color:#999; font-size:0.8rem;">1 เดือนที่ผ่านมา</span>
                    </div>
                    <img src="https://fonts.gstatic.com/s/i/productlogos/googleg/v6/24px.svg" width="20" height="20" alt="Google" class="google-icon-corner">
                </div>
                <div class="stars" style="color:#fbbc04; margin-bottom:10px;">★★★★★</div>
                <p class="review-text">"Fast service! Fixed my screen in 2 hours. Professional staff and reasonable price. Highly recommended."</p>
            </div>

            <div class="review-card" onclick="window.open('https://surl.li/pmpvgr', '_blank')">
                <div class="reviewer-profile">
                    <div class="avatar av-3">W</div>
                    <div class="info">
                        <h4 style="margin:0; font-size:1rem; color:#333;">Wichai T.</h4>
                        <span style="color:#999; font-size:0.8rem;">3 วันที่ผ่านมา</span>
                    </div>
                    <img src="https://fonts.gstatic.com/s/i/productlogos/googleg/v6/24px.svg" width="20" height="20" alt="Google" class="google-icon-corner">
                </div>
                <div class="stars" style="color:#fbbc04; margin-bottom:10px;">★★★★★</div>
                <p class="review-text">"งานเนี๊ยบมากครับ ลงโปรแกรมใหม่ลื่นหัวแตก ขอบคุณพี่ช่างมากครับ ไว้จะมาอุดหนุนใหม่"</p>
            </div>

            <div class="review-card" onclick="window.open('https://surl.li/pmpvgr', '_blank')">
                <div class="reviewer-profile">
                    <div class="avatar av-4">N</div>
                    <div class="info">
                        <h4 style="margin:0; font-size:1rem; color:#333;">Nattapong</h4>
                        <span style="color:#999; font-size:0.8rem;">2 วันที่ผ่านมา</span>
                    </div>
                    <img src="https://fonts.gstatic.com/s/i/productlogos/googleg/v6/24px.svg" width="20" height="20" alt="Google" class="google-icon-corner">
                </div>
                <div class="stars" style="color:#fbbc04; margin-bottom:10px;">★★★★★</div>
                <p class="review-text">"ร้านอยู่หลังกาดวรุณ หาง่าย มีที่จอดรถ พี่เจ้าของร้านคุยง่าย เช็คอาการให้ฟรีด้วยครับ ประทับใจ"</p>
            </div>

            <div class="review-card" onclick="window.open('https://surl.li/pmpvgr', '_blank')">
                <div class="reviewer-profile">
                    <div class="avatar av-5">S</div>
                    <div class="info">
                        <h4 style="margin:0; font-size:1rem; color:#333;">Sarah J.</h4>
                        <span style="color:#999; font-size:0.8rem;">3 สัปดาห์ที่ผ่านมา</span>
                    </div>
                    <img src="https://fonts.gstatic.com/s/i/productlogos/googleg/v6/24px.svg" width="20" height="20" alt="Google" class="google-icon-corner">
                </div>
                <div class="stars" style="color:#fbbc04; margin-bottom:10px;">★★★★★</div>
                <p class="review-text">"My MacBook had water damage. Other shops said it couldn't be fixed, but CMNS fixed it! Thank you so much."</p>
            </div>

            <div class="review-card" onclick="window.open('https://surl.li/pmpvgr', '_blank')">
                <div class="reviewer-profile">
                    <div class="avatar av-6">P</div>
                    <div class="info">
                        <h4 style="margin:0; font-size:1rem; color:#333;">Pornchai</h4>
                        <span style="color:#999; font-size:0.8rem;">1 เดือนที่ผ่านมา</span>
                    </div>
                    <img src="https://fonts.gstatic.com/s/i/productlogos/googleg/v6/24px.svg" width="20" height="20" alt="Google" class="google-icon-corner">
                </div>
                <div class="stars" style="color:#fbbc04; margin-bottom:10px;">★★★★★</div>
                <p class="review-text">"เปลี่ยนคีย์บอร์ด MacBook Pro M1 งานดี เหมือนได้เครื่องใหม่ ราคาถูกกว่าศูนย์เยอะครับ"</p>
            </div>

            <div class="review-card" onclick="window.open('https://surl.li/pmpvgr', '_blank')">
                <div class="reviewer-profile">
                    <div class="avatar av-7">A</div>
                    <div class="info">
                        <h4 style="margin:0; font-size:1rem; color:#333;">Ananda</h4>
                        <span style="color:#999; font-size:0.8rem;">5 วันที่ผ่านมา</span>
                    </div>
                    <img src="https://fonts.gstatic.com/s/i/productlogos/googleg/v6/24px.svg" width="20" height="20" alt="Google" class="google-icon-corner">
                </div>
                <div class="stars" style="color:#fbbc04; margin-bottom:10px;">★★★★★</div>
                <p class="review-text">"เอา iMac มาอัปเกรด SSD เครื่องเร็วขึ้นมากกกก จากที่อืดๆ ตอนนี้เปิดโปรแกรมปุ๊บปั๊บเลย"</p>
            </div>

            <div class="review-card" onclick="window.open('https://surl.li/pmpvgr', '_blank')">
                <div class="reviewer-profile">
                    <div class="avatar av-8">T</div>
                    <div class="info">
                        <h4 style="margin:0; font-size:1rem; color:#333;">Thanawat</h4>
                        <span style="color:#999; font-size:0.8rem;">2 เดือนที่ผ่านมา</span>
                    </div>
                    <img src="https://fonts.gstatic.com/s/i/productlogos/googleg/v6/24px.svg" width="20" height="20" alt="Google" class="google-icon-corner">
                </div>
                <div class="stars" style="color:#fbbc04; margin-bottom:10px;">★★★★★</div>
                <p class="review-text">"ช่างมีความรู้จริง อธิบายอาการเสียละเอียด ไม่หลอกฟันราคา ใครหาที่ซ่อมแมคในเชียงใหม่ แนะนำที่นี่ครับ"</p>
            </div>

            <div class="review-card" onclick="window.open('https://surl.li/pmpvgr', '_blank')">
                <div class="reviewer-profile">
                    <div class="avatar av-9">M</div>
                    <div class="info">
                        <h4 style="margin:0; font-size:1rem; color:#333;">Mayuree</h4>
                        <span style="color:#999; font-size:0.8rem;">1 สัปดาห์ที่ผ่านมา</span>
                    </div>
                    <img src="https://fonts.gstatic.com/s/i/productlogos/googleg/v6/24px.svg" width="20" height="20" alt="Google" class="google-icon-corner">
                </div>
                <div class="stars" style="color:#fbbc04; margin-bottom:10px;">★★★★★</div>
                <p class="review-text">"พี่เจ้าของร้านใจดีค่ะ ให้คำแนะนำเรื่องการใช้งานดีมาก นักศึกษามาซ่อมลดราคาให้ด้วย น่ารักมากๆ"</p>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
             <a href="https://surl.li/pmpvgr" target="_blank" style="color: #007bff; text-decoration: underline;">ดูรีวิวทั้งหมดบน Google Maps</a>
        </div>
    </div>
</section>

  <section class="section-team" data-aos="fade-up">
    <h2>ทีมช่างของเรา</h2>
    <div class="team-grid tighter">
      <div class="team-member">
        <img src="assets/img/tech1.webp" alt="ช่างแจ็ค">
        <h3>ช่างแจ็ค</h3>
        <p>ผู้เชี่ยวชาญ Mac,MacBook มากกว่า 10 ปี</p>
      </div>
    </div>
    <div class="team-more" id="team-more">
      <div class="team-grid">
        <div class="team-member">
          <div class="img-hover-wrap">
            <img src="assets/img/tech2.webp" loading="lazy" class="default-img" alt="ช่างทัก">
            <img src="assets/img/tech2-hover.webp" loading="lazy" class="hover-img" alt="ช่างทัก">
          </div>
          <h3>ช่างทัก</h3>
          <p>ผู้เชี่ยวชาญระบบ OS และซอฟต์แวร์</p>
        </div>
        <div class="team-member">
          <img src="assets/img/tech3.webp" loading="lazy" alt="แบงค์">
          <h3>แบงค์</h3>
          <p>นักศึกษาฝึกงาน</p>
        </div>
        <div class="team-member">
          <img src="assets/img/tech4.webp" loading="lazy" alt="ไมค์">
          <h3>ไมค์</h3>
          <p>นักศึกษาฝึกงาน</p>
        </div>
        <div class="team-member">
          <img src="assets/img/tech5.webp" loading="lazy" alt="นัฐ">
          <h3>นัฐ</h3>
          <p>นักศึกษาฝึกงาน (Developer)</p>
        </div>
      </div>
    </div>
    <button class="btn show-more-team" id="toggle-team-btn">ดูทีมช่างทั้งหมด</button>
  </section>

  <section class="map-section" id="map" data-aos="fade-up">
    <h2> แผนที่ร้านของเรา</h2>
    <div class="map-container">
      <iframe 
        title="แผนที่ร้าน CMNS FixMac"
        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3777.966030722375!2d98.96507877595568!3d18.751581165039343!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x30da3068631215b3%3A0x10609384752b04c8!2sFix%20Mac%20by%20CMNS!5e0!3m2!1sth!2sth!4v1703000000000!5m2!1sth!2sth"
        width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy"
        referrerpolicy="no-referrer-when-downgrade">
      </iframe>
    </div>
    <div style="text-align: center; margin-top: 15px;">
        <a href="https://www.google.com/maps/place/Fix+Mac+by+CMNS/@18.7515863,98.9650786,17z" target="_blank" class="btn">
            <span class="material-symbols-rounded">map</span> เปิดใน Google Maps
        </a>
    </div>
  </section>

  <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
  <script src="assets/js/main.js"></script>
  <script src="assets/js/swiper-init.js"></script>
  <script src="assets/js/aos-init.js"></script>
  <script src="assets/js/script.js"></script>
  <?php include_once 'includes/floating-buttons.php'; ?>
  <script src="assets/js/floating-buttons.js"></script>
  <?php include_once 'includes/footer.php'; ?>
  <script src="assets/js/lazy-youtube.js"></script>
  <script src="assets/js/preload-images.js"></script>

  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js') 
          .then(registration => {
            console.log('PWA ServiceWorker Registered');
          })
          .catch(error => {
            console.log('PWA ServiceWorker Failed');
          });
      });
    }
  </script>

</body>
</html>