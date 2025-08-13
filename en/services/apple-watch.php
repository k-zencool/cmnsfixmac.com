<?php
// English Page: /en/services/apple-watch.php
$page_title_en = "Apple Watch Repair Chiang Mai | All Series, Expert Technicians | cmnsfixmac";
$page_description_en = "Apple Watch repair shop in Chiang Mai. We service all models, Series 1-9 & Ultra, for cracked screens, battery issues, water damage, charging problems. Specialized technicians, with warranty.";
$page_keywords_en = "Apple Watch repair Chiang Mai, fix Apple Watch Chiang Mai, Apple Watch screen replacement Chiang Mai, Apple Watch battery replacement Chiang Mai, Apple Watch not charging";

require_once '../../includes/db.php'; // Corrected Path
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= $page_title_en ?></title>
    <meta name="description" content="<?= $page_description_en ?>">
    <meta name="keywords" content="<?= $page_keywords_en ?>">
    <meta name="robots" content="index, follow">
    
    <link rel="alternate" hreflang="th" href="https://cmnsfixmac.com/services/apple-watch.php" />
    <link rel="alternate" hreflang="en" href="https://cmnsfixmac.com/en/services/apple-watch.php" />
    <link rel="alternate" hreflang="x-default" href="https://cmnsfixmac.com/en/services/apple-watch.php" />
    
    <link rel="canonical" href="https://cmnsfixmac.com/en/services/apple-watch.php">

    <meta property="og:title" content="<?= $page_title_en ?>">
    <meta property="og:description" content="<?= $page_description_en ?>">
    <meta property="og:type" content="website">
    <meta property="og:image" content="https://cmnsfixmac.com/assets/img/applewatch-repair-og.jpg">
    <meta property="og:url" content="https://cmnsfixmac.com/en/services/apple-watch.php">
    <meta property="og:site_name" content="cmnsfixmac Apple Watch Repair Chiang Mai">
    <meta property="og:locale" content="en_US">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= $page_title_en ?>">
    <meta name="twitter:description" content="<?= $page_description_en ?>">
    <meta name="twitter:image" content="https://cmnsfixmac.com/assets/img/applewatch-repair-og.jpg">

    <link rel="shortcut icon" href="/assets/img/favicon1.png">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/footer-style.css">
    <link rel="stylesheet" href="/services/assets/css/apple-watch-style.css"> <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
    
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "FAQPage",
      "mainEntity": [
        {
          "@type": "Question",
          "name": "Is it expensive to repair an Apple Watch in Chiang Mai?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "The cost of repairing an Apple Watch at cmnsfixmac in Chiang Mai depends on the Series and the type of damage, such as screen or battery replacement. You can get a free preliminary estimate from our shop before deciding to proceed with the repair. We offer fair and reasonable prices."
          }
        },
        {
          "@type": "Question",
          "name": "My Apple Watch has water damage. Can it be fixed in Chiang Mai?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Yes. At cmnsfixmac in Chiang Mai, we repair water-damaged Apple Watches, including cleaning, circuit board inspection, and replacement of damaged parts. However, the success of the repair depends on the extent of the internal damage. We recommend bringing your device for an assessment as soon as possible."
          }
        },
        {
            "@type": "Question",
            "name": "How long does it take to replace an Apple Watch battery at cmnsfixmac in Chiang Mai?",
            "acceptedAnswer": {
                "@type": "Answer",
                "text": "Apple Watch battery replacement at cmnsfixmac in Chiang Mai is generally a quick process. If we have the correct part in stock, you can often wait and receive it within 1-2 hours, depending on the queue. For convenience, customers can book an appointment in advance."
            }
        }
      ]
    }
    </script>
</head>
<body>
<?php include_once '../../includes/header_en.php'; // Use English header ?>

<main>
  <section class="hero-applewatch">
    <div class="hero-overlay">
      <div class="hero-text" data-aos="fade-up">
        <h1>Apple Watch Repair Service for All Issues</h1> <p>Screen, Battery, Logic Board Repair, Water Damage, Not Charging. All Series.</p> </div>
    </div>
  </section>

  <div class="floating-menu" data-aos="fade-up">
    <ul>
      <li><a href="/en/services/macbook.php"><span class="material-symbols-rounded">laptop_mac</span><div>MacBook Repair</div></a></li>
      <li><a href="/en/services/imac.php"><span class="material-symbols-rounded">desktop_mac</span><div>iMac Repair</div></a></li>
      <li><a href="/en/services/iphone.php"><span class="material-symbols-rounded">smartphone</span><div>iPhone Repair</div></a></li>
      <li><a href="/en/services/ipad.php"><span class="material-symbols-rounded">tablet_mac</span><div>iPad Repair</div></a></li>
      <li class="active"><a href="/en/services/apple-watch.php"><span class="material-symbols-rounded">watch</span><div>Apple Watch Repair</div></a></li>
      <li><a href="/en/services/airpods.php"><span class="material-symbols-rounded">headphones</span><div>AirPods Repair</div></a></li>
      <li><a href="/en/services/software.php"><span class="material-symbols-rounded">terminal</span><div>Program/OS Service</div></a></li>
    </ul>
  </div>

  <section class="applewatch-services container">
    <h2 data-aos="fade-up">All-Model Apple Watch Repair Services</h2> <p class="section-desc" data-aos="fade-up" data-aos-delay="100">
      We repair all Apple Watch models, Series 1-9 and Ultra, covering issues like cracked screens, battery drain, power issues, logic board failure, and water damage, by expert technicians.
    </p>
    <div class="service-grid">
      <div class="service-box" data-aos="fade-up" data-aos-delay="200">
        <span class="material-symbols-rounded">display_settings</span>
        <h3>Screen Replacement</h3>
        <p>Cracked screen, unresponsive touch, black screen, or lines on display.</p>
      </div>
      <div class="service-box" data-aos="fade-up" data-aos-delay="300">
        <span class="material-symbols-rounded">battery_alert</span>
        <h3>Battery Replacement</h3>
        <p>Battery drain, swelling, won't charge. Genuine battery replacement with warranty.</p>
      </div>
      <div class="service-box" data-aos="fade-up" data-aos-delay="400">
        <span class="material-symbols-rounded">water_damage</span>
        <h3>Water Damage Repair</h3>
        <p>Water damage to the screen or board. Repair without needing a full replacement.</p>
      </div>
      <div class="service-box" data-aos="fade-up" data-aos-delay="500">
        <span class="material-symbols-rounded">settings</span>
        <h3>Logic Board Repair</h3>
        <p>Apple Watch won't turn on, not charging, or freezes.</p>
      </div>
    </div>
  </section>

  <section class="applewatch-price-tabs container">
    <h2 data-aos="fade-up">Apple Watch Repair Price List</h2> <div class="tab-buttons" data-aos="fade-up">
      <button class="tab-btn active" data-tab="display">Screen Replacement</button>
      <button class="tab-btn" data-tab="battery">Battery Replacement</button>
      <button class="tab-btn" data-tab="board">Board Repair</button>
    </div>
    <div class="tab-contents">
      <?php
      // Using English keys for categories
      $categories_en = ['display' => 'Screen Replacement', 'battery' => 'Battery Replacement', 'board' => 'Board Repair'];
      foreach ($categories_en as $key => $title_en):
        // Fetching both Thai and English details
        $stmt = $pdo->prepare("SELECT model, detail, detail_en, price FROM applewatch_fix_pricing WHERE category = ?");
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
    <h2 data-aos="fade-up">Examples of Our Apple Watch Repairs</h2> <div class="fix-result-grid">
      <?php
      // Fetching both Thai and English titles from `repairs` table
      $stmt_repairs = $pdo->prepare("SELECT id, image, title, title_en, model, views FROM repairs WHERE LOWER(category) = 'applewatch' ORDER BY created_at DESC LIMIT 6");
      $stmt_repairs->execute();
      while ($row = $stmt_repairs->fetch()):
        $imagePath = '/uploads/' . htmlspecialchars($row['image']); // Root-relative path
        $display_title = !empty(trim($row['title_en'] ?? '')) ? $row['title_en'] : ($row['title'] ?? 'Apple Watch Repair');
      ?>
      <a href="/en/work-detail.php?id=<?= htmlspecialchars($row['id']) ?>" class="fix-result-box" data-aos="fade-up">
        <img src="<?= $imagePath ?>" alt="Apple Watch Repair - <?= htmlspecialchars($display_title) ?> Model <?= htmlspecialchars($row['model']) ?>" loading="lazy">
        <div class="fix-result-info">
          <h3><?= htmlspecialchars($display_title) ?></h3>
          <p><?= htmlspecialchars($row['model']) ?></p>
          <p class="views"><?= number_format($row['views']) ?> views</p>
        </div>
      </a>
      <?php endwhile; ?>
    </div>
    <div class="view-all-link" data-aos="fade-up">
      <a href="/en/works.php?category=applewatch" class="btn-orange">View All Work</a> </div>
  </section>
</main>

<?php include_once '../../includes/footer_en.php'; // Use English footer ?>

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