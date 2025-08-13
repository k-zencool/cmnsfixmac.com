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
  <link rel="stylesheet" href="/assets/css/style.css"> <link rel="stylesheet" href="/assets/css/works-detail-style.css"> <link rel="stylesheet" href="/assets/css/footer-style.css"> <link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png" />
  
  <?php
  // --- Hreflang Tags for Work Detail Page (Corrected Placement) ---
  $currentItemId = $data['id'] ?? 0;
  $pageName = 'work-detail.php'; // Correct page name

  if ($currentItemId > 0) {
    $th_url = "https://cmnsfixmac.com/" . $pageName . "?id=" . $currentItemId;
    $en_url = "https://cmnsfixmac.com/en/" . $pageName . "?id=" . $currentItemId;

    echo '<link rel="alternate" hreflang="th" href="' . htmlspecialchars($th_url) . '" />' . "\n";
    echo '    <link rel="alternate" hreflang="en" href="' . htmlspecialchars($en_url) . '" />' . "\n";
    echo '    <link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($en_url) . '" />' . "\n";
  }
  ?>

  <meta property="og:title" content="<?= e($data['title']) ?> - CMNS Mac Repair">
  <meta property="og:description" content="<?= e(mb_substr(strip_tags($data['fix_detail']), 0, 100)) ?>...">
  <meta property="og:image" content="https://cmnsfixmac.com/uploads/<?= e($data['image']) ?>">
  <meta property="og:url" content="https://cmnsfixmac.com/work-detail.php?id=<?= $id ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="description" content="<?= e(mb_substr(strip_tags($data['fix_detail']), 0, 160)) ?>">
  <meta name="keywords" content="<?= e($data['title']) ?>, ซ่อม <?= e($data['model']) ?>, <?= e($data['category']) ?>">

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
    <img src="/uploads/<?= e($data['image']) ?>" class="main-image" alt="ภาพงานซ่อม <?= e($data['title']) ?> รุ่น <?= e($data['model']) ?> หมวด <?= e($data['category']) ?>">
    <?php if ($images): ?>
      <section class="article-gallery">
        <h2>แกลเลอรี</h2>
        <div class="gallery-grid">
          <?php foreach ($images as $img): ?>
            <figure>
              <img src="/<?= e($img['image_path']) ?>" alt="<?= e($img['caption']) ?>">
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
    </div>
  <?php include_once 'includes/footer.php'; ?>
</body>
</html>