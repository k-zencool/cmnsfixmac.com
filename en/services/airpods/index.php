<?php
require_once '../../../includes/db.php';

$page_title       = 'AirPods Repair Chiang Mai | Battery, Sound, Charging | CMNS FixMac';
$page_description = 'AirPods repair in Chiang Mai. Battery replacement, sound issues, charging case repair, cleaning, Bluetooth problems, ANC fix. All models. Up to 3-month warranty.';
$page_keywords    = 'AirPods repair Chiang Mai, AirPods battery replacement, AirPods Pro repair, AirPods charging case repair, AirPods no sound, AirPods cleaning';
$page_css         = ['/assets/css/services/airpods-style.css?v=1', 'https://unpkg.com/aos@2.3.4/dist/aos.css'];
$switch_to_lang_url = '/services/airpods/';

$faq_schema = [
    ['How much does AirPods battery replacement cost?',
     'AirPods battery replacement starts from ฿890 per earbud. Charging case battery replacement starts from ฿890. Free diagnosis included.'],
    ['How long does AirPods repair take?',
     'Most AirPods repairs take 1–2 days. Cleaning and minor fixes can often be done same day. We update you via LINE.'],
    ['Can you fix AirPods Pro Active Noise Cancellation?',
     'Yes. ANC issues are often due to dirty or damaged mesh or a software calibration problem. We diagnose and fix the root cause.'],
    ['My AirPods charging case broke — can it be repaired?',
     'Yes, we can replace the battery in the charging case and repair charging port issues for both standard and MagSafe cases.'],
    ['One AirPod has no sound — is it fixable?',
     'Usually yes. The cause is often earwax buildup, water damage, or a speaker fault. Bring them in for a free inspection.'],
    ['Can you repair AirPods Max?',
     'Yes. We service AirPods Max including ear cup replacement, headband repair, battery service, and sound issues.'],
];

ob_start(); ?>
<meta name="description"   content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8') ?>">
<meta name="keywords"      content="<?= htmlspecialchars($page_keywords, ENT_QUOTES, 'UTF-8') ?>">
<meta name="robots"        content="index, follow">
<link rel="canonical"      href="https://cmnsfixmac.com/en/services/airpods/">
<link rel="shortcut icon"  href="/assets/img/favicon1.png">
<link rel="alternate" hreflang="th"        href="https://cmnsfixmac.com/services/airpods/">
<link rel="alternate" hreflang="en"        href="https://cmnsfixmac.com/en/services/airpods/">
<link rel="alternate" hreflang="x-default" href="https://cmnsfixmac.com/en/services/airpods/">
<meta property="og:title"        content="<?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:description"  content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:type"         content="website">
<meta property="og:image"        content="https://cmnsfixmac.com/assets/img/macbook-repair-og.jpg">
<meta property="og:image:width"  content="1200">
<meta property="og:image:height" content="630">
<meta property="og:locale"       content="en_US">
<meta property="og:url"          content="https://cmnsfixmac.com/en/services/airpods/">
<meta name="twitter:card"        content="summary_large_image">
<meta name="twitter:title"       content="<?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8') ?>">
<meta name="twitter:image"       content="https://cmnsfixmac.com/assets/img/macbook-repair-og.jpg">
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@graph'   => [
        ['@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home',           'item' => 'https://cmnsfixmac.com/en/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Services',       'item' => 'https://cmnsfixmac.com/en/services/'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => 'AirPods Repair', 'item' => 'https://cmnsfixmac.com/en/services/airpods/'],
        ]],
        ['@type' => 'Service',
         'name'  => 'AirPods Repair Chiang Mai',
         'description' => 'AirPods repair for all models. Battery, sound issues, cleaning, charging case, Bluetooth. Up to 3-month warranty.',
         'provider' => ['@type' => 'LocalBusiness', 'name' => 'CMNS FixMac',
             'telephone' => '+66-84-151-1684', 'url' => 'https://cmnsfixmac.com',
             'address'   => ['@type' => 'PostalAddress', 'streetAddress' => '482 Moo 8, Behind Kad Warun',
                             'addressLocality' => 'Chiang Mai', 'postalCode' => '50100', 'addressCountry' => 'TH'],
         ],
         'areaServed' => 'Chiang Mai',
         'url'        => 'https://cmnsfixmac.com/en/services/airpods/',
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
     WHERE status='published' AND TRIM(LOWER(category))='airpods'
     ORDER BY created_at DESC LIMIT 8"
)->fetchAll();

$pricing_raw = $pdo->query(
    "SELECT sp.device_name, sp.price, sp.price_note, sp.warranty_days,
            pc.id AS cat_id, pc.name AS cat_name, pc.sort_order
     FROM service_pricing sp
     JOIN pricing_categories pc ON sp.category_id = pc.id
     WHERE sp.device_type = 'AirPods'
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
        <span class="material-symbols-rounded">headphones</span>
        CMNS FixMac · Chiang Mai
      </span>
      <h1 class="sv-h1">AirPods Repair<br><span class="sv-h1-accent">Sound, Battery & More</span></h1>
      <p class="sv-hero-sub">Genuine Parts · Expert Technicians · Up to 3-Month Warranty<br>Free Diagnosis Before Every Repair</p>
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
        <span class="sv-pill"><span class="material-symbols-rounded">verified</span> Genuine Parts</span>
        <span class="sv-pill"><span class="material-symbols-rounded">bolt</span> 1–2 Days</span>
      </div>
    </div>
    <div class="sv-hero-visual" data-aos="fade-left" data-aos-duration="700" data-aos-delay="120">
      <div class="sv-device-wrap">
        <img src="/assets/img/airpods.png" alt="AirPods Repair Chiang Mai CMNS FixMac" class="sv-device-img">
        <div class="sv-float-badge sv-fbadge-1">
          <span class="material-symbols-rounded">build_circle</span>
          <div><strong>400+</strong><span>AirPods Repairs</span></div>
        </div>
        <div class="sv-float-badge sv-fbadge-2">
          <span class="material-symbols-rounded">workspace_premium</span>
          <div><strong>Up to 3-Month Warranty</strong><span>Parts & labor covered</span></div>
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
    <a href="/en/services/airpods/"     class="sv-nav-item active"><span class="material-symbols-rounded">headphones</span><span>AirPods</span></a>
    <a href="/en/services/software/"    class="sv-nav-item"><span class="material-symbols-rounded">terminal</span><span>Software</span></a>
  </nav>
</div>

<section class="sv-section sv-services">
  <div class="sv-container">
    <div class="sv-section-head" data-aos="fade-up">
      <span class="section-label">Our Services</span>
      <h2>Full AirPods Repair Coverage</h2>
      <p class="sv-desc">We repair AirPods 1st–4th gen, AirPods Pro 1st & 2nd gen, and AirPods Max — battery, sound, case, and more.</p>
    </div>
    <div class="sv-card-grid">
      <?php foreach ([
          ['battery_alert',   'Battery Replacement', 'Earbuds or case battery draining too fast. We replace with proper capacity cells to restore full use time.', 'From ฿890'],
          ['volume_off',      'Sound Issues',         'No sound, low volume on one side, crackling audio. We diagnose and repair or replace the speaker driver.', 'Quote on inspection'],
          ['cleaning_services','Deep Cleaning',       'Earwax and dust blocking sound or mic mesh. Professional ultrasonic cleaning to restore audio quality.', 'From ฿290'],
          ['battery_charging_full', 'Charging Case Repair', 'Case won\'t charge, charging port damaged, lid hinge broken. Battery and port replacement available.', 'From ฿1,200'],
          ['bluetooth',       'Bluetooth Issues',     'AirPods dropping connection, pairing fails, stuttering audio. We diagnose firmware and hardware faults.', 'Quote on inspection'],
          ['noise_control_off','ANC / Transparency',  'Active Noise Cancellation or Transparency mode not working on AirPods Pro. Mesh cleaning and sensor calibration.', 'Quote on inspection'],
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
        ['400+', 'AirPods Repairs',        'build'],
        ['10+',  'Years Experience',         'engineering'],
        ['4.9★', 'Google Reviews',           'star'],
        ['Up to 3-Month Warranty', 'Parts & labor covered', 'workspace_premium'],
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
      <h2>Transparent AirPods Repair Pricing</h2>
      <p class="sv-desc">Approximate prices — varies by model and condition. <strong>Free diagnosis before every job.</strong></p>
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
        <thead><tr><th>Model / Service</th><th>Approx. Price</th><th>Warranty</th></tr></thead>
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
      Prices are approximate and may vary by model and actual condition.
      <a href="tel:0841511684">Call or bring your device in for a free quote.</a>
    </p>
  </div>
</section>

<section class="sv-section sv-process">
  <div class="sv-container">
    <div class="sv-section-head" data-aos="fade-up">
      <span class="section-label">How It Works</span>
      <h2>AirPods Repair in Just 4 Steps</h2>
    </div>
    <div class="sv-steps">
      <?php foreach ([
          ['1', 'call',             'Bring In or Ship It',         'Visit us in Chiang Mai or ship via Kerry / Grab.'],
          ['2', 'search',           'Free Diagnosis & Quote',       'We inspect and give you a price before any work begins.'],
          ['3', 'build',            'Repair with Status Updates',   'Expert technicians fix your AirPods. We update you via LINE.'],
          ['4', 'workspace_premium','Pick Up with Warranty Card',   'Take your AirPods home with warranty covering parts & labor.'],
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

<?php if ($repairs): ?>
<section class="sv-section sv-gallery">
  <div class="sv-container">
    <div class="sv-section-head" data-aos="fade-up">
      <span class="section-label">Our Work</span>
      <h2>AirPods Repair Gallery</h2>
      <p class="sv-desc">Real repairs by our technicians. Every job is documented and reviewed by real customers.</p>
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
            <span class="material-symbols-rounded">headphones</span>
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
      <a href="/en/works/?category=AirPods" class="sv-more-link">
        View All Work <span class="material-symbols-rounded">arrow_forward</span>
      </a>
    </div>
  </div>
</section>
<?php endif; ?>

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
      <span class="material-symbols-rounded sv-cta-icon">headphones</span>
      <h2>AirPods Not Sounding Right?<br>Let Us Fix Them.</h2>
      <p>Free diagnosis · Genuine parts · Up to 3-month warranty<br>Expert technicians available Mon–Sat 9:00–19:00</p>
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
