<?php
include 'includes/db.php';

// ✅ ป้องกัน XSS
function e($string)
{
  return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// ✅ รับ id
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
  header("Location: works.php");
  exit;
}

// ✅ เพิ่มวิว
$pdo->prepare("UPDATE repairs SET views = views + 1 WHERE id = ?")->execute([$id]);

// ✅ ดึงข้อมูลซ่อม
$stmt = $pdo->prepare("SELECT * FROM repairs WHERE id = ?");
$stmt->execute([$id]);
$data = $stmt->fetch();
if (!$data) {
  echo "<p>ไม่พบข้อมูล</p>";
  exit;
}

// ✅ ดึงภาพเพิ่มเติม
$imgStmt = $pdo->prepare("SELECT * FROM repair_images WHERE repair_id = ?");
$imgStmt->execute([$id]);
$images = $imgStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title><?= e($data['title']) ?> - รายละเอียดงานซ่อม</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/works-detail-style.css">
  <link rel="stylesheet" href="assets/css/footer-style.css">
  <link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png" />

  <meta property="og:title" content="<?= e($data['title']) ?> - CMNS Mac Repair">
  <meta property="og:description" content="<?= e(mb_substr(strip_tags($data['fix_detail']), 0, 100)) ?>...">
  <meta property="og:image" content="https://cmnsfixmac.com/uploads/<?= e($data['image']) ?>">
  <meta property="og:url" content="https://cmnsfixmac.com<?= $_SERVER['REQUEST_URI'] ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="description" content="<?= e(mb_substr(strip_tags($data['fix_detail']), 0, 160)) ?>">
  <meta name="keywords" content="<?= e($data['title']) ?>, ซ่อม <?= e($data['model']) ?>, <?= e($data['category']) ?>">



  <script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Article",
  "headline": "<?= e($data['title']) ?>",
  "image": ["https://cmnsfixmac.com/uploads/<?= e($data['image']) ?>"],
  "datePublished": "<?= $data['created_at'] ?>",
  "author": {
    "@type": "Organization",
    "name": "CMNS Mac Repair"
  }
}
</script>

</head>

<body>

  <?php include_once 'includes/header.php'; ?>

  <div class="container article-detail">
    <h1><?= e($data['title']) ?> model <?= e($data['model']) ?></h1>

    <nav class="breadcrumb-bar">
      <a href="works.php" class="breadcrumb-home">ผลงานทั้งหมด</a> &gt;
      <span class="breadcrumb-current"><?= e($data['title']) ?></span>
      <p><strong>วันที่โพสต์:</strong> <?= date('d/m/Y H:i', strtotime($data['created_at'])) ?></p>
    </nav>

    <img src="uploads/<?= e($data['image']) ?>" class="main-image" alt="ภาพงานซ่อม <?= e($data['title']) ?> รุ่น <?= e($data['model']) ?> หมวด <?= e($data['category']) ?>">

    <?php if ($images): ?>
      <section class="article-gallery">
        <h2>แกลเลอรี</h2>
        <div class="gallery-grid">
          <?php foreach ($images as $img): ?>
            <figure>
              <img src="<?= e($img['image_path']) ?>" alt="<?= e($img['caption']) ?>">
              <figcaption><?= e($img['caption']) ?></figcaption>
            </figure>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
    <div class="article-meta">
      <p><strong>รุ่น:</strong> <?= e($data['model']) ?></p>
      <p><strong>อาการเสีย:</strong><br><?= nl2br(e($data['issue'])) ?></p>
      <p><strong>รายละเอียดการซ่อม:</strong><br><?= nl2br(e($data['fix_detail'])) ?></p>
      <p><strong>หมวดหมู่:</strong> <?= e($data['category']) ?></p>
      <p class="views"><strong>เข้าชม:</strong> <?= number_format($data['views']) ?> ครั้ง</p>
    </div>


    <?php
    $relatedStmt = $pdo->prepare("SELECT id, title, image FROM repairs WHERE category = ? AND id != ? ORDER BY created_at DESC LIMIT 3");
    $relatedStmt->execute([$data['category'], $data['id']]);
    $related = $relatedStmt->fetchAll();
    ?>

    <?php if ($related): ?>
      <section class="related-articles">
        <h2>ผลงานอื่นในหมวด <?= e($data['category']) ?></h2>
        <div class="related-list">
          <?php foreach ($related as $item): ?>
            <a href="work-detail.php?id=<?= e($item['id']) ?>" class="related-item">
              <img src="uploads/<?= e($item['image']) ?>" alt="<?= e($item['title']) ?>"
                style="width:100%; border-radius: 8px;">
              <h3><?= e($item['title']) ?></h3>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <div class="article-actions">
      <button class="share-btn native" onclick="shareNative()">
        <img src="assets/img/icons/Share.png" alt=""> แชร์งานซ่อม
      </button>

      <a class="share-btn facebook" target="_blank"
        href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode('https://cmnsfixmac.com' . $_SERVER['REQUEST_URI']) ?>">
        <img src="assets/img/icons/facebook.png" alt=""> แชร์ Facebook
      </a>

      <a class="share-btn line" target="_blank"
        href="https://social-plugins.line.me/lineit/share?url=<?= urlencode('https://cmnsfixmac.com' . $_SERVER['REQUEST_URI']) ?>">
        <img src="assets/img/icons/Line.png" alt=""> แชร์ LINE
      </a>

      <button class="share-btn copy" onclick="copyLink()">
        <img src="assets/img/icons/Link.png" alt=""> คัดลอกลิงก์
      </button>
    </div>
    <?php
    $prevStmt = $pdo->prepare("SELECT id, title FROM repairs WHERE id < ? ORDER BY id DESC LIMIT 1");
    $prevStmt->execute([$id]);
    $prev = $prevStmt->fetch();

    $nextStmt = $pdo->prepare("SELECT id, title FROM repairs WHERE id > ? ORDER BY id ASC LIMIT 1");
    $nextStmt->execute([$id]);
    $next = $nextStmt->fetch();
    ?>


    <div class="article-nav">
      <?php if ($prev): ?>
        <a href="work-detail.php?id=<?= e($prev['id']) ?>">← <?= e($prev['title']) ?></a>
      <?php endif; ?>
      <?php if ($next): ?>
        <a href="work-detail.php?id=<?= e($next['id']) ?>"><?= e($next['title']) ?> →</a>
      <?php endif; ?>
    </div>

  </div>


  <script>
    function shareNative() {
      const url = window.location.href;
      if (navigator.share) {
        navigator.share({
          title: document.title,
          url: url
        }).catch(console.error);
      } else {
        alert("อุปกรณ์ของคุณไม่รองรับการแชร์อัตโนมัติ กรุณาแชร์ด้วยปุ่มด้านล่าง");
        document.querySelector('.share-fallback').style.display = 'flex';
      }
    }

    function copyLink() {
      const url = window.location.href;
      navigator.clipboard.writeText(url).then(() => {
        alert("คัดลอกลิงก์แล้ว");
      });
    }
  </script>

  <?php include_once 'includes/footer.php'; ?>
</body>

</html>