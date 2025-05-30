<?php
include 'includes/db.php';

$slug = $_GET['slug'] ?? '';
if (!$slug) {
  header("Location: articles.php");
  exit;
}

$stmt = $pdo->prepare("SELECT * FROM articles WHERE slug = ? LIMIT 1");
$stmt->execute([$slug]);
$article = $stmt->fetch();

if (!$article) {
  http_response_code(404);
  echo "<h1>ไม่พบบทความ</h1>";
  exit;
}

$stmtImg = $pdo->prepare("SELECT * FROM article_images WHERE article_id = ?");
$stmtImg->execute([$article['id']]);
$images = $stmtImg->fetchAll();

$fullUrl = "https://cmnsfixmac.com/article-detail.php?slug=" . urlencode($slug);

$stmtRelated = $pdo->prepare("SELECT * FROM articles WHERE category = ? AND id != ? ORDER BY created_at DESC LIMIT 4");
$stmtRelated->execute([$article['category'], $article['id']]);
$related = $stmtRelated->fetchAll();

$pdo->prepare("UPDATE articles SET views = views + 1 WHERE id = ?")->execute([$article['id']]);

$stmtPopular = $pdo->prepare("SELECT * FROM articles WHERE id != ? ORDER BY views DESC LIMIT 4");
$stmtPopular->execute([$article['id']]);
$popular = $stmtPopular->fetchAll();

$prevStmt = $pdo->prepare("SELECT * FROM articles WHERE id < ? ORDER BY id DESC LIMIT 1");
$prevStmt->execute([$article['id']]);
$prev = $prevStmt->fetch();

$nextStmt = $pdo->prepare("SELECT * FROM articles WHERE id > ? ORDER BY id ASC LIMIT 1");
$nextStmt->execute([$article['id']]);
$next = $nextStmt->fetch();

?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($article['title']) ?> | FixMac</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= htmlspecialchars(mb_substr(strip_tags($article['excerpt'] ?: $article['content']), 0, 160)) ?>">
  <link rel="stylesheet" href="assets/css/navbar-style.css">
  <link rel="stylesheet" href="assets/css/article-detail-style.css">
  <link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png" />
  <link rel="stylesheet" href="/assets/css/footer-style.css">
  <link rel="canonical" href="<?= $fullUrl ?>">

  <meta property="og:title" content="<?= htmlspecialchars($article['title']) ?> | FixMac">
  <meta property="og:description" content="<?= htmlspecialchars(mb_substr(strip_tags($article['excerpt'] ?: $article['content']), 0, 160)) ?>">
  <meta property="og:image" content="https://cmnsfixmac.com/uploads/<?= htmlspecialchars($article['image']) ?>">
  <meta property="og:url" content="<?= $fullUrl ?>">
  <meta property="og:type" content="article">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= htmlspecialchars($article['title']) ?>">
  <meta name="twitter:description" content="<?= htmlspecialchars(mb_substr(strip_tags($article['excerpt'] ?: $article['content']), 0, 160)) ?>">
  <meta name="twitter:image" content="https://cmnsfixmac.com/uploads/<?= htmlspecialchars($article['image']) ?>">
  
  <meta name="keywords" content="<?= htmlspecialchars($article['tags'] ?? '') ?>">
  
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "Article",
    "headline": "<?= htmlspecialchars($article['title']) ?>",
    "image": ["https://cmnsfixmac.com/uploads/<?= htmlspecialchars($article['image']) ?>"],
    "author": { "@type": "Organization", "name": "FixMac" },
    "publisher": {
      "@type": "Organization",
      "name": "FixMac",
      "logo": {
        "@type": "ImageObject",
        "url": "https://cmnsfixmac.com/assets/img/Logo1.png"
      }
    },
    "datePublished": "<?= date('Y-m-d', strtotime($article['created_at'])) ?>",
    "dateModified": "<?= date('Y-m-d', strtotime($article['created_at'])) ?>",
    "mainEntityOfPage": {
      "@type": "WebPage",
      "@id": "<?= $fullUrl ?>"
    }
  }
  </script>

  <script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    {
      "@type": "ListItem",
      "position": 1,
      "name": "บทความทั้งหมด",
      "item": "https://cmnsfixmac.com/articles.php"
    },
    {
      "@type": "ListItem",
      "position": 2,
      "name": "<?= htmlspecialchars($article['title']) ?>",
      "item": "<?= $fullUrl ?>"
    }
  ]
}
</script>

</head>
<body>
<?php include 'includes/header.php'; ?>

<main class="article-detail container">
  <h1><?= htmlspecialchars($article['title']) ?></h1>
  <p class="date">เผยแพร่เมื่อ <?= date('d F Y', strtotime($article['created_at'])) ?></p>
  <p class="views">รับชม <?= number_format($article['views']) ?> ครั้ง</p>

  <div class="breadcrumb-bar">
    <a href="articles.php" class="breadcrumb-home">บทความทั้งหมด</a>
    <span class="breadcrumb-separator">›</span>
    <span class="breadcrumb-current"><?= htmlspecialchars($article['title']) ?></span>
  </div>

  <?php if (!empty($article['image'])): ?>
    <img class="main-image" src="uploads/<?= htmlspecialchars($article['image']) ?>" alt="<?= htmlspecialchars($article['title']) ?>">
  <?php endif; ?>

  <article class="article-content">
    <?= nl2br($article['content']) ?>
  </article>

  <?php if ($images || !empty($article['youtube_url'])): ?>
    <section class="article-gallery">
      <h2>ภาพเพิ่มเติม</h2>
      <div class="gallery-grid">
        <?php foreach ($images as $img): ?>
          <figure>
            <img loading="lazy" src="uploads/<?= htmlspecialchars($img['image_path']) ?>" alt="<?= htmlspecialchars($img['caption']) ?>">
            <?php if (!empty($img['caption'])): ?>
              <figcaption><strong>คำอธิบาย:</strong> <?= $img['caption'] ?></figcaption>
            <?php endif; ?>
          </figure>
        <?php endforeach; ?>
      </div>

      <h2>คริปเพิ่มเติม</h2>
      <?php if (!empty($article['youtube_url'])): ?>
        <div class="article-video">
          <iframe src="https://www.youtube.com/embed/<?= htmlspecialchars($article['youtube_url']) ?>" allowfullscreen></iframe>
        </div>
      <?php endif; ?>

    </section>
  <?php endif; ?>

<div class="article-actions">
  <button class="share-btn native" onclick="shareNative()">
    <img src="assets/img/icons/Share.png" alt="แชร์บทความ">
    แชร์บทความ
  </button>

  <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($fullUrl) ?>" 
     target="_blank" 
     rel="noopener noreferrer" 
     class="share-btn facebook desktop-only">
    <img src="assets/img/icons/facebook.png" alt="แชร์ Facebook">
    แชร์ Facebook
  </a>

  <a href="https://social-plugins.line.me/lineit/share?url=<?= urlencode($fullUrl) ?>" 
     target="_blank" 
     rel="noopener noreferrer" 
     class="share-btn line desktop-only">
    <img src="assets/img/icons/Line.png" alt="แชร์ LINE">
    แชร์ LINE
  </a>

  <button class="share-btn copy" onclick="copyArticleLink()">
    <img src="assets/img/icons/Link.png" alt="คัดลอกลิงก์">
    คัดลอกลิงก์
  </button>
</div>



  <?php if ($related): ?>
    <section class="related-articles">
      <h2>บทความที่เกี่ยวข้อง</h2>
      <div class="related-list">
        <?php foreach ($related as $item): ?>
          <a href="article-detail.php?slug=<?= urlencode($item['slug']) ?>" class="related-item">
            <h3><?= htmlspecialchars($item['title']) ?></h3>
            <p><?= mb_substr(strip_tags($item['content']), 0, 80) ?>...</p>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($popular): ?>
    <section class="popular-articles">
      <h2>บทความยอดนิยม</h2>
      <div class="popular-list">
        <?php foreach ($popular as $pop): ?>
          <a href="article-detail.php?slug=<?= urlencode($pop['slug']) ?>" class="popular-item">
            <h3><?= htmlspecialchars($pop['title']) ?></h3>
            <p><?= mb_substr(strip_tags($pop['excerpt'] ?: $pop['content']), 0, 80) ?>...</p>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

<?php if ($prev || $next): ?>
  <nav class="article-nav short">
    <?php if ($prev): ?>
      <a class="prev-article" href="article-detail.php?slug=<?= urlencode($prev['slug']) ?>">
        ← ก่อนหน้า
      </a>
    <?php endif; ?>
    <?php if ($next): ?>
      <a class="next-article" href="article-detail.php?slug=<?= urlencode($next['slug']) ?>">
        ถัดไป →
      </a>
    <?php endif; ?>
  </nav>
<?php endif; ?>


</main>

<?php include_once 'includes/footer.php'; ?>

<script>
function shareNative() {
  if (navigator.share) {
    navigator.share({
      title: document.title,
      url: "<?= $fullUrl ?>"
    });
  } else {
    alert("อุปกรณ์ของคุณไม่รองรับการแชร์อัตโนมัติ กรุณาแชร์ด้วยปุ่มด้านล่าง");
  }
}

function copyArticleLink() {
  const url = "<?= $fullUrl ?>";
  navigator.clipboard.writeText(url).then(() => {
    alert("คัดลอกลิงก์เรียบร้อยแล้ว!");
  }).catch(() => {
    alert("ไม่สามารถคัดลอกลิงก์ได้ กรุณาคัดลอกเอง");
  });
}

</script>

</body>
</html>
