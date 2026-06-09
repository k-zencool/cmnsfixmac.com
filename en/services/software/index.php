<?php
require_once '../../../includes/db.php';

$page_title       = 'Mac Software & OS Service Chiang Mai | macOS, Data Recovery | CMNS FixMac';
$page_description = 'Mac software service in Chiang Mai. macOS reinstall, Windows Boot Camp/VM, malware removal, software setup, data recovery, fix slow Mac. Same-day service available.';
$page_keywords    = 'Mac software service Chiang Mai, macOS reinstall Chiang Mai, Mac slow fix, Mac data recovery, Windows Boot Camp Mac, Mac malware removal';
$page_css         = ['/assets/css/services/software-style.css?v=1', 'https://unpkg.com/aos@2.3.4/dist/aos.css'];
$switch_to_lang_url = '/services/software/';

$faq_schema = [
    ['How much does macOS reinstall cost?',
     'Clean macOS reinstall starts from ฿500. This includes a backup of your data (if the drive is accessible), a fresh install, and software setup.'],
    ['How long does Mac software service take?',
     'Most software jobs are done same day. Data recovery and complex malware removal may take 1–2 days depending on severity.'],
    ['Can you install Windows on a Mac?',
     'Yes. We can set up Windows via Boot Camp (on Intel Macs) or Parallels/VMware (on all models including Apple Silicon).'],
    ['My Mac got a virus — can you remove it?',
     'Yes. We remove malware, adware, and other threats completely. We also check for any data that may have been compromised.'],
    ['Can you recover data from a dead Mac?',
     'Often yes, depending on the failure type. We extract data from damaged drives and dead logic boards. Come in for a free assessment.'],
    ['My Mac is very slow — can you fix it?',
     'Yes. Common causes include too little RAM, a nearly full SSD, or background processes. We diagnose and optimize your system for ฿400+.'],
];

ob_start(); ?>
<meta name="description"   content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8') ?>">
<meta name="keywords"      content="<?= htmlspecialchars($page_keywords, ENT_QUOTES, 'UTF-8') ?>">
<meta name="robots"        content="index, follow">
<link rel="canonical"      href="https://cmnsfixmac.com/en/services/software/">
<link rel="shortcut icon"  href="/assets/img/favicon1.png">
<link rel="alternate" hreflang="th"        href="https://cmnsfixmac.com/services/software/">
<link rel="alternate" hreflang="en"        href="https://cmnsfixmac.com/en/services/software/">
<link rel="alternate" hreflang="x-default" href="https://cmnsfixmac.com/en/services/software/">
<meta property="og:title"        content="<?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:description"  content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:type"         content="website">
<meta property="og:image"        content="https://cmnsfixmac.com/assets/img/macbook-repair-og.jpg">
<meta property="og:image:width"  content="1200">
<meta property="og:image:height" content="630">
<meta property="og:locale"       content="en_US">
<meta property="og:url"          content="https://cmnsfixmac.com/en/services/software/">
<meta name="twitter:card"        content="summary_large_image">
<meta name="twitter:title"       content="<?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8') ?>">
<meta name="twitter:image"       content="https://cmnsfixmac.com/assets/img/macbook-repair-og.jpg">
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@graph'   => [
        ['@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home',             'item' => 'https://cmnsfixmac.com/en/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Services',         'item' => 'https://cmnsfixmac.com/en/services/'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => 'Mac Software & OS','item' => 'https://cmnsfixmac.com/en/services/software/'],
        ]],
        ['@type' => 'Service',
         'name'  => 'Mac Software & OS Service Chiang Mai',
         'description' => 'Mac software service: macOS reinstall, Windows, malware removal, data recovery, slow Mac fix. Same-day available.',
         'provider' => ['@type' => 'LocalBusiness', 'name' => 'CMNS FixMac',
             'telephone' => '+66-84-151-1684', 'url' => 'https://cmnsfixmac.com',
             'address'   => ['@type' => 'PostalAddress', 'streetAddress' => '482 Moo 8, Behind Kad Warun',
                             'addressLocality' => 'Chiang Mai', 'postalCode' => '50100', 'addressCountry' => 'TH'],
         ],
         'areaServed' => 'Chiang Mai',
         'url'        => 'https://cmnsfixmac.com/en/services/software/',
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

include_once '../../../includes/header_en.php';
?>

<main>

<section class="sv-hero">
  <div class="sv-hero-dots" aria-hidden="true"></div>
  <canvas id="sv-particles" aria-hidden="true"></canvas>
  <div class="sv-hero-orb sv-orb-1" aria-hidden="true"></div>
  <div class="sv-hero-orb sv-orb-2" aria-hidden="true"></div>
  <div class="sv-hero-inner">
    <div class="sv-hero-text" data-aos="fade-right" data-aos-duration="700">
      <span class="sv-eyebrow">
        <span class="material-symbols-rounded">terminal</span>
        CMNS FixMac · Chiang Mai
      </span>
      <h1 class="sv-h1">Mac Software<br><span class="sv-h1-accent">& OS Service</span></h1>
      <p class="sv-hero-sub">macOS · Windows · Data Recovery · Malware Removal<br>Fast, Professional, Same-Day Available</p>
      <div class="sv-hero-cta">
        <a href="tel:0841511684" class="btn btn-accent">
          <span class="material-symbols-rounded">call</span> Call for Free Advice
        </a>
        <a href="#sv-pricing" class="btn btn-ghost">
          View Pricing <span class="material-symbols-rounded">arrow_downward</span>
        </a>
      </div>
      <div class="sv-trust-pills">
        <span class="sv-pill"><span class="material-symbols-rounded">star</span> 4.9 Google</span>
        <span class="sv-pill"><span class="material-symbols-rounded">schedule</span> Same-Day Possible</span>
        <span class="sv-pill"><span class="material-symbols-rounded">verified</span> All Mac Models</span>
      </div>
    </div>
    <div class="sv-hero-visual" data-aos="fade-left" data-aos-duration="700" data-aos-delay="120">
      <div class="sv-device-wrap">
        <img src="/assets/img/macbook.png" alt="Mac Software Service Chiang Mai CMNS FixMac" class="sv-device-img">
        <div class="sv-float-badge sv-fbadge-1">
          <span class="material-symbols-rounded">build_circle</span>
          <div><strong>1,500+</strong><span>Software Jobs</span></div>
        </div>
        <div class="sv-float-badge sv-fbadge-2">
          <span class="material-symbols-rounded">schedule</span>
          <div><strong>Same-Day Service</strong><span>Most jobs done in 24 hrs</span></div>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="sv-nav-outer">
  <nav class="sv-nav" data-aos="fade-up" data-aos-offset="0" aria-label="Apple Repair Services">
    <a href="/en/services/macbook/"     class="sv-nav-item"><span class="material-symbols-rounded">laptop_mac</span><span>MacBook</span></a>
    <a href="/en/services/imac/"        class="sv-nav-item"><span class="material-symbols-rounded">desktop_mac</span><span>iMac</span></a>
    <a href="/en/services/iphone/"      class="sv-nav-item"><span class="material-symbols-rounded">smartphone</span><span>iPhone</span></a>
    <a href="/en/services/ipad/"        class="sv-nav-item"><span class="material-symbols-rounded">tablet_mac</span><span>iPad</span></a>
    <a href="/en/services/apple-watch/" class="sv-nav-item"><span class="material-symbols-rounded">watch</span><span>Apple Watch</span></a>
    <a href="/en/services/airpods/"     class="sv-nav-item"><span class="material-symbols-rounded">headphones</span><span>AirPods</span></a>
    <a href="/en/services/software/"    class="sv-nav-item active"><span class="material-symbols-rounded">terminal</span><span>Software</span></a>
  </nav>
</div>

<section class="sv-section sv-services">
  <div class="sv-container">
    <div class="sv-section-head" data-aos="fade-up">
      <span class="section-label">Our Services</span>
      <h2>Full Mac Software Coverage</h2>
      <p class="sv-desc">From clean macOS installs to Windows setup, data recovery, and performance optimization — we handle all Mac software jobs fast.</p>
    </div>
    <div class="sv-card-grid">
      <?php foreach ([
          ['terminal',    'macOS Reinstall',           'Clean install, upgrade, or downgrade macOS. Data backup included. Restore your Mac to factory-fresh performance.', 'From ฿500'],
          ['window',      'Windows Boot Camp / VM',    'Run Windows natively via Boot Camp (Intel) or virtualized via Parallels/VMware (all models including M-series).', 'From ฿800'],
          ['security',    'Malware Removal',           'Adware, ransomware, rogue software removed completely. We also check for data exposure and harden your settings.', 'From ฿500'],
          ['apps',        'Software Setup',            'Install and configure Office 365, Adobe CC, AutoCAD, Final Cut, or any other app you need — properly licensed.', 'From ฿300'],
          ['save',        'Data Recovery',             'Deleted files, dead drive, or corrupted macOS. We extract your data using professional recovery tools.', 'Quote on inspection'],
          ['speed',       'Fix Slow Mac',              'Optimize startup, clear cache, fix background processes, and tune your Mac for maximum performance.', 'From ฿400'],
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

<section class="sv-stats">
  <div class="sv-container sv-stats-inner">
    <?php foreach ([
        ['1,500+', 'Software Jobs',       'build'],
        ['10+',    'Years Experience',     'engineering'],
        ['4.9★',   'Google Reviews',       'star'],
        ['Same-Day Service', 'Most jobs done in 24 hrs', 'schedule'],
    ] as $i => [$num, $label, $icon]): ?>
    <div class="sv-stat" data-aos="fade-up" data-aos-delay="<?= $i * 80 ?>">
      <span class="material-symbols-rounded"><?= $icon ?></span>
      <strong><?= $num ?></strong>
      <span><?= $label ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="sv-section sv-pricing" id="sv-pricing">
  <div class="sv-container">
    <div class="sv-section-head" data-aos="fade-up">
      <span class="section-label">Pricing</span>
      <h2>Mac Software Service Pricing</h2>
      <p class="sv-desc">Fixed and approximate prices. <strong>Free diagnosis and consultation before every job.</strong></p>
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
        <thead><tr><th>Service</th><th>Price</th><th>Note</th></tr></thead>
        <tbody>
          <?php foreach ($grp['items'] as $item): ?>
          <tr>
            <td><?= htmlspecialchars($item['device_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= $item['price_note'] ? htmlspecialchars($item['price_note'], ENT_QUOTES, 'UTF-8') : '฿' . number_format($item['price']) ?></td>
            <td><?= $item['warranty_days'] ? $item['warranty_days'] . ' days' : '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php $first = false; endforeach; ?>
    <?php else: ?>
    <p style="text-align:center;color:var(--text-secondary);padding:40px 0;">
      No pricing data yet — <a href="tel:0841511684">call us for a quote</a>
    </p>
    <?php endif; ?>
    <p class="sv-price-note" data-aos="fade-up">
      <span class="material-symbols-rounded">info</span>
      Complex data recovery or multi-software setups may cost more.
      <a href="tel:0841511684">Call or bring your Mac in for a free consultation.</a>
    </p>
  </div>
</section>

<section class="sv-section sv-process">
  <div class="sv-container">
    <div class="sv-section-head" data-aos="fade-up">
      <span class="section-label">How It Works</span>
      <h2>Mac Software Service in Just 4 Steps</h2>
    </div>
    <div class="sv-steps">
      <?php foreach ([
          ['1', 'call',     'Bring In or Drop Us a Line', 'Visit us in Chiang Mai or call/LINE for quick advice before coming in.'],
          ['2', 'search',   'Free Consultation & Quote',  'We assess your Mac\'s issue and give you a clear price. No surprises.'],
          ['3', 'terminal', 'Fast Same-Day Service',      'Most software jobs are done same day. We update you every step via LINE.'],
          ['4', 'check_circle', 'Pick Up Your Mac',       'Take your Mac home running perfectly. We walk you through what was done.'],
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

<section class="sv-section sv-faq">
  <div class="sv-container sv-faq-wrap">
    <div class="sv-section-head" data-aos="fade-up">
      <span class="section-label">FAQ</span>
      <h2>Frequently Asked Questions</h2>
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

<section class="sv-cta">
  <div class="sv-container">
    <div class="sv-cta-inner" data-aos="fade-up">
      <span class="material-symbols-rounded sv-cta-icon">terminal</span>
      <h2>Mac Running Slow or Broken?<br>We'll Sort It Out.</h2>
      <p>Free consultation · Fast turnaround · Same-day service available<br>Expert technicians available Mon–Sat 9:00–19:00</p>
      <div class="sv-cta-btns">
        <a href="tel:0841511684" class="btn btn-accent">
          <span class="material-symbols-rounded">call</span> 084-151-1684
        </a>
        <a href="https://line.me/R/ti/p/@cmns" target="_blank" rel="noopener" class="btn sv-btn-line">
          <img src="/assets/img/line-icon.png" alt="LINE" width="18" height="18"> LINE: @cmns
        </a>
        <a href="https://maps.app.goo.gl/bDboFFwykRSCSMX7A" target="_blank" rel="noopener" class="btn btn-ghost">
          <span class="material-symbols-rounded">location_on</span> Get Directions
        </a>
      </div>
    </div>
  </div>
</section>

</main>

<?php include_once '../../../includes/footer_en.php'; ?>
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
      ctx.fillStyle = p.isAccent
        ? `rgba(252,116,4,${alpha * (isDark() ? 0.55 : 0.35)})`
        : (isDark() ? `rgba(255,255,255,${alpha * 0.22})` : `rgba(80,80,80,${alpha * 0.12})`);
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
