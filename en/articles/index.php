<?php
require_once '../../includes/db.php';

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function at_img(string $raw): string {
    if (!$raw) return '/assets/img/placeholder.png';
    if ($raw[0] === '/' || str_starts_with($raw, 'http')) return $raw;
    return '/assets/img/placeholder.png';
}

$q        = trim((string)(filter_input(INPUT_GET, 'q',    FILTER_SANITIZE_SPECIAL_CHARS) ?? ''));
$category = trim((string)(filter_input(INPUT_GET, 'cat',  FILTER_SANITIZE_SPECIAL_CHARS) ?? ''));
$sort     = trim((string)(filter_input(INPUT_GET, 'sort', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'latest'));
$page     = max(1, (int)(filter_input(INPUT_GET, 'page',  FILTER_VALIDATE_INT) ?? 1));
$limit    = 12;

if (!in_array($sort, ['latest', 'views', 'az'])) $sort = 'latest';

$categories = [
    ''       => 'All',
    'tip'    => 'Tips & Tricks',
    'repair' => 'Repair Insights',
    'update' => 'Updates',
];

function at_url(string $q, string $cat, string $sort, array $merge = []): string {
    $p = array_merge(['q' => $q, 'cat' => $cat, 'sort' => $sort], $merge);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null && $v !== 'latest');
    return '?' . http_build_query($p);
}

$where  = ['status = 1'];
$params = [];

if ($q !== '') {
    $where[]  = "(COALESCE(NULLIF(title_en,''), title) LIKE ? OR COALESCE(NULLIF(content_en,''), content) LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($category !== '') {
    $where[]  = "LOWER(category) = LOWER(?)";
    $params[] = $category;
}
$whereSQL = ' WHERE ' . implode(' AND ', $where);
$orderBy  = ['views' => 'views DESC, created_at DESC', 'az' => 'COALESCE(NULLIF(title_en,\'\'), title) ASC'][$sort] ?? 'created_at DESC';

$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM articles$whereSQL");
$cntStmt->execute($params);
$total      = (int)$cntStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $limit));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $limit;

$stmt = $pdo->prepare(
    "SELECT id,
            COALESCE(NULLIF(title_en,''), title) AS title,
            COALESCE(NULLIF(slug_en,''), slug)   AS slug,
            COALESCE(NULLIF(excerpt_en,''), excerpt) AS excerpt,
            COALESCE(NULLIF(content_en,''), content) AS content,
            image, category, views, created_at
     FROM articles$whereSQL ORDER BY $orderBy LIMIT $limit OFFSET $offset"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$cntAllStmt = $pdo->query("SELECT COUNT(*) FROM articles WHERE status=1");
$totalAll   = (int)$cntAllStmt->fetchColumn();

$cat_label = $categories[$category] ?? 'All';
$page_title = ($category !== '' ? "$cat_label — " : '') . 'Articles | CMNS FixMac';
$page_css   = ['/assets/css/articles-style.css?v=2'];
$switch_to_lang_url = '/articles/' . ($category !== '' ? '?cat=' . urlencode($category) : '');

ob_start(); ?>
<meta name="description" content="Browse <?= $totalAll ?>+ Apple tech articles from CMNS FixMac Chiang Mai — MacBook tips, iPhone guides, repair insights and updates.">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://cmnsfixmac.com/en/articles/">
<link rel="shortcut icon" href="/assets/img/favicon1.png">
<link rel="alternate" hreflang="th"        href="https://cmnsfixmac.com/articles/">
<link rel="alternate" hreflang="en"        href="https://cmnsfixmac.com/en/articles/">
<link rel="alternate" hreflang="x-default" href="https://cmnsfixmac.com/en/articles/">
<meta property="og:title"       content="<?= e($page_title) ?>">
<meta property="og:description" content="Browse <?= $totalAll ?>+ Apple tech articles — tips, repair insights and updates from Chiang Mai experts.">
<meta property="og:type"        content="website">
<meta property="og:image"       content="https://cmnsfixmac.com/assets/img/og-cover.jpg">
<meta property="og:url"         content="https://cmnsfixmac.com/en/articles/">
<meta property="og:locale"      content="en_US">
<?php $page_head_extra = ob_get_clean();
include_once '../../includes/header_en.php';
?>

<main class="at-main">

  <div class="at-header">
    <div class="at-header-bg" aria-hidden="true"></div>
    <div class="at-header-inner">
      <span class="at-eyebrow">
        <span class="material-symbols-rounded">menu_book</span>
        Tips · Guides · Updates
      </span>
      <h1 class="at-h1">Articles from Our<span class="at-h1-accent"> Technicians</span></h1>
      <div class="at-header-pills">
        <span class="at-hpill"><span class="material-symbols-rounded">article</span> <?= $totalAll ?>+ Articles</span>
        <span class="at-hpill"><span class="material-symbols-rounded">lightbulb</span> Real Tips from Experts</span>
        <span class="at-hpill"><span class="material-symbols-rounded">update</span> Regularly Updated</span>
      </div>
    </div>
  </div>

  <div class="at-toolbar-wrap">
    <div class="at-toolbar">
      <div class="at-chips" role="list" aria-label="Filter by category">
        <?php foreach ($categories as $val => $label):
            $isActive = $category === $val;
            $url = at_url($q, $val, $sort, ['page' => null]);
        ?>
        <a href="<?= e($url) ?>"
           class="at-chip<?= $isActive ? ' at-chip--active' : '' ?>"
           role="listitem"
           <?= $isActive ? 'aria-current="true"' : '' ?>>
          <?= e($label) ?>
        </a>
        <?php endforeach; ?>
      </div>

      <form class="at-search-form" method="GET" action="/en/articles/">
        <?php if ($category !== ''): ?>
        <input type="hidden" name="cat" value="<?= e($category) ?>">
        <?php endif; ?>
        <div class="at-search-input-wrap">
          <span class="material-symbols-rounded at-search-icon">search</span>
          <input type="text" name="q" value="<?= e($q) ?>"
                 placeholder="Search articles…"
                 autocomplete="off" spellcheck="false">
          <?php if ($q !== ''): ?>
          <a href="<?= e(at_url('', $category, $sort)) ?>" class="at-clear-btn" title="Clear search">
            <span class="material-symbols-rounded">close</span>
          </a>
          <?php endif; ?>
        </div>
        <select name="sort" onchange="this.form.submit()" class="at-sort-select">
          <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>Latest</option>
          <option value="views"  <?= $sort === 'views'  ? 'selected' : '' ?>>Popular</option>
          <option value="az"     <?= $sort === 'az'     ? 'selected' : '' ?>>A-Z</option>
        </select>
      </form>
    </div>
  </div>

  <div class="at-container">
    <div class="at-results-bar">
      <p class="at-results-count">
        <?php if ($total === 0): ?>
          No articles found<?= $q ? " for &ldquo;" . e($q) . "&rdquo;" : '' ?>
        <?php elseif ($q !== '' || $category !== ''): ?>
          <strong><?= number_format($total) ?></strong> article<?= $total !== 1 ? 's' : '' ?>
          <?= $category !== '' ? " in <strong>$cat_label</strong>" : '' ?>
          <?= $q !== '' ? " matching &ldquo;" . e($q) . "&rdquo;" : '' ?>
        <?php else: ?>
          Showing all <strong><?= number_format($total) ?></strong> articles
        <?php endif; ?>
      </p>
      <p class="at-page-indicator">Page <?= $page ?> of <?= $totalPages ?></p>
    </div>

    <?php if ($rows): ?>
    <div class="at-grid">
      <?php foreach ($rows as $i => $r):
          $img      = at_img($r['image'] ?? '');
          $catSlug  = strtolower($r['category'] ?? '');
          $catDisp  = $categories[$catSlug] ?? $catSlug;
          $url      = $r['slug'] ? '/en/article/' . $r['slug'] : '/en/articles/detail.php?id=' . (int)$r['id'];
          $excerpt  = trim($r['excerpt'] ?? '') ?: mb_substr(strip_tags($r['content'] ?? ''), 0, 120);
      ?>
      <a href="<?= e($url) ?>" class="at-card" data-aos-i="<?= $i % 9 ?>">
        <div class="at-card-thumb">
          <img src="<?= e($img) ?>"
               alt="<?= e($r['title']) ?>"
               loading="lazy" decoding="async"
               onerror="this.src='/assets/img/placeholder.png'">
          <?php if ($catSlug): ?>
          <span class="at-badge" data-cat="<?= e($catSlug) ?>"><?= e($catDisp) ?></span>
          <?php endif; ?>
        </div>
        <div class="at-card-body">
          <h3 class="at-card-title"><?= e($r['title']) ?></h3>
          <p class="at-card-excerpt"><?= e(mb_substr(strip_tags($excerpt), 0, 90)) ?>…</p>
          <div class="at-card-meta">
            <span class="at-card-date">
              <span class="material-symbols-rounded">calendar_today</span>
              <?= date('d M Y', strtotime($r['created_at'])) ?>
            </span>
            <span class="at-card-views">
              <span class="material-symbols-rounded">visibility</span>
              <?= number_format((int)($r['views'] ?? 0)) ?>
            </span>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav class="at-pagination" aria-label="Pagination">
      <?php
      $range = 2;
      $start = max(1, $page - $range);
      $end   = min($totalPages, $page + $range);
      if ($page > 1):
          echo '<a href="' . e(at_url($q, $category, $sort, ['page' => 1])) . '" class="at-pgbtn at-pgbtn--arrow" title="First"><span class="material-symbols-rounded">first_page</span></a>';
          echo '<a href="' . e(at_url($q, $category, $sort, ['page' => $page - 1])) . '" class="at-pgbtn at-pgbtn--arrow" title="Previous"><span class="material-symbols-rounded">chevron_left</span></a>';
      endif;
      if ($start > 1) echo '<span class="at-pgdots">…</span>';
      for ($i = $start; $i <= $end; $i++):
          $active = $i === $page ? ' at-pgbtn--active' : '';
          echo '<a href="' . e(at_url($q, $category, $sort, ['page' => $i])) . '" class="at-pgbtn' . $active . '" aria-label="Page ' . $i . '">' . $i . '</a>';
      endfor;
      if ($end < $totalPages) echo '<span class="at-pgdots">…</span>';
      if ($page < $totalPages):
          echo '<a href="' . e(at_url($q, $category, $sort, ['page' => $page + 1])) . '" class="at-pgbtn at-pgbtn--arrow" title="Next"><span class="material-symbols-rounded">chevron_right</span></a>';
          echo '<a href="' . e(at_url($q, $category, $sort, ['page' => $totalPages])) . '" class="at-pgbtn at-pgbtn--arrow" title="Last"><span class="material-symbols-rounded">last_page</span></a>';
      endif;
      ?>
    </nav>
    <?php endif; ?>

    <?php else: ?>
    <div class="at-empty">
      <span class="material-symbols-rounded">article</span>
      <p>No articles match your search</p>
      <a href="/en/articles/" class="at-empty-reset">Clear filters</a>
    </div>
    <?php endif; ?>

  </div><!-- /at-container -->
</main>

<?php include_once '../../includes/footer_en.php'; ?>
<script>
(function () {
  const cards = document.querySelectorAll('.at-card');
  if (!cards.length) return;
  const io = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        const delay = (e.target.dataset.aosI || 0) * 50;
        setTimeout(() => e.target.classList.add('at-card--in'), delay);
        io.unobserve(e.target);
      }
    });
  }, { threshold: 0.05, rootMargin: '0px 0px -40px 0px' });
  cards.forEach(c => io.observe(c));
})();
</script>
</body>
</html>
