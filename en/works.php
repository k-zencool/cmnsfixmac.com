<?php
// Assuming this file is /en/works.php
include '../includes/db.php'; // Path changed

function e($string)
{
  return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

$q = filter_input(INPUT_GET, 'q', FILTER_SANITIZE_SPECIAL_CHARS);
$category_filter = filter_input(INPUT_GET, 'category', FILTER_SANITIZE_SPECIAL_CHARS); // Renamed from $category to avoid conflict with $row['category']
$sort = filter_input(INPUT_GET, 'sort', FILTER_SANITIZE_SPECIAL_CHARS);
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
if (!$page || $page < 1) $page = 1;

// Define which title column to use and search against
$title_column = "title_en"; // For English page, use title_en
$title_for_display_alias = "title_display"; // Alias for easier use in template

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Our Work Portfolio - CMNS Mac Repair Chiang Mai</title>

  <link rel="alternate" hreflang="th" href="https://cmnsfixmac.com/works.php" />
  <link rel="alternate" hreflang="en" href="https://cmnsfixmac.com/en/works.php" />
  <link rel="alternate" hreflang="x-default" href="https://cmnsfixmac.com/en/works.php" />
  
  <meta name="description" content="View our portfolio of successful Apple device repairs in Chiang Mai. CMNS Mac Repair showcases MacBook, iMac, iPhone, and iPad repair work.">
  <meta name="keywords" content="MacBook repair portfolio, iPhone repair examples, Apple service Chiang Mai, CMNS FixMac work">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/works-style.css">
  <link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png" />
  <link rel="stylesheet" href="../assets/css/footer-style.css">
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

  <?php include_once '../includes/header_en.php'; // Changed to header_en.php and path adjusted 
  ?>

  <div class="page-container">

    <h1>Our Repair Portfolio</h1>
    <form method="GET" action="works.php"> <input type="text" name="q" placeholder="Search model or issue (e.g., A1708, screen)" <?= $q ? 'value="' . e($q) . '"' : '' ?> />
      <select name="category">
        <option value="">All Categories</option>
        <option value="MacBook" <?= $category_filter === "MacBook" ? "selected" : "" ?>>MacBook</option>
        <option value="iMac" <?= $category_filter === "iMac" ? "selected" : "" ?>>iMac</option>
        <option value="iPhone" <?= $category_filter === "iPhone" ? "selected" : "" ?>>iPhone</option>
        <option value="iPad" <?= $category_filter === "iPad" ? "selected" : "" ?>>iPad</option>
        <option value="AirPods" <?= $category_filter === "AirPods" ? "selected" : "" ?>>AirPods</option>
        <option value="AppleWatch" <?= $category_filter === "AppleWatch" ? "selected" : "" ?>>Apple Watch</option>
      </select>
      <select name="sort">
        <option value="latest" <?= $sort === "latest" ? "selected" : "" ?>>Latest</option>
        <option value="views" <?= $sort === "views" ? "selected" : "" ?>>Popular</option>
      </select>
      <button type="submit">Search</button>
    </form>

    <?php
    $where = [];
    $params = [];

    // IMPORTANT: Only show items that have an English title
    $where[] = "($title_column IS NOT NULL AND $title_column != '')";

    if (!empty($q)) {
      // Search against English title and model
      // TODO: If you add issue_en to DB, you could search that too: OR issue_en LIKE ?
      $where[] = "($title_column LIKE ? OR model LIKE ?)";
      $params[] = '%' . $q . '%';
      $params[] = '%' . $q . '%';
    }

    if (!empty($category_filter)) { // Used $category_filter
      $where[] = "LOWER(category) = LOWER(?)";
      $params[] = $category_filter;
    }

    $limit = 12;
    $offset = ($page - 1) * $limit;

    $countSql = "SELECT COUNT(*) FROM repairs";
    if ($where) {
      $countSql .= " WHERE " . implode(" AND ", $where);
    }
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();
    $totalPages = ceil($total / $limit);

    if ($total > 0) {
      echo '<p class="results-count">Found ' . $total . ' items</p>'; // Translated
    } else {
      echo '<p class="results-count">No items found matching your search. (Ensure English titles are available for items to appear)</p>'; // Translated and added note
    }

    // Select English title and alias it for display
    $sql = "SELECT id, $title_column AS $title_for_display_alias, model, image, views, created_at, category FROM repairs";
    if ($where) {
      $sql .= " WHERE " . implode(" AND ", $where);
    }

    if ($sort === 'views') {
      $sql .= " ORDER BY views DESC, created_at DESC";
    } else { // Default to latest
      $sql .= " ORDER BY created_at DESC";
    }

    $sql .= " LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    ?>

    <div class="work-grid">
      <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
        <?php
        $imagePath = (!empty($row['image']) && file_exists('../uploads/' . e($row['image']))) // Path changed
          ? '../uploads/' . e($row['image']) // Path changed
          : '../assets/img/placeholder.png'; // Path changed

        // Use the English title (aliased as title_display) for alt text if available, otherwise generic
        $alt_text = !empty($row[$title_for_display_alias]) ? e($row[$title_for_display_alias]) : "Repair work for " . e($row['model']);
        ?>
        <a href="work-detail.php?id=<?= e($row['id']) ?>" class="work-card-link">
          <div class="work-card">
            <img src="<?= $imagePath ?>" alt="<?= $alt_text ?>" loading="lazy">
            <h3><?= e($row[$title_for_display_alias]) ?></h3>
            <p><?= e($row['model']) ?></p>
            <p class="views"><?= number_format($row['views']) ?> views</p>
          </div>
        </a>
      <?php endwhile; ?>
    </div>

    <div class="pagination">
      <?php if ($totalPages > 1): ?>
        <?php
        // Pagination logic, href="?..." keeps query params on current /en/works.php
        $range = 2;
        $start = max(1, $page - $range);
        $end = min($totalPages, $page + $range);
        $query_params_pagination = $_GET; // Use current GET params for pagination links

        // First & Prev
        if ($page > 1) {
          $query_params_pagination['page'] = 1;
          echo '<a href="?' . http_build_query($query_params_pagination) . '" class="arrow">« First</a> ';
          $query_params_pagination['page'] = $page - 1;
          echo '<a href="?' . http_build_query($query_params_pagination) . '" class="arrow">‹ Prev</a> ';
        }

        if ($start > 1 && $start > 2) echo '<span class="dots">...</span> ';


        for ($i = $start; $i <= $end; $i++) {
          $query_params_pagination['page'] = $i;
          echo '<a href="?' . http_build_query($query_params_pagination) . '" class="' . ($i == $page ? 'active' : '') . '">' . $i . '</a> ';
        }

        if ($end < $totalPages && $end < $totalPages - 1) echo '<span class="dots">...</span> ';

        // Next & Last
        if ($page < $totalPages) {
          $query_params_pagination['page'] = $page + 1;
          echo '<a href="?' . http_build_query($query_params_pagination) . '" class="arrow">Next ›</a> ';
          if ($page < $totalPages - 1) {
            $query_params_pagination['page'] = $totalPages;
            echo '<a href="?' . http_build_query($query_params_pagination) . '" class="arrow">Last »</a>';
          }
        }
        ?>
      <?php endif; ?>
    </div>

    <a href="/en/" class="back-link">← Back to Home</a>
  </div>

  <?php include_once '../includes/footer_en.php'; // Changed to footer_en.php and path adjusted 
  ?>
  <script src="../assets/js/preload-images.js"></script>
</body>

</html>