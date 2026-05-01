<?php 
include '../includes/db.php'; 

/** @var \PDO $pdo */  // <--- ใส่บรรทัดนี้เพิ่มเข้าไป!
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>MacBook Repair Chiang Mai | Apple Specialist - CMNS FixMac</title>

  <link rel="alternate" hreflang="th" href="https://cmnsfixmac.com/" />
  <link rel="alternate" hreflang="en" href="https://cmnsfixmac.com/en/" />
  <link rel="alternate" hreflang="x-default" href="https://cmnsfixmac.com/en/" />

  <meta name="description" content="MacBook Repair Shop in Chiang Mai. iPhone, iPad, iMac repair by Apple specialists. Genuine parts, warranty included, real customer reviews.">
  <meta name="keywords" content="MacBook Repair, Apple Repair Shop, iPhone Screen Replacement, Mac Repair Chiang Mai, FixMac">
  <meta name="author" content="CMNS FixMac - MacBook Repair Chiang Mai">
  <meta name="robots" content="index, follow">

  <meta property="og:title" content="MacBook Repair Chiang Mai by Apple Specialist - CMNS FixMac">
  <meta property="og:description" content="Specialized Apple repair service. Real experience, genuine parts, free diagnosis in Chiang Mai.">
  <meta property="og:image" content="https://cmnsfixmac.com/assets/img/og-cover.jpg">
  <meta property="og:url" content="https://cmnsfixmac.com/en/">
  <meta property="og:type" content="website">
  <meta property="og:locale" content="en_US">

  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="MacBook Repair Chiang Mai | CMNS FixMac">
  <meta name="twitter:description" content="Apple specialists in Chiang Mai. Warranty included, genuine parts, real customer reviews.">
  <meta name="twitter:image" content="https://cmnsfixmac.com/assets/img/og-cover.jpg">

  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/floating-buttons.css">
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
      cursor: pointer;
    }
    .review-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 40px rgba(0,0,0,0.1) !important;
      border-color: #007bff;
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
    /* Avatar Colors */
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
      "name": "MacBook Repair Chiang Mai | CMNS FixMac",
      "image": "https://cmnsfixmac.com/assets/img/apple-logo.png",
      "url": "https://cmnsfixmac.com/en/",
      "telephone": "+66-84-151-1684",
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "482 Moo 8, Behind Kad Varun, Chiang Mai-Hang Dong Rd, Mae Hia",
        "addressLocality": "Mueang Chiang Mai",
        "postalCode": "50100",
        "addressCountry": "TH"
      }
    }
  </script>
</head>

<body>

  <?php include_once '../includes/header.php'; ?>

  <section class="hero">
    <div class="hero-content" data-aos="fade-up">
      <h1>MacBook Repair Chiang Mai by Apple Specialists</h1>
      <h2>Full Service: Repair, Replace, Upgrade</h2>
      <p>
        Genuine parts, standard service by experienced technicians directly skilled with Apple products.<br>
        Various grades available to suit your budget and value.
      </p>
      <a href="#work" class="btn">View Work</a>
      <a href="#tools" class="btn">Test Device Before Visit</a>
    </div>
  </section>

  <section class="feature-highlight-floating" data-aos="fade-up">
    <div class="feature-box">
      <span class="material-symbols-rounded">request_quote</span>
      <h3>Free Diagnosis</h3>
      <p>No check-up fee</p>
    </div>
    <div class="feature-box">
      <span class="material-symbols-rounded">local_shipping</span>
      <h3>Mail-in Service</h3>
      <p>Via Transport or Grab/Lalamove</p>
    </div>
    <div class="feature-box">
      <span class="material-symbols-rounded">engineering</span>
      <h3>Experienced Techs</h3>
      <p>Directly with Mac</p>
    </div>
    <div class="feature-box">
      <span class="material-symbols-rounded">autorenew</span>
      <h3>Buy - Sell - Trade</h3>
      <p>We buy old devices for parts</p>
    </div>
  </section>

  <section class="section-work" id="work" data-aos="fade-up">
    <h2>Recent Work</h2>
    <div class="work-grid">
      <?php
      $stmt = $pdo->query("SELECT * FROM repairs ORDER BY created_at DESC LIMIT 2");
      while ($row = $stmt->fetch()) {
        echo '<div class="work-card">';
        // Note: Title and Model from DB might still be in Thai
        echo '<img src="../uploads/' . htmlspecialchars($row["image"]) . '" alt="' . htmlspecialchars($row["title"]) . '" loading="lazy">';
        echo '<h3>' . htmlspecialchars($row["title"]) . '</h3>';
        echo '<p>' . htmlspecialchars($row["model"]) . '</p>';
        echo '</div>';
      }
      ?>
    </div>
    <br>
    <a href="works.php" class="btn">View More</a>
  </section>

  <section class="service-highlight">
    <h2>Our Services</h2>
    <div class="services-container">
      <a href="/services/macbook.php" class="service-box" style="text-decoration:none; color:inherit;">
        <img src="../assets/img/icons/icon-macbook.png" loading="lazy" alt="MacBook Repair" class="service-icon">
        <h3>MacBook Repair</h3>
        <p>Screen, Board, Battery, Dead unit, Startup issues.</p>
      </a>
      <a href="/services/imac.php" class="service-box" style="text-decoration:none; color:inherit;">
        <img src="../assets/img/icons/icon-imac.png" loading="lazy" alt="iMac Repair" class="service-icon">
        <h3>iMac Repair</h3>
        <p>SSD Upgrade, RAM, Screen repair, Board repair for all models.</p>
      </a>
      <a href="/services/iphone.php" class="service-box extra" style="text-decoration:none; color:inherit;">
        <img src="../assets/img/icons/icon-iphone.png" loading="lazy" alt="iPhone Repair" class="service-icon">
        <h3>iPad / iPhone Repair</h3>
        <p>Screen replacement, Battery, Speaker, Camera, Advanced board repair.</p>
      </a>
      <a href="/services/apple-watch.php" class="service-box extra" style="text-decoration:none; color:inherit;">
        <img src="../assets/img/icons/icon-applewatch.png" loading="lazy" alt="Apple Watch Repair" class="service-icon">
        <h3>Apple Watch Repair</h3>
        <p>Screen replacement, Battery, Delamination, Broken glass.</p>
      </a>
      <a href="/services/airpods.php" class="service-box extra" style="text-decoration:none; color:inherit;">
        <img src="../assets/img/icons/icon-airpods.png" loading="lazy" alt="AirPods Repair" class="service-icon">
        <h3>AirPods Repair</h3>
        <p>Battery drainage, Mic issues, Charging problems.</p>
      </a>
      <a href="/services/software.php" class="service-box extra" style="text-decoration:none; color:inherit;">
        <img src="../assets/img/icons/icon-os.png" loading="lazy" alt="Software Service" class="service-icon">
        <h3>Software & OS</h3>
        <p>macOS installation, Work software setup.</p>
      </a>
    </div>
    <button id="toggleBtn">View More</button>
  </section>

  <section class="section-diagnose" data-aos="fade-up" id="tools">
    <h2>Basic Diagnostic Tools</h2>
    <p>Test your device online before visiting our shop.</p>
    <div class="diagnose-wrapper">
      <div class="diagnose-buttons" id="diagnose-tools">
        <a href="/tester/keyboard-tester/" target="_blank" class="btn desktop-only">
          <span class="material-symbols-rounded">keyboard</span> Keyboard Test
        </a>
        <a href="/tester/sounds-tester/" target="_blank" class="btn">
          <span class="material-symbols-rounded">volume_up</span> Speaker Test
        </a>
        <a href="/tester/monitor-tester/" target="_blank" class="btn">
          <span class="material-symbols-rounded">monitor</span> Screen Test
        </a>
        <a href="/tester/camera-tester/" target="_blank" class="btn">
          <span class="material-symbols-rounded">photo_camera</span> Camera Test
        </a>
        <a href="/tester/microphone-tester/" target="_blank" class="btn">
          <span class="material-symbols-rounded">mic</span> Mic Test
        </a>
        <a href="/tester/touchscreen-tester/" target="_blank" class="btn mobile-only">
          <span class="material-symbols-rounded">touch_app</span> Touchscreen Test
        </a>
      </div>
    </div>
    <button class="toggle-btn" onclick="toggleDiagnose()">View More</button>
  </section>

  <section class="section-atmosphere" data-aos="fade-up">
    <h2>Our Shop Atmosphere</h2>
    <div class="swiper atmosphereSwiper">
      <div class="swiper-wrapper">
        <div class="swiper-slide"><img src="../assets/img/store1.webp" loading="lazy" alt="Store Front"></div>
        <div class="swiper-slide"><img src="../assets/img/store2.webp" loading="lazy" alt="Counter"></div>
        <div class="swiper-slide"><img src="../assets/img/store3.webp" loading="lazy" alt="Inside Store"></div>
        <div class="swiper-slide"><img src="../assets/img/store4.webp" loading="lazy" alt="Service Area"></div>
        <div class="swiper-slide"><img src="../assets/img/store5.webp" loading="lazy" alt="Genuine Parts"></div>
        <div class="swiper-slide"><img src="../assets/img/store6.webp" loading="lazy" alt="iPhone Repair"></div>
        <div class="swiper-slide"><img src="../assets/img/store7.webp" loading="lazy" alt="Customers"></div>
        <div class="swiper-slide"><img src="../assets/img/store8.webp" loading="lazy" alt="Outside View"></div>
      </div>
      <div class="swiper-pagination"></div>
    </div>
  </section>

  <section class="section-reasons" data-aos="fade-up">
    <h2>Why Choose Us?</h2>
    <div class="reasons-grid">
      <div class="reason-box">
        <span class="material-symbols-rounded">support_agent</span>
        <h3>Free Diagnosis</h3>
        <p>We are happy to answer questions and analyze problems to estimate repair costs for free.</p>
      </div>
      <div class="reason-box">
        <span class="material-symbols-rounded">build_circle</span>
        <h3>High Quality Parts</h3>
        <p>We use standard, modern tools and genuine parts. Ready to serve with warranty on all parts.</p>
      </div>
      <div class="reason-box">
        <span class="material-symbols-rounded">engineering</span>
        <h3>Expert Team</h3>
        <p>Our team specializes in Mac repair, both hardware and software. You can trust our professionalism.</p>
      </div>
    </div>
  </section>


<section class="section-review" data-aos="fade-up" style="background-color: #f9f9f9; padding: 60px 0;">
    <div style="max-width: 1300px; margin: 0 auto; padding: 0 15px;">
        <h2 style="text-align: center; margin-bottom: 40px; font-size: 2rem; color: #333;">Customer Reviews on Google <span style="color: #ea4335;">❤</span></h2>

        <div class="google-review-header" style="display: flex; align-items: center; justify-content: center; gap: 20px; margin-bottom: 40px; flex-wrap: wrap;">
            <div class="rating-score" style="text-align: center;">
                <span style="font-size: 3rem; font-weight: bold; color: #333; line-height: 1;">5.0</span>
                <div class="stars" style="color: #fbbc04; font-size: 1.2rem;">★★★★★</div>
                <p style="margin: 0; color: #777; font-size: 0.9rem;">From real customers on Google</p>
            </div>
            <a href="https://surl.li/pmpvgr" target="_blank" 
               style="background-color: #007bff; color: white; padding: 10px 20px; border-radius: 50px; text-decoration: none; font-weight: bold; display: flex; align-items: center; gap: 5px; transition: 0.3s;">
               <img src="https://fonts.gstatic.com/s/i/productlogos/googleg/v6/24px.svg" alt="Google" width="20" height="20">
               Write a Review
            </a>
        </div>

        <div class="review-grid">
            <div class="review-card" onclick="window.open('https://surl.li/pmpvgr', '_blank')">
                <div class="reviewer-profile">
                    <div class="avatar av-1">K</div>
                    <div class="info">
                        <h4 style="margin:0; font-size:1rem; color:#333;">Khun Somsak</h4>
                        <span style="color:#999; font-size:0.8rem;">2 weeks ago</span>
                    </div>
                    <img src="https://fonts.gstatic.com/s/i/productlogos/googleg/v6/24px.svg" width="20" height="20" alt="Google" class="google-icon-corner">
                </div>
                <div class="stars" style="color:#fbbc04; margin-bottom:10px;">★★★★★</div>
                <p class="review-text">"Great service. Took my MacBook for battery replacement, got it back instantly. The technician gave great advice, very transparent, friendly price. Highly recommended."</p>
            </div>

            <div class="review-card" onclick="window.open('https://surl.li/pmpvgr', '_blank')">
                <div class="reviewer-profile">
                    <div class="avatar av-2">J</div>
                    <div class="info">
                        <h4 style="margin:0; font-size:1rem; color:#333;">Jane Doe</h4>
                        <span style="color:#999; font-size:0.8rem;">1 month ago</span>
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
                        <span style="color:#999; font-size:0.8rem;">3 days ago</span>
                    </div>
                    <img src="https://fonts.gstatic.com/s/i/productlogos/googleg/v6/24px.svg" width="20" height="20" alt="Google" class="google-icon-corner">
                </div>
                <div class="stars" style="color:#fbbc04; margin-bottom:10px;">★★★★★</div>
                <p class="review-text">"Excellent work. New OS installation is super smooth. Thank you so much. Will definitely come back."</p>
            </div>

            <div class="review-card" onclick="window.open('https://surl.li/pmpvgr', '_blank')">
                <div class="reviewer-profile">
                    <div class="avatar av-4">N</div>
                    <div class="info">
                        <h4 style="margin:0; font-size:1rem; color:#333;">Nattapong</h4>
                        <span style="color:#999; font-size:0.8rem;">2 days ago</span>
                    </div>
                    <img src="https://fonts.gstatic.com/s/i/productlogos/googleg/v6/24px.svg" width="20" height="20" alt="Google" class="google-icon-corner">
                </div>
                <div class="stars" style="color:#fbbc04; margin-bottom:10px;">★★★★★</div>
                <p class="review-text">"Shop is behind Kad Varun, easy to find, parking available. Owner is easy to talk to, free check-up. Impressed."</p>
            </div>

            <div class="review-card" onclick="window.open('https://surl.li/pmpvgr', '_blank')">
                <div class="reviewer-profile">
                    <div class="avatar av-5">S</div>
                    <div class="info">
                        <h4 style="margin:0; font-size:1rem; color:#333;">Sarah J.</h4>
                        <span style="color:#999; font-size:0.8rem;">3 weeks ago</span>
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
                        <span style="color:#999; font-size:0.8rem;">1 month ago</span>
                    </div>
                    <img src="https://fonts.gstatic.com/s/i/productlogos/googleg/v6/24px.svg" width="20" height="20" alt="Google" class="google-icon-corner">
                </div>
                <div class="stars" style="color:#fbbc04; margin-bottom:10px;">★★★★★</div>
                <p class="review-text">"MacBook Pro M1 keyboard replacement, great job, feels like new. Much cheaper than the official center."</p>
            </div>

            <div class="review-card" onclick="window.open('https://surl.li/pmpvgr', '_blank')">
                <div class="reviewer-profile">
                    <div class="avatar av-7">A</div>
                    <div class="info">
                        <h4 style="margin:0; font-size:1rem; color:#333;">Ananda</h4>
                        <span style="color:#999; font-size:0.8rem;">5 days ago</span>
                    </div>
                    <img src="https://fonts.gstatic.com/s/i/productlogos/googleg/v6/24px.svg" width="20" height="20" alt="Google" class="google-icon-corner">
                </div>
                <div class="stars" style="color:#fbbc04; margin-bottom:10px;">★★★★★</div>
                <p class="review-text">"Brought my iMac for SSD upgrade. It's blazing fast now! Programs open instantly."</p>
            </div>

            <div class="review-card" onclick="window.open('https://surl.li/pmpvgr', '_blank')">
                <div class="reviewer-profile">
                    <div class="avatar av-8">T</div>
                    <div class="info">
                        <h4 style="margin:0; font-size:1rem; color:#333;">Hemmawan Wyatt-Carter</h4>
                        <span style="color:#999; font-size:0.8rem;">5 years ago</span>
                    </div>
                    <img src="https://fonts.gstatic.com/s/i/productlogos/googleg/v6/24px.svg" width="20" height="20" alt="Google" class="google-icon-corner">
                </div>
                <div class="stars" style="color:#fbbc04; margin-bottom:10px;">★★★★★</div>
                <p class="review-text">"Been a customer for over 10 years. My foreigner husband chose this shop because the owner speaks English well. Reasonable prices, honest, and they always inform the cost before repair. Excellent after-sales service."</p>
            </div>

            <div class="review-card" onclick="window.open('https://surl.li/pmpvgr', '_blank')">
                <div class="reviewer-profile">
                    <div class="avatar av-9">M</div>
                    <div class="info">
                        <h4 style="margin:0; font-size:1rem; color:#333;">Mayuree</h4>
                        <span style="color:#999; font-size:0.8rem;">1 week ago</span>
                    </div>
                    <img src="https://fonts.gstatic.com/s/i/productlogos/googleg/v6/24px.svg" width="20" height="20" alt="Google" class="google-icon-corner">
                </div>
                <div class="stars" style="color:#fbbc04; margin-bottom:10px;">★★★★★</div>
                <p class="review-text">"The owner is very kind and gave great advice on usage. Also gave a discount for students. Very lovely!"</p>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
             <a href="https://surl.li/pmpvgr" target="_blank" style="color: #007bff; text-decoration: underline;">View all reviews on Google Maps</a>
        </div>
    </div>
</section>

  <section class="section-team" data-aos="fade-up">
    <h2>Our Team</h2>
    <div class="team-grid tighter">
      <div class="team-member">
        <img src="../assets/img/tech1.webp" alt="Technician Jack">
        <h3>Technician Jack</h3>
        <p>Mac & MacBook Specialist (10+ Years Exp.)</p>
      </div>
    </div>
    <div class="team-more" id="team-more">
      <div class="team-grid">
        <div class="team-member">
          <div class="img-hover-wrap">
            <img src="../assets/img/tech2.webp" loading="lazy" class="default-img" alt="Technician Tak">
            <img src="../assets/img/tech2-hover.webp" loading="lazy" class="hover-img" alt="Technician Tak">
          </div>
          <h3>Technician Tak</h3>
          <p>OS & Software Specialist</p>
        </div>
        <div class="team-member">
          <img src="../assets/img/tech3.webp" loading="lazy" alt="Bank">
          <h3>Bank</h3>
          <p>Intern</p>
        </div>
        <div class="team-member">
          <img src="../assets/img/tech4.webp" loading="lazy" alt="Mike">
          <h3>Mike</h3>
          <p>Intern</p>
        </div>
        <div class="team-member">
          <img src="../assets/img/tech5.webp" loading="lazy" alt="Nat">
          <h3>Nat</h3>
          <p>Intern (Developer)</p>
        </div>
      </div>
    </div>
    <button class="btn show-more-team" id="toggle-team-btn">Show All Team</button>
  </section>

  <section class="map-section" id="map" data-aos="fade-up">
    <h2>Our Location</h2>
    <div class="map-container">
      <iframe 
        title="Map of CMNS FixMac"
        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3777.966030722375!2d98.96507877595568!3d18.751581165039343!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x30da3068631215b3%3A0x10609384752b04c8!2sFix%20Mac%20by%20CMNS!5e0!3m2!1sth!2sth!4v1703000000000!5m2!1sth!2sth"
        width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy"
        referrerpolicy="no-referrer-when-downgrade">
      </iframe>
    </div>
    <div style="text-align: center; margin-top: 15px;">
        <a href="https://www.google.com/maps/place/Fix+Mac+by+CMNS/@18.7515863,98.9650786,17z" target="_blank" class="btn">
            <span class="material-symbols-rounded">map</span> Open in Google Maps
        </a>
    </div>
  </section>

  <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
  
  <script src="../assets/js/main.js"></script>
  <script src="../assets/js/swiper-init.js"></script>
  <script src="../assets/js/aos-init.js"></script>
  <script src="../assets/js/script.js"></script>
  <?php include_once '../includes/floating-buttons.php'; ?>
  <script src="../assets/js/floating-buttons.js"></script>
  <?php include_once '../includes/footer.php'; ?>
  <script src="../assets/js/preload-images.js"></script>

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