<?php
// English Page: /en/services/airpods.php
$page_title_en = "AirPods Repair in Chiang Mai | All Models, All Issues | cmnsfixmac";
$page_description_en = "AirPods repair shop in Chiang Mai. We service AirPods, AirPods Pro, AirPods Max for battery drain, charging case issues, water damage, no sound, by specialized technicians with warranty.";
$page_keywords_en = "AirPods repair Chiang Mai, AirPods battery replacement Chiang Mai, AirPods case not charging Chiang Mai, fix AirPods Chiang Mai, AirPods service";

require_once '../../includes/db.php'; // Corrected Path
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
    
    <link rel="alternate" hreflang="th" href="https://cmnsfixmac.com/services/airpods.php" />
    <link rel="alternate" hreflang="en" href="https://cmnsfixmac.com/en/services/airpods.php" />
    <link rel="alternate" hreflang="x-default" href="https://cmnsfixmac.com/en/services/airpods.php" />
    
    <link rel="canonical" href="https://cmnsfixmac.com/en/services/airpods.php">

    <meta property="og:title" content="<?= $page_title_en ?>">
    <meta property="og:description" content="<?= $page_description_en ?>">
    <meta property="og:type" content="website">
    <meta property="og:image" content="https://cmnsfixmac.com/assets/img/airpods-repair-og.jpg">
    <meta property="og:url" content="https://cmnsfixmac.com/en/services/airpods.php">
    <meta property="og:site_name" content="cmnsfixmac AirPods Repair Chiang Mai">
    <meta property="og:locale" content="en_US">


    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= $page_title_en ?>">
    <meta name="twitter:description" content="<?= $page_description_en ?>">
    <meta name="twitter:image" content="https://cmnsfixmac.com/assets/img/airpods-repair-og.jpg">

    <link rel="shortcut icon" href="/assets/img/favicon1.png">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/footer-style.css">
    <link rel="stylesheet" href="/services/assets/css/airpods-style.css"> <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
    
    <script type="application/ld+json">
    {
      // ... (JSON-LD for LocalBusiness in English) ...
    }
    </script>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "FAQPage",
      "mainEntity": [
        {
          "@type": "Question",
          "name": "How much is AirPods battery replacement in Chiang Mai?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "The price for AirPods battery replacement at cmnsfixmac Chiang Mai depends on the model (e.g., AirPods 1, 2, Pro, Max). It generally ranges from several hundred to over a thousand Baht per earpiece or case. You can inquire for the exact price for your model."
          }
        },
        {
          "@type": "Question",
          "name": "Can you fix an AirPod with low volume on one side in Chiang Mai?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Yes, we can. If you have an AirPod with low volume or no sound on one side, you can bring it to cmnsfixmac in Chiang Mai for a check-up. The issue could be related to the battery, speaker, or internal connections. Our technicians will assess it and inform you of the repair options."
          }
        },
        {
            "@type": "Question",
            "name": "My AirPods charging case is broken. Is it better to repair or buy a new one at cmnsfixmac Chiang Mai?",
            "acceptedAnswer": {
                "@type": "Answer",
                "text": "At cmnsfixmac Chiang Mai, we offer both repair services for AirPods charging cases and sell replacement cases (subject to stock). If the damage is minor, a repair might be more cost-effective. However, if it's severely damaged or unrepairable, buying a new case is an option. Our technicians are happy to provide recommendations."
            }
        }
      ]
    }
    </script>
</head>
<body>
<?php include_once '../../includes/header_en.php'; // Use English header ?>

<main>
  <section class="hero-airpods">
    <div class="hero-overlay">
      <div class="hero-text" data-aos="fade-up">
        <h1>AirPods Repair Service for All Issues</h1> <p>Battery Replacement, Case Repair, Water Damage, One-Sided Sound. Parts in stock, with warranty.</p> </div>
    </div>
  </section>

  <div class="floating-menu" data-aos="fade-up">
    <ul>
        <li><a href="/en/services/macbook.php"><span class="material-symbols-rounded">laptop_mac</span><div>MacBook Repair</div></a></li>
        <li><a href="/en/services/imac.php"><span class="material-symbols-rounded">desktop_mac</span><div>iMac Repair</div></a></li>
        <li><a href="/en/services/iphone.php"><span class="material-symbols-rounded">smartphone</span><div>iPhone Repair</div></a></li>
        <li><a href="/en/services/ipad.php"><span class="material-symbols-rounded">tablet_mac</span><div>iPad Repair</div></a></li>
        <li><a href="/en/services/apple-watch.php"><span class="material-symbols-rounded">watch</span><div>Apple Watch Repair</div></a></li>
        <li class="active"><a href="/en/services/airpods.php"><span class="material-symbols-rounded">headphones</span><div>AirPods Repair</div></a></li>
        <li><a href="/en/services/software.php"><span class="material-symbols-rounded">terminal</span><div>Program/OS Service</div></a></li>
    </ul>
  </div>

  <section class="airpods-services container">
    <h2 data-aos="fade-up">Comprehensive AirPods Repair Services</h2> <div class="service-grid">
      <div class="service-box" data-aos="fade-up" data-aos-delay="200">
        <span class="material-symbols-rounded">battery_alert</span>
        <h3>Battery Replacement</h3>
        <p>Fast battery drain, swollen battery, won't charge. Single earpiece replacement available.</p>
      </div>
      <div class="service-box" data-aos="fade-up" data-aos-delay="300">
        <span class="material-symbols-rounded">settings</span>
        <h3>Charging Case Repair</h3>
        <p>Case not charging, loose port, lid won't close. Repair or replacement services.</p>
      </div>
      <div class="service-box" data-aos="fade-up" data-aos-delay="400">
        <span class="material-symbols-rounded">water_damage</span>
        <h3>Water Damage Cleanup</h3>
        <p>Low sound, intermittent audio, water in speakers or on the board. We can clean and repair it.</p>
      </div>
    </div>
  </section>

  <section class="airpods-price-tabs container">
    <h2 data-aos="fade-up">AirPods Repair Price List</h2> <div class="tab-buttons" data-aos="fade-up">
      <button class="tab-btn active" data-tab="battery">Battery</button>
      <button class="tab-btn" data-tab="case">Charging Case</button>
      <button class="tab-btn" data-tab="water">Water Damage</button>
    </div>
    <div class="tab-contents">
      <?php
      // Using English keys for categories
      $categories_en = ['battery' => 'Battery', 'case' => 'Charging Case', 'water' => 'Water Damage'];
      foreach ($categories_en as $key => $title_en):
        // Fetching both Thai and English details
        $stmt = $pdo->prepare("SELECT model, detail, detail_en, price FROM airpods_fix_pricing WHERE category = ?");
        $stmt->execute([$key]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
      ?>
      <div class="tab-content <?= $key === 'battery' ? 'active' : '' ?>" id="<?= $key ?>">
        <table class="fix-table">
          <thead><tr><th>Model</th><th>Detail</th><th>Price</th></tr></thead>
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
    <h2 data-aos="fade-up">Examples of Our AirPods Repairs</h2> <div class="fix-result-grid">
      <?php
      // Fetching both Thai and English titles from `repairs` table
      $stmt = $pdo->prepare("SELECT id, image, title, title_en, model, views FROM repairs WHERE LOWER(category) = 'airpods' ORDER BY created_at DESC LIMIT 6");
      $stmt->execute();
      while ($row = $stmt->fetch()):
        $imagePath = '/uploads/' . htmlspecialchars($row['image']); // Root-relative path
        $display_title = !empty(trim($row['title_en'] ?? '')) ? $row['title_en'] : ($row['title'] ?? 'AirPods Repair');
      ?>
      <a href="/en/work-detail.php?id=<?= htmlspecialchars($row['id']) ?>" class="fix-result-box" data-aos="fade-up">
        <img src="<?= $imagePath ?>" alt="AirPods Repair - <?= htmlspecialchars($display_title) ?>" loading="lazy">
        <div class="fix-result-info">
          <h3><?= htmlspecialchars($display_title) ?></h3>
          <p><?= htmlspecialchars($row['model']) ?></p>
          <p class="views"><?= number_format($row['views']) ?> views</p>
        </div>
      </a>
      <?php endwhile; ?>
    </div>

    <div class="view-all-link" data-aos="fade-up">
      <a href="/en/works.php?category=AirPods" class="btn-orange">View All Work</a> </div>
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