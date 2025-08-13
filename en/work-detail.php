<?php
// Assuming this file is /en/work-detail.php
include '../includes/db.php'; 

function e($string) { 
  return htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
  header("Location: works.php"); 
  exit;
}

$pdo->prepare("UPDATE repairs SET views = views + 1 WHERE id = ?")->execute([$id]);

$stmt = $pdo->prepare("SELECT *, title AS title_th, issue AS issue_th, fix_detail AS fix_detail_th FROM repairs WHERE id = ?");
$stmt->execute([$id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
  http_response_code(404);
  echo "<h1>Work Item Not Found</h1>"; 
  exit;
}

$stmtImages = $pdo->prepare("SELECT *, caption as caption_th FROM repair_images WHERE repair_id = ?");
$stmtImages->execute([$id]);
$images = $stmtImages->fetchAll(PDO::FETCH_ASSOC);

// --- English Content Logic with Fallbacks ---
$display_name_en = !empty(trim($data['title_en'])) ? e($data['title_en']) : (!empty(trim($data['title_th'])) ? e($data['title_th']) . " (Details in Thai)" : "Repair Job ID: #" . e($id));
$page_title_en = $display_name_en . " | Repair Details | CMNS FixMac";
$meta_description_en = !empty(trim($data['fix_detail_en'])) ? e(mb_substr(strip_tags($data['fix_detail_en']), 0, 160)) : "View details for repair job: " . $display_name_en . " at CMNS FixMac Chiang Mai.";
$main_image_alt_en = "Main image for " . $display_name_en;
$issue_display_en = !empty(trim($data['issue_en'])) ? nl2br(e($data['issue_en'])) : (!empty(trim($data['issue_th'])) ? "<em>(Issue details below are in Thai)</em><br>" . nl2br(e($data['issue_th'])) : "<em>Issue details will be updated in English soon.</em>");
$fix_detail_display_en = !empty(trim($data['fix_detail_en'])) ? nl2br(e($data['fix_detail_en'])) : (!empty(trim($data['fix_detail_th'])) ? "<em>(Repair process details below are in Thai)</em><br>" . nl2br(e($data['fix_detail_th'])) : "<em>Repair details will be updated in English soon.</em>");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= $page_title_en ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= $meta_description_en ?>">
  
  <?php
  // --- Hreflang Tags for Work Detail Page (Corrected) ---
  $currentItemId = $data['id'] ?? 0;
  if ($currentItemId > 0) {
    $th_url = "https://cmnsfixmac.com/work-detail.php?id=" . $currentItemId;
    $en_url = "https://cmnsfixmac.com/en/work-detail.php?id=" . $currentItemId;

    echo '<link rel="alternate" hreflang="th" href="' . htmlspecialchars($th_url) . '" />' . "\n";
    echo '    <link rel="alternate" hreflang="en" href="' . htmlspecialchars($en_url) . '" />' . "\n";
    echo '    <link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($en_url) . '" />' . "\n";
  }
  ?>
  
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="/assets/css/works-detail-style.css">
  <link rel="stylesheet" href="/assets/css/footer-style.css">
  <link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png" />
  </head>
<body>
  <?php include_once '../includes/header_en.php'; ?>
  
  <div class="container article-detail">
    <h1><?= $display_name_en ?></h1>
    
    <nav class="breadcrumb-bar">
      <a href="/en/works.php" class="breadcrumb-home">All Work</a> &gt;
      <span class="breadcrumb-current"><?= $display_name_en ?></span>
      <p><strong>Date Posted:</strong> <?= date('F d, Y, H:i', strtotime($data['created_at'])) ?></p>
    </nav>

    <img src="/uploads/<?= e($data['image']) ?>" class="main-image" alt="<?= $main_image_alt_en ?>">

    <?php if ($images): ?>
      <section class="article-gallery">
        <h2>Gallery</h2>
        <div class="gallery-grid">
          <?php foreach ($images as $img_idx => $img): 
              $gallery_image_path = (strpos($img['image_path'], 'uploads/') === 0) ? e($img['image_path']) : 'uploads/' . e($img['image_path']);
              $gallery_alt = $main_image_alt_en . " - image " . ($img_idx + 1);
              $gallery_caption = !empty(trim($img['caption_en'])) ? e($img['caption_en']) : (!empty(trim($img['caption_th'])) ? e($img['caption_th']) . ' (in Thai)' : '');
          ?>
            <figure>
              <img src="/<?= $gallery_image_path ?>" alt="<?= $gallery_alt ?>">
              <?php if ($gallery_caption): ?>
                <figcaption><?= $gallery_caption ?></figcaption>
              <?php endif; ?>
            </figure>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <div class="article-meta">
      <p><strong>Model:</strong> <?= e($data['model']) ?></p>
      <p><strong>Category:</strong> <?= e($data['category']) ?></p>
      <h3>Issue Reported:</h3>
      <div><?= $issue_display_en ?></div>
      <h3>Repair Details:</h3>
      <div><?= $fix_detail_display_en ?></div>
      <p class="views"><strong>Views:</strong> <?= number_format($data['views']) ?> views</p>
    </div>
    
    </div>
  
  <?php include_once '../includes/footer_en.php'; ?>
</body>
</html>