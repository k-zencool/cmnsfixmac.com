<?php
/*
 * en/article-detail.php
 * - [GEMINI FINAL V3 - FORMATTING FIXED]
 * - Fixed: Allow HTML Formatting (Bold, Lists, Br) but remove <div> tags
 * - Matches visual style of TH version
 */

include '../includes/db.php';

function e($string) {
  return htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8');
}

// 1. รับค่า ID
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
  header("Location: articles.php");
  exit;
}

// 2. เพิ่มยอดวิว
$pdo->prepare("UPDATE articles SET views = views + 1 WHERE id = ?")->execute([$id]);

// 3. ดึงข้อมูลบทความ
$stmt = $pdo->prepare("SELECT *, 
                        title_en, content_en, excerpt_en,
                        title AS title_th, content AS content_th, excerpt AS excerpt_th
                      FROM articles 
                      WHERE id = ? AND status = 1");
$stmt->execute([$id]);
$article = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$article) {
  http_response_code(404);
  echo "<h1>Article Not Found</h1>";
  exit;
}

// --- Language Switch Logic ---
$switch_to_lang_url = "/article-detail.php?id=" . $article['id'];

// --- Content Logic ---
$display_title = !empty($article['title_en']) ? $article['title_en'] : $article['title_th'];
$display_content = !empty($article['content_en']) ? $article['content_en'] : $article['content_th'];

if (empty($article['content_en']) && !empty($article['content_th'])) {
    $display_content = "<p style='color:#666; font-style:italic; margin-bottom:20px;'>(This article is currently available in Thai only.)</p>" . $display_content;
}

$meta_excerpt = !empty($article['excerpt_en']) ? $article['excerpt_en'] : ($article['excerpt_th'] ?? '');

// --- Image Path Logic (Main Image) ---
$image_filename = $article['image'] ?? '';
$imagePath = '/assets/img/placeholder.png'; 
$ogImagePath = 'https://cmnsfixmac.com/assets/img/placeholder.png';

if (!empty($image_filename)) {
    if (strpos($image_filename, '/') !== false) {
        $imagePath = e($image_filename);
    } else {
        $imagePath = '/uploads/' . e($image_filename);
    }
    
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $ogImagePath = "$protocol://$host" . $imagePath;
}

// ดึงรูปเพิ่มเติม
$stmtImg = $pdo->prepare("SELECT * FROM article_images WHERE article_id = ?");
$stmtImg->execute([$article['id']]);
$images = $stmtImg->fetchAll(PDO::FETCH_ASSOC);

// --- Related & Popular ---
$select_cols = "id, title_en, title AS title_th, excerpt_en, content_en, image, created_at, category"; 

$relatedStmt = $pdo->prepare("SELECT {$select_cols} FROM articles WHERE category = ? AND id != ? AND status = 1 ORDER BY created_at DESC LIMIT 4");
$relatedStmt->execute([$article['category'], $article['id']]);
$related = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);

$popularStmt = $pdo->prepare("SELECT {$select_cols} FROM articles WHERE id != ? AND status = 1 ORDER BY views DESC LIMIT 4");
$popularStmt->execute([$article['id']]);
$popular = $popularStmt->fetchAll(PDO::FETCH_ASSOC);

// Prev/Next
$prevStmt = $pdo->prepare("SELECT id FROM articles WHERE id < ? AND status = 1 ORDER BY id DESC LIMIT 1");
$prevStmt->execute([$article['id']]);
$prev = $prevStmt->fetch(PDO::FETCH_ASSOC);

$nextStmt = $pdo->prepare("SELECT id FROM articles WHERE id > ? AND status = 1 ORDER BY id ASC LIMIT 1");
$nextStmt->execute([$article['id']]);
$next = $nextStmt->fetch(PDO::FETCH_ASSOC);

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$currentUrl = "$protocol://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title><?= e($display_title) ?> | CMNS FixMac</title>
  
  <?php $baseUrl = "$protocol://$_SERVER[HTTP_HOST]"; ?>
  <link rel="alternate" hreflang="th" href="<?= $baseUrl ?>/article-detail.php?id=<?= $article['id'] ?>" />
  <link rel="alternate" hreflang="en" href="<?= $baseUrl ?>/en/article-detail.php?id=<?= $article['id'] ?>" />
  <link rel="alternate" hreflang="x-default" href="<?= $baseUrl ?>/en/article-detail.php?id=<?= $article['id'] ?>" />

  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= e(mb_substr(strip_tags($meta_excerpt ?: $display_content), 0, 160)) ?>">
  
  <link rel="stylesheet" href="/assets/css/navbar-style.css">
  <link rel="stylesheet" href="/assets/css/article-detail-style.css">
  <link rel="stylesheet" href="/assets/css/footer-style.css">
  <link rel="shortcut icon" href="/assets/img/favicon1.png" />
  <link rel="canonical" href="<?= $currentUrl ?>">

  <meta property="og:title" content="<?= e($display_title) ?> | FixMac">
  <meta property="og:description" content="<?= e(mb_substr(strip_tags($meta_excerpt ?: $display_content), 0, 160)) ?>">
  <meta property="og:image" content="<?= htmlspecialchars($ogImagePath) ?>">
  <meta property="og:url" content="<?= $currentUrl ?>">
  <meta property="og:type" content="article">

  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= e($display_title) ?>">
  <meta name="twitter:description" content="<?= e(mb_substr(strip_tags($meta_excerpt ?: $display_content), 0, 160)) ?>">
  <meta name="twitter:image" content="<?= htmlspecialchars($ogImagePath) ?>">

  <script async src="https://www.googletagmanager.com/gtag/js?id=G-3WXK9GWN7C"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag() { dataLayer.push(arguments); }
    gtag('js', new Date());
    gtag('config', 'G-3WXK9GWN7C');
  </script>
</head>

<body>
  <?php include '../includes/header_en.php'; ?>

  <main class="article-detail container">
    <h1><?= e($display_title) ?></h1>
    <p class="date">Published on <?= date('d F Y', strtotime($article['created_at'])) ?></p>
    <p class="views"><?= number_format($article['views']) ?> views</p>

    <div class="breadcrumb-bar">
      <a href="/en/articles.php" class="breadcrumb-home">All Articles</a>
      <span class="breadcrumb-separator">›</span>
      <span class="breadcrumb-current"><?= e($display_title) ?></span>
    </div>

    <?php if (!empty($imagePath)): ?>
      <img class="main-image" src="<?= $imagePath ?>" alt="<?= e($display_title) ?>">
    <?php endif; ?>

    <article class="article-content">
      <?= nl2br($display_content) ?>
    </article>

    <?php if ($images || !empty($article['youtube_url'])): ?>
      <section class="article-gallery">
        <h2>Additional Images</h2>
        <div class="gallery-grid">
          <?php foreach ($images as $img): ?>
            <?php 
                $gImgPath = '/assets/img/placeholder.png';
                if (!empty($img['image_path'])) {
                     if (strpos($img['image_path'], '/') !== false) {
                        $gImgPath = e($img['image_path']);
                     } else {
                        $gImgPath = '/uploads/' . e($img['image_path']);
                     }
                }
                
                // --- [GEMINI FIXED LOGIC V3] ---
                // ดึง Caption
                $imgCaptionRaw = !empty($img['caption_en']) ? $img['caption_en'] : ($img['caption'] ?? '');
                
                // อนุญาตให้ใช้ Tag เหล่านี้ได้ (ตัวหนา, รายการ, ย่อหน้า) เพื่อให้รูปแบบเหมือนฝั่งไทย
                // แต่จะลบ <div> ออก เพื่อไม่ให้ติดปัญหาเดิม
                $allowed_tags = '<b><strong><i><em><u><ul><ol><li><p><br>';
                $imgCaptionDisplay = strip_tags($imgCaptionRaw, $allowed_tags);
            ?>
            <figure>
              <img loading="lazy" src="<?= $gImgPath ?>" alt="Gallery Image">
              <?php if (!empty($imgCaptionDisplay)): ?>
                <figcaption><strong>Description:</strong> <?= $imgCaptionDisplay ?></figcaption>
              <?php endif; ?>
            </figure>
          <?php endforeach; ?>
        </div>

        <?php if (!empty($article['youtube_url'])): ?>
            <h2>Video</h2>
          <div class="article-video">
            <iframe src="https://www.youtube.com/embed/<?= htmlspecialchars($article['youtube_url']) ?>" allowfullscreen></iframe>
          </div>
        <?php endif; ?>

      </section>
    <?php endif; ?>

    <div class="article-actions">
      <button class="share-btn native" onclick="shareNative()">
        <img src="/assets/img/icons/Share.png" alt="Share"> Share
      </button>
      <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($currentUrl) ?>"
        target="_blank" rel="noopener noreferrer" class="share-btn facebook desktop-only">
        <img src="/assets/img/icons/facebook.png" alt="Facebook"> Facebook
      </a>
      <a href="https://social-plugins.line.me/lineit/share?url=<?= urlencode($currentUrl) ?>"
        target="_blank" rel="noopener noreferrer" class="share-btn line desktop-only">
        <img src="/assets/img/icons/Line.png" alt="LINE"> LINE
      </a>
      <button class="share-btn copy" onclick="copyArticleLink()">
        <img src="/assets/img/icons/Link.png" alt="Copy Link"> Copy Link
      </button>
    </div>

    <?php if ($related): ?>
      <section class="related-articles">
        <h2>Related Articles</h2>
        <div class="related-list">
          <?php foreach ($related as $item): ?>
            <?php
            $imgVal = $item['image'] ?? '';
            if (!empty($imgVal) && strpos($imgVal, '/') !== false) {
                $imgRel = e($imgVal);
            } elseif (!empty($imgVal)) {
                $imgRel = '/uploads/' . e($imgVal);
            } else {
                $imgRel = '/assets/img/placeholder.png';
            }
            $titleRel = !empty($item['title_en']) ? $item['title_en'] : $item['title_th'];
            $excerptRel = '';
            if (!empty($item['excerpt_en'])) {
                $excerptRel = mb_substr(strip_tags($item['excerpt_en']), 0, 100) . '...';
            } elseif (!empty($item['content_en'])) {
                $excerptRel = mb_substr(strip_tags($item['content_en']), 0, 100) . '...';
            }
            ?>
            <a href="/en/article-detail.php?id=<?= e($item['id']) ?>" class="related-item">
              <img src="<?= $imgRel ?>" alt="<?= e($titleRel) ?>">
              <div class="related-item-content">
                <h3><?= e($titleRel) ?></h3>
                <?php if ($excerptRel): ?>
                    <p style="font-size: 0.9rem; color: #666; margin-top: 8px;"><?= e($excerptRel) ?></p>
                <?php endif; ?>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($popular): ?>
      <section class="popular-articles">
        <h2>Popular Articles</h2>
        <div class="popular-list">
          <?php foreach ($popular as $pop): ?>
             <?php
            $imgPopVal = $pop['image'] ?? '';
            if (!empty($imgPopVal) && strpos($imgPopVal, '/') !== false) {
                $imgPop = e($imgPopVal);
            } elseif (!empty($imgPopVal)) {
                $imgPop = '/uploads/' . e($imgPopVal);
            } else {
                $imgPop = '/assets/img/placeholder.png';
            }
            $titlePop = !empty($pop['title_en']) ? $pop['title_en'] : $pop['title_th'];
            $excerptPop = '';
            if (!empty($pop['excerpt_en'])) {
                $excerptPop = mb_substr(strip_tags($pop['excerpt_en']), 0, 100) . '...';
            } elseif (!empty($pop['content_en'])) {
                $excerptPop = mb_substr(strip_tags($pop['content_en']), 0, 100) . '...';
            }
            ?>
            <a href="/en/article-detail.php?id=<?= e($pop['id']) ?>" class="popular-item">
              <img src="<?= $imgPop ?>" alt="<?= e($titlePop) ?>">
              <div class="popular-item-content">
                <h3><?= e($titlePop) ?></h3>
                <?php if ($excerptPop): ?>
                    <p style="font-size: 0.9rem; color: #666; margin-top: 8px;"><?= e($excerptPop) ?></p>
                <?php endif; ?>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($prev || $next): ?>
      <nav class="article-nav short">
        <?php if ($prev): ?>
          <a class="prev-article" href="/en/article-detail.php?id=<?= e($prev['id']) ?>">← Previous</a>
        <?php endif; ?>
        <?php if ($next): ?>
          <a class="next-article" href="/en/article-detail.php?id=<?= e($next['id']) ?>">Next →</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>

  </main>

  <?php include_once '../includes/footer_en.php'; ?>

  <script>
    function shareNative() {
      if (navigator.share) {
        navigator.share({
          title: document.title,
          url: "<?= $currentUrl ?>"
        });
      } else {
        alert("Your device does not support automatic sharing.");
      }
    }

    function copyArticleLink() {
      const url = "<?= $currentUrl ?>";
      navigator.clipboard.writeText(url).then(() => {
        alert("Link copied!");
      }).catch(() => {
        alert("Failed to copy link.");
      });
    }
  </script>

</body>
</html>