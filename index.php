<?php
include 'includes/db.php';

$page_title = 'ซ่อม MacBook เชียงใหม่ | ร้านซ่อม Apple โดยช่างผู้เชี่ยวชาญ - CMNS FixMac';
$page_css   = [
    '/assets/css/style.css?v=28',
    'https://unpkg.com/aos@2.3.4/dist/aos.css',
];
$page_head_extra = <<<'HTML'
<link rel="preload" as="image" href="/assets/img/macbook.webp" type="image/webp" fetchpriority="high">
<link rel="stylesheet" href="/assets/css/floating-buttons.css?v=2" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="/assets/css/floating-buttons.css?v=2"></noscript>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css"></noscript>
<link rel="alternate" hreflang="th" href="https://cmnsfixmac.com/" />
<link rel="alternate" hreflang="en" href="https://cmnsfixmac.com/en/" />
<link rel="alternate" hreflang="x-default" href="https://cmnsfixmac.com/en/" />
<meta name="description" content="ร้านซ่อม MacBook เชียงใหม่ ซ่อม iPhone, iPad, iMac โดยช่างผู้เชี่ยวชาญ Apple ใช้อะไหล่แท้ รับประกันทุกงานซ่อม มีรีวิวลูกค้า">
<meta name="keywords" content="ซ่อม MacBook, ร้านซ่อม Apple, เปลี่ยนจอ iPhone, ซ่อม Mac เชียงใหม่, FixMac">
<meta name="author" content="CMNS FixMac - ซ่อม MacBook เชียงใหม่">
<meta name="robots" content="index, follow">
<link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png">
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
<script async src="https://www.googletagmanager.com/gtag/js?id=G-3WXK9GWN7C"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','G-3WXK9GWN7C');</script>
<script type="application/ld+json">{"@context":"https://schema.org","@type":"ProfessionalService","@id":"#business","name":"ซ่อม MacBook เชียงใหม่ | CMNS FixMac","image":"https://cmnsfixmac.com/assets/img/apple-logo.png","url":"https://cmnsfixmac.com","telephone":"+66-84-151-1684","priceRange":"฿฿","address":{"@type":"PostalAddress","streetAddress":"482 หมู่ 8 หลังกาดวรุณ ถนนเชียงใหม่-หางดง ต.แม่เหียะ อ.เมือง","addressLocality":"เชียงใหม่","postalCode":"50100","addressCountry":"TH"},"sameAs":["https://www.facebook.com/CmnsShop","https://www.youtube.com/@cmns-fixmac","https://page.line.me/cmns","https://www.tiktok.com/@cmns_fixmac"]}</script>
<script type="application/ld+json">{"@context":"https://schema.org","@type":"FAQPage","mainEntity":[{"@type":"Question","name":"ซ่อม MacBook ใช้เวลานานไหม?","acceptedAnswer":{"@type":"Answer","text":"โดยปกติใช้เวลา 1-3 วัน ขึ้นอยู่กับอาการและอะไหล่"}},{"@type":"Question","name":"สามารถส่งเครื่องมาซ่อมทางขนส่งได้ไหม?","acceptedAnswer":{"@type":"Answer","text":"สามารถส่งทาง Grab, หรือ Kerry ได้"}}]}</script>
HTML;
include_once 'includes/header.php';
?>


  <!-- ═══════════════════════════════════════════════
       HERO
  ════════════════════════════════════════════════ -->
  <section class="hero">
    <div class="hero-orb hero-orb-1" aria-hidden="true"></div>
    <div class="hero-orb hero-orb-2" aria-hidden="true"></div>

    <div class="hero-inner">
      <!-- Text -->
      <div class="hero-text" data-aos="fade-right" data-aos-duration="800">
        <span class="hero-eyebrow">
          <span class="material-symbols-rounded">location_on</span>
          เชียงใหม่ — Apple Specialist
        </span>
        <h1>ซ่อม MacBook<br><span class="hero-h1-accent">โดยช่างผู้เชี่ยวชาญ</span></h1>
        <p>อะไหล่แท้ บริการมาตรฐาน ประเมินฟรีก่อนตัดสินใจซ่อม<br>ทุกผลิตภัณฑ์ Apple ที่เชียงใหม่</p>
        <div class="hero-cta">
          <a href="tel:0841511684" class="btn btn-accent">
            <span class="material-symbols-rounded">call</span> โทรปรึกษาฟรี
          </a>
          <a href="#work" class="btn btn-ghost">
            ดูผลงานซ่อม
            <span class="material-symbols-rounded">arrow_forward</span>
          </a>
        </div>
        <div class="hero-trust">
          <div class="trust-badge">
            <span class="material-symbols-rounded">star</span>
            <span>4.9 Google Reviews</span>
          </div>
          <div class="trust-badge">
            <span class="material-symbols-rounded">verified</span>
            <span>อะไหล่แท้ รับประกัน</span>
          </div>
          <div class="trust-badge">
            <span class="material-symbols-rounded">bolt</span>
            <span>ซ่อมเร็ว 1–3 วัน</span>
          </div>
        </div>
      </div>

      <!-- Device visual -->
      <div class="hero-visual" data-aos="fade-left" data-aos-duration="800" data-aos-delay="150">
        <div class="hero-device-wrap">
          <picture style="display:contents">
            <source srcset="/assets/img/macbook.webp" type="image/webp">
            <img src="/assets/img/macbook.png" class="hero-device" alt="ซ่อม MacBook เชียงใหม่ CMNS FixMac"
                 width="800" height="800" fetchpriority="high" decoding="async">
          </picture>

          <!-- Repair job card -->
          <div class="hero-badge-float hero-job-card hero-badge-1">
            <div class="job-card-header">
              <span class="material-symbols-rounded">build_circle</span>
              <span>งานซ่อมวันนี้</span>
              <span class="job-live-dot"></span>
            </div>
            <div class="job-entry">
              <span class="job-dot job-dot--done"></span>
              <span class="job-name">MacBook Pro 14" M3</span>
              <span class="job-status-label job-label--done">เสร็จแล้ว</span>
            </div>
            <div class="job-entry">
              <span class="job-dot job-dot--progress"></span>
              <span class="job-name">iPhone 15 Pro Max</span>
              <span class="job-status-label job-label--progress">กำลังซ่อม</span>
            </div>
            <div class="job-entry">
              <span class="job-dot job-dot--wait"></span>
              <span class="job-name">iMac 24" M1</span>
              <span class="job-status-label job-label--wait">รอชิ้นส่วน</span>
            </div>
          </div>

          <div class="hero-badge-float hero-badge-2">
            <span class="material-symbols-rounded">schedule</span>
            <div>
              <strong>ใช้เวลา 2 ชั่วโมง</strong>
              <span>เปลี่ยนแบตเตอรี่</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="hero-scroll" aria-hidden="true">
      <span class="material-symbols-rounded">keyboard_arrow_down</span>
    </div>
  </section>


  <!-- ═══════════════════════════════════════════════
       TICKER
  ════════════════════════════════════════════════ -->
  <div class="ticker-wrap" aria-hidden="true">
    <div class="ticker-track">
      <span class="ticker-item"><span class="ticker-dot ticker-dot--done">✓</span>MacBook Pro 14" M3 — เปลี่ยนแบตเตอรี่</span>
      <span class="ticker-sep">·</span>
      <span class="ticker-item"><span class="ticker-dot ticker-dot--progress">●</span>iPhone 15 Pro Max — กำลังเปลี่ยนจอ OLED</span>
      <span class="ticker-sep">·</span>
      <span class="ticker-item"><span class="ticker-dot ticker-dot--done">✓</span>iMac 24" M1 — อัปเกรด SSD 2TB เสร็จแล้ว</span>
      <span class="ticker-sep">·</span>
      <span class="ticker-item"><span class="ticker-dot ticker-dot--progress">●</span>MacBook Air M2 — ตรวจบอร์ดไหม้</span>
      <span class="ticker-sep">·</span>
      <span class="ticker-item"><span class="ticker-dot ticker-dot--done">✓</span>AirPods Pro 2 — เปลี่ยนแบตเสร็จแล้ว</span>
      <span class="ticker-sep">·</span>
      <span class="ticker-item"><span class="ticker-dot ticker-dot--wait">◐</span>Apple Watch S9 — รอชิ้นส่วนจอ</span>
      <span class="ticker-sep">·</span>
      <span class="ticker-item"><span class="ticker-dot ticker-dot--done">✓</span>iPad Pro 12.9" — เปลี่ยน connector เสร็จ</span>
      <span class="ticker-sep">·</span>
      <span class="ticker-item"><span class="ticker-dot ticker-dot--progress">●</span>MacBook Pro 16" Intel — GPU fault กำลังตรวจ</span>
      <span class="ticker-sep">·</span>
      <!-- duplicate for seamless loop -->
      <span class="ticker-item"><span class="ticker-dot ticker-dot--done">✓</span>MacBook Pro 14" M3 — เปลี่ยนแบตเตอรี่</span>
      <span class="ticker-sep">·</span>
      <span class="ticker-item"><span class="ticker-dot ticker-dot--progress">●</span>iPhone 15 Pro Max — กำลังเปลี่ยนจอ OLED</span>
      <span class="ticker-sep">·</span>
      <span class="ticker-item"><span class="ticker-dot ticker-dot--done">✓</span>iMac 24" M1 — อัปเกรด SSD 2TB เสร็จแล้ว</span>
      <span class="ticker-sep">·</span>
      <span class="ticker-item"><span class="ticker-dot ticker-dot--progress">●</span>MacBook Air M2 — ตรวจบอร์ดไหม้</span>
      <span class="ticker-sep">·</span>
      <span class="ticker-item"><span class="ticker-dot ticker-dot--done">✓</span>AirPods Pro 2 — เปลี่ยนแบตเสร็จแล้ว</span>
      <span class="ticker-sep">·</span>
      <span class="ticker-item"><span class="ticker-dot ticker-dot--wait">◐</span>Apple Watch S9 — รอชิ้นส่วนจอ</span>
      <span class="ticker-sep">·</span>
      <span class="ticker-item"><span class="ticker-dot ticker-dot--done">✓</span>iPad Pro 12.9" — เปลี่ยน connector เสร็จ</span>
      <span class="ticker-sep">·</span>
      <span class="ticker-item"><span class="ticker-dot ticker-dot--progress">●</span>MacBook Pro 16" Intel — GPU fault กำลังตรวจ</span>
      <span class="ticker-sep">·</span>
    </div>
  </div>


  <!-- ═══════════════════════════════════════════════
       STATS BAR
  ════════════════════════════════════════════════ -->
  <section class="stats-bar" data-aos="fade-up">
    <div class="stats-inner">
      <div class="stat-box">
        <span class="stat-box-num">3,000+</span>
        <span class="stat-box-label">งานซ่อมสำเร็จ</span>
      </div>
      <div class="stat-box">
        <span class="stat-box-num">4.9★</span>
        <span class="stat-box-label">Google Reviews</span>
      </div>
      <div class="stat-box">
        <span class="stat-box-num">18+ ปี</span>
        <span class="stat-box-label">ประสบการณ์ซ่อม</span>
      </div>
      <div class="stat-box">
        <span class="stat-box-num">ฟรี</span>
        <span class="stat-box-label">ประเมินราคา</span>
      </div>
    </div>
  </section>


  <!-- ═══════════════════════════════════════════════
       SERVICES
  ════════════════════════════════════════════════ -->
  <section class="service-highlight" id="services">
    <div class="section-inner">
      <span class="section-label">Services</span>
      <h2>บริการของเรา</h2>
      <p class="section-desc">ซ่อม อัพเกรด อะไหล่แท้ ทุกผลิตภัณฑ์ Apple โดยช่างผู้เชี่ยวชาญ</p>

      <div class="services-bento">

        <!-- MacBook: featured 2-col -->
        <a href="/services/macbook.php" class="svc-card svc-card--featured" data-aos="fade-up" data-aos-delay="0">
          <span class="svc-badge">ยอดนิยม</span>
          <div class="svc-img">
            <span class="material-symbols-rounded">laptop_mac</span>
          </div>
          <div class="svc-body">
            <h3>ซ่อม MacBook</h3>
            <p>ซ่อมจอ บอร์ด แบต และปัญหาเครื่องดับ เปิดไม่ติด ทุกรุ่น — ทั้ง Intel และ Apple Silicon</p>
            <div class="svc-tags">
              <span class="svc-tag">แบตเตอรี่</span>
              <span class="svc-tag">จอ</span>
              <span class="svc-tag">บอร์ด</span>
              <span class="svc-tag">Apple Silicon</span>
            </div>
            <div class="svc-stat">
              <strong>1,200+</strong>
              <span>เครื่องซ่อมแล้ว</span>
            </div>
            <span class="svc-link">ดูรายละเอียด <span class="material-symbols-rounded">arrow_forward</span></span>
          </div>
        </a>

        <!-- iMac: 1-col -->
        <a href="/services/imac.php" class="svc-card" data-aos="fade-up" data-aos-delay="60">
          <div class="svc-img">
            <span class="material-symbols-rounded">desktop_mac</span>
          </div>
          <div class="svc-body">
            <h3>ซ่อม iMac</h3>
            <p>อัปเกรด SSD เพิ่มแรม ซ่อมจอ ซ่อมบอร์ด iMac ทุกรุ่น</p>
            <div class="svc-tags">
              <span class="svc-tag">SSD</span>
              <span class="svc-tag">RAM</span>
              <span class="svc-tag">จอ</span>
              <span class="svc-tag">บอร์ด</span>
            </div>
            <span class="svc-link">ดูรายละเอียด <span class="material-symbols-rounded">arrow_forward</span></span>
          </div>
        </a>

        <!-- iPhone: 1-col -->
        <a href="/services/iphone.php" class="svc-card" data-aos="fade-up" data-aos-delay="0">
          <div class="svc-img">
            <span class="material-symbols-rounded">smartphone</span>
          </div>
          <div class="svc-body">
            <h3>ซ่อม iPhone / iPad</h3>
            <p>เปลี่ยนจอ แบตเตอรี่ ลำโพง กล้อง ซ่อมบอร์ดขั้นสูง</p>
            <div class="svc-tags">
              <span class="svc-tag">จอ</span>
              <span class="svc-tag">แบต</span>
              <span class="svc-tag">กล้อง</span>
              <span class="svc-tag">บอร์ด</span>
            </div>
            <span class="svc-link">ดูรายละเอียด <span class="material-symbols-rounded">arrow_forward</span></span>
          </div>
        </a>

        <!-- Apple Watch: 1-col -->
        <a href="/services/apple-watch.php" class="svc-card" data-aos="fade-up" data-aos-delay="60">
          <div class="svc-img">
            <span class="material-symbols-rounded">watch</span>
          </div>
          <div class="svc-body">
            <h3>ซ่อม Apple Watch</h3>
            <p>เปลี่ยนจอ แบตเตอรี่ ซ่อมจอลอก จอแตก ทุกซีรีส์</p>
            <div class="svc-tags">
              <span class="svc-tag">จอ</span>
              <span class="svc-tag">แบต</span>
              <span class="svc-tag">ทุกซีรีส์</span>
            </div>
            <span class="svc-link">ดูรายละเอียด <span class="material-symbols-rounded">arrow_forward</span></span>
          </div>
        </a>

        <!-- AirPods: 1-col -->
        <a href="/services/airpods.php" class="svc-card" data-aos="fade-up" data-aos-delay="120">
          <div class="svc-img">
            <span class="material-symbols-rounded">headphones</span>
          </div>
          <div class="svc-body">
            <h3>ซ่อม AirPods</h3>
            <p>แก้ปัญหาแบตเสื่อม ไมค์เสีย ชาร์จไม่เข้า ทุกรุ่น</p>
            <div class="svc-tags">
              <span class="svc-tag">แบต</span>
              <span class="svc-tag">ไมค์</span>
              <span class="svc-tag">ชาร์จ</span>
            </div>
            <span class="svc-link">ดูรายละเอียด <span class="material-symbols-rounded">arrow_forward</span></span>
          </div>
        </a>

        <!-- Software: wide full row -->
        <a href="/services/software.php" class="svc-card svc-card--wide" data-aos="fade-up" data-aos-delay="0">
          <div class="svc-img">
            <span class="material-symbols-rounded">terminal</span>
          </div>
          <div class="svc-body">
            <h3>Software & OS</h3>
            <p>ลง macOS เวอร์ชันใหม่ โปรแกรมทำงาน ซอฟต์แวร์เฉพาะทาง และแก้ไขปัญหาระบบทุกประเภท</p>
            <div class="svc-tags">
              <span class="svc-tag">macOS</span>
              <span class="svc-tag">โปรแกรม</span>
              <span class="svc-tag">ระบบ</span>
            </div>
            <span class="svc-link">ดูรายละเอียด <span class="material-symbols-rounded">arrow_forward</span></span>
          </div>
        </a>

      </div>
    </div>
  </section>


  <!-- ═══════════════════════════════════════════════
       PORTFOLIO / WORKS
  ════════════════════════════════════════════════ -->
  <section class="section-work" id="work" data-aos="fade-up">
    <div class="section-inner">
      <span class="section-label">Portfolio</span>
      <h2>ผลงานล่าสุด</h2>
      <p class="section-desc">ตัวอย่างงานซ่อมจริงจากทีมช่างของเรา</p>

      <div class="work-meta-bar">
        <span><span class="material-symbols-rounded">check_circle</span> งานซ่อมสำเร็จ 1,200+ ชิ้น</span>
        <span class="work-meta-dot"></span>
        <span><span class="material-symbols-rounded">schedule</span> ส่งงานภายใน 24 ชม.</span>
        <span class="work-meta-dot"></span>
        <span><span class="material-symbols-rounded">star</span> คะแนน 4.9 / 5</span>
      </div>

      <div class="work-grid">
        <?php
        $stmt = $pdo->query("SELECT * FROM repairs WHERE status = 'published' ORDER BY created_at DESC LIMIT 6");
        $wi = 0;
        while ($row = $stmt->fetch()) {
          $raw = $row['image'] ?? '';
          if ($raw === '') {
            $img_src = '';
          } elseif (str_starts_with($raw, '/')) {
            $img_src = $raw;
          } else {
            $img_src = '/' . $raw;
          }
          $title   = htmlspecialchars($row['title']);
          $model   = htmlspecialchars($row['model'] ?? '');
          $is_feat = $wi === 0;
          $cls     = $is_feat ? 'work-card work-card--featured' : 'work-card';

          echo '<div class="' . $cls . '">';
          echo '<div class="work-card-img-wrap">';
          if ($img_src) {
            echo '<img src="' . htmlspecialchars($img_src) . '" alt="' . $title . '" loading="lazy"
                      onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'">';
          }
          echo '<div class="work-card-placeholder" style="display:' . ($img_src ? 'none' : 'flex') . '">
                  <span class="material-symbols-rounded">build_circle</span>
                </div>';
          echo '<span class="work-badge-done"><span class="material-symbols-rounded">check_circle</span> ซ่อมแล้ว</span>';
          if ($is_feat) {
            echo '<div class="work-card-overlay">';
            echo '<h3>' . $title . '</h3>';
            if ($model) echo '<span class="work-chip">' . $model . '</span>';
            echo '</div>';
          }
          echo '</div>';
          if (!$is_feat) {
            echo '<div class="work-card-info">';
            echo '<h3>' . $title . '</h3>';
            if ($model) echo '<span class="work-chip">' . $model . '</span>';
            echo '</div>';
          }
          echo '</div>';
          $wi++;
        }
        ?>
      </div>

      <div class="work-cta">
        <a href="/works/" class="work-cta-link">
          ดูผลงานทั้งหมด
          <span class="material-symbols-rounded">arrow_forward</span>
        </a>
      </div>
    </div>
  </section>


  <!-- ═══════════════════════════════════════════════
       WHY US
  ════════════════════════════════════════════════ -->
  <section class="section-reasons" data-aos="fade-up">
    <div class="section-inner">
      <span class="section-label">Why Us</span>
      <h2>ทำไมต้องเลือก CMNS</h2>
      <p class="section-desc">มากกว่าแค่การซ่อม — คือความมั่นใจที่คุณได้คืนกลับมา</p>

      <div class="reasons-list">

        <div class="reason-h">
          <div class="reason-h-meta">
            <span class="reason-num">01</span>
            <div class="reason-icon-wrap">
              <span class="material-symbols-rounded reason-icon">support_agent</span>
            </div>
          </div>
          <div class="reason-h-content">
            <h3>วิเคราะห์อาการและประเมินราคาฟรี — ไม่มีค่าตรวจ</h3>
            <p>CMNS FixMac ให้บริการตรวจวิเคราะห์อาการเสียของ MacBook, iPhone, iPad, iMac และ Apple ทุกรุ่นฟรี ไม่มีค่าตรวจ ไม่มีค่าใช้จ่ายแอบแฝง ทีมช่างจะแจ้งสาเหตุและราคาค่าซ่อมก่อนดำเนินการทุกครั้ง ลูกค้าอนุมัติก่อนถึงจะเริ่มงาน</p>
            <div class="reason-chips">
              <span>ฟรีค่าตรวจ</span>
              <span>แจ้งราคาก่อนซ่อม</span>
              <span>ไม่มีค่าใช้จ่ายแอบแฝง</span>
            </div>
          </div>
        </div>

        <div class="reason-h">
          <div class="reason-h-meta">
            <span class="reason-num">02</span>
            <div class="reason-icon-wrap">
              <span class="material-symbols-rounded reason-icon">build_circle</span>
            </div>
          </div>
          <div class="reason-h-content">
            <h3>อะไหล่คุณภาพสูง รับประกันงานซ่อมทุกชิ้น</h3>
            <p>ใช้อะไหล่แท้และอะไหล่คุณภาพเทียบเท่า OEM สำหรับการซ่อม MacBook Air, MacBook Pro, iMac, iPhone และ iPad ทุกรุ่น พร้อมรับประกันอะไหล่และฝีมือการซ่อม ครอบคลุมตั้งแต่การเปลี่ยนจอ เปลี่ยนแบต ซ่อมบอร์ด ไปจนถึงอัปเกรด SSD และ RAM</p>
            <div class="reason-chips">
              <span>อะไหล่แท้ / OEM</span>
              <span>รับประกันทุกงาน</span>
              <span>เครื่องมือมาตรฐาน</span>
            </div>
          </div>
        </div>

        <div class="reason-h">
          <div class="reason-h-meta">
            <span class="reason-num">03</span>
            <div class="reason-icon-wrap">
              <span class="material-symbols-rounded reason-icon">engineering</span>
            </div>
          </div>
          <div class="reason-h-content">
            <h3>ทีมช่างเชี่ยวชาญ Apple เชียงใหม่ ประสบการณ์กว่า 10 ปี</h3>
            <p>ทีมช่างของ CMNS FixMac มีความเชี่ยวชาญด้านการซ่อม Apple โดยเฉพาะ ครอบคลุม MacBook ทุกรุ่นทั้ง Intel และ Apple Silicon (M1 M2 M3 M4), iPhone ทุกซีรีส์, iPad, iMac, Apple Watch และ AirPods ทั้งงานซ่อมฮาร์ดแวร์ระดับบอร์ด (Board-level repair) และแก้ไขปัญหาซอฟต์แวร์ทุกประเภท</p>
            <div class="reason-chips">
              <span>Apple Silicon M1–M4</span>
              <span>Board-level repair</span>
              <span>ประสบการณ์ 10+ ปี</span>
            </div>
          </div>
        </div>

        <div class="reason-h">
          <div class="reason-h-meta">
            <span class="reason-num">04</span>
            <div class="reason-icon-wrap">
              <span class="material-symbols-rounded reason-icon">location_on</span>
            </div>
          </div>
          <div class="reason-h-content">
            <h3>ร้านซ่อม Apple ใจกลางเชียงใหม่ รับ-ส่งได้ทั่วประเทศ</h3>
            <p>ร้าน CMNS FixMac ตั้งอยู่ใจกลางเชียงใหม่ เดินทางง่าย มีที่จอดรถ สะดวกสำหรับลูกค้าในเชียงใหม่และจังหวัดใกล้เคียง นอกจากนี้ยังรองรับการส่งซ่อมทางไปรษณีย์ทั่วประเทศ พร้อมติดตามสถานะซ่อมออนไลน์และแจ้งเตือนผ่าน LINE OA ได้ตลอด 24 ชั่วโมง</p>
            <div class="reason-chips">
              <span>ใจกลางเชียงใหม่</span>
              <span>ส่งซ่อมทั่วประเทศ</span>
              <span>ติดตามสถานะออนไลน์</span>
            </div>
          </div>
        </div>

      </div>
    </div>
  </section>


  <!-- ═══════════════════════════════════════════════
       DIAGNOSE / SELF-CHECK TOOLS
  ════════════════════════════════════════════════ -->
  <section class="section-diagnose" id="tools" data-aos="fade-up">
    <div class="section-inner">
      <span class="section-label">Self-Check</span>
      <h2>ทดสอบอุปกรณ์ก่อนมาร้าน</h2>
      <p class="section-desc">เช็คอาการเบื้องต้นด้วยตัวเองก่อน — ฟรี ไม่ต้องโหลด App</p>

      <div class="diagnose-grid">
        <a href="/tester/keyboard-tester/" target="_blank" class="diagnose-card desktop-only">
          <div class="icon-wrap"><span class="material-symbols-rounded">keyboard</span></div>
          <span>ทดสอบคีย์บอร์ด</span>
        </a>
        <a href="/tester/sounds-tester/" target="_blank" class="diagnose-card">
          <div class="icon-wrap"><span class="material-symbols-rounded">volume_up</span></div>
          <span>ทดสอบลำโพง</span>
        </a>
        <a href="/tester/monitor-tester/" target="_blank" class="diagnose-card">
          <div class="icon-wrap"><span class="material-symbols-rounded">monitor</span></div>
          <span>ตรวจจอเสีย</span>
        </a>
        <a href="/tester/camera-tester/" target="_blank" class="diagnose-card">
          <div class="icon-wrap"><span class="material-symbols-rounded">photo_camera</span></div>
          <span>ตรวจกล้อง</span>
        </a>
        <a href="/tester/microphone-tester/" target="_blank" class="diagnose-card">
          <div class="icon-wrap"><span class="material-symbols-rounded">mic</span></div>
          <span>ตรวจไมค์</span>
        </a>
        <a href="/tester/touchscreen-tester/" target="_blank" class="diagnose-card mobile-only">
          <div class="icon-wrap"><span class="material-symbols-rounded">touch_app</span></div>
          <span>ตรวจทัชสกรีน</span>
        </a>
      </div>

      <div class="diagnose-cta">
        <a href="/tester/" class="btn btn-accent">
          <span class="material-symbols-rounded">smart_toy</span>
          ดูเครื่องมือทดสอบทั้งหมด
          <span class="material-symbols-rounded">arrow_forward</span>
        </a>
      </div>
    </div>
  </section>


  <!-- ═══════════════════════════════════════════════
       STORE ATMOSPHERE
  ════════════════════════════════════════════════ -->
  <section class="section-atmosphere" data-aos="fade-up">
    <div class="section-inner atm-header">
      <span class="section-label">Our Store</span>
      <h2>บรรยากาศร้านของเรา</h2>
      <p class="section-desc">ร้านซ่อมมาตรฐาน พร้อมอุปกรณ์และเครื่องมือที่ทันสมัย</p>
    </div>
    <div class="swiper atmosphereSwiper">
      <div class="swiper-wrapper">
        <div class="swiper-slide"><img src="/assets/img/store1.webp" loading="lazy" alt="หน้าร้านซ่อม MacBook เชียงใหม่ CMNS FixMac"></div>
        <div class="swiper-slide"><img src="/assets/img/store2.webp" loading="lazy" alt="เคาน์เตอร์ต้อนรับร้าน CMNS FixMac เชียงใหม่"></div>
        <div class="swiper-slide"><img src="/assets/img/store3.webp" loading="lazy" alt="บรรยากาศภายในร้านซ่อม MacBook เชียงใหม่"></div>
        <div class="swiper-slide"><img src="/assets/img/store4.webp" loading="lazy" alt="ซ่อม MacBook เชียงใหม่ CMNS"></div>
        <div class="swiper-slide"><img src="/assets/img/store5.webp" loading="lazy" alt="อุปกรณ์ซ่อม Apple มาตรฐานในร้าน CMNS FixMac"></div>
        <div class="swiper-slide"><img src="/assets/img/store6.webp" loading="lazy" alt="ซ่อม iPhone iPad MacBook ที่ร้าน CMNS Fixmac"></div>
        <div class="swiper-slide"><img src="/assets/img/store7.webp" loading="lazy" alt="ลูกค้ามาใช้บริการร้านซ่อม MacBook เชียงใหม่"></div>
        <div class="swiper-slide"><img src="/assets/img/store8.webp" loading="lazy" alt="บรรยากาศร้านซ่อม Apple เชียงใหม่ FixMac"></div>
      </div>
      <div class="swiper-pagination"></div>
      <div class="atm-counter"><span class="atm-current">1</span> / 8</div>
    </div>
  </section>


  <!-- ═══════════════════════════════════════════════
       GOOGLE REVIEWS
  ════════════════════════════════════════════════ -->
  <section class="section-review">
    <div class="section-inner">
      <span class="section-label">Reviews</span>
      <h2>เสียงจากลูกค้าจริง</h2>
      <p class="section-desc">รีวิวจาก Google Maps — ตรงไปตรงมา ไม่มีตกแต่ง</p>
      <div class="elfsight-app-257bd58d-8d43-4106-8bc8-09588ce23452" data-elfsight-lazy></div>
    </div>
  </section>


  <!-- ═══════════════════════════════════════════════
       TEAM
  ════════════════════════════════════════════════ -->
  <section class="section-team" data-aos="fade-up">
    <div class="section-inner">
      <span class="section-label">Our Team</span>
      <h2>ทีมช่างของเรา</h2>
      <p class="section-desc">ช่างผู้เชี่ยวชาญ Apple โดยตรง ประสบการณ์จริงกว่า 10 ปี</p>

      <div class="team-cards">

        <div class="team-card">
          <div class="team-card-photo">
            <img src="/assets/img/tech1.webp" alt="ช่างแจ็ค หัวหน้าช่างซ่อม MacBook เชียงใหม่ CMNS FixMac">
            <span class="team-exp-badge">18+ ปี</span>
          </div>
          <div class="team-card-info">
            <h3>ช่างแจ็ค</h3>
            <p class="team-role">หัวหน้าช่าง — ผู้เชี่ยวชาญ Mac & Hardware</p>
            <p class="team-bio">เชี่ยวชาญซ่อม MacBook ทุกรุ่นทั้ง Intel และ Apple Silicon (M1–M4) งาน Board-level repair บัดกรีบอร์ด เปลี่ยนจอ เปลี่ยนแบต และอัปเกรด SSD, RAM สำหรับ MacBook Air, MacBook Pro และ iMac</p>
            <div class="team-skills">
              <span>MacBook Air / Pro</span>
              <span>Apple Silicon M1–M4</span>
              <span>Board-level Repair</span>
              <span>iMac</span>
            </div>
          </div>
        </div>

        <div class="team-card">
          <div class="team-card-photo img-hover-wrap">
            <img src="/assets/img/tech2.webp" loading="lazy" alt="ช่างทัก ผู้เชี่ยวชาญ macOS และ iOS เชียงใหม่" class="default-img">
            <img src="/assets/img/tech2-hover.webp" loading="lazy" alt="ช่างทัก กำลังซ่อม Apple ที่ร้าน CMNS FixMac" class="hover-img">
            <span class="team-exp-badge">10+ ปี</span>
          </div>
          <div class="team-card-info">
            <h3>ช่างทัก</h3>
            <p class="team-role">ผู้เชี่ยวชาญ Software, iOS & Mobile</p>
            <p class="team-bio">เชี่ยวชาญระบบ macOS และ iOS ครบวงจร ซ่อม iPhone ทุกซีรีส์ iPad, Apple Watch แก้ไขปัญหาซอฟต์แวร์ทุกประเภท</p>
            <div class="team-skills">
              <span>iPhone / iPad</span>
              <span>Apple Watch</span>
              <span>macOS / iOS</span>
              <span>Software Repair</span>
            </div>
          </div>
        </div>

      </div>
    </div>
  </section>


  <!-- ═══════════════════════════════════════════════
       CONTACT
  ════════════════════════════════════════════════ -->
  <section class="section-contact" id="contact" data-aos="fade-up">
    <div class="section-inner">
      <span class="section-label">ติดต่อเรา</span>
      <h2>พร้อมช่วยทุกปัญหา Apple</h2>
      <p class="section-desc">ปรึกษาฟรี ไม่มีค่าใช้จ่าย — ทักมาได้เลย</p>

      <div class="contact-channels">

        <div class="contact-col">
          <p class="contact-col-label">โทรศัพท์</p>
          <a href="tel:0841511684" class="contact-phone-hero">084-151-1684</a>
          <p class="contact-col-sub">โทรได้เลย จ–ส 09:00–18:00</p>
        </div>

        <div class="contact-col-divider"></div>

        <div class="contact-col">
          <p class="contact-col-label">แชท / โซเชียล</p>
          <a href="https://page.line.me/cmns" target="_blank" rel="noopener" class="contact-social-row">
            <span class="contact-social-icon line-ic">
              <span class="material-symbols-rounded">chat_bubble</span>
            </span>
            <span class="contact-social-info">
              <strong>LINE OA</strong>
              <span>@cmns</span>
            </span>
            <span class="material-symbols-rounded contact-arrow">arrow_forward</span>
          </a>
          <a href="https://www.facebook.com/CmnsShop" target="_blank" rel="noopener" class="contact-social-row">
            <span class="contact-social-icon fb-ic">
              <span class="material-symbols-rounded">thumb_up</span>
            </span>
            <span class="contact-social-info">
              <strong>Facebook</strong>
              <span>CmnsShop</span>
            </span>
            <span class="material-symbols-rounded contact-arrow">arrow_forward</span>
          </a>
        </div>

        <div class="contact-col-divider"></div>

        <div class="contact-col">
          <p class="contact-col-label">ที่ตั้งร้าน</p>
          <p class="contact-address">482 หมู่ 8 หลังกาดวรุณ<br>ถ.เชียงใหม่-หางดง เชียงใหม่</p>
          <div class="contact-hours">
            <span class="material-symbols-rounded">schedule</span>
            จันทร์–เสาร์ 09:00–18:00 น.
          </div>
          <a href="https://maps.google.com/maps?q=18.75005733629581,98.96466192390933" target="_blank" rel="noopener" class="contact-map-link">
            <span class="material-symbols-rounded">near_me</span> นำทางมาร้าน
          </a>
        </div>

      </div>
    </div>
  </section>


  <!-- ═══════════════════════════════════════════════
       MAP
  ════════════════════════════════════════════════ -->
  <section class="map-section" id="map" data-aos="fade-up">
    <div class="section-inner">
      <span class="section-label">Location</span>
      <h2>แผนที่ร้าน</h2>
      <p class="section-desc">482 หมู่ 8 หลังกาดวรุณ ถ.เชียงใหม่-หางดง ต.แม่เหียะ เชียงใหม่</p>
      <div class="map-container">
        <iframe
          title="แผนที่ร้าน CMNS FixMac"
          src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2757.0147940546653!2d98.96466192390933!3d18.75005733629581!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x30da3aa79be8e5db%3A0x1a948e6def350e!2z4LiL4LmI4Lit4LihIG1hYyDguYDguIrguLXguKLguIfguYPguKvguKHguYggKEZpeCBtYWMgQ2hpYW5nbWFpKQ!5e0!3m2!1sth!2sth!4v1745215403938!5m2!1sth!2sth"
          width="100%" height="360" style="border:0;" allowfullscreen="" loading="lazy"
          referrerpolicy="no-referrer-when-downgrade">
        </iframe>
      </div>
    </div>
  </section>


  <!-- JS Libraries -->
  <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>

  <!-- Custom JS -->
  <script src="/assets/js/main.js"></script>
  <script src="/assets/js/swiper-init.js"></script>
  <script src="/assets/js/aos-init.js"></script>
  <script src="/assets/js/script.js"></script>

  <!-- Contact form handler -->
  <script>
  function handleContactSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const name    = form.name.value.trim();
    const contact = form.contact.value.trim();
    const device  = form.device.value;
    const issue   = form.issue.value.trim();
    const msg = encodeURIComponent(
      `สวัสดีครับ ต้องการปรึกษาการซ่อม\nชื่อ: ${name}\nติดต่อ: ${contact}\nอุปกรณ์: ${device}\nอาการ: ${issue}`
    );
    window.open(`https://line.me/R/oaMessage/%40cmns/?${msg}`, '_blank');
    form.classList.add('hidden');
    document.getElementById('contact-success').classList.add('show');
  }
  </script>

  <div class="grain-overlay" aria-hidden="true"></div>

  <?php include_once 'includes/floating-buttons.php'; ?>

  <script>
  /* Elfsight lazy load */
  (function () {
    const target = document.querySelector('[data-elfsight-lazy]');
    if (!target) return;
    const obs = new IntersectionObserver((entries, o) => {
      if (!entries[0].isIntersecting) return;
      const s = document.createElement('script');
      s.src = 'https://static.elfsight.com/platform/platform.js';
      s.async = true;
      document.head.appendChild(s);
      o.disconnect();
    }, { rootMargin: '200px' });
    obs.observe(target);
  })();
  </script>
  <script src="/assets/js/floating-buttons.js"></script>

  <?php include_once 'includes/footer.php'; ?>

  <script src="/assets/js/preload-images.js"></script>
  <script>
    window.addEventListener('pageshow', function(e) {
      if (e.persisted) window.location.reload();
    });
  </script>

</body>
</html>
