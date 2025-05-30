<?php
include 'includes/db.php';

function e($string) {
  return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

$q = filter_input(INPUT_GET, 'q', FILTER_SANITIZE_SPECIAL_CHARS);
$category = filter_input(INPUT_GET, 'category', FILTER_SANITIZE_SPECIAL_CHARS);
$sort = filter_input(INPUT_GET, 'sort', FILTER_SANITIZE_SPECIAL_CHARS);
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
if (!$page || $page < 1) $page = 1;
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>ผลงานทั้งหมด - CMNS Mac Repair</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/works-style.css">
  <link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png" />
  <link rel="stylesheet" href="/assets/css/footer-style.css">
  
</head>
<body>

<?php include_once 'includes/header.php'; ?>

<div class="page-container">

  <h1>ผลงานซ่อมทั้งหมด</h1>

  <form method="GET">
    <input type="text" name="q" placeholder="ค้นหารุ่น เช่น A1708"
      <?= $q ? 'value="' . e($q) . '"' : '' ?> />
    <select name="category">
      <option value="">ทั้งหมด</option>
      <option value="MacBook" <?= $category === "MacBook" ? "selected" : "" ?>>MacBook</option>
      <option value="iMac" <?= $category === "iMac" ? "selected" : "" ?>>iMac</option>
      <option value="iPhone" <?= $category === "iPhone" ? "selected" : "" ?>>iPhone</option>
      <option value="iPad" <?= $category === "iPad" ? "selected" : "" ?>>iPad</option>
      <option value="AirPods" <?= $category === "AirPods" ? "selected" : "" ?>>AirPods</option>
      <option value="AppleWatch" <?= $category === "AppleWatch" ? "selected" : "" ?>>AppleWatch</option>

    </select>
    <select name="sort">
      <option value="latest" <?= $sort === "latest" ? "selected" : "" ?>>ใหม่ล่าสุด</option>
      <option value="views" <?= $sort === "views" ? "selected" : "" ?>>ยอดนิยม</option>
    </select>
    <button type="submit">ค้นหา</button>
  </form>

  <?php
    $where = [];
    $params = [];

    if (!empty($q)) {
      $where[] = "(title LIKE ? OR model LIKE ?)";
      $params[] = '%' . $q . '%';
      $params[] = '%' . $q . '%';
    }

    if (!empty($category)) {
      $where[] = "LOWER(category) = LOWER(?)";
      $params[] = $category;
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
      echo '<p class="results-count">พบทั้งหมด ' . $total . ' รายการ</p>';
    } else {
      echo '<p class="results-count">ไม่พบข้อมูลที่ค้นหา</p>';
    }

    $sql = "SELECT * FROM repairs";
    if ($where) {
      $sql .= " WHERE " . implode(" AND ", $where);
    }

    if ($sort === 'views') {
      $sql .= " ORDER BY views DESC, created_at DESC";
    } else {
      $sql .= " ORDER BY created_at DESC";
    }

    $sql .= " LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
  ?>

  <div class="work-grid">
    <?php while ($row = $stmt->fetch()): ?>
      <?php
        $imagePath = (!empty($row['image']) && file_exists('uploads/' . $row['image']))
          ? 'uploads/' . e($row['image'])
          : 'assets/img/placeholder.png';
      ?>
      <a href="work-detail.php?id=<?= e($row['id']) ?>" class="work-card-link">
        <div class="work-card">
          <img src="<?= $imagePath ?>" alt="<?= e($row['title']) ?>" loading="lazy">
          <h3><?= e($row['title']) ?></h3>
          <p><?= e($row['model']) ?></p>
          <p class="views"><?= number_format($row['views']) ?> เข้าชม</p>
        </div>
      </a>
    <?php endwhile; ?>
  </div>

  <div class="pagination">
    <?php if ($totalPages > 1): ?>
      <?php
        $range = 2;
        $start = max(1, $page - $range);
        $end = min($totalPages, $page + $range);
        $query = $_GET;

        if ($page > 1) {
          $query['page'] = 1;
          echo '<a href="?' . http_build_query($query) . '" class="arrow">«</a>';
        }

        if ($start > 1) echo '<span class="dots">...</span>';

        for ($i = $start; $i <= $end; $i++) {
          $query['page'] = $i;
          echo '<a href="?' . http_build_query($query) . '" class="' . ($i == $page ? 'active' : '') . '">' . $i . '</a>';
        }

        if ($end < $totalPages) echo '<span class="dots">...</span>';

        if ($page < $totalPages) {
          $query['page'] = $totalPages;
          echo '<a href="?' . http_build_query($query) . '" class="arrow">»</a>';
        }
      ?>
    <?php endif; ?>
  </div>

  <a href="index.php" class="back-link">← กลับหน้าแรก</a>
</div>

<?php include_once 'includes/footer.php'; ?>
<script src="assets/js/preload-images.js"></script>
</body>
</html>
