<?php
// English Page: /en/services/iphone.php
$page_title_en = "iPhone Repair Chiang Mai | All Models, Expert Technicians | cmnsfixmac";
$page_description_en = "iPhone repair shop in Chiang Mai. We service all models for cracked screens, battery issues, water damage, or power-on failures by expert technicians with warranty.";
$page_keywords_en = "iPhone repair Chiang Mai, fix iPhone Chiang Mai, iPhone screen replacement Chiang Mai, iPhone battery replacement Chiang Mai, iPhone not charging, cheap iPhone repair Chiang Mai";

require_once '../../includes/db.php'; 
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

    <link rel="alternate" hreflang="th" href="https://cmnsfixmac.com/services/iphone.php" />
    <link rel="alternate" hreflang="en" href="https://cmnsfixmac.com/en/services/iphone.php" />
    <link rel="alternate" hreflang="x-default" href="https://cmnsfixmac.com/en/services/iphone.php" />
    
    <link rel="canonical" href="https://cmnsfixmac.com/en/services/iphone.php">

    <meta property="og:title" content="<?= $page_title_en ?>">
    <meta property="og:description" content="<?= $page_description_en ?>">
    <meta property="og:type" content="website">
    <meta property="og:image" content="https://cmnsfixmac.com/assets/img/iphone-repair-og.jpg">
    <meta property="og:url" content="https://cmnsfixmac.com/en/services/iphone.php">
    <meta property="og:site_name" content="cmnsfixmac iPhone Repair Chiang Mai">
    <meta property="og:locale" content="en_US">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= $page_title_en ?>">
    <meta name="twitter:description" content="<?= $page_description_en ?>">
    <meta name="twitter:image" content="https://cmnsfixmac.com/assets/img/iphone-repair-og.jpg">

    <link rel="shortcut icon" href="/assets/img/favicon1.png">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/footer-style.css">
    <link rel="stylesheet" href="/services/assets/css/iphone-style.css"> 
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">

    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "FAQPage",
      "mainEntity": [
        {
          "@type": "Question",
          "name": "How much does iPhone repair cost in Chiang Mai?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "The cost of iPhone repair at cmnsfixmac in Chiang Mai depends on the model and the issue. For example, screen replacement starts from around 1,200 THB, and battery replacement starts from 990 THB. You can request a free estimate before any repair work."
          }
        },
        {
          "@type": "Question",
          "name": "How long does an iPhone repair take in Chiang Mai?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "At cmnsfixmac in Chiang Mai, common iPhone repairs take about 1-2 hours. If the required parts are in stock, you can often wait for it. For logic board repairs or more complex issues, it may take longer, and our technicians will inform you before proceeding."
          }
        },
        { 
            "@type": "Question",
            "name": "Where is the cmnsfixmac iPhone repair shop located in Chiang Mai?",
            "acceptedAnswer": {
                "@type": "Answer",
                "text": "The cmnsfixmac shop is located at 482 Moo 8, Chiang Mai-Hang Dong Rd, Mae Hia, Mueang, Chiang Mai. It's easily accessible, and you can find a map on our website or contact us for directions."
            }
        }
      ]
    }
    </script>
</head>
<body>
    <?php include_once '../../includes/header_en.php'; ?>

    <main>
        <section class="hero-iphone">
            <div class="hero-overlay">
                <div class="hero-text" data-aos="fade-up">
                    <h1>iPhone Repair Service for All Issues</h1> <p>All iPhone Models. Quality parts with warranty on every repair.</p> </div>
            </div>
        </section>

        <div class="floating-menu" data-aos="fade-up">
            <ul>
                <li><a href="/en/services/macbook.php"><span class="material-symbols-rounded">laptop_mac</span><div>MacBook Repair</div></a></li>
                <li><a href="/en/services/imac.php"><span class="material-symbols-rounded">desktop_mac</span><div>iMac Repair</div></a></li>
                <li class="active"><a href="/en/services/iphone.php"><span class="material-symbols-rounded">smartphone</span><div>iPhone Repair</div></a></li>
                <li><a href="/en/services/ipad.php"><span class="material-symbols-rounded">tablet_mac</span><div>iPad Repair</div></a></li>
                <li><a href="/en/services/apple-watch.php"><span class="material-symbols-rounded">watch</span><div>Apple Watch Repair</div></a></li>
                <li><a href="/en/services/airpods.php"><span class="material-symbols-rounded">headphones</span><div>AirPods Repair</div></a></li>
                <li><a href="/en/services/software.php"><span class="material-symbols-rounded">terminal</span><div>Program/OS Service</div></a></li>
            </ul>
        </div>

        <section class="iphone-services container">
            <h2 data-aos="fade-up">Comprehensive iPhone Repair Services</h2> <p class="section-desc" data-aos="fade-up" data-aos-delay="100">
                We repair all iPhone models, from iPhone 6s to iPhone 15 Pro Max. We use genuine parts and provide a warranty, whether it's a cracked screen, battery issue, camera failure, speaker problem, or water damage. Our expert team is ready to help.
            </p>
            <div class="service-grid">
                <div class="service-box" data-aos="fade-up" data-aos-delay="200">
                    <span class="material-symbols-rounded">display_settings</span>
                    <h3>iPhone Screen Replacement</h3>
                    <p>Cracked screen, unresponsive touch, lines on display, or black screen. OLED & LCD supported.</p>
                </div>
                <div class="service-box" data-aos="fade-up" data-aos-delay="300">
                    <span class="material-symbols-rounded">battery_alert</span>
                    <h3>Battery Replacement</h3>
                    <p>Degraded or swollen battery, not charging. Genuine battery replacement with battery health calibration.</p>
                </div>
                <div class="service-box" data-aos="fade-up" data-aos-delay="400">
                    <span class="material-symbols-rounded">water_damage</span>
                    <h3>Water Damage Repair</h3>
                    <p>Water damage to the logic board or screen. Component-level repair is more affordable than full replacement.</p>
                </div>
                <div class="service-box" data-aos="fade-up" data-aos-delay="500">
                    <span class="material-symbols-rounded">settings</span>
                    <h3>Other Component Repairs</h3>
                    <p>Replacement for cameras, charging ports, Face ID, speakers, and buttons for all models.</p>
                </div>
            </div>
        </section>

        <section class="why-row container">
            <h2 data-aos="fade-up">Why Repair with cmnsfixmac?</h2>
            <div class="why-row-items" data-aos="fade-up" data-aos-delay="100">
                <div class="why-row-item"><span class="material-symbols-rounded">verified</span><p>Warranty on All Repairs</p></div>
                <div class="why-row-item"><span class="material-symbols-rounded">search</span><p>Free Diagnostics</p></div>
                <div class="why-row-item"><span class="material-symbols-rounded">bolt</span><p>Genuine Parts, Fast Service</p></div>
                <div class="why-row-item"><span class="material-symbols-rounded">engineering</span><p>Specialized Technicians</p></div>
            </div>
        </section>

        <section class="iphone-price-tabs container">
            <h2 data-aos="fade-up">iPhone Repair Price List</h2> <div class="tab-buttons" data-aos="fade-up">
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
                    $stmt = $pdo->prepare("SELECT model, detail, detail_en, price FROM iphone_fix_pricing WHERE category = ?");
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
            <h2 data-aos="fade-up">Examples of Our iPhone Repairs</h2> <div class="fix-result-grid">
                <?php
                // Fetching both Thai and English titles from `repairs` table
                $stmt_repairs = $pdo->prepare("SELECT id, image, title, title_en, model, views FROM repairs WHERE LOWER(category) = 'iphone' ORDER BY created_at DESC LIMIT 6");
                $stmt_repairs->execute();
                while ($row = $stmt_repairs->fetch()):
                    $imagePath = '/uploads/' . htmlspecialchars($row['image']); // Root-relative path
                    $display_title = !empty(trim($row['title_en'] ?? '')) ? $row['title_en'] : ($row['title'] ?? 'iPhone Repair');
                ?>
                    <a href="/en/work-detail.php?id=<?= htmlspecialchars($row['id']) ?>" class="fix-result-box"
                        data-aos="fade-up">
                        <img src="<?= $imagePath ?>"
                            alt="iPhone Repair - <?= htmlspecialchars($display_title) ?> Model <?= htmlspecialchars($row['model']) ?>"
                            loading="lazy">
                        <div class="fix-result-info">
                            <h3><?= htmlspecialchars($display_title) ?></h3>
                            <p><?= htmlspecialchars($row['model']) ?></p>
                            <p class="views"><?= number_format($row['views']) ?> views</p>
                        </div>
                    </a>
                <?php endwhile; ?>
            </div>
            <div class="view-all-link" data-aos="fade-up">
                <a href="/en/works.php?category=iPhone" class="btn-orange">View All Work</a> </div>
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