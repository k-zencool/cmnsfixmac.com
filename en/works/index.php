<?php
require_once '../../includes/db.php';

$q        = trim((string)(filter_input(INPUT_GET, 'q',        FILTER_SANITIZE_SPECIAL_CHARS) ?? ''));
$category = trim((string)(filter_input(INPUT_GET, 'category', FILTER_SANITIZE_SPECIAL_CHARS) ?? ''));
$sort     = trim((string)(filter_input(INPUT_GET, 'sort',     FILTER_SANITIZE_SPECIAL_CHARS) ?? 'latest'));
$page     = max(1, (int)(filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?? 1));
$limit    = 16;

if (!in_array($sort, ['latest', 'views'])) $sort = 'latest';

$categories = [
    ''           => 'All',
    'MacBook'    => 'MacBook',
    'iMac'       => 'iMac',
    'iPhone'     => 'iPhone',
    'iPad'       => 'iPad',
    'AirPods'    => 'AirPods',
    'AppleWatch' => 'Apple Watch',
];

function wk_url(string $q, string $cat, string $sort, array $merge = []): string {
    $p = array_merge(['q' => $q, 'category' => $cat, 'sort' => $sort], $merge);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null && $v !== 'latest');
    return '?' . http_build_query($p);
}

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function wk_img(string $raw): string {
    if (!$raw) return '/assets/img/placeholder.png';
    if ($raw[0] === '/' || str_starts_with($raw, 'http')) return $raw;
    if (str_contains($raw, '/')) return '/' . ltrim($raw, '/');
    return '/assets/img/placeholder.png';
}

$where  = ["status = 'published'"];
$params = [];

if ($q !== '') {
    $where[]  = "(COALESCE(title_en, title) LIKE ? OR model LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($category !== '') {
    $where[]  = "TRIM(LOWER(category)) = LOWER(?)";
    $params[] = $category;
}
$whereSQL = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM repairs$whereSQL");
$cntStmt->execute($params);
$total      = (int)$cntStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $limit));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $limit;

$orderBy = $sort === 'views' ? 'views DESC, created_at DESC' : 'created_at DESC';
$stmt    = $pdo->prepare("SELECT id, COALESCE(NULLIF(title_en,''), title) AS title, model, image, category, views FROM repairs$whereSQL ORDER BY $orderBy LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$cntAllStmt = $pdo->query("SELECT COUNT(*) FROM repairs WHERE status='published'");
$totalAll   = (int)$cntAllStmt->fetchColumn();

$cat_label = $categories[$category] ?? 'All';
$page_title = ($category !== '' ? "$cat_label — " : '') . 'Our Repair Portfolio | CMNS FixMac';

$page_css         = ['/assets/css/works-style.css?v=1'];
$switch_to_lang_url = '/works/' . ($category ? '?category=' . urlencode($category) : '');

ob_start(); ?>
<meta name="description" content="Browse <?= $totalAll ?>+ real Apple repairs by CMNS FixMac Chiang Mai — MacBook, iPhone, iPad, iMac and more. Photos, models, and customer reviews.">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://cmnsfixmac.com/en/works/<?= $category ? '?category=' . e(urlencode($category)) : '' ?>">
<link rel="shortcut icon" href="/assets/img/favicon1.png">
<link rel="alternate" hreflang="th"        href="https://cmnsfixmac.com/works/">
<link rel="alternate" hreflang="en"        href="https://cmnsfixmac.com/en/works/">
<link rel="alternate" hreflang="x-default" href="https://cmnsfixmac.com/en/works/">
<meta property="og:title"       content="<?= e($page_title) ?>">
<meta property="og:description" content="Browse <?= $totalAll ?>+ real Apple repairs — MacBook, iPhone, iPad, iMac and more. Expert technicians in Chiang Mai.">
<meta property="og:type"        content="website">
<meta property="og:image"       content="https://cmnsfixmac.com/assets/img/og-cover.jpg">
<meta property="og:url"         content="https://cmnsfixmac.com/en/works/">
<meta property="og:locale"      content="en_US">
<?php $page_head_extra = ob_get_clean();

include_once '../../includes/header_en.php';
?>

<main class="wk-main">

  <!-- ── Page Header ── -->
  <div class="wk-header">
    <div class="wk-header-bg" aria-hidden="true"></div>
    <div class="wk-header-inner">
      <span class="wk-eyebrow">
        <span class="material-symbols-rounded">build_circle</span>
        Real Repairs · Real Photos · Real Reviews
      </span>
      <h1 class="wk-h1">Our Repair<span class="wk-h1-accent">Portfolio</span></h1>
      <div class="wk-header-pills">
        <span class="wk-hpill"><span class="material-symbols-rounded">build</span> <?= $totalAll ?>+ Repairs</span>
        <span class="wk-hpill"><span class="material-symbols-rounded">star</span> 4.9 Google</span>
        <span class="wk-hpill"><span class="material-symbols-rounded">workspace_premium</span> Warranty on Every Job</span>
      </div>
    </div>
  </div>

  <!-- ── Toolbar: chips + search ── -->
  <div class="wk-toolbar-wrap">
    <div class="wk-toolbar">

      <!-- Category chips -->
      <div class="wk-chips" role="list" aria-label="Filter by category">
        <?php foreach ($categories as $val => $label):
            $isActive = $category === $val;
            $url = wk_url($q, $val, $sort, ['page' => null]);
        ?>
        <a href="<?= e($url) ?>"
           class="wk-chip<?= $isActive ? ' wk-chip--active' : '' ?>"
           role="listitem"
           <?= $isActive ? 'aria-current="true"' : '' ?>>
          <?= e($label) ?>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- Search + sort -->
      <form class="wk-search-form" method="GET" action="/en/works/">
        <?php if ($category !== ''): ?>
        <input type="hidden" name="category" value="<?= e($category) ?>">
        <?php endif; ?>
        <div class="wk-search-input-wrap">
          <span class="material-symbols-rounded wk-search-icon">search</span>
          <input type="text" name="q" value="<?= e($q) ?>"
                 placeholder="Search model or issue…"
                 autocomplete="off" spellcheck="false">
          <?php if ($q !== ''): ?>
          <a href="<?= e(wk_url('', $category, $sort)) ?>" class="wk-clear-btn" title="Clear search">
            <span class="material-symbols-rounded">close</span>
          </a>
          <?php endif; ?>
        </div>
        <select name="sort" onchange="this.form.submit()" class="wk-sort-select">
          <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>Latest</option>
          <option value="views"  <?= $sort === 'views'  ? 'selected' : '' ?>>Popular</option>
        </select>
      </form>

    </div>
  </div>

  <!-- ── Results bar ── -->
  <div class="wk-container">
    <div class="wk-results-bar">
      <p class="wk-results-count">
        <?php if ($total === 0): ?>
          No results<?= $q ? " for &ldquo;<?= e($q) ?>&rdquo;" : '' ?>
        <?php elseif ($q !== '' || $category !== ''): ?>
          <strong><?= number_format($total) ?></strong> result<?= $total !== 1 ? 's' : '' ?>
          <?= $category !== '' ? " in <strong>$cat_label</strong>" : '' ?>
          <?= $q !== '' ? " matching &ldquo;<?= e($q) ?>&rdquo;" : '' ?>
        <?php else: ?>
          Showing all <strong><?= number_format($total) ?></strong> repairs
        <?php endif; ?>
      </p>
      <p class="wk-page-indicator">Page <?= $page ?> of <?= $totalPages ?></p>
    </div>

    <!-- ── Card grid ── -->
    <?php if ($rows): ?>
    <div class="wk-grid">
      <?php foreach ($rows as $i => $r):
          $img     = wk_img($r['image'] ?? '');
          $catSlug = strtolower(preg_replace('/\s+/', '', $r['category'] ?? ''));
      ?>
      <a href="/en/works/detail.php?id=<?= (int)$r['id'] ?>" class="wk-card" data-aos-i="<?= $i % 8 ?>">
        <div class="wk-card-thumb">
          <img src="<?= e($img) ?>"
               alt="<?= e($r['title']) ?>"
               loading="lazy" decoding="async"
               onerror="this.src='/assets/img/placeholder.png'">
          <span class="wk-badge" data-cat="<?= e($catSlug) ?>"><?= e($r['category'] ?? '') ?></span>
          <div class="wk-card-overlay" aria-hidden="true">
            <span class="material-symbols-rounded">open_in_new</span>
          </div>
        </div>
        <div class="wk-card-body">
          <h3 class="wk-card-title"><?= e($r['title']) ?></h3>
          <div class="wk-card-meta">
            <span class="wk-card-model"><?= e($r['model'] ?? '') ?></span>
            <span class="wk-card-views">
              <span class="material-symbols-rounded">visibility</span>
              <?= number_format((int)($r['views'] ?? 0)) ?>
            </span>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- ── Pagination ── -->
    <?php if ($totalPages > 1): ?>
    <nav class="wk-pagination" aria-label="Pagination">
      <?php
      $range = 2;
      $start = max(1, $page - $range);
      $end   = min($totalPages, $page + $range);

      if ($page > 1):
          echo '<a href="' . e(wk_url($q, $category, $sort, ['page' => 1])) . '" class="wk-pgbtn wk-pgbtn--arrow" title="First">
                  <span class="material-symbols-rounded">first_page</span>
                </a>';
          echo '<a href="' . e(wk_url($q, $category, $sort, ['page' => $page - 1])) . '" class="wk-pgbtn wk-pgbtn--arrow" title="Previous">
                  <span class="material-symbols-rounded">chevron_left</span>
                </a>';
      endif;

      if ($start > 1) echo '<span class="wk-pgdots">…</span>';

      for ($i = $start; $i <= $end; $i++):
          $active = $i === $page ? ' wk-pgbtn--active' : '';
          echo '<a href="' . e(wk_url($q, $category, $sort, ['page' => $i])) . '" class="wk-pgbtn' . $active . '" aria-label="Page ' . $i . '">' . $i . '</a>';
      endfor;

      if ($end < $totalPages) echo '<span class="wk-pgdots">…</span>';

      if ($page < $totalPages):
          echo '<a href="' . e(wk_url($q, $category, $sort, ['page' => $page + 1])) . '" class="wk-pgbtn wk-pgbtn--arrow" title="Next">
                  <span class="material-symbols-rounded">chevron_right</span>
                </a>';
          echo '<a href="' . e(wk_url($q, $category, $sort, ['page' => $totalPages])) . '" class="wk-pgbtn wk-pgbtn--arrow" title="Last">
                  <span class="material-symbols-rounded">last_page</span>
                </a>';
      endif;
      ?>
    </nav>
    <?php endif; ?>

    <?php else: ?>
    <div class="wk-empty">
      <span class="material-symbols-rounded">search_off</span>
      <p>No repairs match your search</p>
      <a href="/en/works/" class="wk-empty-reset">Clear filters</a>
    </div>
    <?php endif; ?>

  </div><!-- /wk-container -->

</main>

<?php include_once '../../includes/footer_en.php'; ?>
<script>
(function () {
  const cards = document.querySelectorAll('.wk-card');
  if (!cards.length) return;
  const io = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        const delay = (e.target.dataset.aosI || 0) * 50;
        setTimeout(() => e.target.classList.add('wk-card--in'), delay);
        io.unobserve(e.target);
      }
    });
  }, { threshold: 0.05, rootMargin: '0px 0px -40px 0px' });
  cards.forEach(c => io.observe(c));
})();
</script>
</body>
</html>
