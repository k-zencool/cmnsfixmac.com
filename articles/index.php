<?php
require_once '../includes/db.php';

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
    ''       => 'ทั้งหมด',
    'tip'    => 'เทคนิค',
    'repair' => 'การซ่อม',
    'update' => 'อัปเดต',
];

function at_url(string $q, string $cat, string $sort, array $merge = []): string {
    $p = array_merge(['q' => $q, 'cat' => $cat, 'sort' => $sort], $merge);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null && $v !== 'latest');
    return '?' . http_build_query($p);
}

$where  = ['status = 1'];
$params = [];

if ($q !== '') {
    $where[]  = "(title LIKE ? OR content LIKE ? OR excerpt LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($category !== '') {
    $where[]  = "LOWER(category) = LOWER(?)";
    $params[] = $category;
}
$whereSQL = ' WHERE ' . implode(' AND ', $where);
$orderBy  = match($sort) {
    'views' => 'views DESC, created_at DESC',
    'az'    => 'title ASC',
    default => 'created_at DESC',
};

$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM articles$whereSQL");
$cntStmt->execute($params);
$total      = (int)$cntStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $limit));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $limit;

$stmt = $pdo->prepare("SELECT id, title, slug, excerpt, content, image, category, views, created_at FROM articles$whereSQL ORDER BY $orderBy LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$cntAllStmt = $pdo->query("SELECT COUNT(*) FROM articles WHERE status=1");
$totalAll   = (int)$cntAllStmt->fetchColumn();

$cat_label = $categories[$category] ?? 'ทั้งหมด';
$page_title = ($category !== '' ? "$cat_label — " : '') . 'บทความ | CMNS FixMac';
$page_css   = ['/assets/css/articles-style.css?v=2'];
$switch_to_lang_url = '/en/articles/' . ($category !== '' ? '?cat=' . urlencode($category) : '');

ob_start(); ?>
<meta name="description" content="บทความเทคนิค Apple จากช่างผู้เชี่ยวชาญ CMNS FixMac เชียงใหม่ — MacBook, iPhone, iPad และการดูแลรักษาอุปกรณ์ Apple">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://cmnsfixmac.com/articles/">
<link rel="shortcut icon" href="/assets/img/favicon1.png">
<link rel="alternate" hreflang="th"        href="https://cmnsfixmac.com/articles/">
<link rel="alternate" hreflang="en"        href="https://cmnsfixmac.com/en/articles/">
<link rel="alternate" hreflang="x-default" href="https://cmnsfixmac.com/en/articles/">
<meta property="og:title"       content="<?= e($page_title) ?>">
<meta property="og:description" content="บทความเทคนิค Apple กว่า <?= $totalAll ?> บทความ โดยช่างผู้เชี่ยวชาญที่เชียงใหม่">
<meta property="og:type"        content="website">
<meta property="og:image"       content="https://cmnsfixmac.com/assets/img/og-cover.jpg">
<meta property="og:url"         content="https://cmnsfixmac.com/articles/">
<meta property="og:locale"      content="th_TH">
<?php $page_head_extra = ob_get_clean();
include_once '../includes/header.php';
?>

<main class="at-main">

  <div class="at-header">
    <div class="at-header-bg" aria-hidden="true"></div>
    <div class="at-header-inner">
      <span class="at-eyebrow">
        <span class="material-symbols-rounded">menu_book</span>
        เทคนิค · เคล็ดลับ · อัปเดต
      </span>
      <h1 class="at-h1">บทความจาก<span class="at-h1-accent">ช่างแอปเปิล</span></h1>
      <div class="at-header-pills">
        <span class="at-hpill"><span class="material-symbols-rounded">article</span> <?= $totalAll ?>+ บทความ</span>
        <span class="at-hpill"><span class="material-symbols-rounded">lightbulb</span> เทคนิคจริงจากช่าง</span>
        <span class="at-hpill"><span class="material-symbols-rounded">update</span> อัปเดตสม่ำเสมอ</span>
      </div>
    </div>
  </div>

  <div class="at-toolbar-wrap">
    <div class="at-toolbar">
      <div class="at-chips" role="list" aria-label="กรองหมวดหมู่">
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

      <form class="at-search-form" method="GET" action="/articles/">
        <?php if ($category !== ''): ?>
        <input type="hidden" name="cat" value="<?= e($category) ?>">
        <?php endif; ?>
        <div class="at-search-input-wrap">
          <span class="material-symbols-rounded at-search-icon">search</span>
          <input type="text" name="q" value="<?= e($q) ?>"
                 placeholder="ค้นหาบทความ..."
                 autocomplete="off" spellcheck="false">
          <?php if ($q !== ''): ?>
          <a href="<?= e(at_url('', $category, $sort)) ?>" class="at-clear-btn" title="ล้างการค้นหา">
            <span class="material-symbols-rounded">close</span>
          </a>
          <?php endif; ?>
        </div>
        <select name="sort" onchange="this.form.submit()" class="at-sort-select">
          <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>ใหม่ล่าสุด</option>
          <option value="views"  <?= $sort === 'views'  ? 'selected' : '' ?>>ยอดนิยม</option>
          <option value="az"     <?= $sort === 'az'     ? 'selected' : '' ?>>A-Z</option>
        </select>
      </form>
    </div>
  </div>

  <div class="at-container">
    <div class="at-results-bar">
      <p class="at-results-count">
        <?php if ($total === 0): ?>
          ไม่พบบทความ<?= $q ? " สำหรับ &ldquo;" . e($q) . "&rdquo;" : '' ?>
        <?php elseif ($q !== '' || $category !== ''): ?>
          พบ <strong><?= number_format($total) ?></strong> บทความ
          <?= $category !== '' ? "ใน <strong>$cat_label</strong>" : '' ?>
          <?= $q !== '' ? " ที่ตรงกับ &ldquo;" . e($q) . "&rdquo;" : '' ?>
        <?php else: ?>
          แสดงทั้งหมด <strong><?= number_format($total) ?></strong> บทความ
        <?php endif; ?>
      </p>
      <p class="at-page-indicator">หน้า <?= $page ?> / <?= $totalPages ?></p>
    </div>

    <?php if ($rows): ?>
    <div class="at-grid">
      <?php foreach ($rows as $i => $r):
          $img      = at_img($r['image'] ?? '');
          $catSlug  = strtolower($r['category'] ?? '');
          $catDisp  = $categories[$catSlug] ?? $catSlug;
          $url      = $r['slug'] ? '/article/' . $r['slug'] : '/articles/detail.php?id=' . (int)$r['id'];
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
          echo '<a href="' . e(at_url($q, $category, $sort, ['page' => 1])) . '" class="at-pgbtn at-pgbtn--arrow" title="หน้าแรก"><span class="material-symbols-rounded">first_page</span></a>';
          echo '<a href="' . e(at_url($q, $category, $sort, ['page' => $page - 1])) . '" class="at-pgbtn at-pgbtn--arrow" title="ก่อนหน้า"><span class="material-symbols-rounded">chevron_left</span></a>';
      endif;
      if ($start > 1) echo '<span class="at-pgdots">…</span>';
      for ($i = $start; $i <= $end; $i++):
          $active = $i === $page ? ' at-pgbtn--active' : '';
          echo '<a href="' . e(at_url($q, $category, $sort, ['page' => $i])) . '" class="at-pgbtn' . $active . '" aria-label="หน้า ' . $i . '">' . $i . '</a>';
      endfor;
      if ($end < $totalPages) echo '<span class="at-pgdots">…</span>';
      if ($page < $totalPages):
          echo '<a href="' . e(at_url($q, $category, $sort, ['page' => $page + 1])) . '" class="at-pgbtn at-pgbtn--arrow" title="ถัดไป"><span class="material-symbols-rounded">chevron_right</span></a>';
          echo '<a href="' . e(at_url($q, $category, $sort, ['page' => $totalPages])) . '" class="at-pgbtn at-pgbtn--arrow" title="หน้าสุดท้าย"><span class="material-symbols-rounded">last_page</span></a>';
      endif;
      ?>
    </nav>
    <?php endif; ?>

    <?php else: ?>
    <div class="at-empty">
      <span class="material-symbols-rounded">article</span>
      <p>ไม่พบบทความที่ตรงกับการค้นหา</p>
      <a href="/articles/" class="at-empty-reset">ล้างตัวกรอง</a>
    </div>
    <?php endif; ?>

  </div><!-- /at-container -->
</main>

<?php include_once '../includes/footer.php'; ?>
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
