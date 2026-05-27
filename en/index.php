<?php
include '../includes/db.php';

$page_title        = 'MacBook Repair Chiang Mai | Apple Specialist — CMNS FixMac';
$switch_to_lang_url = '/';
$page_css = [
  '/assets/css/style.css?v=23',
  '/assets/css/floating-buttons.css?v=2',
  'https://unpkg.com/aos@2.3.4/dist/aos.css',
  'https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css',
];
$page_head_extra = <<<'HTML'
<link rel="alternate" hreflang="th" href="https://cmnsfixmac.com/" />
<link rel="alternate" hreflang="en" href="https://cmnsfixmac.com/en/" />
<link rel="alternate" hreflang="x-default" href="https://cmnsfixmac.com/en/" />
<meta name="description" content="MacBook Repair Chiang Mai by Apple specialists. iPhone, iPad, iMac repair. Genuine parts, warranty on every job, real customer reviews. Free diagnosis.">
<meta name="keywords" content="MacBook Repair Chiang Mai, Apple Repair Shop, iPhone Screen Replacement, Mac Repair Chiang Mai, FixMac, Apple Specialist">
<meta name="author" content="CMNS FixMac - MacBook Repair Chiang Mai">
<meta name="robots" content="index, follow">
<link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png">
<meta property="og:title" content="MacBook Repair Chiang Mai by Apple Specialist — CMNS FixMac">
<meta property="og:description" content="Specialized Apple repair service. Real experience, genuine parts, free diagnosis in Chiang Mai.">
<meta property="og:image" content="https://cmnsfixmac.com/assets/img/og-cover.jpg">
<meta property="og:url" content="https://cmnsfixmac.com/en/">
<meta property="og:type" content="website">
<meta property="og:locale" content="en_US">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="MacBook Repair Chiang Mai | CMNS FixMac">
<meta name="twitter:description" content="Apple specialists in Chiang Mai. Warranty included, genuine parts, real customer reviews.">
<meta name="twitter:image" content="https://cmnsfixmac.com/assets/img/og-cover.jpg">
<script async src="https://www.googletagmanager.com/gtag/js?id=G-3WXK9GWN7C"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','G-3WXK9GWN7C');</script>
<script type="application/ld+json">{"@context":"https://schema.org","@type":"ProfessionalService","@id":"#business","name":"MacBook Repair Chiang Mai | CMNS FixMac","image":"https://cmnsfixmac.com/assets/img/apple-logo.png","url":"https://cmnsfixmac.com/en/","telephone":"+66-84-151-1684","priceRange":"฿฿","address":{"@type":"PostalAddress","streetAddress":"482 Moo 8, Behind Kad Varun, Chiang Mai-Hang Dong Rd, Mae Hia","addressLocality":"Mueang Chiang Mai","postalCode":"50100","addressCountry":"TH"},"sameAs":["https://www.facebook.com/CmnsShop","https://page.line.me/cmns"]}</script>
HTML;
include_once '../includes/header_en.php';
?>


  <!-- ═══════════════════════════════════════════════
       HERO
  ════════════════════════════════════════════════ -->
  <section class="hero">
    <canvas id="heroCanvas" class="hero-canvas" aria-hidden="true"></canvas>
    <div class="hero-orb hero-orb-1" aria-hidden="true"></div>
    <div class="hero-orb hero-orb-2" aria-hidden="true"></div>

    <div class="hero-inner">
      <!-- Text -->
      <div class="hero-text" data-aos="fade-right" data-aos-duration="800">
        <span class="hero-eyebrow">
          <span class="material-symbols-rounded">location_on</span>
          Chiang Mai — Apple Specialist
        </span>
        <h1>MacBook Repair<br><span class="hero-h1-accent">by Expert Technicians</span></h1>
        <p>Genuine parts, professional service with experienced Apple-certified technicians.<br>
           Full Apple product support in Chiang Mai.</p>
        <div class="hero-cta">
          <a href="tel:0841511684" class="btn btn-accent">
            <span class="material-symbols-rounded">call</span> Call for Free Consult
          </a>
          <a href="#work" class="btn btn-ghost">
            View Our Work
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
            <span>Genuine Parts, Warrantied</span>
          </div>
          <div class="trust-badge">
            <span class="material-symbols-rounded">bolt</span>
            <span>Fast Turnaround 1–3 Days</span>
          </div>
        </div>
      </div>

      <!-- Device visual -->
      <div class="hero-visual" data-aos="fade-left" data-aos-duration="800" data-aos-delay="150">
        <div class="hero-device-wrap">
          <img src="/assets/img/macbook.png" class="hero-device" alt="MacBook Repair Chiang Mai CMNS FixMac">

          <!-- Repair job card -->
          <div class="hero-badge-float hero-job-card hero-badge-1">
            <div class="job-card-header">
              <span class="material-symbols-rounded">build_circle</span>
              <span>Today's Jobs</span>
              <span class="job-live-dot"></span>
            </div>
            <div class="job-entry">
              <span class="job-dot job-dot--done"></span>
              <span class="job-name">MacBook Pro 14" M3</span>
              <span class="job-status-label job-label--done">Done</span>
            </div>
            <div class="job-entry">
              <span class="job-dot job-dot--progress"></span>
              <span class="job-name">iPhone 15 Pro Max</span>
              <span class="job-status-label job-label--progress">In Progress</span>
            </div>
            <div class="job-entry">
              <span class="job-dot job-dot--wait"></span>
              <span class="job-name">iMac 24" M1</span>
              <span class="job-status-label job-label--wait">Awaiting Part</span>
            </div>
          </div>

          <div class="hero-badge-float hero-badge-2">
            <span class="material-symbols-rounded">schedule</span>
            <div>
              <strong>Done in 2 hours</strong>
              <span>Battery replacement</span>
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
      <span class="ticker-item"><span class="ticker-dot ticker-dot--done">✓</span>MacBook Pro 14" M3 — Battery replaced</span>
      <span class="ticker-sep">·</span>
      <span class="ticker-item"><span class="ticker-dot ticker-dot--progress">●</span>iPhone 15 Pro Max — OLED screen swap in progress</span>
      <span class="ticker-sep">·</span>
      <span class="ticker-item"><span class="ticker-dot ticker-dot--done">✓</span>iMac 24" M1 — SSD 2TB upgrade complete</span>
      <span class="ticker-sep">·</span>
      <span class="ticker-item"><span class="ticker-dot ticker-dot--progress">●</span>MacBook Air M2 — Board burn inspection</span>
      <span class="ticker-sep">·</span>
      <span class="ticker-item"><span class="ticker-dot ticker-dot--done">✓</span>AirPods Pro 2 — Battery replacement done</span>
      <span class="ticker-sep">·</span>
      <span class="ticker-item"><span class="ticker-dot ticker-dot--wait">◐</span>Apple Watch S9 — Awaiting display part</span>
      <span class="ticker-sep">·</span>
      <span class="ticker-item"><span class="ticker-dot ticker-dot--done">✓</span>iPad Pro 12.9" — Connector replacement done</span>
      <span class="ticker-sep">·</span>
      <span class="ticker-item"><span class="ticker-dot ticker-dot--progress">●</span>MacBook Pro 16" Intel — GPU fault diagnosis</span>
      <span class="ticker-sep">·</span>
      <!-- duplicate for seamless loop -->
      <span class="ticker-item"><span class="ticker-dot ticker-dot--done">✓</span>MacBook Pro 14" M3 — Battery replaced</span>
      <span class="ticker-sep">·</span>
      <span class="ticker-item"><span class="ticker-dot ticker-dot--progress">●</span>iPhone 15 Pro Max — OLED screen swap in progress</span>
      <span class="ticker-sep">·</span>
      <span class="ticker-item"><span class="ticker-dot ticker-dot--done">✓</span>iMac 24" M1 — SSD 2TB upgrade complete</span>
      <span class="ticker-sep">·</span>
      <span class="ticker-item"><span class="ticker-dot ticker-dot--progress">●</span>MacBook Air M2 — Board burn inspection</span>
      <span class="ticker-sep">·</span>
      <span class="ticker-item"><span class="ticker-dot ticker-dot--done">✓</span>AirPods Pro 2 — Battery replacement done</span>
      <span class="ticker-sep">·</span>
      <span class="ticker-item"><span class="ticker-dot ticker-dot--wait">◐</span>Apple Watch S9 — Awaiting display part</span>
      <span class="ticker-sep">·</span>
      <span class="ticker-item"><span class="ticker-dot ticker-dot--done">✓</span>iPad Pro 12.9" — Connector replacement done</span>
      <span class="ticker-sep">·</span>
      <span class="ticker-item"><span class="ticker-dot ticker-dot--progress">●</span>MacBook Pro 16" Intel — GPU fault diagnosis</span>
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
        <span class="stat-box-label">Repairs Completed</span>
      </div>
      <div class="stat-box">
        <span class="stat-box-num">4.9★</span>
        <span class="stat-box-label">Google Reviews</span>
      </div>
      <div class="stat-box">
        <span class="stat-box-num">18+ Yrs</span>
        <span class="stat-box-label">Experience</span>
      </div>
      <div class="stat-box">
        <span class="stat-box-num">Free</span>
        <span class="stat-box-label">Diagnosis</span>
      </div>
    </div>
  </section>


  <!-- ═══════════════════════════════════════════════
       SERVICES
  ════════════════════════════════════════════════ -->
  <section class="service-highlight" id="services">
    <div class="section-inner">
      <span class="section-label">Services</span>
      <h2>Our Services</h2>
      <p class="section-desc">Repair, upgrade, genuine parts — all Apple products by certified specialists</p>

      <div class="services-bento">

        <!-- MacBook: featured 2-col -->
        <a href="/services/macbook.php" class="svc-card svc-card--featured" data-aos="fade-up" data-aos-delay="0">
          <span class="svc-badge">Most Popular</span>
          <div class="svc-img">
            <span class="material-symbols-rounded">laptop_mac</span>
          </div>
          <div class="svc-body">
            <h3>MacBook Repair</h3>
            <p>Screen, board, battery replacement. Dead units, boot issues — all models, Intel & Apple Silicon.</p>
            <div class="svc-tags">
              <span class="svc-tag">Battery</span>
              <span class="svc-tag">Screen</span>
              <span class="svc-tag">Board</span>
              <span class="svc-tag">Apple Silicon</span>
            </div>
            <div class="svc-stat">
              <strong>1,200+</strong>
              <span>MacBooks repaired</span>
            </div>
            <span class="svc-link">View Details <span class="material-symbols-rounded">arrow_forward</span></span>
          </div>
        </a>

        <!-- iMac -->
        <a href="/services/imac.php" class="svc-card" data-aos="fade-up" data-aos-delay="60">
          <div class="svc-img">
            <span class="material-symbols-rounded">desktop_mac</span>
          </div>
          <div class="svc-body">
            <h3>iMac Repair</h3>
            <p>SSD upgrade, RAM expansion, screen repair, board repair — all iMac models.</p>
            <div class="svc-tags">
              <span class="svc-tag">SSD</span>
              <span class="svc-tag">RAM</span>
              <span class="svc-tag">Screen</span>
              <span class="svc-tag">Board</span>
            </div>
            <span class="svc-link">View Details <span class="material-symbols-rounded">arrow_forward</span></span>
          </div>
        </a>

        <!-- iPhone / iPad -->
        <a href="/services/iphone.php" class="svc-card" data-aos="fade-up" data-aos-delay="0">
          <div class="svc-img">
            <span class="material-symbols-rounded">phone_iphone</span>
          </div>
          <div class="svc-body">
            <h3>iPhone / iPad Repair</h3>
            <p>Screen, battery, speaker, camera replacement. Advanced board-level repair.</p>
            <div class="svc-tags">
              <span class="svc-tag">Screen</span>
              <span class="svc-tag">Battery</span>
              <span class="svc-tag">Camera</span>
              <span class="svc-tag">Board</span>
            </div>
            <span class="svc-link">View Details <span class="material-symbols-rounded">arrow_forward</span></span>
          </div>
        </a>

        <!-- Apple Watch -->
        <a href="/services/apple-watch.php" class="svc-card" data-aos="fade-up" data-aos-delay="60">
          <div class="svc-img">
            <span class="material-symbols-rounded">watch</span>
          </div>
          <div class="svc-body">
            <h3>Apple Watch Repair</h3>
            <p>Screen, battery replacement. Delamination, cracked glass — all series.</p>
            <div class="svc-tags">
              <span class="svc-tag">Screen</span>
              <span class="svc-tag">Battery</span>
              <span class="svc-tag">All Series</span>
            </div>
            <span class="svc-link">View Details <span class="material-symbols-rounded">arrow_forward</span></span>
          </div>
        </a>

        <!-- AirPods -->
        <a href="/services/airpods.php" class="svc-card" data-aos="fade-up" data-aos-delay="120">
          <div class="svc-img">
            <span class="material-symbols-rounded">headphones</span>
          </div>
          <div class="svc-body">
            <h3>AirPods Repair</h3>
            <p>Battery drain, mic issues, charging problems — all models.</p>
            <div class="svc-tags">
              <span class="svc-tag">Battery</span>
              <span class="svc-tag">Mic</span>
              <span class="svc-tag">Charging</span>
            </div>
            <span class="svc-link">View Details <span class="material-symbols-rounded">arrow_forward</span></span>
          </div>
        </a>

        <!-- Software: wide -->
        <a href="/services/software.php" class="svc-card svc-card--wide" data-aos="fade-up" data-aos-delay="0">
          <div class="svc-img">
            <span class="material-symbols-rounded">terminal</span>
          </div>
          <div class="svc-body">
            <h3>Software & OS</h3>
            <p>macOS installation, productivity software setup, and all system troubleshooting for every Apple device.</p>
            <div class="svc-tags">
              <span class="svc-tag">macOS</span>
              <span class="svc-tag">Software</span>
              <span class="svc-tag">System</span>
            </div>
            <span class="svc-link">View Details <span class="material-symbols-rounded">arrow_forward</span></span>
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
      <h2>Latest Work</h2>
      <p class="section-desc">Real repair jobs from our team of technicians</p>

      <div class="work-meta-bar">
        <span><span class="material-symbols-rounded">check_circle</span> 1,200+ successful repairs</span>
        <span class="work-meta-dot"></span>
        <span><span class="material-symbols-rounded">schedule</span> Delivered within 24 hrs</span>
        <span class="work-meta-dot"></span>
        <span><span class="material-symbols-rounded">star</span> Rated 4.9 / 5</span>
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
          echo '<span class="work-badge-done"><span class="material-symbols-rounded">check_circle</span> Repaired</span>';
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
        <a href="/en/works/" class="work-cta-link">
          View all work
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
      <h2>Why Choose CMNS</h2>
      <p class="section-desc">More than just repair — it's the confidence you get back</p>

      <div class="reasons-list">

        <div class="reason-h">
          <div class="reason-h-meta">
            <span class="reason-num">01</span>
            <div class="reason-icon-wrap">
              <span class="material-symbols-rounded reason-icon">support_agent</span>
            </div>
          </div>
          <div class="reason-h-content">
            <h3>Free Diagnosis — No Inspection Fee</h3>
            <p>CMNS FixMac provides free diagnosis for MacBook, iPhone, iPad, iMac and all Apple products. No inspection fee, no hidden charges. Our technicians report the cause and repair cost before starting any work. Your approval is always required before we begin.</p>
            <div class="reason-chips">
              <span>Free Diagnosis</span>
              <span>Quote Before Repair</span>
              <span>No Hidden Fees</span>
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
            <h3>High-Quality Parts, Warranty on Every Repair</h3>
            <p>We use genuine and OEM-equivalent parts for MacBook Air, MacBook Pro, iMac, iPhone and iPad repairs across all models. All parts and workmanship are fully warrantied — covering screen replacement, battery swap, board repair, SSD and RAM upgrades.</p>
            <div class="reason-chips">
              <span>Genuine / OEM Parts</span>
              <span>Warranted Work</span>
              <span>Professional Tools</span>
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
            <h3>Apple-Specialist Technicians with 10+ Years Experience</h3>
            <p>Our team specializes exclusively in Apple repair — covering all MacBook models (Intel & Apple Silicon M1 M2 M3 M4), all iPhone series, iPad, iMac, Apple Watch, and AirPods. Both board-level hardware repair and all software issues are handled in-house.</p>
            <div class="reason-chips">
              <span>Apple Silicon M1–M4</span>
              <span>Board-level Repair</span>
              <span>10+ Years Exp.</span>
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
            <h3>Central Chiang Mai Location — Nationwide Mail-in Available</h3>
            <p>CMNS FixMac is located in the heart of Chiang Mai, easy to reach with parking on-site. We also accept mail-in repairs from anywhere in Thailand with online repair status tracking and LINE OA notifications around the clock.</p>
            <div class="reason-chips">
              <span>Central Chiang Mai</span>
              <span>Nationwide Mail-in</span>
              <span>Online Tracking</span>
            </div>
          </div>
        </div>

      </div>
    </div>
  </section>


  <!-- ═══════════════════════════════════════════════
       SELF-CHECK TOOLS
  ════════════════════════════════════════════════ -->
  <section class="section-diagnose" id="tools" data-aos="fade-up">
    <div class="section-inner">
      <span class="section-label">Self-Check</span>
      <h2>Test Your Device Before Visiting</h2>
      <p class="section-desc">Run a quick check yourself — free, no app download needed</p>

      <div class="diagnose-grid">
        <a href="/en/tester/keyboard-tester/" target="_blank" class="diagnose-card desktop-only">
          <div class="icon-wrap"><span class="material-symbols-rounded">keyboard</span></div>
          <span>Keyboard Test</span>
        </a>
        <a href="/en/tester/sounds-tester/" target="_blank" class="diagnose-card">
          <div class="icon-wrap"><span class="material-symbols-rounded">volume_up</span></div>
          <span>Speaker Test</span>
        </a>
        <a href="/en/tester/monitor-tester/" target="_blank" class="diagnose-card">
          <div class="icon-wrap"><span class="material-symbols-rounded">monitor</span></div>
          <span>Screen Test</span>
        </a>
        <a href="/en/tester/camera-tester/" target="_blank" class="diagnose-card">
          <div class="icon-wrap"><span class="material-symbols-rounded">photo_camera</span></div>
          <span>Camera Test</span>
        </a>
        <a href="/en/tester/microphone-tester/" target="_blank" class="diagnose-card">
          <div class="icon-wrap"><span class="material-symbols-rounded">mic</span></div>
          <span>Mic Test</span>
        </a>
        <a href="/en/tester/touchscreen-tester/" target="_blank" class="diagnose-card mobile-only">
          <div class="icon-wrap"><span class="material-symbols-rounded">touch_app</span></div>
          <span>Touchscreen Test</span>
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
      <h2>Shop Atmosphere</h2>
      <p class="section-desc">A professional repair environment with modern tools and equipment</p>
    </div>
    <div class="swiper atmosphereSwiper">
      <div class="swiper-wrapper">
        <div class="swiper-slide"><img src="/assets/img/store1.webp" loading="lazy" alt="CMNS FixMac shop front Chiang Mai"></div>
        <div class="swiper-slide"><img src="/assets/img/store2.webp" loading="lazy" alt="Reception counter at CMNS FixMac"></div>
        <div class="swiper-slide"><img src="/assets/img/store3.webp" loading="lazy" alt="Inside the MacBook repair shop Chiang Mai"></div>
        <div class="swiper-slide"><img src="/assets/img/store4.webp" loading="lazy" alt="MacBook repair area CMNS"></div>
        <div class="swiper-slide"><img src="/assets/img/store5.webp" loading="lazy" alt="Apple repair tools and equipment"></div>
        <div class="swiper-slide"><img src="/assets/img/store6.webp" loading="lazy" alt="iPhone iPad MacBook repair at CMNS FixMac"></div>
        <div class="swiper-slide"><img src="/assets/img/store7.webp" loading="lazy" alt="Customers at CMNS FixMac Chiang Mai"></div>
        <div class="swiper-slide"><img src="/assets/img/store8.webp" loading="lazy" alt="Apple repair shop Chiang Mai exterior"></div>
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
      <h2>What Our Customers Say</h2>
      <p class="section-desc">Real reviews from Google Maps — unfiltered, unedited</p>
      <div class="elfsight-app-257bd58d-8d43-4106-8bc8-09588ce23452" data-elfsight-lazy></div>
    </div>
  </section>


  <!-- ═══════════════════════════════════════════════
       TEAM
  ════════════════════════════════════════════════ -->
  <section class="section-team" data-aos="fade-up">
    <div class="section-inner">
      <span class="section-label">Our Team</span>
      <h2>Meet the Technicians</h2>
      <p class="section-desc">Apple-dedicated specialists with over 10 years of hands-on experience</p>

      <div class="team-cards">

        <div class="team-card">
          <div class="team-card-photo">
            <img src="/assets/img/tech1.webp" alt="Jack — Lead Technician at CMNS FixMac Chiang Mai">
            <span class="team-exp-badge">18+ Yrs</span>
          </div>
          <div class="team-card-info">
            <h3>Technician Jack</h3>
            <p class="team-role">Lead Technician — Mac & Hardware Specialist</p>
            <p class="team-bio">Specializes in MacBook repair across all models — Intel and Apple Silicon (M1–M4). Board-level soldering, screen replacement, battery swap, and SSD/RAM upgrades for MacBook Air, MacBook Pro, and iMac.</p>
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
            <img src="/assets/img/tech2.webp" loading="lazy" alt="Tak — Software & iOS Specialist at CMNS FixMac" class="default-img">
            <img src="/assets/img/tech2-hover.webp" loading="lazy" alt="Technician Tak repairing Apple devices at CMNS FixMac" class="hover-img">
            <span class="team-exp-badge">10+ Yrs</span>
          </div>
          <div class="team-card-info">
            <h3>Technician Tak</h3>
            <p class="team-role">Software, iOS & Mobile Specialist</p>
            <p class="team-bio">Expert in macOS and iOS systems. Repairs iPhone across all series, iPad, Apple Watch. Handles all software issues from system recovery to complex OS troubleshooting.</p>
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
      <span class="section-label">Contact Us</span>
      <h2>Ready to Help with Any Apple Problem</h2>
      <p class="section-desc">Free consultation, no obligation — just reach out</p>

      <div class="contact-channels">

        <div class="contact-col">
          <p class="contact-col-label">Phone</p>
          <a href="tel:0841511684" class="contact-phone-hero">084-151-1684</a>
          <p class="contact-col-sub">Call us Mon–Sat 09:00–18:00</p>
        </div>

        <div class="contact-col-divider"></div>

        <div class="contact-col">
          <p class="contact-col-label">Chat / Social</p>
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
          <p class="contact-col-label">Location</p>
          <p class="contact-address">482 Moo 8, Behind Kad Varun<br>Chiang Mai–Hang Dong Rd, Chiang Mai</p>
          <div class="contact-hours">
            <span class="material-symbols-rounded">schedule</span>
            Mon–Sat 09:00–18:00
          </div>
          <a href="https://maps.google.com/maps?q=18.75005733629581,98.96466192390933" target="_blank" rel="noopener" class="contact-map-link">
            <span class="material-symbols-rounded">near_me</span> Get Directions
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
      <h2>Find Us</h2>
      <p class="section-desc">482 Moo 8, Behind Kad Varun, Chiang Mai–Hang Dong Rd, Mae Hia, Chiang Mai</p>
      <div class="map-container">
        <iframe
          title="Map of CMNS FixMac Chiang Mai"
          src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2757.0147940546653!2d98.96466192390933!3d18.75005733629581!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x30da3aa79be8e5db%3A0x1a948e6def350e!2z4LiL4LmI4Lit4LihIG1hYyDguYDguIrguLXguKLguIfguYPguKvguKHguYggKEZpeCBtYWMgQ2hpYW5nbWFpKQ!5e0!3m2!1sen!2sth!4v1745215403938!5m2!1sen!2sth"
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
  <script src="/assets/js/aos-init.js"></script>
  <script src="/assets/js/script.js"></script>

  <div class="grain-overlay" aria-hidden="true"></div>

  <?php include_once '../includes/floating-buttons.php'; ?>
  <script src="/assets/js/hero-particles.js"></script>

  <script>
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

  <?php include_once '../includes/footer_en.php'; ?>

  <script>
    window.addEventListener('pageshow', function(e) {
      if (e.persisted) window.location.reload();
    });
  </script>

</body>
</html>
