<?php
require_once '../../includes/db.php';

$page_title       = 'ซ่อม Apple Watch เชียงใหม่ ทุกรุ่น ทุกซีรีส์ | CMNS FixMac';
$page_description = 'ซ่อม Apple Watch ทุกรุ่น Series 1–10, Ultra จอแตก แบตเสื่อม น้ำเข้า Digital Crown พัง ช่างผู้เชี่ยวชาญเชียงใหม่ ประกันสูงสุด 3 เดือน ประเมินฟรี';
$page_keywords    = 'ซ่อม Apple Watch เชียงใหม่, เปลี่ยนจอ Apple Watch, เปลี่ยนแบต Apple Watch, Apple Watch น้ำเข้า, ซ่อม Apple Watch Ultra, ราคาซ่อม Apple Watch เชียงใหม่';
$page_css         = ['/assets/css/services/apple-watch-style.css?v=1', 'https://unpkg.com/aos@2.3.4/dist/aos.css'];

$faq_schema = [
    ['ซ่อม Apple Watch ที่ CMNS FixMac ราคาเท่าไหร่?',
     'ราคาขึ้นอยู่กับรุ่นและอาการ เช่น เปลี่ยนจอ Apple Watch Series 8 เริ่มที่ 2,500 บาท เปลี่ยนแบตเริ่มที่ 1,200 บาท ทุกงานประเมินฟรีก่อนตัดสินใจ'],
    ['ใช้เวลาซ่อม Apple Watch นานแค่ไหน?',
     'งานทั่วไปเช่น เปลี่ยนจอ เปลี่ยนแบต ใช้เวลา 2–3 วัน ขึ้นอยู่กับสต็อกอะไหล่'],
    ['Apple Watch ที่ซ่อมมีประกันไหม?',
     'มีประกันทุกงานซ่อม 1–3 เดือน ขึ้นอยู่กับประเภทงาน ครอบคลุมทั้งอะไหล่และค่าแรง นำมาเคลมได้ฟรีในช่วงประกัน'],
    ['Apple Watch Ultra ซ่อมได้ไหม?',
     'ซ่อมได้ ทีมช่างมีประสบการณ์กับ Apple Watch ทุก Series ตั้งแต่ Series 1 ถึง Ultra 2'],
    ['Apple Watch โดนน้ำ ยังกู้คืนได้ไหม?',
     'แม้ Watch จะกันน้ำ แต่ถ้าน้ำเข้าระดับ seal เสีย ยังซ่อมได้ อย่าชาร์จก่อน นำมาให้ตรวจสอบด่วน'],
    ['ซ่อม Digital Crown ได้ไหม?',
     'ซ่อมได้ ทั้งกรณีหมุนไม่ได้ ค้าง หรือ Side Button ไม่ตอบสนอง เปลี่ยนชิ้นส่วนใหม่ได้เลย'],
];

ob_start(); ?>
<meta name="description"   content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8') ?>">
<meta name="keywords"      content="<?= htmlspecialchars($page_keywords, ENT_QUOTES, 'UTF-8') ?>">
<meta name="robots"        content="index, follow">
<link rel="canonical"      href="https://cmnsfixmac.com/services/apple-watch/">
<link rel="shortcut icon"  href="/assets/img/favicon1.png">
<meta property="og:title"        content="<?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:description"  content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:type"         content="website">
<meta property="og:image"        content="https://cmnsfixmac.com/assets/img/applewatch-repair-og.jpg">
<meta property="og:image:width"  content="1200">
<meta property="og:image:height" content="630">
<meta property="og:locale"       content="th_TH">
<meta property="og:url"          content="https://cmnsfixmac.com/services/apple-watch/">
<meta name="twitter:card"        content="summary_large_image">
<meta name="twitter:title"       content="<?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8') ?>">
<meta name="twitter:image"       content="https://cmnsfixmac.com/assets/img/applewatch-repair-og.jpg">
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@graph'   => [
        ['@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'หน้าแรก',         'item' => 'https://cmnsfixmac.com'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'บริการ',          'item' => 'https://cmnsfixmac.com/services/'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => 'ซ่อม Apple Watch','item' => 'https://cmnsfixmac.com/services/apple-watch/'],
        ]],
        ['@type' => 'Service',
         'name'  => 'ซ่อม Apple Watch เชียงใหม่',
         'description' => 'บริการซ่อม Apple Watch ทุกรุ่น ทุกซีรีส์ โดยช่างผู้เชี่ยวชาญ ประกันสูงสุด 3 เดือน',
         'provider' => ['@type' => 'LocalBusiness', 'name' => 'CMNS FixMac',
             'telephone' => '+66-84-151-1684', 'url' => 'https://cmnsfixmac.com',
             'address'   => ['@type' => 'PostalAddress', 'streetAddress' => '482 หมู่ 8 หลังกาดวรุณ',
                             'addressLocality' => 'เชียงใหม่', 'postalCode' => '50100', 'addressCountry' => 'TH'],
         ],
         'areaServed' => 'เชียงใหม่',
         'url'        => 'https://cmnsfixmac.com/services/apple-watch/',
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
     WHERE status='published' AND TRIM(LOWER(category))='applewatch'
     ORDER BY created_at DESC LIMIT 8"
)->fetchAll();

$pricing_raw = $pdo->query(
    "SELECT sp.device_name, sp.price, sp.price_note, sp.warranty_days,
            pc.id AS cat_id, pc.name AS cat_name, pc.sort_order
     FROM service_pricing sp
     JOIN pricing_categories pc ON sp.category_id = pc.id
     WHERE sp.device_type = 'Apple Watch'
       AND sp.is_active  = 1
       AND sp.show_on_web = 1
     ORDER BY pc.sort_order, sp.price"
)->fetchAll();

$pricing_groups = [];
foreach ($pricing_raw as $row) {
    $pricing_groups[$row['cat_id']]['name'] = $row['cat_name'];
    $pricing_groups[$row['cat_id']]['items'][] = $row;
}

include_once '../../includes/header.php';
?>

<main>

<!-- ═══════════════════════════════════════════════
     HERO
════════════════════════════════════════════════ -->
<section class="sv-hero">
  <div class="sv-hero-dots" aria-hidden="true"></div>
  <canvas id="sv-particles" aria-hidden="true"></canvas>
  <div class="sv-hero-orb sv-orb-1" aria-hidden="true"></div>
  <div class="sv-hero-orb sv-orb-2" aria-hidden="true"></div>
  <div class="sv-hero-inner">

    <div class="sv-hero-text" data-aos="fade-right" data-aos-duration="700">
      <span class="sv-eyebrow">
        <span class="material-symbols-rounded">watch</span>
        CMNS FixMac · เชียงใหม่
      </span>
      <h1 class="sv-h1">ซ่อม Apple Watch<br><span class="sv-h1-accent">ทุกรุ่น ทุกซีรีส์</span></h1>
      <p class="sv-hero-sub">ช่างผู้เชี่ยวชาญ ประกันสูงสุด 3 เดือน<br>ประเมินฟรีก่อนตัดสินใจทุกครั้ง</p>
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
        <span class="sv-pill"><span class="material-symbols-rounded">bolt</span> 2–3 วัน</span>
      </div>
    </div>

    <div class="sv-hero-visual" data-aos="fade-left" data-aos-duration="700" data-aos-delay="120">
      <div class="sv-device-wrap">
        <img src="/assets/img/watch.png" alt="ซ่อม Apple Watch เชียงใหม่ CMNS FixMac" class="sv-device-img">
        <div class="sv-float-badge sv-fbadge-1">
          <span class="material-symbols-rounded">build_circle</span>
          <div><strong>300+</strong><span>งานซ่อม Apple Watch</span></div>
        </div>
        <div class="sv-float-badge sv-fbadge-2">
          <span class="material-symbols-rounded">workspace_premium</span>
          <div><strong>ประกันสูงสุด 3 เดือน</strong><span>1–3 เดือน แล้วแต่งาน</span></div>
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
    <a href="/services/macbook/"    class="sv-nav-item"><span class="material-symbols-rounded">laptop_mac</span><span>MacBook</span></a>
    <a href="/services/imac/"       class="sv-nav-item"><span class="material-symbols-rounded">desktop_mac</span><span>iMac</span></a>
    <a href="/services/iphone/"     class="sv-nav-item"><span class="material-symbols-rounded">smartphone</span><span>iPhone</span></a>
    <a href="/services/ipad/"       class="sv-nav-item"><span class="material-symbols-rounded">tablet_mac</span><span>iPad</span></a>
    <a href="/services/apple-watch/" class="sv-nav-item active"><span class="material-symbols-rounded">watch</span><span>Apple Watch</span></a>
    <a href="/services/airpods/"    class="sv-nav-item"><span class="material-symbols-rounded">headphones</span><span>AirPods</span></a>
    <a href="/services/software/"   class="sv-nav-item"><span class="material-symbols-rounded">terminal</span><span>Software</span></a>
  </nav>
</div>

<!-- ═══════════════════════════════════════════════
     SERVICES GRID
════════════════════════════════════════════════ -->
<section class="sv-section sv-services">
  <div class="sv-container">
    <div class="sv-section-head" data-aos="fade-up">
      <span class="section-label">บริการทั้งหมด</span>
      <h2>ซ่อม Apple Watch ครบทุกประเภท</h2>
      <p class="sv-desc">รับซ่อม Apple Watch Series 1–10, Ultra, SE ทุกขนาด 41mm / 45mm / 49mm โดยทีมช่างผู้เชี่ยวชาญ</p>
    </div>
    <div class="sv-card-grid">
      <?php foreach ([
          ['display_settings', 'เปลี่ยนหน้าจอ',         'จอแตก จอลาย จอไม่ติด เปลี่ยนจอ OLED LTPO ทุก Series รวมถึง Ultra',     'เริ่ม 1,500 บาท'],
          ['battery_alert',    'เปลี่ยนแบตเตอรี่',      'แบตเสื่อม ชาร์จไม่อยู่ แบตบวม รับเปลี่ยนแบตทุก Series',              'เริ่ม 1,200 บาท'],
          ['water_drop',       'ซ่อมน้ำเข้า',            'น้ำเข้าชิด หน้าจอมีไอน้ำ ซ่อม seal ล้างบอร์ดและซ่อมชิป',             'ประเมินหน้างาน'],
          ['tune',             'เปลี่ยน Digital Crown',  'มงกุฎหมุนไม่ได้ ค้าง Side Button ไม่ตอบสนอง เปลี่ยนชิ้นส่วนใหม่',   'เริ่ม 800 บาท'],
          ['power_off',        'ซ่อมเปิดไม่ติด',         'Watch ไม่เปิด ค้าง Apple Logo ชาร์จไม่ขึ้น ซ่อมระดับชิป',            'ประเมินหน้างาน'],
          ['link',             'เปลี่ยนสาย',             'เปลี่ยนสาย Sport Band, Milanese Loop, Braided Solo Loop ทุกสี ทุกขนาด','เริ่ม 390 บาท'],
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
        ['300+', 'งานซ่อม Apple Watch',   'build'],
        ['10+',  'ปีประสบการณ์',           'engineering'],
        ['4.9★', 'Google Reviews',         'star'],
        ['ประกันสูงสุด 3 เดือน', '1–3 เดือน แล้วแต่งาน', 'workspace_premium'],
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
      <h2>ราคาซ่อม Apple Watch โปร่งใส ไม่มีบวกเพิ่ม</h2>
      <p class="sv-desc">ราคาโดยประมาณ ขึ้นอยู่กับรุ่นและสภาพเครื่อง <strong>ประเมินฟรีทุกครั้งก่อนเริ่มงาน</strong></p>
    </div>
    <?php if ($pricing_groups): ?>
    <div class="sv-tab-row" data-aos="fade-up">
      <?php $first = true; foreach ($pricing_groups as $cat_id => $grp): ?>
      <button class="sv-tab-btn<?= $first ? ' active' : '' ?>" data-tab="tp-<?= $cat_id ?>">
        <?= htmlspecialchars($grp['name'], ENT_QUOTES, 'UTF-8') ?>
      </button>
      <?php $first = false; endforeach; ?>
    </div>
    <?php $first = true; foreach ($pricing_groups as $cat_id => $grp): ?>
    <div class="sv-tab-pane<?= $first ? ' active' : '' ?>" id="tp-<?= $cat_id ?>" data-aos="fade-up">
      <table class="sv-table">
        <thead><tr><th>รุ่น / บริการ</th><th>ราคาโดยประมาณ</th><th>รับประกัน</th></tr></thead>
        <tbody>
          <?php foreach ($grp['items'] as $item): ?>
          <tr>
            <td><?= htmlspecialchars($item['device_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= $item['price_note'] ? htmlspecialchars($item['price_note'], ENT_QUOTES, 'UTF-8') : '฿' . number_format($item['price']) . ' บาท' ?></td>
            <td><?= $item['warranty_days'] ? $item['warranty_days'] . ' วัน' : '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php $first = false; endforeach; ?>
    <?php else: ?>
    <p style="text-align:center;color:var(--text-secondary);padding:40px 0;">
      ยังไม่มีข้อมูลราคา — <a href="tel:0841511684">โทรสอบถามได้เลย</a>
    </p>
    <?php endif; ?>
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
      <h2>ซ่อม Apple Watch กับเรา ง่ายแค่ 4 ขั้นตอน</h2>
    </div>
    <div class="sv-steps">
      <?php foreach ([
          ['1', 'call',             'นำเครื่องมา หรือส่งได้เลย',     'เดินทางมาที่เชียงใหม่ หรือส่งผ่าน Kerry / Grab ได้เลย'],
          ['2', 'search',           'ตรวจสอบและประเมินราคาฟรี',       'ช่างตรวจสอบอาการและแจ้งราคาก่อนทุกครั้ง ไม่มีค่าใช้จ่าย'],
          ['3', 'build',            'เริ่มซ่อม พร้อมอัปเดตสถานะ',     'ซ่อมโดยช่างผู้เชี่ยวชาญ แจ้งสถานะผ่าน LINE ตลอด'],
          ['4', 'workspace_premium','รับเครื่อง พร้อมใบรับประกัน', 'รับเครื่องพร้อมใบรับประกัน 1–3 เดือน ขึ้นอยู่กับประเภทงาน'],
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
      <h2>ตัวอย่างผลงานซ่อม Apple Watch</h2>
      <p class="sv-desc">ทุกชิ้นงานผ่านมือช่างผู้เชี่ยวชาญของเรา พร้อมรีวิวจากลูกค้าจริง</p>
    </div>
    <div class="sv-gallery-grid">
      <?php foreach ($repairs as $i => $r):
          $raw = $r['image'] ?? '';
          if (!$raw) $img = '';
          elseif ($raw[0] === '/' || str_starts_with($raw, 'http')) $img = $raw;
          else $img = '/' . $raw;
      ?>
      <a href="/works/detail.php?id=<?= (int)$r['id'] ?>"
         class="sv-gcard" data-aos="fade-up" data-aos-delay="<?= ($i % 4) * 70 ?>">
        <div class="sv-gcard-img">
          <?php if ($img): ?>
          <img src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>"
               alt="<?= htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8') ?>"
               loading="lazy" decoding="async"
               onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
          <?php endif; ?>
          <div class="sv-gcard-fallback" style="display:<?= $img ? 'none' : 'flex' ?>">
            <span class="material-symbols-rounded">watch</span>
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
      <a href="/works/?category=Apple+Watch" class="sv-more-link">
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
      <h2>คำถามที่พบบ่อย ซ่อม Apple Watch</h2>
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
      <span class="material-symbols-rounded sv-cta-icon">watch</span>
      <h2>Apple Watch มีปัญหา?<br>ให้เราดูแลให้</h2>
      <p>ประเมินฟรี ไม่มีค่าใช้จ่าย · ประกันสูงสุด 3 เดือน<br>ช่างผู้เชี่ยวชาญพร้อมรับเครื่องทุกวัน จ.–ส. 9:00–19:00</p>
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

<?php include_once '../../includes/footer.php'; ?>
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

(function () {
  const canvas = document.getElementById('sv-particles');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const hero = canvas.closest('.sv-hero');
  const COUNT = 45;
  let W, H, particles = [], raf;
  function resize() {
    const dpr = window.devicePixelRatio || 1;
    W = hero.offsetWidth; H = hero.offsetHeight;
    canvas.width = W * dpr; canvas.height = H * dpr;
    canvas.style.width = W + 'px'; canvas.style.height = H + 'px';
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  }
  function isDark() { return document.documentElement.getAttribute('data-theme') === 'dark'; }
  function mkParticle(randomY) {
    const maxLife = 220 + Math.random() * 180;
    return { x: Math.random() * W, y: randomY ? Math.random() * H : H + 8,
      r: Math.random() * 1.6 + 0.4, vy: -(Math.random() * 0.35 + 0.12),
      vx: (Math.random() - 0.5) * 0.18, life: randomY ? Math.random() * maxLife : 0,
      maxLife, isAccent: Math.random() > 0.55 };
  }
  function init() { particles = Array.from({ length: COUNT }, () => mkParticle(true)); }
  function draw() {
    ctx.clearRect(0, 0, W, H);
    const dark = isDark();
    for (let i = 0; i < particles.length; i++) {
      const p = particles[i];
      p.x += p.vx; p.y += p.vy; p.life++;
      if (p.life >= p.maxLife || p.y < -10) { particles[i] = mkParticle(false); continue; }
      const t = p.life / p.maxLife;
      const alpha = t < 0.15 ? t / 0.15 : t > 0.75 ? (1 - t) / 0.25 : 1;
      if (p.isAccent) {
        ctx.fillStyle = `rgba(252,116,4,${alpha * (dark ? 0.55 : 0.35)})`;
      } else {
        ctx.fillStyle = dark ? `rgba(255,255,255,${alpha * 0.22})` : `rgba(80,80,80,${alpha * 0.12})`;
      }
      ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, 6.2832); ctx.fill();
    }
    raf = requestAnimationFrame(draw);
  }
  resize(); init(); draw();
  window.addEventListener('resize', () => { cancelAnimationFrame(raf); resize(); init(); draw(); }, { passive: true });
})();
</script>
</body>
</html>
