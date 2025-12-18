<?php
/*
 * articles.php
 * - [GEMINI FINAL UPGRADE]
 * - เปลี่ยนลิงก์ทั้งหมดให้ใช้ URL สวย (/article/slug) เพื่อ SEO
 * - Fallback กลับไปใช้ ID ถ้าไม่มี Slug
 */

include 'includes/db.php';

function e($str) {
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

$conditions[] = "status = 1"; 

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
if ($sort === 'oldest') $orderBy = "created_at ASC";
if ($sort === 'az') $orderBy = "title ASC";

// Pagination Logic
$countSql = "SELECT COUNT(*) FROM articles $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalArticles = $countStmt->fetchColumn();
$totalPages = ceil($totalArticles / $perPage);

$sql = "SELECT * FROM articles $whereClause ORDER BY $orderBy LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$articles = $stmt->fetchAll();

// Popular Articles
$popularStmt = $pdo->query("SELECT id, title, slug, image FROM articles WHERE status = 1 ORDER BY views DESC LIMIT 3");
$popularArticles = $popularStmt->fetchAll();

// --- Helper Function สร้างลิงก์สวยๆ ---
function getArticleLink($row) {
    // ถ้ามี slug ให้ใช้ /article/slug
    if (!empty($row['slug'])) {
        return '/article/' . e($row['slug']);
    }
    // ถ้าไม่มีให้ใช้ id แบบเดิม
    return 'article-detail.php?id=' . e($row['id']);
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>บทความ | FixMac</title>

  <link rel="alternate" hreflang="th" href="https://cmnsfixmac.com/articles.php" />
  <link rel="alternate" hreflang="en" href="https://cmnsfixmac.com/en/articles.php" />
  <link rel="alternate" hreflang="x-default" href="https://cmnsfixmac.com/en/articles.php" />

  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/articles-style.css">
  <link rel="stylesheet" href="/assets/css/footer-style.css">
  <link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png" />
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />


  <script async src="https://www.googletagmanager.com/gtag/js?id=G-3WXK9GWN7C"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag() { dataLayer.push(arguments); }
    gtag('js', new Date());
    gtag('config', 'G-3WXK9GWN7C');
  </script>

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
          <option value="?cat=<?= $category ?>&q=<?= urlencode($search) ?>&sort=az" <?= $sort == 'az' ? 'selected' : '' ?>>A-Z</option>
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
            <?php
            $image_filename_pop = $pop['image'] ?? '';
            $imagePathPop = (!empty($image_filename_pop) && strpos($image_filename_pop, '/') !== false) ? e($image_filename_pop) : 'assets/img/placeholder.png';
            
            // [GEMINI EDIT] ใช้ Function สร้างลิงก์สวยๆ
            $articleLink = getArticleLink($pop);
            ?>
            <a href="<?= $articleLink ?>" class="popular-item">
              <img src="<?= $imagePathPop ?>" alt="<?= e($pop['title']) ?>">
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
        <?php
        $image_filename_main = $row['image'] ?? '';
        $imagePathMain = (!empty($image_filename_main) && strpos($image_filename_main, '/') !== false) ? e($image_filename_main) : 'assets/img/placeholder.png';
        
        // [GEMINI EDIT] ใช้ Function สร้างลิงก์สวยๆ
        $articleLink = getArticleLink($row);
        ?>
        <a href="<?= $articleLink ?>" class="article-card">
          <div class="article-image">
            <img src="<?= $imagePathMain ?>" alt="<?= e($row['title']) ?>">
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
      if ($page > 1) echo '<a href="' . $base . '1">«</a>';
      if ($page > 3) echo '<span class="dots">...</span>';
      for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) {
        echo '<a href="' . $base . $i . '" class="' . ($i == $page ? 'active' : '') . '">' . $i . '</a>';
      }
      if ($page < $totalPages - 2) echo '<span class="dots">...</span>';
      if ($page < $totalPages) echo '<a href="' . $base . $totalPages . '">»</a>';
      ?>
    </div>
  <?php endif; ?>

  <?php include_once 'includes/footer.php'; ?>
</body>

</html>