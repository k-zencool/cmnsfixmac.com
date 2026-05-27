<?php
require_once '../includes/db.php';

$page_title       = 'ซ่อม MacBook เชียงใหม่ ทุกรุ่น ทุกอาการ | CMNS FixMac';
$page_description = 'ซ่อม MacBook Air / Pro ทุกรุ่น ทุกอาการ จอแตก แบตเสื่อม น้ำเข้า เปิดไม่ติด ช่างผู้เชี่ยวชาญเชียงใหม่ อะไหล่แท้ ประกัน 90 วัน ประเมินฟรี';
$page_keywords    = 'ซ่อม MacBook เชียงใหม่, เปลี่ยนจอ MacBook, เปลี่ยนแบต MacBook, MacBook น้ำเข้า, ซ่อม MacBook Air, ซ่อม MacBook Pro, ราคาซ่อม MacBook';
$page_css         = ['/assets/css/services/macbook-style.css?v=2', 'https://unpkg.com/aos@2.3.4/dist/aos.css'];

$faq_schema = [
    ['ซ่อม MacBook ที่ CMNS FixMac ราคาเท่าไหร่?',
     'ราคาขึ้นอยู่กับรุ่นและอาการ เช่น เปลี่ยนจอ MacBook Air เริ่มที่ 5,900 บาท เปลี่ยนแบตเริ่มที่ 2,900 บาท ทุกงานประเมินฟรีก่อนตัดสินใจ'],
    ['ใช้เวลาซ่อม MacBook นานแค่ไหน?',
     'งานทั่วไปเช่น เปลี่ยนแบต เปลี่ยนจอ ใช้เวลา 1–3 วัน บางงานทำได้ภายในวันเดียว ขึ้นอยู่กับสต็อกอะไหล่'],
    ['MacBook ที่ซ่อมมีประกันไหม?',
     'มีประกันทุกงานซ่อม 90 วัน ครอบคลุมทั้งอะไหล่และค่าแรง หากมีปัญหาในช่วงประกันนำมาเคลมได้ฟรี'],
    ['MacBook M1 / M2 / M3 ซ่อมได้ไหม?',
     'ซ่อมได้ ทีมช่างมีประสบการณ์ซ่อม MacBook ชิป Apple Silicon ทุกรุ่น ทั้งเปลี่ยนจอ เปลี่ยนแบต และซ่อม Logic Board'],
    ['MacBook โดนน้ำ ยังกู้คืนได้ไหม?',
     'ส่วนใหญ่กู้ได้หากนำมาทันที อย่าเปิดเครื่องต่อ นำมาให้ตรวจสอบก่อน เราล้างบอร์ดและซ่อมชิปที่เสียหาย'],
    ['ส่งเครื่องมาซ่อมทางไปรษณีย์ได้ไหม?',
     'ส่งได้ผ่าน Kerry / Grab เราบรรจุคืนอย่างดีและแจ้งสถานะทาง LINE ตลอดการซ่อม'],
];

ob_start(); ?>
<meta name="description"   content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8') ?>">
<meta name="keywords"      content="<?= htmlspecialchars($page_keywords, ENT_QUOTES, 'UTF-8') ?>">
<meta name="robots"        content="index, follow">
<link rel="canonical"      href="https://cmnsfixmac.com/services/macbook.php">
<link rel="shortcut icon"  href="/assets/img/favicon1.png">
<meta property="og:title"        content="<?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:description"  content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:type"         content="website">
<meta property="og:image"        content="https://cmnsfixmac.com/assets/img/macbook-repair-og.jpg">
<meta property="og:image:width"  content="1200">
<meta property="og:image:height" content="630">
<meta property="og:locale"       content="th_TH">
<meta property="og:url"          content="https://cmnsfixmac.com/services/macbook.php">
<meta name="twitter:card"        content="summary_large_image">
<meta name="twitter:title"       content="<?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8') ?>">
<meta name="twitter:image"       content="https://cmnsfixmac.com/assets/img/macbook-repair-og.jpg">
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@graph'   => [
        ['@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'หน้าแรก',   'item' => 'https://cmnsfixmac.com'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'บริการ',    'item' => 'https://cmnsfixmac.com/services/'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => 'ซ่อม MacBook', 'item' => 'https://cmnsfixmac.com/services/macbook.php'],
        ]],
        ['@type' => 'Service',
         'name'  => 'ซ่อม MacBook เชียงใหม่',
         'description' => 'บริการซ่อม MacBook Air / Pro ทุกรุ่น ทุกอาการ โดยช่างผู้เชี่ยวชาญ อะไหล่แท้ ประกัน 90 วัน',
         'provider' => ['@type' => 'LocalBusiness', 'name' => 'CMNS FixMac',
             'telephone' => '+66-84-151-1684', 'url' => 'https://cmnsfixmac.com',
             'address'   => ['@type' => 'PostalAddress', 'streetAddress' => '482 หมู่ 8 หลังกาดวรุณ',
                             'addressLocality' => 'เชียงใหม่', 'postalCode' => '50100', 'addressCountry' => 'TH'],
         ],
         'areaServed' => 'เชียงใหม่',
         'url'        => 'https://cmnsfixmac.com/services/macbook.php',
        ],
        ['@type' => 'FAQPage', 'mainEntity' => array_map(fn($f) => [
            '@type' => 'Question', 'name' => $f[0],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
        ], $faq_schema)],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php
$page_head_extra = ob_get_clean();

$repairs = $pdo->query(
    "SELECT id, title, model, image, views FROM repairs
     WHERE status='published' AND TRIM(LOWER(category))='macbook'
     ORDER BY created_at DESC LIMIT 8"
)->fetchAll();

include_once '../includes/header.php';
?>

<main>

<!-- ═══════════════════════════════════════════════
     HERO
════════════════════════════════════════════════ -->
<section class="sv-hero">
  <div class="sv-hero-orb sv-orb-1" aria-hidden="true"></div>
  <div class="sv-hero-orb sv-orb-2" aria-hidden="true"></div>
  <div class="sv-hero-inner">

    <div class="sv-hero-text" data-aos="fade-right" data-aos-duration="700">
      <span class="sv-eyebrow">
        <span class="material-symbols-rounded">laptop_mac</span>
        CMNS FixMac · เชียงใหม่
      </span>
      <h1 class="sv-h1">ซ่อม MacBook<br><span class="sv-h1-accent">ทุกรุ่น ทุกอาการ</span></h1>
      <p class="sv-hero-sub">อะไหล่แท้ ช่างผู้เชี่ยวชาญ ประกัน 90 วัน<br>ประเมินฟรีก่อนตัดสินใจทุกครั้ง</p>
      <div class="sv-hero-cta">
        <a href="tel:0841511684" class="btn btn-accent">
          <span class="material-symbols-rounded">call</span> โทรปรึกษาฟรี
        </a>
        <a href="#sv-pricing" class="btn btn-ghost">
          ดูราคา <span class="material-symbols-rounded">arrow_downward</span>
        </a>
      </div>
      <div class="sv-trust-pills">
        <span class="sv-pill"><span class="material-symbols-rounded">star</span> 4.9 Google</span>
        <span class="sv-pill"><span class="material-symbols-rounded">verified</span> อะไหล่แท้</span>
        <span class="sv-pill"><span class="material-symbols-rounded">bolt</span> 1–3 วัน</span>
      </div>
    </div>

    <div class="sv-hero-visual" data-aos="fade-left" data-aos-duration="700" data-aos-delay="120">
      <div class="sv-device-wrap">
        <img src="/assets/img/macbook.png" alt="ซ่อม MacBook เชียงใหม่ CMNS FixMac" class="sv-device-img">
        <div class="sv-float-badge sv-fbadge-1">
          <span class="material-symbols-rounded">build_circle</span>
          <div><strong>1,200+</strong><span>งานซ่อม</span></div>
        </div>
        <div class="sv-float-badge sv-fbadge-2">
          <span class="material-symbols-rounded">workspace_premium</span>
          <div><strong>ประกัน 90 วัน</strong><span>ทุกงานซ่อม</span></div>
        </div>
      </div>
    </div>

  </div>
</section>

<!-- ═══════════════════════════════════════════════
     SERVICE NAV
════════════════════════════════════════════════ -->
<div class="sv-nav-outer">
  <nav class="sv-nav" data-aos="fade-up" data-aos-offset="0" aria-label="บริการซ่อม Apple">
    <a href="/services/macbook.php"    class="sv-nav-item active"><span class="material-symbols-rounded">laptop_mac</span><span>MacBook</span></a>
    <a href="/services/imac.php"       class="sv-nav-item"><span class="material-symbols-rounded">desktop_mac</span><span>iMac</span></a>
    <a href="/services/iphone.php"     class="sv-nav-item"><span class="material-symbols-rounded">smartphone</span><span>iPhone</span></a>
    <a href="/services/ipad.php"       class="sv-nav-item"><span class="material-symbols-rounded">tablet_mac</span><span>iPad</span></a>
    <a href="/services/apple-watch.php" class="sv-nav-item"><span class="material-symbols-rounded">watch</span><span>Apple Watch</span></a>
    <a href="/services/airpods.php"    class="sv-nav-item"><span class="material-symbols-rounded">headphones</span><span>AirPods</span></a>
    <a href="/services/software.php"   class="sv-nav-item"><span class="material-symbols-rounded">terminal</span><span>Software</span></a>
  </nav>
</div>

<!-- ═══════════════════════════════════════════════
     SERVICES GRID
════════════════════════════════════════════════ -->
<section class="sv-section sv-services">
  <div class="sv-container">
    <div class="sv-section-head" data-aos="fade-up">
      <span class="section-label">บริการทั้งหมด</span>
      <h2>ซ่อม MacBook ครบทุกประเภท</h2>
      <p class="sv-desc">รับซ่อม MacBook Air / Pro ทุกรุ่น ทั้งชิป Intel, M1, M2, M3, M4 โดยทีมช่างผู้เชี่ยวชาญ พร้อมอะไหล่คุณภาพ</p>
    </div>
    <div class="sv-card-grid">
      <?php foreach ([
          ['display_settings', 'เปลี่ยนจอ MacBook',       'จอแตก จอลาย จอเป็นเส้น จอไม่ติด รับเปลี่ยน Retina / Liquid Retina ทุกขนาด', 'เริ่ม 5,900 บาท'],
          ['battery_alert',    'เปลี่ยนแบตเตอรี่',        'แบตเสื่อม แบตบวม ชาร์จไม่เข้า รับเปลี่ยนแบตทุกรุ่น พร้อมรับประกัน',        'เริ่ม 2,900 บาท'],
          ['memory',           'ซ่อม Logic Board',         'เปิดไม่ติด เครื่องดับ ช็อต น้ำเข้า ซ่อมระดับชิป คืนชีวิตเครื่องได้',        'ประเมินหน้างาน'],
          ['water_drop',       'MacBook โดนน้ำ',           'น้ำหก ของเหลวเข้า ทำความสะอาดบอร์ดและซ่อมชิปที่เสียหาย',                    'ประเมินหน้างาน'],
          ['storage',          'อัปเกรด SSD / RAM',        'เพิ่มพื้นที่ เพิ่มความเร็ว รองรับ MacBook Intel รุ่นที่ถอดเปลี่ยนได้',       'เริ่ม 2,500 บาท'],
          ['terminal',         'ลง macOS / ซ่อมซอฟต์แวร์', 'ลง OS ใหม่ แก้เครื่องบูทช้า ลงโปรแกรม Office Adobe AutoCAD',               'เริ่ม 500 บาท'],
      ] as $i => [$icon, $title, $desc, $price]): ?>
      <div class="sv-card" data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 80 ?>">
        <div class="sv-card-icon"><span class="material-symbols-rounded"><?= $icon ?></span></div>
        <h3><?= $title ?></h3>
        <p><?= $desc ?></p>
        <span class="sv-card-price"><?= $price ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════════
     STATS
════════════════════════════════════════════════ -->
<section class="sv-stats">
  <div class="sv-container sv-stats-inner">
    <?php foreach ([
        ['1,200+', 'งานซ่อม MacBook',    'build'],
        ['10+',    'ปีประสบการณ์',        'engineering'],
        ['4.9★',   'Google Reviews',      'star'],
        ['90 วัน', 'รับประกันทุกงาน',    'workspace_premium'],
    ] as $i => [$num, $label, $icon]): ?>
    <div class="sv-stat" data-aos="fade-up" data-aos-delay="<?= $i * 80 ?>">
      <span class="material-symbols-rounded"><?= $icon ?></span>
      <strong><?= $num ?></strong>
      <span><?= $label ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ═══════════════════════════════════════════════
     PRICING
════════════════════════════════════════════════ -->
<section class="sv-section sv-pricing" id="sv-pricing">
  <div class="sv-container">
    <div class="sv-section-head" data-aos="fade-up">
      <span class="section-label">ราคาซ่อม</span>
      <h2>ราคาซ่อม MacBook โปร่งใส ไม่มีบวกเพิ่ม</h2>
      <p class="sv-desc">ราคาโดยประมาณ ขึ้นอยู่กับรุ่นและสภาพเครื่อง <strong>ประเมินฟรีทุกครั้งก่อนเริ่มงาน</strong></p>
    </div>
    <div class="sv-tab-row" data-aos="fade-up">
      <button class="sv-tab-btn active" data-tab="tp-screen">เปลี่ยนจอ</button>
      <button class="sv-tab-btn"        data-tab="tp-battery">เปลี่ยนแบต</button>
      <button class="sv-tab-btn"        data-tab="tp-board">ซ่อมบอร์ด / น้ำ</button>
      <button class="sv-tab-btn"        data-tab="tp-upgrade">อัปเกรด / OS</button>
    </div>

    <div class="sv-tab-pane active" id="tp-screen" data-aos="fade-up">
      <table class="sv-table">
        <thead><tr><th>รุ่น MacBook</th><th>ราคาโดยประมาณ</th><th>รับประกัน</th></tr></thead>
        <tbody>
          <tr><td>MacBook Air 13" Intel (2017–2020)</td><td>5,900 – 7,900 บาท</td><td>90 วัน</td></tr>
          <tr><td>MacBook Air M1 / M2 13"</td><td>7,900 – 12,900 บาท</td><td>90 วัน</td></tr>
          <tr><td>MacBook Air M2 / M3 15"</td><td>9,900 – 15,900 บาท</td><td>90 วัน</td></tr>
          <tr><td>MacBook Pro 13" Intel (2015–2020)</td><td>6,900 – 9,900 บาท</td><td>90 วัน</td></tr>
          <tr><td>MacBook Pro 14" / 16" (M1–M4)</td><td>9,900 – 19,900 บาท</td><td>90 วัน</td></tr>
          <tr><td>MacBook Pro 15" / 16" Intel (2015–2019)</td><td>8,900 – 13,900 บาท</td><td>90 วัน</td></tr>
        </tbody>
      </table>
    </div>

    <div class="sv-tab-pane" id="tp-battery" data-aos="fade-up">
      <table class="sv-table">
        <thead><tr><th>รุ่น MacBook</th><th>ราคาโดยประมาณ</th><th>รับประกัน</th></tr></thead>
        <tbody>
          <tr><td>MacBook Air 11" / 13" Intel</td><td>2,900 – 3,900 บาท</td><td>90 วัน</td></tr>
          <tr><td>MacBook Air M1 / M2 / M3</td><td>3,500 – 4,500 บาท</td><td>90 วัน</td></tr>
          <tr><td>MacBook Pro 13" Intel (2015–2020)</td><td>3,200 – 4,500 บาท</td><td>90 วัน</td></tr>
          <tr><td>MacBook Pro 14" / 16" (M1–M4)</td><td>3,900 – 5,900 บาท</td><td>90 วัน</td></tr>
          <tr><td>MacBook Pro 15" / 16" Intel</td><td>3,500 – 5,500 บาท</td><td>90 วัน</td></tr>
        </tbody>
      </table>
    </div>

    <div class="sv-tab-pane" id="tp-board" data-aos="fade-up">
      <table class="sv-table">
        <thead><tr><th>บริการ</th><th>ราคาโดยประมาณ</th><th>หมายเหตุ</th></tr></thead>
        <tbody>
          <tr><td>ล้างเครื่องโดนน้ำ (ทำความสะอาดบอร์ด)</td><td>1,500 – 2,500 บาท</td><td>ขึ้นกับสภาพ</td></tr>
          <tr><td>ซ่อม Logic Board ระดับชิป</td><td>3,500 – 12,000 บาท</td><td>ประเมินหน้างาน</td></tr>
          <tr><td>MacBook เปิดไม่ติด (วินิจฉัย + ซ่อม)</td><td>2,500 – 8,000 บาท</td><td>ประเมินหน้างาน</td></tr>
          <tr><td>เปลี่ยนพอร์ต MagSafe / USB-C</td><td>2,500 – 4,500 บาท</td><td>90 วัน</td></tr>
          <tr><td>เปลี่ยนคีย์บอร์ด MacBook Pro</td><td>3,500 – 6,900 บาท</td><td>90 วัน</td></tr>
        </tbody>
      </table>
    </div>

    <div class="sv-tab-pane" id="tp-upgrade" data-aos="fade-up">
      <table class="sv-table">
        <thead><tr><th>บริการ</th><th>ราคาโดยประมาณ</th><th>หมายเหตุ</th></tr></thead>
        <tbody>
          <tr><td>เปลี่ยน SSD (Intel รุ่นที่ถอดได้)</td><td>2,500 – 5,900 บาท</td><td>รวมอะไหล่</td></tr>
          <tr><td>เพิ่ม RAM (Intel รุ่นที่ถอดได้)</td><td>2,000 – 4,500 บาท</td><td>รวมอะไหล่</td></tr>
          <tr><td>ลง macOS ใหม่</td><td>500 – 1,200 บาท</td><td>—</td></tr>
          <tr><td>ลงโปรแกรม + ตั้งค่า (Office, Adobe ฯลฯ)</td><td>500 – 1,500 บาท</td><td>—</td></tr>
          <tr><td>ย้ายข้อมูล Mac → Mac ใหม่</td><td>800 – 1,500 บาท</td><td>—</td></tr>
        </tbody>
      </table>
    </div>

    <p class="sv-price-note" data-aos="fade-up">
      <span class="material-symbols-rounded">info</span>
      ราคาเป็นโดยประมาณ อาจเปลี่ยนแปลงตามรุ่นและอาการจริง
      <a href="tel:0841511684">โทรสอบถามหรือนำเครื่องมาประเมินฟรีได้เลย</a>
    </p>
  </div>
</section>

<!-- ═══════════════════════════════════════════════
     PROCESS
════════════════════════════════════════════════ -->
<section class="sv-section sv-process">
  <div class="sv-container">
    <div class="sv-section-head" data-aos="fade-up">
      <span class="section-label">ขั้นตอน</span>
      <h2>ซ่อม MacBook กับเรา ง่ายแค่ 4 ขั้นตอน</h2>
    </div>
    <div class="sv-steps">
      <?php foreach ([
          ['1', 'call',             'นำเครื่องมา หรือส่งได้เลย',       'เดินทางมาที่เชียงใหม่ หรือส่งผ่าน Kerry / Grab ได้เลย'],
          ['2', 'search',           'ตรวจสอบและประเมินราคาฟรี',         'ช่างตรวจสอบอาการและแจ้งราคาก่อนทุกครั้ง ไม่มีค่าใช้จ่าย'],
          ['3', 'build',            'เริ่มซ่อม พร้อมอัปเดตสถานะ',       'ซ่อมโดยช่างผู้เชี่ยวชาญ แจ้งสถานะผ่าน LINE ตลอด'],
          ['4', 'workspace_premium','รับเครื่อง พร้อมรับประกัน 90 วัน', 'รับเครื่องพร้อมใบรับประกัน 90 วัน ครอบคลุมอะไหล่และค่าแรง'],
      ] as $i => [$num, $icon, $title, $desc]): ?>
      <div class="sv-step" data-aos="fade-up" data-aos-delay="<?= $i * 100 ?>">
        <div class="sv-step-num"><?= $num ?></div>
        <div class="sv-step-icon"><span class="material-symbols-rounded"><?= $icon ?></span></div>
        <h4><?= $title ?></h4>
        <p><?= $desc ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════════
     REPAIR GALLERY
════════════════════════════════════════════════ -->
<?php if ($repairs): ?>
<section class="sv-section sv-gallery">
  <div class="sv-container">
    <div class="sv-section-head" data-aos="fade-up">
      <span class="section-label">ผลงานจริง</span>
      <h2>ตัวอย่างผลงานซ่อม MacBook</h2>
      <p class="sv-desc">ทุกชิ้นงานผ่านมือช่างผู้เชี่ยวชาญของเรา พร้อมรีวิวจากลูกค้าจริง</p>
    </div>
    <div class="sv-gallery-grid">
      <?php foreach ($repairs as $i => $r):
          $img = !empty($r['image']) ? '/uploads/' . htmlspecialchars($r['image'], ENT_QUOTES, 'UTF-8') : '/assets/img/placeholder.png';
      ?>
      <a href="/works/detail.php?id=<?= (int)$r['id'] ?>"
         class="sv-gcard" data-aos="fade-up" data-aos-delay="<?= ($i % 4) * 70 ?>">
        <div class="sv-gcard-img">
          <img src="<?= $img ?>" alt="<?= htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8') ?>"
               loading="lazy" decoding="async"
               onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
          <div class="sv-gcard-fallback" style="display:none">
            <span class="material-symbols-rounded">laptop_mac</span>
          </div>
        </div>
        <div class="sv-gcard-info">
          <h3><?= htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8') ?></h3>
          <span><?= htmlspecialchars($r['model'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <div class="sv-view-all" data-aos="fade-up">
      <a href="/works/?category=MacBook" class="sv-more-link">
        ดูผลงานทั้งหมด <span class="material-symbols-rounded">arrow_forward</span>
      </a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════
     FAQ
════════════════════════════════════════════════ -->
<section class="sv-section sv-faq">
  <div class="sv-container sv-faq-wrap">
    <div class="sv-section-head" data-aos="fade-up">
      <span class="section-label">FAQ</span>
      <h2>คำถามที่พบบ่อย ซ่อม MacBook</h2>
    </div>
    <div class="sv-faq-list" data-aos="fade-up">
      <?php foreach ($faq_schema as $faq): ?>
      <div class="faq-item">
        <button class="faq-q" type="button">
          <span><?= htmlspecialchars($faq[0], ENT_QUOTES, 'UTF-8') ?></span>
          <span class="material-symbols-rounded faq-arr">expand_more</span>
        </button>
        <div class="faq-a"><p><?= htmlspecialchars($faq[1], ENT_QUOTES, 'UTF-8') ?></p></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════════
     BOTTOM CTA
════════════════════════════════════════════════ -->
<section class="sv-cta">
  <div class="sv-container">
    <div class="sv-cta-inner" data-aos="fade-up">
      <span class="material-symbols-rounded sv-cta-icon">laptop_mac</span>
      <h2>MacBook มีปัญหา?<br>ให้เราดูแลให้</h2>
      <p>ประเมินฟรี ไม่มีค่าใช้จ่าย · อะไหล่แท้ · ประกัน 90 วัน<br>ช่างผู้เชี่ยวชาญพร้อมรับเครื่องทุกวัน จ.–ส. 9:00–19:00</p>
      <div class="sv-cta-btns">
        <a href="tel:0841511684" class="btn btn-accent">
          <span class="material-symbols-rounded">call</span> 084-151-1684
        </a>
        <a href="https://line.me/R/ti/p/@cmns" target="_blank" rel="noopener" class="btn sv-btn-line">
          <img src="/assets/img/line-icon.png" alt="LINE" width="18" height="18"> LINE: @cmns
        </a>
        <a href="https://maps.app.goo.gl/bDboFFwykRSCSMX7A" target="_blank" rel="noopener" class="btn btn-ghost">
          <span class="material-symbols-rounded">location_on</span> ดูแผนที่
        </a>
      </div>
    </div>
  </div>
</section>

</main>

<?php include_once '../includes/footer.php'; ?>
<script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
<script>
AOS.init({ duration: 700, once: true, offset: 60 });

document.querySelectorAll('.sv-tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.sv-tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.sv-tab-pane').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById(btn.dataset.tab).classList.add('active');
    });
});

document.querySelectorAll('.faq-q').forEach(btn => {
    btn.addEventListener('click', () => {
        const item = btn.closest('.faq-item');
        const open = item.classList.contains('open');
        document.querySelectorAll('.faq-item.open').forEach(i => i.classList.remove('open'));
        if (!open) item.classList.add('open');
    });
});
</script>
</body>
</html>
