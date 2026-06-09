<?php
require_once '../../includes/db.php';

$page_title = 'CMNS Mac: We Buy Used MacBooks, iPhones, iPads, iMacs in Chiang Mai | Fair Price';
$switch_to_lang_url = '/buyback/';
$page_css   = [
    '/assets/css/buyback-style.css?v=2',
    'https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.3/dist/css/splide.min.css',
    'https://unpkg.com/aos@2.3.4/dist/aos.css',
];

$faqs = [
    ["My device is severely damaged and won't turn on. Do you really buy it?",
     "Yes! We buy devices in any condition, as stated. Even if it doesn't turn on, we can value it for its parts. Feel free to send it for a no-obligation quote."],
    ['Do you buy iCloud-locked devices or other locked devices?',
     'We will consider it. The price will depend on the model, condition, and the type of lock. Please provide full details when you send it for an evaluation.'],
    ['I live outside of Chiang Mai. Can I send my device to sell?',
     'Yes. If you cannot come to Chiang Mai, you can securely pack and ship your device to us. After we receive and inspect the device and agree on the price, we will transfer the money to you immediately. (We recommend asking us for detailed shipping instructions first).'],
    ['How long does it take to get a price and receive the money?',
     'The initial price evaluation via LINE is very fast, usually within a few hours (during business hours). If you agree to sell and we meet up or receive the device, the final inspection and payment are also very quick.'],
    ['What do I need to prepare when selling my device?',
     'Mainly the device itself. If you have the original box and all genuine accessories (charging cable, adapter), it will help you get a better price. Most importantly, please remember to sign out of your Apple ID and iCloud first.'],
];

ob_start(); ?>
<meta name="description" content="We buy used MacBooks, iPhones, iPads, and iMacs of all models and conditions in Chiang Mai! Get a fair price, free evaluation, and local pickup. Get a quote easily via LINE.">
<link rel="canonical" href="https://cmnsfixmac.com/en/buyback/">
<link rel="alternate" hreflang="th" href="https://cmnsfixmac.com/buyback/">
<link rel="alternate" hreflang="en" href="https://cmnsfixmac.com/en/buyback/">
<link rel="alternate" hreflang="x-default" href="https://cmnsfixmac.com/en/buyback/">
<link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png">
<script async src="https://www.googletagmanager.com/gtag/js?id=G-3WXK9GWN7C"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','G-3WXK9GWN7C');</script>
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'FAQPage',
    'mainEntity' => array_map(fn($f) => [
        '@type' => 'Question', 'name' => $f[0],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
    ], $faqs),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php
$page_head_extra = ob_get_clean();

include_once '../../includes/header_en.php';
?>

<main>

<!-- ═══════════════════════════════════════════════ HERO ═══════════════════════════════════════════════ -->
<section class="sv-hero">
  <div class="sv-hero-dots" aria-hidden="true"></div>
  <canvas id="sv-particles" aria-hidden="true"></canvas>
  <div class="sv-hero-orb sv-orb-1" aria-hidden="true"></div>
  <div class="sv-hero-orb sv-orb-2" aria-hidden="true"></div>
  <div class="sv-hero-inner">

    <div class="sv-hero-text" data-aos="fade-right" data-aos-duration="700">
      <span class="sv-eyebrow">
        <span class="material-symbols-rounded">sell</span>
        CMNS Mac · Buyback Chiang Mai
      </span>
      <h1 class="sv-h1">We Buy MacBook iPhone iPad iMac<br><span class="sv-h1-accent">Any Model, Any Condition</span></h1>
      <p class="sv-hero-sub">Broken, won't turn on, cracked screen, bad battery? We take it all!<br>Fair prices, fast payment, local pickup or shipping available.</p>
      <div class="sv-hero-cta">
        <a href="https://page.line.me/cmns" target="_blank" rel="noopener" class="btn btn-line">
          <img src="/assets/img/line-icon.png" alt="LINE" width="18" height="18"> Get a quote via LINE
        </a>
        <a href="#bb-devices" class="btn btn-ghost">
          Models we buy <span class="material-symbols-rounded">arrow_downward</span>
        </a>
      </div>
      <div class="sv-trust-pills">
        <span class="sv-pill"><span class="material-symbols-rounded">price_check</span> Free quote</span>
        <span class="sv-pill"><span class="material-symbols-rounded">handshake</span> Any condition</span>
        <span class="sv-pill"><span class="material-symbols-rounded">bolt</span> Instant pay</span>
      </div>
      <p class="bb-hero-note">Free valuation, no pressure to sell. Terms apply. We may decline valuation if the device doesn't meet our criteria or isn't of interest. In some cases, device inspection at the shop may be required.</p>
    </div>

    <div class="sv-hero-visual" data-aos="fade-left" data-aos-duration="700" data-aos-delay="120">
      <div class="sv-device-wrap">
        <img src="/assets/img/buyback/Macbook.webp" alt="We buy used MacBook iPhone iPad iMac in any condition, Chiang Mai" class="sv-device-img">
        <div class="sv-float-badge sv-fbadge-1">
          <span class="material-symbols-rounded">paid</span>
          <div><strong>1,857 devices</strong><span>bought so far</span></div>
        </div>
        <div class="sv-float-badge sv-fbadge-2">
          <span class="material-symbols-rounded">verified</span>
          <div><strong>Fair prices</strong><span>valued by real condition</span></div>
        </div>
      </div>
    </div>

  </div>
</section>

<!-- ═══════════════════════════════════════════════ ANCHOR NAV ═══════════════════════════════════════════════ -->
<div class="sv-nav-outer">
  <nav class="sv-nav" data-aos="fade-up" data-aos-offset="0" aria-label="Buyback page menu">
    <a href="#bb-devices" class="sv-nav-item"><span class="material-symbols-rounded">devices</span><span>Models</span></a>
    <a href="#bb-why"     class="sv-nav-item"><span class="material-symbols-rounded">verified_user</span><span>Why us</span></a>
    <a href="#bb-steps"   class="sv-nav-item"><span class="material-symbols-rounded">list_alt</span><span>How to sell</span></a>
    <a href="#bb-gallery" class="sv-nav-item"><span class="material-symbols-rounded">photo_library</span><span>Examples</span></a>
    <a href="#bb-faq"     class="sv-nav-item"><span class="material-symbols-rounded">help_outline</span><span>FAQ</span></a>
    <a href="#bb-contact" class="sv-nav-item"><span class="material-symbols-rounded">location_on</span><span>Contact</span></a>
  </nav>
</div>

<!-- ═══════════════════════════════════════════════ WHY US ═══════════════════════════════════════════════ -->
<section class="sv-section sv-services" id="bb-why">
  <div class="sv-container">
    <div class="sv-section-head" data-aos="fade-up">
      <span class="section-label">Why us</span>
      <h2>Why sell your Apple device to CMNS Mac?</h2>
      <p class="sv-desc">A local Chiang Mai shop — valued by real condition, no lowballing, fast payment, any condition accepted.</p>
    </div>
    <div class="sv-card-grid">
      <?php foreach ([
          ['thumb_up',       'Fairest prices in Chiang Mai', 'We evaluate based on the actual condition, no lowballing. Straight, honest pricing.'],
          ['flash_on',       'Fast evaluation',              'Get a preliminary quote quickly via LINE, usually within a few hours.'],
          ['handshake',      'We buy any condition',         "Pristine, minor flaws, cracked screen, won't turn on, iCloud locked — we consider them all."],
          ['local_shipping', 'Convenient',                   'Local pickup in Chiang Mai city and nearby areas, or ship it to us.'],
          ['paid',           'Instant payment',              'Once the price is agreed, get paid in cash or bank transfer immediately.'],
          ['storefront',     'A local shop for locals',      'Friendly and sincere service. Ask us anything.'],
      ] as $i => [$icon, $title, $desc]): ?>
      <div class="sv-card" data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 80 ?>">
        <div class="sv-card-icon"><span class="material-symbols-rounded"><?= $icon ?></span></div>
        <h3><?= $title ?></h3>
        <p><?= $desc ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════════ DEVICES WE BUY ═══════════════════════════════════════════════ -->
<section class="sv-section bb-devices" id="bb-devices">
  <div class="sv-container">
    <div class="sv-section-head" data-aos="fade-up">
      <span class="section-label">Models we buy</span>
      <h2>Which Apple devices do we buy?</h2>
      <p class="sv-desc">Any condition! From pristine to dead-on-arrival — send it for a free quote first.</p>
    </div>
    <div class="bb-device-grid">
      <?php foreach ([
          ['laptop_mac',  'All MacBook models', '/assets/img/buyback/Macbook.webp', 'MacBook Air & Pro, all screen sizes, Intel & M1–M4 chips from 2015 onwards. Any condition accepted.'],
          ['smartphone',  'All iPhone models',  '/assets/img/buyback/iPhone.webp',  'iPhone 11–16, all Pro / Pro Max / Mini / Plus, or ask about older models. Cracked or locked? Still considered.'],
          ['tablet_mac',  'All iPad models',    '/assets/img/buyback/iPad.webp',    'iPad, iPad Air, iPad Pro, iPad mini, all generations from 2015 onwards. Screen issues, bad battery — send your offer.'],
          ['desktop_mac', 'All iMac models',    '/assets/img/buyback/iMac.webp',    'iMac 21.5", 24", 27", Intel, M1, M3 chips from 2015 onwards. Perfect or faulty — we buy it.'],
      ] as $i => [$icon, $title, $img, $desc]): ?>
      <div class="bb-dcard" data-aos="fade-up" data-aos-delay="<?= ($i % 4) * 70 ?>">
        <div class="bb-dcard-img">
          <img src="<?= $img ?>" alt="We buy used <?= $title ?> in Chiang Mai" loading="lazy" decoding="async">
        </div>
        <div class="bb-dcard-body">
          <h3 class="bb-dcard-title"><span class="material-symbols-rounded"><?= $icon ?></span><?= $title ?></h3>
          <p><?= $desc ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <p class="bb-devices-foot">We also consider other devices like Apple Watch and accessories. Just ask!</p>
  </div>
</section>

<!-- ═══════════════════════════════════════════════ STEPS ═══════════════════════════════════════════════ -->
<section class="sv-section bb-process" id="bb-steps">
  <div class="sv-container">
    <div class="sv-section-head" data-aos="fade-up">
      <span class="section-label">How to sell</span>
      <h2>4 easy steps to sell your Apple device</h2>
    </div>
    <div class="sv-steps">
      <?php foreach ([
          ['1', 'photo_camera',  'Take photos',        'Clear shots from multiple angles, especially any flaws (if any).'],
          ['2', 'chat_bubble',   'Send info via LINE', 'Tell us the model, basic specs, and condition, along with the photos.'],
          ['3', 'request_quote', 'Receive our quote',  'We provide a preliminary price estimate quickly.'],
          ['4', 'local_mall',    'Meet up / ship & get paid', 'Meet for a physical check (in Chiang Mai) or ship it. Once agreed, get paid instantly!'],
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

<!-- ═══════════════════════════════════════════════ SAMPLE GALLERY ═══════════════════════════════════════════════ -->
<section class="sv-section bb-gallery" id="bb-gallery">
  <div class="sv-container">
    <div class="sv-section-head" data-aos="fade-up">
      <span class="section-label">Examples</span>
      <h2>Examples of devices we've bought</h2>
      <p class="sv-desc">We mean any condition! From mint to dead-on-arrival.</p>
    </div>
    <div class="splide" data-aos="fade-up">
      <div class="splide__track">
        <ul class="splide__list">
          <?php foreach ([
              ['sample-mbp-good.webp',      'MacBook Pro in good condition', 'MacBook Pro 2020 (Mint Condition)'],
              ['sample-iphone-broken.webp', 'iPhone with a cracked screen',  'iPhone 12 (Cracked screen, still accepted)'],
              ['sample-imac-ok.webp',       'iMac with normal wear',         'iMac 2019 (Some scratches, works fine)'],
              ['sample-ipad-bad.webp',      "iPad that won't turn on",       "iPad Air 3 (Won't turn on, bought for parts)"],
              ['sample-macbook-icloud.webp','iCloud locked MacBook',         'MacBook Air M1 (iCloud locked, we can consult)'],
          ] as [$img, $alt, $cap]): ?>
          <li class="splide__slide">
            <img src="/assets/img/buyback/<?= $img ?>" alt="<?= $alt ?>" loading="lazy">
            <figcaption class="splide-caption"><?= $cap ?></figcaption>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════════ STATS ═══════════════════════════════════════════════ -->
<section class="sv-stats">
  <div class="sv-container sv-stats-inner">
    <div class="sv-stat" data-aos="fade-up" data-aos-delay="0">
      <span class="material-symbols-rounded">sell</span>
      <strong class="counter" data-count="1857">0</strong>
      <span>devices bought</span>
    </div>
    <div class="sv-stat" data-aos="fade-up" data-aos-delay="80">
      <span class="material-symbols-rounded">price_check</span>
      <strong>Free</strong>
      <span>valuation, no charge</span>
    </div>
    <div class="sv-stat" data-aos="fade-up" data-aos-delay="160">
      <span class="material-symbols-rounded">bolt</span>
      <strong>Instant</strong>
      <span>payment once agreed</span>
    </div>
    <div class="sv-stat" data-aos="fade-up" data-aos-delay="240">
      <span class="material-symbols-rounded">handshake</span>
      <strong>Any</strong>
      <span>condition, even locked</span>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════════ FAQ ═══════════════════════════════════════════════ -->
<section class="sv-section sv-faq" id="bb-faq">
  <div class="sv-container sv-faq-wrap">
    <div class="sv-section-head" data-aos="fade-up">
      <span class="section-label">FAQ</span>
      <h2>Frequently asked questions</h2>
    </div>
    <div class="sv-faq-list" data-aos="fade-up">
      <?php foreach ($faqs as $f): ?>
      <div class="faq-item">
        <button class="faq-q" type="button">
          <span><?= htmlspecialchars($f[0], ENT_QUOTES, 'UTF-8') ?></span>
          <span class="material-symbols-rounded faq-arr">expand_more</span>
        </button>
        <div class="faq-a"><p><?= htmlspecialchars($f[1], ENT_QUOTES, 'UTF-8') ?></p></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════════ CTA ═══════════════════════════════════════════════ -->
<section class="sv-cta" id="bb-contact">
  <div class="sv-container">
    <div class="sv-cta-inner" data-aos="fade-up">
      <span class="material-symbols-rounded sv-cta-icon">sell</span>
      <h2>Got an Apple device to sell?<br>Send photos for a quote</h2>
      <p>Free valuation, no pressure · Any condition · Instant payment<br>Local pickup in Chiang Mai or ship it. Mon–Sat 9:00–19:00</p>
      <div class="sv-cta-btns">
        <a href="https://page.line.me/cmns" target="_blank" rel="noopener" class="btn btn-line">
          <img src="/assets/img/line-icon.png" alt="LINE" width="18" height="18"> Quote via LINE
        </a>
        <a href="tel:0841511684" class="btn btn-accent">
          <span class="material-symbols-rounded">call</span> 084-151-1684
        </a>
        <a href="https://maps.app.goo.gl/bDboFFwykRSCSMX7A" target="_blank" rel="noopener" class="btn btn-ghost">
          <span class="material-symbols-rounded">location_on</span> View map
        </a>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════════ MAP ═══════════════════════════════════════════════ -->
<section class="sv-section bb-map-section">
  <div class="sv-container">
    <div class="sv-section-head" data-aos="fade-up">
      <span class="section-label">Meetup point</span>
      <h2>Contact us / meetup point in Chiang Mai</h2>
    </div>
    <p class="bb-map-intro" data-aos="fade-up">Contact us for inquiries, device inspection appointments, or price evaluations. We primarily arrange meetups in Chiang Mai city or as agreed. Call or LINE us to discuss first.</p>
    <div class="bb-map-frame" data-aos="fade-up">
      <iframe
        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3497.641581228488!2d98.967748!3d18.751606100000004!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x30da3aa79be8e5db%3A0x1a948e6def350e!2z4LiL4LmI4Lit4LihIG1hYyDguYDguIrguLXguKLguIfguYPguKvguKHguYggKEZpeCBtYWMgQ2hpYW5nbWFpKQ!5e1!3m2!1sth!2sth!4v1748670162801!5m2!1sth!2sth"
        allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="CMNS Fix Mac map, Chiang Mai"></iframe>
    </div>
  </div>
</section>

</main>

<?php include_once '../../includes/footer_en.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.3/dist/js/splide.min.js"></script>
<script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
<script>
AOS.init({ duration: 700, once: true, offset: 60 });

/* Sample gallery carousel */
if (document.querySelector('.splide')) {
    new Splide('.splide', {
        type: 'loop',
        perPage: 3,
        gap: '1.25rem',
        autoplay: true,
        pauseOnHover: true,
        breakpoints: { 768: { perPage: 2 }, 576: { perPage: 1 } }
    }).mount();
}

/* FAQ accordion */
document.querySelectorAll('.faq-q').forEach(btn => {
    btn.addEventListener('click', () => {
        const item = btn.closest('.faq-item');
        const open = item.classList.contains('open');
        document.querySelectorAll('.faq-item.open').forEach(i => i.classList.remove('open'));
        if (!open) item.classList.add('open');
    });
});

/* Counter — animate once when scrolled into view */
document.querySelectorAll('.counter').forEach(counter => {
    const target = +counter.getAttribute('data-count');
    const io = new IntersectionObserver((entries, obs) => {
        if (!entries[0].isIntersecting) return;
        obs.disconnect();
        const step = Math.max(1, Math.ceil(target / 120));
        const tick = () => {
            const cur = +counter.innerText.replace(/,/g, '');
            if (cur < target) {
                counter.innerText = Math.min(cur + step, target).toLocaleString();
                requestAnimationFrame(tick);
            } else {
                counter.innerText = target.toLocaleString();
            }
        };
        tick();
    }, { threshold: 0.4 });
    io.observe(counter);
});

/* Hero particles */
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
    return {
      x: Math.random() * W, y: randomY ? Math.random() * H : H + 8,
      r: Math.random() * 1.6 + 0.4, vy: -(Math.random() * 0.35 + 0.12),
      vx: (Math.random() - 0.5) * 0.18, life: randomY ? Math.random() * maxLife : 0,
      maxLife: maxLife, isAccent: Math.random() > 0.55
    };
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
      let opacity;
      if (p.isAccent) {
        opacity = alpha * (dark ? 0.55 : 0.35);
        ctx.fillStyle = `rgba(252,116,4,${opacity})`;
      } else {
        opacity = alpha * (dark ? 0.22 : 0.12);
        ctx.fillStyle = dark ? `rgba(255,255,255,${opacity})` : `rgba(80,80,80,${opacity})`;
      }
      ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, 6.2832); ctx.fill();
    }
    raf = requestAnimationFrame(draw);
  }
  resize(); init(); draw();
  window.addEventListener('resize', function () { cancelAnimationFrame(raf); resize(); init(); draw(); }, { passive: true });
})();
</script>
</body>
</html>
