<?php
require '../../includes/db.php';

$id = $_GET['id'] ?? null;

if (!$id) {
  header("Location: index.php");
  exit;
}

// ดึงข้อมูลบทความก่อนลบ
$stmt = $pdo->prepare("SELECT title, image FROM articles WHERE id = ?");
$stmt->execute([$id]);
$article = $stmt->fetch();

if (!$article) {
  echo "<h2>ไม่พบบทความ</h2>";
  exit;
}

// ยืนยันการลบผ่าน POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
  // ลบรูปเพิ่มเติมก่อน
  $stmtImg = $pdo->prepare("SELECT image_path FROM article_images WHERE article_id = ?");
  $stmtImg->execute([$id]);
  $images = $stmtImg->fetchAll();
  foreach ($images as $img) {
    $file = '../../uploads/' . $img['image_path'];
    if (file_exists($file)) unlink($file);
  }
  $pdo->prepare("DELETE FROM article_images WHERE article_id = ?")->execute([$id]);

  // ลบรูปภาพหลัก
  $mainImage = '../../uploads/' . $article['image'];
  if (file_exists($mainImage)) unlink($mainImage);

  // ลบบทความ
  $pdo->prepare("DELETE FROM articles WHERE id = ?")->execute([$id]);
  header("Location: index.php");
  exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>ยืนยันการลบ</title>
  <link rel="stylesheet" href="../../assets/css/dashboard-style.css">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet">
  
  <style>
    .confirm-box {
      max-width: 520px;
      margin: 80px auto;
      padding: 30px;
      background: #fff;
      border-radius: 16px;
      text-align: center;
      box-shadow: 0 8px 16px rgba(0,0,0,0.06);
    }
    .confirm-box h2 {
      font-size: 22px;
      margin-bottom: 12px;
      color: #d32f2f;
    }
    .confirm-box p {
      font-size: 15px;
      margin-bottom: 20px;
    }
    .confirm-box img {
      max-width: 100%;
      border-radius: 10px;
      margin-bottom: 24px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.05);
    }
    .btns {
      display: flex;
      justify-content: center;
      gap: 16px;
    }
    .btns button, .btns a {
      padding: 10px 20px;
      font-size: 14px;
      font-weight: 500;
      border-radius: 6px;
      text-decoration: none;
      border: none;
      cursor: pointer;
    }
    .btn-delete { background: #e53935; color: #fff; }
    .btn-cancel { background: #cfd8dc; color: #333; }
  </style>
</head>
<body>

<!-- Sidebar -->
  <?php include_once '../../templates/sidebar.php'; ?>


  <div class="confirm-box">
    <h2>ยืนยันการลบบทความ</h2>
    <p>คุณแน่ใจหรือไม่ว่าต้องการลบบทความนี้:</p>
    <p><strong><?= htmlspecialchars($article['title']) ?></strong></p>
    <?php if (!empty($article['image'])): ?>
      <img src="../../uploads/<?= htmlspecialchars($article['image']) ?>" alt="<?= htmlspecialchars($article['title']) ?>">
    <?php endif; ?>
    <form method="POST">
      <div class="btns">
        <button type="submit" name="confirm_delete" class="btn-delete">ยืนยันการลบ</button>
        <a href="index.php" class="btn-cancel">ยกเลิก</a>
      </div>
    </form>
  </div>
</body>
</html>
