<?php
// English Page: /en/services/imac.php
$page_title_en = "iMac Repair Chiang Mai | All Models, Expert Technicians | cmnsfixmac";
$page_description_en = "iMac repair shop in Chiang Mai. We service all iMac models for screen issues, power-on failures, SSD/RAM upgrades, by specialized technicians with warranty.";
$page_keywords_en = "iMac repair Chiang Mai, fix iMac Chiang Mai, iMac won't turn on, iMac screen replacement, upgrade iMac SSD Chiang Mai, iMac logic board repair";

require_once '../../includes/db.php'; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title_en ?></title>
    <meta name="description" content="<?= $page_description_en ?>">
    <meta name="keywords" content="<?= $page_keywords_en ?>">
    <meta name="robots" content="index, follow">
    
    <link rel="alternate" hreflang="th" href="https://cmnsfixmac.com/services/imac.php" />
    <link rel="alternate" hreflang="en" href="https://cmnsfixmac.com/en/services/imac.php" />
    <link rel="alternate" hreflang="x-default" href="https://cmnsfixmac.com/en/services/imac.php" />
    
    <link rel="canonical" href="https://cmnsfixmac.com/en/services/imac.php">

    <meta property="og:title" content="<?= $page_title_en ?>">
    <meta property="og:description" content="<?= $page_description_en ?>">
    <meta property="og:type" content="website">
    <meta property="og:image" content="https://cmnsfixmac.com/assets/img/imac-repair-og.jpg">
    <meta property="og:url" content="https://cmnsfixmac.com/en/services/imac.php">
    <meta property="og:site_name" content="cmnsfixmac iMac Repair Chiang Mai">
    <meta property="og:locale" content="en_US">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= $page_title_en ?>">
    <meta name="twitter:description" content="<?= $page_description_en ?>">
    <meta name="twitter:image" content="https://cmnsfixmac.com/assets/img/imac-repair-og.jpg">

    <link rel="shortcut icon" href="/assets/img/favicon1.png">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/footer-style.css">
    <link rel="stylesheet" href="/services/assets/css/imac-style.css"> 
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">

    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "FAQPage",
      "mainEntity": [
        {
          "@type": "Question",
          "name": "How much does iMac repair cost in Chiang Mai?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "The cost of iMac repair at cmnsfixmac in Chiang Mai depends on the model and the issue. For example, an iMac screen replacement can range from thousands to tens of thousands of Baht. SSD or RAM upgrades also have various prices depending on capacity. You can get a free preliminary estimate before deciding on the repair."
          }
        },
        {
          "@type": "Question",
          "name": "How long does it take to upgrade an iMac's SSD in Chiang Mai?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Upgrading an iMac's SSD at cmnsfixmac in Chiang Mai typically takes about 1-2 days, including installation and new software setup. If there's a queue or a specific part needs to be ordered, it might take slightly longer. The shop will inform you in advance."
          }
        },
        {
            "@type": "Question",
            "name": "Does cmnsfixmac also repair older iMac models in Chiang Mai?",
            "acceptedAnswer": {
                "@type": "Answer",
                "text": "Yes, at cmnsfixmac in Chiang Mai, we repair and upgrade many iMac models, including older ones. You can bring your device for our technicians to assess the issue and check the feasibility of repair or upgrade for your specific model."
            }
        }
      ]
    }
    </script>
</head>
<body>
<?php include_once '../../includes/header_en.php'; ?>

<main>
  <section class="hero-imac">
    <div class="hero-overlay">
      <div class="hero-text" data-aos="fade-up">
        <h1>iMac Repair Service for All Issues</h1> <p>All iMac Models. Quality parts with warranty on every repair.</p> </div>
    </div>
  </section>

  <div class="floating-menu" data-aos="fade-up">
    <ul>
        <li><a href="/en/services/macbook.php"><span class="material-symbols-rounded">laptop_mac</span><div>MacBook Repair</div></a></li>
        <li class="active"><a href="/en/services/imac.php"><span class="material-symbols-rounded">desktop_mac</span><div>iMac Repair</div></a></li>
        <li><a href="/en/services/iphone.php"><span class="material-symbols-rounded">smartphone</span><div>iPhone Repair</div></a></li>
        <li><a href="/en/services/ipad.php"><span class="material-symbols-rounded">tablet_mac</span><div>iPad Repair</div></a></li>
        <li><a href="/en/services/apple-watch.php"><span class="material-symbols-rounded">watch</span><div>Apple Watch Repair</div></a></li>
        <li><a href="/en/services/airpods.php"><span class="material-symbols-rounded">headphones</span><div>AirPods Repair</div></a></li>
        <li><a href="/en/services/software.php"><span class="material-symbols-rounded">terminal</span><div>Program/OS Service</div></a></li>
    </ul>
  </div>

  <section class="imac-services container">
    <h2 data-aos="fade-up">Comprehensive iMac Repair Services</h2> <p class="section-desc" data-aos="fade-up" data-aos-delay="100">
      We repair all iMac models, including iMac 21.5", 27", iMac M1, M3, for issues like screen damage, power failures, slowness, freezing, new macOS installation, or SSD upgrades by our professional team.
    </p>
    <div class="service-grid">
      <div class="service-box" data-aos="fade-up" data-aos-delay="200">
        <span class="material-symbols-rounded">display_settings</span>
        <h3>iMac Screen Replacement</h3>
        <p>Black screen, lines on screen, cracked, no display. We replace with full QC.</p>
      </div>
      <div class="service-box" data-aos="fade-up" data-aos-delay="300">
        <span class="material-symbols-rounded">memory</span>
        <h3>SSD / RAM Upgrade</h3>
        <p>Slow machine, long app launch times. Add an SSD or RAM for instant speed.</p>
      </div>
      <div class="service-box" data-aos="fade-up" data-aos-delay="400">
        <span class="material-symbols-rounded">power</span>
        <h3>Power-on Repair</h3>
        <p>iMac won't turn on, has sound but no video. We perform detailed board and PSU checks.</p>
      </div>
      <div class="service-box" data-aos="fade-up" data-aos-delay="500">
        <span class="material-symbols-rounded">terminal</span>
        <h3>macOS & Software</h3>
        <p>Install new macOS versions, Office, Adobe suite, Final Cut, Logic Pro, and more.</p>
      </div>
    </div>
  </section>

  <section class="why-row container">
    <h2 data-aos="fade-up">Why Repair with cmnsfixmac?</h2> <div class="why-row-items" data-aos="fade-up" data-aos-delay="100">
      <div class="why-row-item"><span class="material-symbols-rounded">verified</span><p>Warranty on All Repairs</p></div>
      <div class="why-row-item"><span class="material-symbols-rounded">search</span><p>Free Diagnostics</p></div>
      <div class="why-row-item"><span class="material-symbols-rounded">bolt</span><p>Genuine Parts, Fast Service</p></div>
      <div class="why-row-item"><span class="material-symbols-rounded">engineering</span><p>Specialized Technicians</p></div>
    </div>
  </section>

  <section class="imac-price-tabs container">
    <h2 data-aos="fade-up">iMac Repair Price List</h2> <div class="tab-buttons" data-aos="fade-up">
      <button class="tab-btn active" data-tab="display">Screen Replacement</button>
      <button class="tab-btn" data-tab="upgrade">SSD / RAM Upgrade</button>
      <button class="tab-btn" data-tab="board">Board / Power Repair</button>
    </div>
    <div class="tab-contents">
      <?php
      // Using English keys for categories
      $categories_en = ['display' => 'Screen Replacement', 'upgrade' => 'SSD / RAM Upgrade', 'board' => 'Board Repair'];
      foreach ($categories_en as $key => $title_en):
        // Fetching both Thai and English details
        $stmt = $pdo->prepare("SELECT model, detail, detail_en, price FROM imac_fix_pricing WHERE category = ?");
        $stmt->execute([$key]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
      ?>
      <div class="tab-content <?= $key === 'display' ? 'active' : '' ?>" id="<?= $key ?>">
        <table class="fix-table">
          <thead><tr><th>Model</th><th>Detail</th><th>Approx. Price</th></tr></thead>
          <tbody>
            <?php foreach ($results as $row): 
              // Prioritize showing English detail, fallback to Thai if needed
              $detail_display = !empty(trim($row['detail_en'] ?? '')) ? $row['detail_en'] : ($row['detail'] ?? 'N/A');
            ?>
              <tr>
                <td><?= htmlspecialchars($row['model']) ?></td>
                <td><?= htmlspecialchars($detail_display) ?></td>
                <td><?= htmlspecialchars($row['price']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="fix-result container">
    <h2 data-aos="fade-up">Examples of Our iMac Repairs</h2> <div class="fix-result-grid">
      <?php
      // Fetching both Thai and English titles from `repairs` table
      $stmt_repairs = $pdo->prepare("SELECT id, image, title, title_en, model, views FROM repairs WHERE LOWER(category) = 'imac' ORDER BY created_at DESC LIMIT 6");
      $stmt_repairs->execute();
      while ($row = $stmt_repairs->fetch()):
        $imagePath = '/uploads/' . htmlspecialchars($row['image']); // Root-relative path
        $display_title = !empty(trim($row['title_en'] ?? '')) ? $row['title_en'] : ($row['title'] ?? 'iMac Repair');
      ?>
      <a href="/en/work-detail.php?id=<?= htmlspecialchars($row['id']) ?>" class="fix-result-box" data-aos="fade-up">
        <img src="<?= $imagePath ?>" alt="iMac Repair - <?= htmlspecialchars($display_title) ?> Model <?= htmlspecialchars($row['model']) ?>" loading="lazy">
        <div class="fix-result-info">
          <h3><?= htmlspecialchars($display_title) ?></h3>
          <p><?= htmlspecialchars($row['model']) ?></p>
          <p class="views"><?= number_format($row['views']) ?> views</p>
        </div>
      </a>
      <?php endwhile; ?>
    </div>
    <div class="view-all-link" data-aos="fade-up">
      <a href="/en/works.php?category=iMac" class="btn-orange">View All Work</a> </div>
  </section>
</main>

<?php include_once '../../includes/footer_en.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
<script>
  // This JS is language-agnostic and should work fine.
  AOS.init({ duration: 800, once: true });
  document.querySelectorAll(".tab-btn").forEach(btn => {
    btn.addEventListener("click", () => {
      document.querySelectorAll(".tab-btn").forEach(b => b.classList.remove("active"));
      document.querySelectorAll(".tab-content").forEach(c => c.classList.remove("active"));
      btn.classList.add("active");
      document.getElementById(btn.dataset.tab).classList.add("active");
    });
  });
</script>
</body>
</html>