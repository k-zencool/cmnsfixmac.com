<?php
// Assuming this file is /en/articles.php
include '../includes/db.php'; // Path changed

function e($str)
{
  return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
}

// --- English Page Logic ---
$title_col = 'title_en';
$content_col = 'content_en';
$excerpt_col = 'excerpt_en';
// Use slug_en if it exists and is not empty, otherwise fallback to the original slug
$slug_col = "IF(slug_en IS NOT NULL AND slug_en != '', slug_en, slug)";

$search = trim($_GET['q'] ?? '');
$category_filter = $_GET['cat'] ?? 'all';
$sort = $_GET['sort'] ?? 'latest';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 6;
$offset = ($page - 1) * $perPage;

$conditions = [];
$params = [];

// IMPORTANT: Only show articles that have been translated into English
$conditions[] = "status = 1 AND ({$title_col} IS NOT NULL AND {$title_col} != '')";

if ($search !== '') {
  // Search against English title and content
  $conditions[] = "({$title_col} LIKE ? OR {$content_col} LIKE ?)";
  $params[] = "%$search%";
  $params[] = "%$search%";
}
if ($category_filter !== 'all') {
  $conditions[] = "LOWER(category) = LOWER(?)";
  $params[] = $category_filter;
}

$whereClause = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";
$orderBy = "created_at DESC"; // Default: Latest
if ($sort === 'oldest') $orderBy = "created_at ASC";
if ($sort === 'az') $orderBy = "{$title_col} ASC"; // Sort by English title

// Count total articles for pagination
$countSql = "SELECT COUNT(*) FROM articles $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalArticles = $countStmt->fetchColumn();
$totalPages = ceil($totalArticles / $perPage);

// Fetch articles for the current page
$sql = "SELECT id, {$title_col} AS title_display, {$content_col} AS content_display, {$excerpt_col} AS excerpt_display, {$slug_col} AS slug_display, image, created_at, views, category FROM articles $whereClause ORDER BY $orderBy LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch popular articles (English version)
$popularStmt = $pdo->query("SELECT id, {$title_col} AS title_display, {$slug_col} AS slug_display, image FROM articles WHERE status = 1 AND ({$title_col} IS NOT NULL AND {$title_col} != '') ORDER BY views DESC LIMIT 3");
$popularArticles = $popularStmt->fetchAll(PDO::FETCH_ASSOC);

// Define English category labels
$categories_en = [
  'all' => 'All Articles',
  'tip' => 'Tips & Tricks',
  'repair' => 'Repair Insights',
  'update' => 'Updates'
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Articles & Tech Tips | CMNS FixMac Chiang Mai</title>

  <link rel="alternate" hreflang="th" href="https://cmnsfixmac.com/articles.php" />
  <link rel="alternate" hreflang="en" href="https://cmnsfixmac.com/en/articles.php" />
  <link rel="alternate" hreflang="x-default" href="https://cmnsfixmac.com/en/articles.php" />

  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Read useful articles, tech tips, and updates from the Apple repair experts at CMNS FixMac in Chiang Mai. Learn more about your Apple devices.">
  <meta name="keywords" content="Apple tips, MacBook help, iPhone tricks, Mac repair articles, Chiang Mai tech blog">

  <link rel="stylesheet" href="/assets/css/navbar-style.css">
  <link rel="stylesheet" href="/assets/css/articles-style.css">
  <link rel="stylesheet" href="/assets/css/footer-style.css">
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png" />
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />



  <script async src="https://www.googletagmanager.com/gtag/js?id=G-3WXK9GWN7C"></script>
  <script>
    window.dataLayer = window.dataLayer || [];

    function gtag() {
      dataLayer.push(arguments);
    }
    gtag('js', new Date());
    gtag('config', 'G-3WXK9GWN7C');
  </script>
</head>

<body>
  <?php include '../includes/header_en.php'; ?>


  <section class="article-hero">
    <div class="container">
      <h1>Articles from Our Apple Technicians</h1>
      <form method="GET" class="article-search" action="articles.php">
        <input type="text" name="q" placeholder="Search articles..." value="<?= e($search) ?>"> <button type="submit"><span class="material-symbols-rounded">search</span></button>
      </form>

      <div class="article-categories">
        <?php foreach ($categories_en as $key => $label_en): ?>
          <a href="?cat=<?= $key ?>&q=<?= urlencode($search) ?>&sort=<?= $sort ?>" class="<?= ($category_filter === $key ? 'active' : '') ?>">
            <?= $label_en ?>
          </a>
        <?php endforeach; ?>
      </div>

      <div class="sort-options">
        <label for="sort-select">Sort by:</label> <select id="sort-select" onchange="if(this.value) location.href=this.value;">
          <option value="?cat=<?= $category_filter ?>&q=<?= urlencode($search) ?>&sort=latest" <?= $sort == 'latest' ? 'selected' : '' ?>>Latest</option>
          <option value="?cat=<?= $category_filter ?>&q=<?= urlencode($search) ?>&sort=oldest" <?= $sort == 'oldest' ? 'selected' : '' ?>>Oldest</option>
          <option value="?cat=<?= $category_filter ?>&q=<?= urlencode($search) ?>&sort=az" <?= $sort == 'az' ? 'selected' : '' ?>>A-Z</option>
        </select>
      </div>
    </div>
  </section>

  <?php if ($popularArticles): ?>
    <section class="articles-container popular-articles-section">
      <div class="container">
        <h2>Popular Articles</h2>
        <div class="popular-list">
          <?php foreach ($popularArticles as $pop): ?>
            <a href="article-detail.php?slug=<?= urlencode($pop['slug_display']) ?>" class="popular-item"> <img src="/uploads/<?= e($pop['image']) ?>" alt="<?= e($pop['title_display']) ?>">
              <h3><?= e($pop['title_display']) ?></h3>
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
          <a href="article-detail.php?slug=<?= urlencode($row['slug_display']) ?>" class="article-card">
            <div class="article-image">
              <img src="/uploads/<?= e($row['image']) ?>" alt="<?= e($row['title_display']) ?>">
            </div>
            <div class="article-body">
              <p class="date"><?= date('d M Y', strtotime($row['created_at'])) ?></p>
              <h2><?= e($row['title_display']) ?></h2>
              <p class="excerpt"><?= e(mb_substr(strip_tags($row['excerpt_display'] ?: $row['content_display']), 0, 100)) ?>...</p>
              <p class="views">👁 <?= number_format($row['views']) ?> views</p> <span class="read-more">Read More</span>
            </div>
          </a>
        <?php endforeach; ?>
      <?php else: ?>
        <p>No articles found matching your search or selected category. (Note: Only articles with English translations will be shown.)</p> <?php endif; ?>
  </section>



  <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <?php
      $base_en = "?cat=$category_filter&q=" . urlencode($search) . "&sort=$sort&page=";
      if ($page > 1) echo '<a href="' . $base_en . '1">« First</a> ';
      if ($page > 1) echo '<a href="' . $base_en . ($page - 1) . '">‹ Prev</a> ';
      if ($page > 3) echo '<span class="dots">...</span> ';

      for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) {
        echo '<a href="' . $base_en . $i . '" class="' . ($i == $page ? 'active' : '') . '">' . $i . '</a> ';
      }

      if ($page < $totalPages - 2) echo '<span class="dots">...</span> ';
      if ($page < $totalPages) echo '<a href="' . $base_en . ($page + 1) . '">Next ›</a> ';
      if ($page < $totalPages) echo '<a href="' . $base_en . $totalPages . '">Last »</a>';
      ?>
    </div>
  <?php endif; ?>

  <?php include_once '../includes/footer_en.php'; // Path changed 
  ?>
</body>

</html>