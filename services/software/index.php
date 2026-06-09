<?php
require_once '../../includes/db.php';

$page_title       = 'ลงโปรแกรม Mac / ซ่อมซอฟต์แวร์ เชียงใหม่ | CMNS FixMac';
$page_description = 'ลง macOS ลง Windows Boot Camp ลง Office Adobe AutoCAD ลบไวรัส กู้ข้อมูล แก้เครื่องช้า บริการซอฟต์แวร์ Mac ครบ เชียงใหม่ ราคาเริ่มต้น 300 บาท';
$page_keywords    = 'ลงโปรแกรม Mac เชียงใหม่, ลง macOS เชียงใหม่, ลง Office Mac, ลง Adobe Mac, ลบไวรัส Mac, กู้ข้อมูล Mac, แก้เครื่องช้า Mac เชียงใหม่';
$page_css         = ['/assets/css/services/software-style.css?v=1', 'https://unpkg.com/aos@2.3.4/dist/aos.css'];

$faq_schema = [
    ['ลง macOS ใหม่ราคาเท่าไหร่?',
     'ลง macOS เริ่มต้นที่ 500 บาท รวมการติดตั้ง ตั้งค่าเบื้องต้น และทดสอบระบบ ถ้าต้องการโอนข้อมูลด้วยจะมีค่าใช้จ่ายเพิ่มตามปริมาณข้อมูล'],
    ['ใช้เวลานานแค่ไหน?',
     'งานทั่วไปเช่น ลง macOS ลงโปรแกรม ส่วนใหญ่เสร็จภายในวันเดียวกัน งานกู้ข้อมูลอาจใช้เวลา 1–3 วัน'],
    ['ลง Windows บน Mac ได้ไหม?',
     'ได้ ทั้งแบบ Boot Camp (MacBook Intel) และแบบ Virtual Machine ผ่าน Parallels (ทั้ง Intel และ Apple Silicon) ราคาเริ่มต้นที่ 800 บาท'],
    ['กู้ข้อมูลจาก Mac ที่เปิดไม่ติดได้ไหม?',
     'ได้ในหลายกรณี ขึ้นอยู่กับสาเหตุที่เปิดไม่ติด เราประเมินก่อนทุกครั้ง ถ้ากู้ได้จะแจ้งราคาก่อนดำเนินการ'],
    ['ลง Office / Adobe ได้ไหม?',
     'ได้ รับลง Microsoft 365, Adobe Creative Cloud, Final Cut Pro, Logic Pro, AutoCAD, DaVinci Resolve และอื่นๆ ราคาเริ่มต้นที่ 300 บาท'],
    ['ส่งเครื่องมาซ่อมทางไปรษณีย์ได้ไหม?',
     'ส่งได้ผ่าน Kerry / Grab เราบรรจุคืนอย่างดีและแจ้งสถานะทาง LINE ตลอดการซ่อม'],
];

ob_start(); ?>
<meta name="description"   content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8') ?>">
<meta name="keywords"      content="<?= htmlspecialchars($page_keywords, ENT_QUOTES, 'UTF-8') ?>">
<meta name="robots"        content="index, follow">
<link rel="canonical"      href="https://cmnsfixmac.com/services/software/">
<link rel="shortcut icon"  href="/assets/img/favicon1.png">
<meta property="og:title"        content="<?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:description"  content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:type"         content="website">
<meta property="og:image"        content="https://cmnsfixmac.com/assets/img/macbook-repair-og.jpg">
<meta property="og:image:width"  content="1200">
<meta property="og:image:height" content="630">
<meta property="og:locale"       content="th_TH">
<meta property="og:url"          content="https://cmnsfixmac.com/services/software/">
<meta name="twitter:card"        content="summary_large_image">
<meta name="twitter:title"       content="<?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8') ?>">
<meta name="twitter:image"       content="https://cmnsfixmac.com/assets/img/macbook-repair-og.jpg">
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@graph'   => [
        ['@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'หน้าแรก',      'item' => 'https://cmnsfixmac.com'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'บริการ',       'item' => 'https://cmnsfixmac.com/services/'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => 'ซ่อมซอฟต์แวร์','item' => 'https://cmnsfixmac.com/services/software/'],
        ]],
        ['@type' => 'Service',
         'name'  => 'ลงโปรแกรม Mac / ซ่อมซอฟต์แวร์ เชียงใหม่',
         'description' => 'บริการลง macOS, Windows, Office, Adobe และซ่อมซอฟต์แวร์ Mac ครบวงจร เชียงใหม่',
         'provider' => ['@type' => 'LocalBusiness', 'name' => 'CMNS FixMac',
             'telephone' => '+66-84-151-1684', 'url' => 'https://cmnsfixmac.com',
             'address'   => ['@type' => 'PostalAddress', 'streetAddress' => '482 หมู่ 8 หลังกาดวรุณ',
                             'addressLocality' => 'เชียงใหม่', 'postalCode' => '50100', 'addressCountry' => 'TH'],
         ],
         'areaServed' => 'เชียงใหม่',
         'url'        => 'https://cmnsfixmac.com/services/software/',
        ],
        ['@type' => 'FAQPage', 'mainEntity' => array_map(fn($f) => [
            '@type' => 'Question', 'name' => $f[0],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
        ], $faq_schema)],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php
$page_head_extra = ob_get_clean();

$pricing_raw = $pdo->query(
    "SELECT sp.device_name, sp.price, sp.price_note, sp.warranty_days,
            pc.id AS cat_id, pc.name AS cat_name, pc.sort_order
     FROM service_pricing sp
     JOIN pricing_categories pc ON sp.category_id = pc.id
     WHERE sp.device_type = 'Software'
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
        <span class="material-symbols-rounded">terminal</span>
        CMNS FixMac · เชียงใหม่
      </span>
      <h1 class="sv-h1">ลงโปรแกรม<br><span class="sv-h1-accent">ซ่อมซอฟต์แวร์ Mac</span></h1>
      <p class="sv-hero-sub">macOS · Windows · Office · Adobe · กู้ข้อมูล<br>บริการวันเดียวเสร็จ ราคาเริ่มต้น 300 บาท</p>
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
        <span class="sv-pill"><span class="material-symbols-rounded">devices</span> Mac & Windows</span>
        <span class="sv-pill"><span class="material-symbols-rounded">bolt</span> วันเดียวเสร็จ</span>
      </div>
    </div>

    <div class="sv-hero-visual" data-aos="fade-left" data-aos-duration="700" data-aos-delay="120">
      <div class="sv-device-wrap">
        <img src="/assets/img/macbook.png" alt="ลงโปรแกรม Mac เชียงใหม่ CMNS FixMac" class="sv-device-img">
        <div class="sv-float-badge sv-fbadge-1">
          <span class="material-symbols-rounded">build_circle</span>
          <div><strong>1,500+</strong><span>งานซ่อมซอฟต์แวร์</span></div>
        </div>
        <div class="sv-float-badge sv-fbadge-2">
          <span class="material-symbols-rounded">schedule</span>
          <div><strong>บริการวันเดียวเสร็จ</strong><span>ส่วนใหญ่เสร็จใน 24 ชม.</span></div>
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
    <a href="/services/apple-watch/" class="sv-nav-item"><span class="material-symbols-rounded">watch</span><span>Apple Watch</span></a>
    <a href="/services/airpods/"    class="sv-nav-item"><span class="material-symbols-rounded">headphones</span><span>AirPods</span></a>
    <a href="/services/software/"   class="sv-nav-item active"><span class="material-symbols-rounded">terminal</span><span>Software</span></a>
  </nav>
</div>

<!-- ═══════════════════════════════════════════════
     SERVICES GRID
════════════════════════════════════════════════ -->
<section class="sv-section sv-services">
  <div class="sv-container">
    <div class="sv-section-head" data-aos="fade-up">
      <span class="section-label">บริการทั้งหมด</span>
      <h2>บริการซอฟต์แวร์ Mac ครบวงจร</h2>
      <p class="sv-desc">รับลงโปรแกรม ซ่อมระบบ กู้ข้อมูล และแก้ปัญหาซอฟต์แวร์ทุกชนิด รองรับทั้ง macOS และ Windows</p>
    </div>
    <div class="sv-card-grid">
      <?php foreach ([
          ['system_update',     'ลง macOS ใหม่',               'ลง macOS ทุกเวอร์ชัน ตั้งแต่ Ventura, Sonoma ถึง Sequoia พร้อมตั้งค่าและทดสอบ',       'เริ่ม 500 บาท'],
          ['desktop_windows',   'ลง Windows (Boot Camp / VM)', 'ลง Windows 10 / 11 บน Mac ผ่าน Boot Camp (Intel) หรือ Parallels (M-chip)',             'เริ่ม 800 บาท'],
          ['security',          'ลบไวรัส / Malware',           'สแกนและลบไวรัส Adware Malware ทำความสะอาดระบบ เพิ่มประสิทธิภาพ',                      'เริ่ม 500 บาท'],
          ['apps',              'ลงโปรแกรม Office / Adobe',    'ลง Microsoft 365, Adobe CC, Final Cut Pro, Logic Pro, AutoCAD, DaVinci Resolve',        'เริ่ม 300 บาท'],
          ['restore_page',      'Recovery / กู้ข้อมูล',        'กู้ข้อมูลจาก SSD / HDD ที่เสีย หรือ Mac ที่ลง OS ใหม่โดยไม่ได้สำรองข้อมูล',           'ประเมินหน้างาน'],
          ['speed',             'แก้เครื่องช้า / ค้าง',        'ปรับแต่ง macOS / Windows ลบโปรแกรมขยะ ทำความสะอาด startup items เพิ่มความเร็ว',       'เริ่ม 400 บาท'],
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
        ['1,500+',    'งานซ่อมซอฟต์แวร์',  'build'],
        ['10+',       'ปีประสบการณ์',        'engineering'],
        ['4.9★',      'Google Reviews',      'star'],
        ['วันเดียวเสร็จ', 'ส่วนใหญ่เสร็จใน 24 ชม.', 'schedule'],
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
      <span class="section-label">ราคาบริการ</span>
      <h2>ราคาบริการซอฟต์แวร์ โปร่งใส ไม่มีบวกเพิ่ม</h2>
      <p class="sv-desc">ราคาเริ่มต้น ขึ้นอยู่กับปริมาณงานจริง <strong>ปรึกษาฟรีทุกครั้งก่อนเริ่มงาน</strong></p>
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
        <thead><tr><th>บริการ</th><th>ราคาเริ่มต้น</th><th>รับประกัน</th></tr></thead>
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
      ราคาเป็นโดยประมาณ อาจเปลี่ยนแปลงตามปริมาณงานจริง
      <a href="tel:0841511684">โทรสอบถามหรือนำเครื่องมาปรึกษาฟรีได้เลย</a>
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
      <h2>ใช้บริการกับเรา ง่ายแค่ 4 ขั้นตอน</h2>
    </div>
    <div class="sv-steps">
      <?php foreach ([
          ['1', 'call',        'แจ้งอาการ หรือนำเครื่องมา',   'โทรปรึกษาก่อน หรือนำเครื่องมาที่ร้านในเชียงใหม่ได้เลย'],
          ['2', 'search',      'ตรวจสอบระบบและประเมินงาน',     'ช่างตรวจสอบสภาพซอฟต์แวร์และแจ้งราคาก่อนทุกครั้ง ไม่มีค่าใช้จ่าย'],
          ['3', 'terminal',    'ดำเนินงาน พร้อมอัปเดตสถานะ',   'ลงโปรแกรม / ซ่อมระบบ แจ้งสถานะผ่าน LINE ตลอด'],
          ['4', 'check_circle','รับเครื่อง พร้อมทดสอบ',        'ทดสอบการทำงานร่วมกัน รับเครื่องคืนพร้อมคำแนะนำการใช้งาน'],
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
     FAQ
════════════════════════════════════════════════ -->
<section class="sv-section sv-faq">
  <div class="sv-container sv-faq-wrap">
    <div class="sv-section-head" data-aos="fade-up">
      <span class="section-label">FAQ</span>
      <h2>คำถามที่พบบ่อย บริการซอฟต์แวร์</h2>
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
      <span class="material-symbols-rounded sv-cta-icon">terminal</span>
      <h2>Mac มีปัญหาซอฟต์แวร์?<br>ให้เราจัดการให้</h2>
      <p>ปรึกษาฟรี ไม่มีค่าใช้จ่าย · บริการวันเดียวเสร็จ<br>ช่างผู้เชี่ยวชาญพร้อมให้บริการทุกวัน จ.–ส. 9:00–19:00</p>
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
