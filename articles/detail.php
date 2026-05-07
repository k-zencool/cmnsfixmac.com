<?php
/*
 * article-detail.php
 * - [GEMINI FINAL FIX] รองรับ URL สวย (Slug) ไม่เด้งกลับหน้าแรก
 */

include '../includes/db.php';

function e($string) {
  return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// -------------------------------------------------------------
// 1. รับค่า Slug และ ID (หัวใจสำคัญของการแก้รอบนี้)
// -------------------------------------------------------------
$slug = filter_input(INPUT_GET, 'slug', FILTER_SANITIZE_STRING);
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// ถ้ามีแต่ Slug ส่งมา (เช่นเข้าผ่าน /article/ชื่อเรื่อง) ให้ไปหา ID มาซะ
if ($slug && !$id) {
    // เช็คทั้ง slug ภาษาไทย และอังกฤษ (กันเหนียว)
    $stmtSlug = $pdo->prepare("SELECT id FROM articles WHERE slug = ? OR slug_en = ? LIMIT 1");
    $stmtSlug->execute([$slug, $slug]);
    $rowSlug = $stmtSlug->fetch();
    
    if ($rowSlug) {
        $id = $rowSlug['id']; // เย้! เจอ ID แล้ว ใช้ตัวนี้ทำงานต่อเลย
    }
}

// ถ้าสุดท้ายยังไม่มี ID (แปลว่า Link ผิด หรือหาไม่เจอ) -> ค่อยดีดไปหน้าแรก
if (!$id) {
  header("Location: /articles/");
  exit;
}

// -------------------------------------------------------------
// 2. โค้ดส่วนเดิม (ทำงานต่อได้เลย เพราะมี $id แล้ว)
// -------------------------------------------------------------

// เพิ่มวิว
$pdo->prepare("UPDATE articles SET views = views + 1 WHERE id = ?")->execute([$id]);

// ดึงข้อมูลบทความ
$stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
$stmt->execute([$id]);
$article = $stmt->fetch();

if (!$article || !$article['status']) { 
  http_response_code(404);
  echo "<h1>ไม่พบบทความ</h1>";
  exit;
}

// Language Switch Logic
$switch_to_lang_url = "/en/article-detail.php?id=" . $article['id'];

// ดึงรูปภาพเพิ่มเติม
$stmtImg = $pdo->prepare("SELECT * FROM article_images WHERE article_id = ?");
$stmtImg->execute([$article['id']]);
$images = $stmtImg->fetchAll();

// ดึงบทความที่เกี่ยวข้อง
$stmtRelated = $pdo->prepare("SELECT * FROM articles WHERE category = ? AND id != ? AND status = 1 ORDER BY created_at DESC LIMIT 4");
$stmtRelated->execute([$article['category'], $article['id']]);
$related = $stmtRelated->fetchAll();

// ดึงบทความยอดนิยม
$stmtPopular = $pdo->prepare("SELECT * FROM articles WHERE id != ? AND status = 1 ORDER BY views DESC LIMIT 4");
$stmtPopular->execute([$article['id']]);
$popular = $stmtPopular->fetchAll();

// Previous/Next Logic
$prevStmt = $pdo->prepare("SELECT * FROM articles WHERE id < ? AND status = 1 ORDER BY id DESC LIMIT 1");
$prevStmt->execute([$article['id']]);
$prev = $prevStmt->fetch();

$nextStmt = $pdo->prepare("SELECT * FROM articles WHERE id > ? AND status = 1 ORDER BY id ASC LIMIT 1");
$nextStmt->execute([$article['id']]);
$next = $nextStmt->fetch();

// Image Path Logic
$image_filename = $article['image'] ?? '';
$imagePath = '/assets/img/placeholder.png'; // รูป Default
$ogImagePath = 'https://cmnsfixmac.com/assets/img/placeholder.png'; // OG ต้องใช้ Full URL

if (!empty($image_filename) && strpos($image_filename, '/') !== false) {
    $imagePath = e($image_filename);
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $ogImagePath = "$protocol://$host" . e($image_filename);
}

// สร้าง Full URL
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$currentUrl = "$protocol://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
?>

<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($article['title']) ?> | FixMac</title>
  
  <?php 
    $baseUrl = "$protocol://$_SERVER[HTTP_HOST]";
  ?>
  <link rel="alternate" hreflang="th" href="<?= $baseUrl ?>/article-detail.php?id=<?= $article['id'] ?>" />
  <link rel="alternate" hreflang="en" href="<?= $baseUrl ?>/en/article-detail.php?id=<?= $article['id'] ?>" />
  <link rel="alternate" hreflang="x-default" href="<?= $baseUrl ?>/en/article-detail.php?id=<?= $article['id'] ?>" />

  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= htmlspecialchars(mb_substr(strip_tags($article['excerpt'] ?: $article['content']), 0, 160)) ?>">
  
  <link rel="stylesheet" href="/assets/css/navbar-style.css">
  <link rel="stylesheet" href="/assets/css/article-detail-style.css">
  <link rel="shortcut icon" href="/assets/img/favicon1.png" />
  <link rel="stylesheet" href="/assets/css/footer-style.css">
  <link rel="canonical" href="<?= $currentUrl ?>">

  <meta property="og:title" content="<?= htmlspecialchars($article['title']) ?> | FixMac">
  <meta property="og:description" content="<?= htmlspecialchars(mb_substr(strip_tags($article['excerpt'] ?: $article['content']), 0, 160)) ?>">
  <meta property="og:image" content="<?= htmlspecialchars($ogImagePath) ?>">
  <meta property="og:url" content="<?= $currentUrl ?>">
  <meta property="og:type" content="article">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= htmlspecialchars($article['title']) ?>">
  <meta name="twitter:description" content="<?= htmlspecialchars(mb_substr(strip_tags($article['excerpt'] ?: $article['content']), 0, 160)) ?>">
  <meta name="twitter:image" content="<?= htmlspecialchars($ogImagePath) ?>">

  <meta name="keywords" content="<?= htmlspecialchars($article['tags'] ?? '') ?>">
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-3WXK9GWN7C"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag() { dataLayer.push(arguments); }
    gtag('js', new Date());
    gtag('config', 'G-3WXK9GWN7C');
  </script>
</head>

<body>
  <?php include '../includes/header.php'; ?>

  <main class="article-detail container">
    <h1><?= htmlspecialchars($article['title']) ?></h1>
    <p class="date">เผยแพร่เมื่อ <?= date('d F Y', strtotime($article['created_at'])) ?></p>
    <p class="views">รับชม <?= number_format($article['views']) ?> ครั้ง</p>

    <div class="breadcrumb-bar">
      <a href="/articles/" class="breadcrumb-home">บทความทั้งหมด</a>
      <span class="breadcrumb-separator">›</span>
      <span class="breadcrumb-current"><?= htmlspecialchars($article['title']) ?></span>
    </div>

    <?php if (!empty($article['image'])): ?>
      <img class="main-image" src="<?= $imagePath ?>" alt="<?= htmlspecialchars($article['title']) ?>">
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
              <img loading="lazy" src="<?= e($img['image_path']) ?>" alt="<?= e($img['caption']) ?>">
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
        <img src="/assets/img/icons/Share.png" alt="แชร์บทความ"> แชร์บทความ
      </button>
      <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($currentUrl) ?>"
        target="_blank" rel="noopener noreferrer" class="share-btn facebook desktop-only">
        <img src="/assets/img/icons/facebook.png" alt="แชร์ Facebook"> แชร์ Facebook
      </a>
      <a href="https://social-plugins.line.me/lineit/share?url=<?= urlencode($currentUrl) ?>"
        target="_blank" rel="noopener noreferrer" class="share-btn line desktop-only">
        <img src="/assets/img/icons/Line.png" alt="แชร์ LINE"> แชร์ LINE
      </a>
      <button class="share-btn copy" onclick="copyArticleLink()">
        <img src="/assets/img/icons/Link.png" alt="คัดลอกลิงก์"> คัดลอกลิงก์
      </button>
    </div>

    <?php if ($related): ?>
      <section class="related-articles">
        <h2>บทความที่เกี่ยวข้อง</h2>
        <div class="related-list">
          <?php foreach ($related as $item): ?>
            <?php
            $image_filename_related = $item['image'] ?? '';
            $imagePathRelated = (!empty($image_filename_related) && strpos($image_filename_related, '/') !== false) ? e($image_filename_related) : '/assets/img/placeholder.png';
            ?>
            <a href="/article-detail.php?id=<?= e($item['id']) ?>" class="related-item">
              <img src="<?= $imagePathRelated ?>" alt="<?= htmlspecialchars($item['title']) ?>">
              <div class="related-item-content">
                <h3><?= htmlspecialchars($item['title']) ?></h3>
              </div>
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
             <?php
            $image_filename_pop = $pop['image'] ?? '';
            $imagePathPop = (!empty($image_filename_pop) && strpos($image_filename_pop, '/') !== false) ? e($image_filename_pop) : '/assets/img/placeholder.png';
            ?>
            <a href="/article-detail.php?id=<?= e($pop['id']) ?>" class="popular-item">
              <img src="<?= $imagePathPop ?>" alt="<?= htmlspecialchars($pop['title']) ?>">
              <div class="popular-item-content">
                <h3><?= htmlspecialchars($pop['title']) ?></h3>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($prev || $next): ?>
      <nav class="article-nav short">
        <?php if ($prev): ?>
          <a class="prev-article" href="/article-detail.php?id=<?= e($prev['id']) ?>">← ก่อนหน้า</a>
        <?php endif; ?>
        <?php if ($next): ?>
          <a class="next-article" href="/article-detail.php?id=<?= e($next['id']) ?>">ถัดไป →</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>

  </main>

  <?php include_once '../includes/footer.php'; ?>

  <script>
    function shareNative() {
      if (navigator.share) {
        navigator.share({
          title: document.title,
          url: "<?= $currentUrl ?>"
        });
      } else {
        alert("อุปกรณ์ของคุณไม่รองรับการแชร์อัตโนมัติ กรุณาแชร์ด้วยปุ่มด้านล่าง");
      }
    }

    function copyArticleLink() {
      const url = "<?= $currentUrl ?>";
      navigator.clipboard.writeText(url).then(() => {
        alert("คัดลอกลิงก์เรียบร้อยแล้ว!");
      }).catch(() => {
        alert("ไม่สามารถคัดลอกลิงก์ได้ กรุณาคัดลอกเอง");
      });
    }
  </script>

</body>
</html>