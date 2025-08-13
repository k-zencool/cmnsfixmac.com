<?php
// Assuming this file is /en/article-detail.php
include '../includes/db.php'; // Path changed

function e($string)
{
  return htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8');
}

$slug = $_GET['slug'] ?? '';
if (!$slug) {
  header("Location: articles.php"); // Will redirect to /en/articles.php
  exit;
}

// Fetch article data including new English columns and aliasing old Thai ones
$stmt = $pdo->prepare("SELECT *, 
                        title AS title_th, content AS content_th, excerpt AS excerpt_th, slug AS slug_th
                      FROM articles 
                      WHERE (slug = :slug OR slug_en = :slug) AND status = 1 LIMIT 1");
$stmt->execute([':slug' => $slug]);
$article = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$article) {
  http_response_code(404);
  // TODO: Create a proper 404 page with admin layout
  echo "<h1>Article Not Found</h1>"; // Translated
  exit;
}

$article_id = $article['id'];

// Increment views
$pdo->prepare("UPDATE articles SET views = views + 1 WHERE id = ?")->execute([$article_id]);

// Fetch additional images including English captions
$stmtImg = $pdo->prepare("SELECT *, caption AS caption_th FROM article_images WHERE article_id = ?");
$stmtImg->execute([$article_id]);
$images = $stmtImg->fetchAll(PDO::FETCH_ASSOC);

// --- English Content Logic with Fallbacks ---
$slug_en = $article['slug_en'] ?? '';
$slug_th = $article['slug_th'] ?? '';
$final_slug = !empty(trim($slug_en)) ? $slug_en : $slug_th; // Use English slug if it exists
$full_url_en = "https://cmnsfixmac.com/en/article-detail.php?slug=" . urlencode($final_slug);

$title_en = $article['title_en'] ?? '';
$title_th = $article['title_th'] ?? '';
$display_title_en = !empty(trim($title_en)) ? e($title_en) : (!empty(trim($title_th)) ? e($title_th) . " (Details in Thai)" : "Article ID: #" . e($article_id));

$content_en = $article['content_en'] ?? '';
$content_th = $article['content_th'] ?? '';
$display_content_en = !empty(trim($content_en)) ? nl2br($content_en) : (!empty(trim($content_th)) ? "<em>(Full article content below is in Thai)</em><br><br>" . nl2br($content_th) : "<em>Article content will be available in English soon.</em>");

$excerpt_en = $article['excerpt_en'] ?? '';
$excerpt_th = $article['excerpt_th'] ?? '';
$meta_description_en = !empty(trim($excerpt_en)) ? e(mb_substr(strip_tags($excerpt_en), 0, 160)) : (!empty(trim($content_en)) ? e(mb_substr(strip_tags($content_en), 0, 160)) : "Read this article from CMNS FixMac: " . $display_title_en);
$main_image_alt_en = "Main image for " . $display_title_en;

$tags_en = $article['tags_en'] ?? ($article['tags'] ?? 'Apple, MacBook, iPhone, Repair, Chiang Mai');

// Fetch related, popular, prev/next articles
$select_cols = "id, title_en, title AS title_th, slug_en, slug AS slug_th, image, content, excerpt, category";
$relatedStmt = $pdo->prepare("SELECT {$select_cols} FROM articles WHERE category = ? AND id != ? AND status = 1 ORDER BY created_at DESC LIMIT 4");
$relatedStmt->execute([$article['category'], $article_id]);
$related = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);

$popularStmt = $pdo->prepare("SELECT {$select_cols} FROM articles WHERE id != ? AND status = 1 ORDER BY views DESC LIMIT 4");
$popularStmt->execute([$article_id]);
$popular = $popularStmt->fetchAll(PDO::FETCH_ASSOC);

$prevStmt = $pdo->prepare("SELECT id, title_en, title AS title_th, slug_en, slug AS slug_th FROM articles WHERE id < ? AND status = 1 ORDER BY id DESC LIMIT 1");
$prevStmt->execute([$article_id]);
$prev = $prevStmt->fetch(PDO::FETCH_ASSOC);

$nextStmt = $pdo->prepare("SELECT id, title_en, title AS title_th, slug_en, slug AS slug_th FROM articles WHERE id > ? AND status = 1 ORDER BY id ASC LIMIT 1");
$nextStmt->execute([$article_id]);
$next = $nextStmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title><?= $display_title_en ?> | CMNS FixMac</title>

  <?php
  // --- Hreflang Tags for Article Detail Page (by SLUG) ---
  // โค้ดนี้จะทำงานหลังจากที่มึงดึงข้อมูลบทความมาใส่ในตัวแปร $article แล้ว

  // ดึง slug ของทั้งสองภาษามาจาก $article 
  // (เราต้อง SELECT slug, slug_en มาจาก DB ด้วยนะ)
  $slug_th = $article['slug'] ?? '';
  // ถ้า slug_en มีค่าและไม่ว่างเปล่า ก็ใช้ slug_en, ถ้าไม่มีก็ใช้ slug_th แทนไปก่อน
  $slug_en = !empty(trim($article['slug_en'] ?? '')) ? $article['slug_en'] : $slug_th;

  if (!empty($slug_th)) {
    $th_url = "https://cmnsfixmac.com/article-detail.php?slug=" . urlencode($slug_th);
    $en_url = "https://cmnsfixmac.com/en/article-detail.php?slug=" . urlencode($slug_en);

    echo '<link rel="alternate" hreflang="th" href="' . htmlspecialchars($th_url) . '" />' . "\n";
    echo '    <link rel="alternate" hreflang="en" href="' . htmlspecialchars($en_url) . '" />' . "\n";
    echo '    <link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($en_url) . '" />' . "\n";
  }
  ?>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= $meta_description_en ?>">
  <meta name="keywords" content="<?= e($tags_en) ?>">

  <link rel="stylesheet" href="/assets/css/navbar-style.css">
  <link rel="stylesheet" href="/assets/css/article-detail-style.css">
  <link rel="stylesheet" href="/assets/css/footer-style.css">
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png" />
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
  <link rel="canonical" href="<?= $full_url_en ?>">

  <meta property="og:title" content="<?= $display_title_en ?> | CMNS FixMac">
  <meta property="og:description" content="<?= $meta_description_en ?>">
  <meta property="og:image" content="https://cmnsfixmac.com/uploads/<?= e($article['image']) ?>">
  <meta property="og:url" content="<?= $full_url_en ?>">
  <meta property="og:type" content="article">
  <meta property="og:locale" content="en_US">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= $display_title_en ?>">
  <meta name="twitter:description" content="<?= $meta_description_en ?>">
  <meta name="twitter:image" content="https://cmnsfixmac.com/uploads/<?= e($article['image']) ?>">

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
      "@type": "Article",
      "headline": "<?= e($display_title_en) ?>",
      "image": ["https://cmnsfixmac.com/uploads/<?= e($article['image']) ?>"],
      "author": {
        "@type": "Organization",
        "name": "CMNS FixMac"
      },
      "publisher": {
        "@type": "Organization",
        "name": "CMNS FixMac",
        "logo": {
          "@type": "ImageObject",
          "url": "https://cmnsfixmac.com/assets/img/Logo1.png"
        }
      },
      "datePublished": "<?= date('Y-m-d', strtotime($article['created_at'])) ?>",
      "dateModified": "<?= date('Y-m-d', strtotime($article['updated_at'] ?? $article['created_at'])) ?>",
      "mainEntityOfPage": {
        "@type": "WebPage",
        "@id": "<?= $full_url_en ?>"
      },
      "description": "<?= $meta_description_en ?>"
    }
  </script>

  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "BreadcrumbList",
      "itemListElement": [{
          "@type": "ListItem",
          "position": 1,
          "name": "All Articles",
          "item": "https://cmnsfixmac.com/en/articles.php"
        },
        {
          "@type": "ListItem",
          "position": 2,
          "name": "<?= e($display_title_en) ?>",
          "item": "<?= $full_url_en ?>"
        }
      ]
    }
  </script>
</head>

<body>
  <?php include '../includes/header_en.php'; // Path changed 
  ?>

  <main class="article-detail container">
    <h1><?= $display_title_en ?></h1>
    <p class="date">Published on <?= date('F d, Y', strtotime($article['created_at'])) ?></p>
    <p class="views"><?= number_format($article['views']) ?> views</p>

    <div class="breadcrumb-bar">
      <a href="articles.php" class="breadcrumb-home">All Articles</a>
      <span class="breadcrumb-separator">›</span>
      <span class="breadcrumb-current"><?= $display_title_en ?></span>
    </div>

    <?php if (!empty($article['image'])): ?>
      <img class="main-image" src="/uploads/<?= e($article['image']) ?>" alt="<?= $main_image_alt_en ?>"> <?php endif; ?>

    <article class="article-content">
      <?= $display_content_en ?> </article>

    <?php if ($images || !empty($article['youtube_url'])): ?>
      <section class="article-gallery">
        <?php if ($images): ?>
          <h2>Additional Images</h2>
          <div class="gallery-grid">
            <?php foreach ($images as $img_idx => $img):
              $gallery_image_path_en = (strpos($img['image_path'], 'uploads/') === 0) ? '../' . e($img['image_path']) : '../uploads/' . e($img['image_path']);
              $gallery_alt_en = !empty($img['caption_en']) ? e($img['caption_en']) : $main_image_alt_en . " - image " . ($img_idx + 1);
              $gallery_caption_display_en = !empty(trim($img['caption_en'])) ? trim($img['caption_en']) : (!empty(trim($img['caption_th'])) ? trim($img['caption_th']) . ' (in Thai)' : '');            ?>
              <figure>
                <img loading="lazy" src="<?= $gallery_image_path_en ?>" alt="<?= $gallery_alt_en ?>">
                <?php if (!empty($gallery_caption_display_en)): ?>
                  <figcaption><strong>Caption:</strong> <?= $gallery_caption_display_en ?></figcaption>
                <?php endif; ?>
              </figure>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($article['youtube_url'])): ?>
          <h2>Additional Video</h2>
          <div class="article-video">
            <iframe src="https://www.youtube.com/embed/<?= htmlspecialchars($article['youtube_url']) ?>" allowfullscreen></iframe>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <div class="article-actions">
      <button class="share-btn native" onclick="shareNative()">
        <img src="/assets/img/icons/Share.png" alt="Share Article"> Share Article
      </button>
      <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($full_url_en) ?>" target="_blank" rel="noopener noreferrer" class="share-btn facebook desktop-only">
        <img src="/assets/img/icons/facebook.png" alt="Share on Facebook"> Share on Facebook
      </a>
      <a href="https://social-plugins.line.me/lineit/share?url=<?= urlencode($full_url_en) ?>" target="_blank" rel="noopener noreferrer" class="share-btn line desktop-only">
        <img src="/assets/img/icons/Line.png" alt="Share on LINE"> Share on LINE
      </a>
      <button class="share-btn copy" onclick="copyArticleLink()">
        <img src="/assets/img/icons/Link.png" alt="Copy Link"> Copy Link
      </button>
    </div>

    <?php if ($related): ?>
      <section class="related-articles">
        <h2>Related Articles</h2>
        <div class="related-list">
          <?php foreach ($related as $item):
            $item_title = !empty(trim($item['title_en'])) ? e($item['title_en']) : e($item['title_th']);
            $item_slug = !empty(trim($item['slug_en'])) ? e($item['slug_en']) : e($item['slug_th']);
            $item_excerpt = !empty(trim($item['excerpt_en'])) ? e(mb_substr(strip_tags($item['excerpt_en']), 0, 80)) . '...' : e(mb_substr(strip_tags($item['content']), 0, 80)) . '...';
          ?>
            <a href="article-detail.php?slug=<?= urlencode($item_slug) ?>" class="related-item">
              <h3><?= $item_title ?></h3>
              <p><?= $item_excerpt ?></p>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($popular): ?>
      <section class="popular-articles">
        <h2>Popular Articles</h2>
        <div class="popular-list">
          <?php foreach ($popular as $pop):
            $pop_title = !empty(trim($pop['title_en'])) ? e($pop['title_en']) : e($pop['title_th']);
            $pop_slug = !empty(trim($pop['slug_en'])) ? e($pop['slug_en']) : e($pop['slug_th']);
            $pop_excerpt = !empty(trim($pop['excerpt_en'])) ? e(mb_substr(strip_tags($pop['excerpt_en']), 0, 80)) . '...' : e(mb_substr(strip_tags($pop['content']), 0, 80)) . '...';
          ?>
            <a href="article-detail.php?slug=<?= urlencode($pop_slug) ?>" class="popular-item">
              <h3><?= $pop_title ?></h3>
              <p><?= $pop_excerpt ?></p>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($prev || $next): ?>
      <nav class="article-nav short">
        <?php if ($prev):
          $prev_title = !empty(trim($prev['title_en'])) ? e($prev['title_en']) : e($prev['title_th']);
          $prev_slug = !empty(trim($prev['slug_en'])) ? e($prev['slug_en']) : e($prev['slug_th']);
        ?>
          <a class="prev-article" href="article-detail.php?slug=<?= urlencode($prev_slug) ?>">
            ← Previous: <?= $prev_title ?>
          </a>
        <?php endif; ?>
        <?php if ($next):
          $next_title = !empty(trim($next['title_en'])) ? e($next['title_en']) : e($next['title_th']);
          $next_slug = !empty(trim($next['slug_en'])) ? e($next['slug_en']) : e($next['slug_th']);
        ?>
          <a class="next-article" href="article-detail.php?slug=<?= urlencode($next_slug) ?>">
            Next: <?= $next_title ?> →
          </a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  </main>

  <?php include_once '../includes/footer_en.php'; // Path changed 
  ?>

  <script>
    function shareNative() {
      if (navigator.share) {
        navigator.share({
          title: "<?= e(str_replace('"', '\"', $display_title_en)) ?>", // Escape quotes for JS string
          url: "<?= $full_url_en ?>"
        });
      } else {
        alert("Your device does not support native sharing. Please use the buttons below."); // Translated
      }
    }

    function copyArticleLink() {
      const url = "<?= $full_url_en ?>";
      navigator.clipboard.writeText(url).then(() => {
        alert("Link copied successfully!"); // Translated
      }).catch(() => {
        alert("Could not copy the link. Please copy it manually."); // Translated
      });
    }
  </script>

</body>

</html>