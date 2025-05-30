<?php
include 'includes/db.php';

// ป้องกัน XSS และจัดการตัวแปร
function e($str)
{
  return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

$search = trim($_GET['q'] ?? '');
$category = $_GET['cat'] ?? 'all';
$sort = $_GET['sort'] ?? 'latest';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 6;
$offset = ($page - 1) * $perPage;

$conditions = [];
$params = [];

$conditions[] = "status = 1"; // ✅ แสดงเฉพาะบทความที่เปิดใช้งานเท่านั้น

if ($search !== '') {
  $conditions[] = "(title LIKE ? OR content LIKE ?)";
  $params[] = "%$search%";
  $params[] = "%$search%";
}
if ($category !== 'all') {
  $conditions[] = "category = ?";
  $params[] = $category;
}

$whereClause = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";
$orderBy = "created_at DESC";
if ($sort === 'oldest')
  $orderBy = "created_at ASC";
if ($sort === 'az')
  $orderBy = "title ASC";

// รวมจำนวนบทความทั้งหมดเพื่อใช้ทำ pagination
$countSql = "SELECT COUNT(*) FROM articles $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalArticles = $countStmt->fetchColumn();
$totalPages = ceil($totalArticles / $perPage);

$sql = "SELECT * FROM articles $whereClause ORDER BY $orderBy LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$articles = $stmt->fetchAll();

// ✅ ดึงบทความยอดนิยม 3 อันดับ
$popularStmt = $pdo->query("SELECT id, title, slug, image FROM articles WHERE status = 1 ORDER BY views DESC LIMIT 3");
$popularArticles = $popularStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>บทความ | FixMac</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="assets/css/navbar-style.css">
  <link rel="stylesheet" href="assets/css/articles-style.css">
  <link rel="stylesheet" href="/assets/css/footer-style.css">
  <link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png" />
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
</head>

<body>
  <?php include 'includes/header.php'; ?>

  <section class="article-hero">
    <div class="container">
      <h1>บทความจากทีมช่าง Apple</h1>
      <form method="GET" class="article-search">
        <input type="text" name="q" placeholder="ค้นหาบทความ..." value="<?= e($search) ?>">
        <button type="submit"><span class="material-symbols-rounded">search</span></button>
      </form>

      <div class="article-categories">
        <?php $cats = ['all' => 'ทั้งหมด', 'tip' => 'เทคนิค', 'repair' => 'การซ่อม', 'update' => 'อัปเดต'];
        foreach ($cats as $key => $label): ?>
          <a href="?cat=<?= $key ?>&q=<?= urlencode($search) ?>" class="<?= ($category === $key ? 'active' : '') ?>">
            <?= $label ?>
          </a>
        <?php endforeach; ?>
      </div>

      <div class="sort-options">
        <label for="sort">เรียงลำดับ:</label>
        <select id="sort" onchange="location.href=this.value">
          <option value="?cat=<?= $category ?>&q=<?= urlencode($search) ?>&sort=latest" <?= $sort == 'latest' ? 'selected' : '' ?>>ล่าสุด</option>
          <option value="?cat=<?= $category ?>&q=<?= urlencode($search) ?>&sort=oldest" <?= $sort == 'oldest' ? 'selected' : '' ?>>เก่าสุด</option>
          <option value="?cat=<?= $category ?>&q=<?= urlencode($search) ?>&sort=az" <?= $sort == 'az' ? 'selected' : '' ?>>
            A-Z</option>
        </select>
      </div>
    </div>
  </section>

  <?php if ($popularArticles): ?>
    <section class="articles-container">
      <div class="container">
        <h2>บทความยอดนิยม</h2>
        <div class="popular-list">
          <?php foreach ($popularArticles as $pop): ?>
            <a href="article-detail.php?slug=<?= urlencode($pop['slug']) ?>" class="popular-item">
              <img src="uploads/<?= e($pop['image']) ?>" alt="<?= e($pop['title']) ?>">
              <h3><?= e($pop['title']) ?></h3>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>
<hr style="margin: 60px auto; max-width: 100px; border: none; border-top: 2px solid #ddd;" />
  <section class="articles-container">
    <?php if ($articles): ?>
      <?php foreach ($articles as $row): ?>
        <a href="article-detail.php?slug=<?= urlencode($row['slug']) ?>" class="article-card">
          <div class="article-image">
            <img src="uploads/<?= e($row['image']) ?>" alt="<?= e($row['title']) ?>">
          </div>
          <div class="article-body">
            <p class="date"><?= date('d M Y', strtotime($row['created_at'])) ?></p>
            <h2><?= e($row['title']) ?></h2>
            <p class="excerpt"><?= mb_substr(strip_tags($row['excerpt'] ?: $row['content']), 0, 100) ?>...</p>
            <p class="views">👁 <?= number_format($row['views']) ?> ครั้ง</p>
            <span class="read-more">อ่านเพิ่มเติม</span>
          </div>
        </a>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="container">
        <p>ไม่พบบทความที่ตรงกับคำค้นหาหรือหมวดหมู่ที่เลือก</p>
      </div>
    <?php endif; ?>
  </section>



  <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <?php
      $base = "?cat=$category&q=" . urlencode($search) . "&sort=$sort&page=";
      if ($page > 1)
        echo '<a href="' . $base . '1">«</a>';
      if ($page > 3)
        echo '<span class="dots">...</span>';
      for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) {
        echo '<a href="' . $base . $i . '" class="' . ($i == $page ? 'active' : '') . '">' . $i . '</a>';
      }
      if ($page < $totalPages - 2)
        echo '<span class="dots">...</span>';
      if ($page < $totalPages)
        echo '<a href="' . $base . $totalPages . '">»</a>';
      ?>
    </div>
  <?php endif; ?>

  <?php include_once 'includes/footer.php'; ?>
</body>

</html>