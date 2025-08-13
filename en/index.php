<?php include '../includes/db.php'; ?>
<?php
// Fetch the latest 3 videos from the youtube_videos table
$stmt = $pdo->query("SELECT * FROM youtube_videos ORDER BY created_at DESC LIMIT 3");
// Convert results to an array
$videos = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>MacBook Repair Chiang Mai | Expert Apple Service - CMNS FixMac</title>

  <link rel="alternate" hreflang="th" href="https://cmnsfixmac.com/" />
  <link rel="alternate" hreflang="en" href="https://cmnsfixmac.com/en/" />
  <link rel="alternate" hreflang="x-default" href="https://cmnsfixmac.com/en/" />

  <meta name="description"
    content="Chiang Mai MacBook repair shop. We fix iPhones, iPads, iMacs with expert Apple technicians, genuine parts, and service warranty. Customer reviews available.">
  <meta name="keywords" content="MacBook repair Chiang Mai, Apple repair Chiang Mai, iPhone screen replacement Chiang Mai, Mac service Chiang Mai, FixMac Chiang Mai">
  <meta name="author" content="CMNS FixMac - MacBook Repair Chiang Mai">
  <meta name="robots" content="index, follow">

  <meta property="og:title" content="Expert MacBook Repair in Chiang Mai - CMNS FixMac">
  <meta property="og:description"
    content="Professional Apple repair services in Chiang Mai by experienced technicians. Genuine parts, free estimates.">
  <meta property="og:image" content="https://cmnsfixmac.com/assets/img/og-cover.jpg">
  <meta property="og:url" content="https://cmnsfixmac.com/en/">
  <meta property="og:type" content="website">
  <meta property="og:locale" content="en_US">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="MacBook Repair Chiang Mai | CMNS FixMac">
  <meta name="twitter:description"
    content="Expert Apple technicians in Chiang Mai. Guaranteed repairs, genuine parts, with real customer reviews.">
  <meta name="twitter:image" content="https://cmnsfixmac.com/assets/img/og-cover.jpg">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/floating-buttons.css">
  <link rel="stylesheet" href="https://unpkg.com/aos@2.3.4/dist/aos.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
  <link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png" />
  <link rel="stylesheet" href="../assets/css/footer-style.css">



  <script async src="https://www.googletagmanager.com/gtag/js?id=G-3WXK9GWN7C"></script>
  <script>
    window.dataLayer = window.dataLayer || [];

    function gtag() {
      dataLayer.push(arguments);
    }
    gtag('js', new Date());
    gtag('config', 'G-3WXK9GWN7C');
  </script>

  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "ProfessionalService",
      "@id": "#business",
      "name": "CMNS FixMac - Apple Repair Chiang Mai",
      "image": "https://cmnsfixmac.com/assets/img/apple-logo.png",
      "url": "https://cmnsfixmac.com/en/",
      "telephone": "+66-84-151-1684",
      "priceRange": "฿฿",
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "482 Moo 8, Behind Kad Warun, Chiang Mai-Hang Dong Rd, Mae Hia, Mueang",
        "addressLocality": "Chiang Mai",
        "postalCode": "50100",
        "addressCountry": "TH"
      },
      "sameAs": [
        "https://www.facebook.com/CmnsShop",
        "https://www.youtube.com/@cmns-fixmac", "https://page.line.me/cmns",
        "https://www.tiktok.com/@cmns_fixmac"
      ]
    }
  </script>

  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Service",
      "serviceType": "MacBook Repair Chiang Mai",
      "provider": {
        "@id": "#business"
      },
      "description": "MacBook repair services: screen replacement, battery replacement, logic board repair, power issues, and more by expert technicians."
    }
  </script>

  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Service",
      "serviceType": "iMac Repair Chiang Mai",
      "provider": {
        "@id": "#business"
      },
      "description": "SSD upgrades, RAM upgrades, screen repair, logic board repair for all iMac models."
    }
  </script>

  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Service",
      "serviceType": "iPhone Repair Chiang Mai",
      "provider": {
        "@id": "#business"
      },
      "description": "All iPhone repairs: screen damage, battery issues, speaker/microphone problems, logic board repair, power issues."
    }
  </script>

  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Service",
      "serviceType": "iPad Repair Chiang Mai",
      "provider": {
        "@id": "#business"
      },
      "description": "iPad repair services: screen replacement, touch issues, battery replacement, logic board repair, display problems, charging port issues."
    }
  </script>

  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "FAQPage",
      "mainEntity": [{
          "@type": "Question",
          "name": "How long does MacBook repair take?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Usually 1-3 days, depending on the issue and parts availability."
          }
        },
        {
          "@type": "Question",
          "name": "Can I send my device for repair via delivery service?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Yes, you can send it via Grab or Kerry Express."
          }
        }
      ]
    }
  </script>

  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Review",
      "reviewRating": {
        "@type": "Rating",
        "ratingValue": "5",
        "bestRating": "5"
      },
      "author": {
        "@type": "Person",
        "name": "Customer from Google"
      },
      "reviewBody": "งานเร็ว น้องพนักงานอธิบายให้ข้อมูลแนะนำดี ร้านหาง่าย อยู่ติดถนนในหมูบ้านวรุณนิเวศน์ มีที่จอดรถในร่ม",
      "itemReviewed": {
        "@id": "#business"
      }
    }
  </script>

  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "VideoObject",
      "name": "MacBook Repair Shop Review Chiang Mai | CMNS FixMac",
      "description": "Video review of our MacBook and Apple repair shop in Chiang Mai by expert technicians from CMNS FixMac.",
      "thumbnailUrl": "https://img.youtube.com/vi/IEutM7RYaYs/maxresdefault.jpg",
      "uploadDate": "2024-11-25",
      "contentUrl": "https://www.youtube.com/shorts/IEutM7RYaYs",
      "embedUrl": "https://www.youtube.com/embed/IEutM7RYaYs",
      "publisher": {
        "@type": "Organization",
        "name": "CMNS FixMac",
        "logo": {
          "@type": "ImageObject",
          "url": "https://cmnsfixmac.com/assets/img/apple-logo.png"
        }
      }
    }
  </script>
</head>

<body>

  <?php include_once '../includes/header_en.php'; ?>
  <section class="hero">
    <div class="hero-content" data-aos="fade-up">
      <h1>Expert Apple MacBook Repair in Chiang Mai</h1>
      <h2>Comprehensive Repairs, Replacements, and Upgrades</h2>
      <p>
        Genuine parts, standard service by Apple-certified technicians with direct experience.<br>
        We offer various grades of parts to suit your budget and provide the best value.
      </p>
      <a href="#work" class="btn">View Our Work</a> <a href="#tools" class="btn">Test Your Device Before Visiting</a>
    </div>
  </section>

  <section class="feature-highlight-floating" data-aos="fade-up">
    <div class="feature-box">
      <span class="material-symbols-rounded">request_quote</span>
      <h3>Free Repair Estimate</h3>
      <p>No charge for initial diagnostics.</p>
    </div>
    <div class="feature-box">
      <span class="material-symbols-rounded">local_shipping</span>
      <h3>Mail-In / Delivery Repair</h3>
      <p>Via Kerry, Grab, or Lalamove.</p>
    </div>
    <div class="feature-box">
      <span class="material-symbols-rounded">engineering</span>
      <h3>Experienced Technicians</h3>
      <p>Specializing in Mac products.</p>
    </div>
    <div class="feature-box">
      <span class="material-symbols-rounded">autorenew</span>
      <h3>Buy - Sell - Trade-In</h3>
      <p>We buy used devices or parts.</p>
    </div>
  </section>

  <section class="section-work" id="work" data-aos="fade-up">
    <h2>Our Latest Repairs</h2>
    <div class="work-grid">
      <?php
      // TODO: Ensure 'title' and 'model' from 'repairs' table have English versions or handle display for English page (e.g., show only if English version exists, or add English description).
      // The alt text for images will also be in Thai if $row["title"] is Thai.
      $stmt = $pdo->query("SELECT * FROM repairs ORDER BY created_at DESC LIMIT 2");
      while ($row = $stmt->fetch()) {
        echo '<div class="work-card">';
        echo '<img src="../uploads/' . htmlspecialchars($row["image"]) . '" alt="' . htmlspecialchars($row["title"]) . '" loading="lazy">'; // Alt text will be Thai if title is Thai
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
      <a href="/en/services/macbook.php" class="service-box" style="text-decoration:none; color:inherit;">
        <img src="../assets/img/icons/icon-macbook.png" loading="lazy" alt="MacBook Repair Chiang Mai" class="service-icon">
        <h3 style="color:#0D1A3E; text-decoration:none;">MacBook Repair</h3>
        <p style="color:#0D1A3E; text-decoration:none;">Screen, logic board, battery repair; power issues.</p>
      </a>
      <a href="/en/services/imac.php" class="service-box" style="text-decoration:none; color:inherit;">
        <img src="../assets/img/icons/icon-imac.png" loading="lazy" alt="iMac Repair Chiang Mai" class="service-icon">
        <h3 style="color:#0D1A3E; text-decoration:none;">iMac Repair</h3>
        <p style="color:#0D1A3E; text-decoration:none;">SSD/RAM upgrades, screen & logic board repair for all models.</p>
      </a>
      <a href="/en/services/iphone.php" class="service-box extra" style="text-decoration:none; color:inherit;">
        <img src="../assets/img/icons/icon-iphone.png" loading="lazy" alt="iPhone & iPad Repair Chiang Mai" class="service-icon">
        <h3 style="color:#0D1A3E; text-decoration:none;">iPad / iPhone Repair</h3>
        <p style="color:#0D1A3E; text-decoration:none;">Screen, battery, speaker, camera, advanced logic board repair.</p>
      </a>
      <a href="/en/services/apple-watch.php" class="service-box extra" style="text-decoration:none; color:inherit;">
        <img src="../assets/img/icons/icon-applewatch.png" loading="lazy" alt="Apple Watch Repair Chiang Mai" class="service-icon">
        <h3 style="color:#0D1A3E; text-decoration:none;">Apple Watch Repair</h3>
        <p style="color:#0D1A3E; text-decoration:none;">Screen replacement, battery, peeling screen, cracked screen.</p>
      </a>
      <a href="/en/services/airpods.php" class="service-box extra" style="text-decoration:none; color:inherit;">
        <img src="../assets/img/icons/icon-airpods.png" loading="lazy" alt="AirPods Repair Chiang Mai" class="service-icon">
        <h3 style="color:#0D1A3E; text-decoration:none;">AirPods Repair</h3>
        <p style="color:#0D1A3E; text-decoration:none;">Battery drain, microphone issues, charging problems for all models.</p>
      </a>
      <a href="/en/services/software.php" class="service-box extra" style="text-decoration:none; color:inherit;">
        <img src="../assets/img/icons/icon-os.png" loading="lazy" alt="Software & OS Installation Chiang Mai" class="service-icon">
        <h3 style="color:#0D1A3E; text-decoration:none;">Software & OS Service</h3>
        <p style="color:#0D1A3E; text-decoration:none;">Install new macOS versions, work applications, specialized software.</p>
      </a>
    </div>
    <button id="toggleBtn">View More</button>
  </section>

  <section class="section-diagnose" data-aos="fade-up" id="tools">
    <h2>Online Diagnostic Tools</h2>
    <p>Try these online tools to test your device before bringing it in.</p>
    <div class="diagnose-wrapper">
      <div class="diagnose-buttons" id="diagnose-tools">
        <a href="/tester/keyboard-tester/" target="_blank" class="btn desktop-only">
          <span class="material-symbols-rounded">keyboard</span> Keyboard Tester
        </a>
        <a href="/tester/sounds-tester/" target="_blank" class="btn">
          <span class="material-symbols-rounded">volume_up</span> Speaker Tester
        </a>
        <a href="/tester/monitor-tester/" target="_blank" class="btn">
          <span class="material-symbols-rounded">monitor</span> Monitor Test
        </a>
        <a href="/tester/camera-tester/" target="_blank" class="btn">
          <span class="material-symbols-rounded">photo_camera</span> Camera Test
        </a>
        <a href="/tester/microphone-tester/" target="_blank" class="btn">
          <span class="material-symbols-rounded">mic</span> Microphone Test
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
        <div class="swiper-slide"><img src="../assets/img/store1.webp" loading="lazy"
            alt="CMNS FixMac - MacBook Repair Shop Front Chiang Mai"></div>
        <div class="swiper-slide"><img src="../assets/img/store2.webp" loading="lazy"
            alt="Reception Counter CMNS FixMac Chiang Mai"></div>
        <div class="swiper-slide"><img src="../assets/img/store3.webp" loading="lazy" alt="Inside CMNS FixMac - Apple Repair Chiang Mai"></div>
        <div class="swiper-slide"><img src="../assets/img/store4.webp" loading="lazy" alt="MacBook Servicing Area Chiang Mai - CMNS"></div>
        <div class="swiper-slide"><img src="../assets/img/store5.webp" loading="lazy" alt="Professional Apple Repair Tools at CMNS FixMac"></div>
        <div class="swiper-slide"><img src="../assets/img/store6.webp" loading="lazy" alt="iPhone, iPad, MacBook Repair Station CMNS Fixmac Chiang Mai"></div>
        <div class="swiper-slide"><img src="../assets/img/store7.webp" loading="lazy" alt="Customer at CMNS FixMac Chiang Mai Apple Repair"></div>
        <div class="swiper-slide"><img src="../assets/img/store8.webp" loading="lazy" alt="Exterior View CMNS FixMac Apple Repair Shop Chiang Mai"></div>
      </div>
      <div class="swiper-pagination"></div>
    </div>
  </section>

  <section class="section-reasons" data-aos="fade-up">
    <h2>Why Choose Us?</h2>
    <div class="reasons-grid">
      <div class="reason-box">
        <span class="material-symbols-rounded">support_agent</span>
        <h3>Free Diagnostics</h3>
        <p>We offer free problem assessment and repair cost estimation.</p>
      </div>
      <div class="reason-box">
        <span class="material-symbols-rounded">build_circle</span>
        <h3>High-Quality Parts</h3>
        <p>We use standard, modern tools and genuine parts, all guaranteed.</p>
      </div>
      <div class="reason-box">
        <span class="material-symbols-rounded">engineering</span>
        <h3>Expert Technicians</h3>
        <p>Our team specializes in Mac hardware and software repairs. Professional service guaranteed.</p>
      </div>
    </div>
  </section>

  <section class="section-review" data-aos="fade-up">
    <h2>Customer Reviews on Google</h2>
    <script src="https://static.elfsight.com/platform/platform.js" async></script>
    <div class="elfsight-app-257bd58d-8d43-4106-8bc8-09588ce23452" data-elfsight-app-lazy></div>
  </section>

  <section class="section-team" data-aos="fade-up">
    <h2>Our Team</h2>
    <div class="team-grid tighter">
      <div class="team-member">
        <img src="../assets/img/tech1.webp" alt="Jack - Lead MacBook Technician Chiang Mai CMNS FixMac">
        <h3>Jack (ช่างแจ็ค)</h3>
        <p>Mac & MacBook Specialist, 10+ years experience.</p>
      </div>
    </div>
    <div class="team-more" id="team-more">
      <div class="team-grid">
        <div class="team-member">
          <div class="img-hover-wrap">
            <img src="../assets/img/tech2.webp" loading="lazy" alt="Tak - macOS and iMac Repair Specialist Chiang Mai" class="default-img">
            <img src="../assets/img/tech2-hover.webp" loading="lazy" alt="Tak working on Apple OS repair at CMNS FixMac Chiang Mai" class="hover-img">
          </div>
          <h3>Tak (ช่างทัก)</h3>
          <p>OS and Software Specialist.</p>
        </div>
        <div class="team-member">
          <img src="../assets/img/tech3.webp" loading="lazy" alt="Bank - iPhone iPad Repair Intern CMNS FixMac Chiang Mai">
          <h3>Bank (แบงค์)</h3>
          <p>Intern Technician.</p>
        </div>
        <div class="team-member">
          <img src="../assets/img/tech4.webp" loading="lazy" alt="Mike - MacBook and Apple Device Repair Intern Chiang Mai">
          <h3>Mike (ไมค์)</h3>
          <p>Intern Technician.</p>
        </div>
        <div class="team-member">
          <img src="../assets/img/tech5.webp" loading="lazy" alt="Natt - Apple Repair Team Intern FixMac Chiang Mai">
          <h3>Natt (นัฐ)</h3>
          <p>Intern Technician.</p>
        </div>
      </div>
    </div>
    <button class="btn show-more-team" id="toggle-team-btn">View Full Team</button>
  </section>


  <section class="map-section" id="map" data-aos="fade-up">
  <h2> Our Location</h2>
  <div class="map-container">
    <iframe
      title="แผนที่ร้าน CMNS FixMac"
      src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2757.0147940546653!2d98.96466192390933!3d18.75005733629581!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x30da3aa79be8e5db%3A0x1a948e6def350e!2z4LiL4LmI4Lit4LihIG1hYyDguYDguIrguLXguKLguIfguYPguKvguKHguYggKEZpeCBtYWMgQ2hpYW5nbWFpKQ!5e0!3m2!1sth!2sth!4v1745215403938!5m2!1sth!2sth"
      width="100%" height="300" style="border:0;" allowfullscreen="" loading="lazy"
      referrerpolicy="no-referrer-when-downgrade">
    </iframe>
  </div>
</section>

  <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>

  <script src="../assets/js/main.js"></script>
  <script src="../assets/js/swiper-init.js"></script>
  <script src="../assets/js/aos-init.js"></script>
  <script src="../assets/js/script.js"></script>


  <?php include_once '../includes/footer_en.php'; ?>
  <script src="../assets/js/lazy-youtube.js"></script>
  <script src="../assets/js/preload-images.js"></script>

</body>

</html>