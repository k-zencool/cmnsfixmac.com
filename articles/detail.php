<?php
require_once '../includes/db.php';

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function ad_img(string $raw): string {
    if (!$raw) return '/assets/img/placeholder.png';
    if ($raw[0] === '/' || str_starts_with($raw, 'http')) return $raw;
    return '/assets/img/placeholder.png';
}

$slug = trim((string)(filter_input(INPUT_GET, 'slug', FILTER_DEFAULT) ?? ''));
$id   = (int)(filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? 0);

if ($slug && !$id) {
    $st = $pdo->prepare("SELECT id FROM articles WHERE slug = ? LIMIT 1");
    $st->execute([$slug]);
    $id = (int)($st->fetchColumn() ?: 0);
}
if (!$id) { header('Location: /articles/'); exit; }

$stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ? AND status = 1");
$stmt->execute([$id]);
$article = $stmt->fetch();
if (!$article) { http_response_code(404); header('Location: /articles/'); exit; }

// unique view: 1 IP per day
$ip  = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')[0]);
$log = $pdo->prepare("INSERT IGNORE INTO article_views (article_id, ip, viewed_date) VALUES (?, ?, ?)");
$log->execute([$id, $ip, date('Y-m-d')]);
if ($log->rowCount() > 0) {
    $pdo->prepare("UPDATE articles SET views = views + 1 WHERE id = ?")->execute([$id]);
    $article['views']++;
}

$imgs = $pdo->prepare("SELECT * FROM article_images WHERE article_id = ? ORDER BY sort_order ASC, id ASC");
$imgs->execute([$id]);
$images = $imgs->fetchAll();

$related = $pdo->prepare(
    "SELECT id, title, slug, image, category, views, created_at FROM articles
     WHERE category = ? AND id != ? AND status = 1 ORDER BY views DESC LIMIT 4"
);
$related->execute([$article['category'], $id]);
$related = $related->fetchAll();

$prev = $pdo->prepare("SELECT id, slug, title FROM articles WHERE id < ? AND status = 1 ORDER BY id DESC LIMIT 1");
$prev->execute([$id]);
$prev = $prev->fetch();

$next = $pdo->prepare("SELECT id, slug, title FROM articles WHERE id > ? AND status = 1 ORDER BY id ASC LIMIT 1");
$next->execute([$id]);
$next = $next->fetch();

$catIcons = ['tip' => 'lightbulb', 'repair' => 'build_circle', 'update' => 'system_update'];
$catLabels = ['tip' => 'เทคนิค', 'repair' => 'การซ่อม', 'update' => 'อัปเดต'];
$catSlug  = strtolower($article['category'] ?? '');
$catIcon  = $catIcons[$catSlug] ?? 'article';
$catLabel = $catLabels[$catSlug] ?? $article['category'];

$mainImg   = ad_img($article['image'] ?? '');
$ogImg     = str_starts_with($mainImg, 'http') ? $mainImg : 'https://cmnsfixmac.com' . $mainImg;
$dateISO   = date('c', strtotime($article['created_at']));
$dateDisp  = date('d M Y', strtotime($article['created_at']));
$canonical = $article['slug']
    ? 'https://cmnsfixmac.com/article/' . $article['slug']
    : 'https://cmnsfixmac.com/articles/detail.php?id=' . $id;

$description = mb_substr(strip_tags($article['excerpt'] ?: $article['content'] ?? ''), 0, 160);

$switch_to_lang_url = '/en/articles/detail.php?id=' . $id;
$page_title         = e($article['title']) . ' | CMNS FixMac';
$page_css           = ['/assets/css/article-detail-style.css?v=2'];

ob_start(); ?>
<meta name="description" content="<?= e($description) ?>">
<meta name="robots"      content="index, follow">
<link rel="canonical"    href="<?= e($canonical) ?>">
<link rel="shortcut icon" href="/assets/img/favicon1.png">
<link rel="alternate" hreflang="th"        href="<?= e($canonical) ?>">
<link rel="alternate" hreflang="en"        href="<?= e('https://cmnsfixmac.com/en/articles/detail.php?id=' . $id) ?>">
<link rel="alternate" hreflang="x-default" href="<?= e('https://cmnsfixmac.com/en/articles/detail.php?id=' . $id) ?>">
<meta property="og:type"        content="article">
<meta property="og:title"       content="<?= e($article['title']) ?> | CMNS FixMac">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:url"         content="<?= e($canonical) ?>">
<meta property="og:image"       content="<?= e($ogImg) ?>">
<meta property="og:locale"      content="th_TH">
<meta property="og:site_name"   content="CMNS FixMac">
<meta property="article:published_time" content="<?= e($dateISO) ?>">
<meta name="twitter:card"       content="summary_large_image">
<meta name="twitter:title"      content="<?= e($article['title']) ?>">
<meta name="twitter:description" content="<?= e($description) ?>">
<meta name="twitter:image"      content="<?= e($ogImg) ?>">
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'Article',
    'headline' => $article['title'],
    'description' => $description,
    'image'    => $ogImg,
    'datePublished' => $dateISO,
    'dateModified'  => $article['updated_at'] ? date('c', strtotime($article['updated_at'])) : $dateISO,
    'inLanguage'    => 'th-TH',
    'url'      => $canonical,
    'author'   => ['@type' => 'Organization', 'name' => 'CMNS FixMac', 'url' => 'https://cmnsfixmac.com'],
    'publisher'=> ['@type' => 'Organization', 'name' => 'CMNS FixMac',
                   'logo' => ['@type' => 'ImageObject', 'url' => 'https://cmnsfixmac.com/assets/img/Logo1.png']],
    'breadcrumb' => ['@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'หน้าแรก',   'item' => 'https://cmnsfixmac.com/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'บทความ',    'item' => 'https://cmnsfixmac.com/articles/'],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $article['title'], 'item' => $canonical],
    ]],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php
$page_head_extra = ob_get_clean();
include_once '../includes/header.php';
?>

<main class="ad-page">

  <div class="ad-breadcrumb-wrap">
    <nav class="ad-breadcrumb" aria-label="breadcrumb">
      <a href="/">หน้าแรก</a>
      <span class="material-symbols-rounded">chevron_right</span>
      <a href="/articles/">บทความ</a>
      <?php if ($catLabel): ?>
      <span class="material-symbols-rounded">chevron_right</span>
      <a href="/articles/?cat=<?= e(urlencode($catSlug)) ?>"><?= e($catLabel) ?></a>
      <?php endif; ?>
      <span class="material-symbols-rounded">chevron_right</span>
      <span class="ad-bc-current" aria-current="page"><?= e($article['title']) ?></span>
    </nav>
  </div>

  <div class="ad-container">

    <header class="ad-hero">
      <div class="ad-hero-meta">
        <?php if ($catLabel): ?>
        <span class="ad-cat-badge" data-cat="<?= e($catSlug) ?>">
          <span class="material-symbols-rounded"><?= e($catIcon) ?></span>
          <?= e($catLabel) ?>
        </span>
        <?php endif; ?>
        <time class="ad-date" datetime="<?= e($dateISO) ?>">
          <span class="material-symbols-rounded">calendar_today</span>
          <?= $dateDisp ?>
        </time>
        <span class="ad-views">
          <span class="material-symbols-rounded">visibility</span>
          <?= number_format((int)$article['views']) ?> เข้าชม
        </span>
      </div>
      <h1 class="ad-title"><?= e($article['title']) ?></h1>
    </header>

    <?php if ($mainImg !== '/assets/img/placeholder.png'): ?>
    <div class="ad-main-img-wrap">
      <img
        src="<?= e($mainImg) ?>"
        alt="<?= e($article['title']) ?>"
        class="ad-main-img"
        fetchpriority="high"
        loading="eager"
        decoding="async"
        onerror="this.src='/assets/img/placeholder.png'">
    </div>
    <?php endif; ?>

    <div class="ad-content">
      <?= $article['content'] ?>
    </div>

    <?php if ($images): ?>
    <section class="ad-gallery-section">
      <div class="ad-gallery-label">
        <span class="material-symbols-rounded">photo_library</span>
        <h2>ภาพประกอบเพิ่มเติม</h2>
      </div>
      <div class="ad-gallery-grid">
        <?php foreach ($images as $img):
            $alt = $img['alt'] ?? $article['title'];
        ?>
        <figure class="ad-gallery-item">
          <img src="<?= e($img['image_path']) ?>"
               alt="<?= e($alt) ?>"
               loading="lazy" decoding="async"
               onerror="this.src='/assets/img/placeholder.png'">
          <?php if (trim($img['caption'] ?? '')): ?>
          <figcaption><?= trim($img['caption']) ?></figcaption>
          <?php endif; ?>
        </figure>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($article['youtube_url'])): ?>
    <section class="ad-video-section">
      <div class="ad-gallery-label">
        <span class="material-symbols-rounded">play_circle</span>
        <h2>วิดีโอประกอบ</h2>
      </div>
      <div class="ad-video-wrap">
        <iframe src="https://www.youtube.com/embed/<?= e($article['youtube_url']) ?>"
                loading="lazy" allowfullscreen title="<?= e($article['title']) ?>"></iframe>
      </div>
    </section>
    <?php endif; ?>

    <div class="ad-share-row">
      <span class="ad-share-label">
        <span class="material-symbols-rounded">share</span> แชร์
      </span>
      <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($canonical) ?>"
         target="_blank" rel="noopener" class="ad-share-btn ad-share-fb">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
        Facebook
      </a>
      <a href="https://social-plugins.line.me/lineit/share?url=<?= urlencode($canonical) ?>"
         target="_blank" rel="noopener" class="ad-share-btn ad-share-line">
        <img src="/assets/img/line-icon.png" width="16" height="16" alt="LINE">
        LINE
      </a>
      <button id="ad-copy-btn" class="ad-share-btn ad-share-copy" type="button">
        <span class="material-symbols-rounded">link</span>
        <span>คัดลอกลิงก์</span>
      </button>
    </div>

    <?php if ($related): ?>
    <section class="ad-related">
      <div class="ad-section-label">
        <span class="material-symbols-rounded">auto_awesome</span>
        <h2>บทความที่เกี่ยวข้อง</h2>
      </div>
      <div class="ad-rel-grid">
        <?php foreach ($related as $r):
            $rImg = ad_img($r['image'] ?? '');
            $rUrl = $r['slug'] ? '/article/' . $r['slug'] : '/articles/detail.php?id=' . (int)$r['id'];
        ?>
        <a href="<?= e($rUrl) ?>" class="ad-rel-card">
          <div class="ad-rel-thumb">
            <img src="<?= e($rImg) ?>"
                 alt="<?= e($r['title']) ?>"
                 loading="lazy" decoding="async"
                 onerror="this.src='/assets/img/placeholder.png'">
          </div>
          <div class="ad-rel-body">
            <h3><?= e($r['title']) ?></h3>
            <span><?= date('d M Y', strtotime($r['created_at'])) ?></span>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($prev || $next): ?>
    <nav class="ad-article-nav" aria-label="Prev/Next">
      <?php if ($prev):
          $prevUrl = $prev['slug'] ? '/article/' . $prev['slug'] : '/articles/detail.php?id=' . (int)$prev['id'];
      ?>
      <a href="<?= e($prevUrl) ?>" class="ad-nav-link">
        <span class="material-symbols-rounded">chevron_left</span>
        ก่อนหน้า
      </a>
      <?php else: ?>
      <span></span>
      <?php endif; ?>
      <?php if ($next):
          $nextUrl = $next['slug'] ? '/article/' . $next['slug'] : '/articles/detail.php?id=' . (int)$next['id'];
      ?>
      <a href="<?= e($nextUrl) ?>" class="ad-nav-link">
        ถัดไป
        <span class="material-symbols-rounded">chevron_right</span>
      </a>
      <?php endif; ?>
    </nav>
    <?php endif; ?>

    <div class="ad-cta">
      <span class="material-symbols-rounded ad-cta-icon">build_circle</span>
      <div class="ad-cta-text">
        <h3>เครื่อง Apple มีปัญหา?</h3>
        <p>ประเมินฟรีก่อนซ่อมทุกครั้ง · อะไหล่แท้ · รับประกันสูงสุด 1 ปี</p>
      </div>
      <div class="ad-cta-btns">
        <a href="tel:0841511684" class="ad-btn-accent">
          <span class="material-symbols-rounded">call</span> 084-151-1684
        </a>
        <a href="https://line.me/R/ti/p/@cmns" target="_blank" rel="noopener" class="ad-btn-line">
          <img src="/assets/img/line-icon.png" alt="LINE" width="16" height="16"> LINE: @cmns
        </a>
      </div>
    </div>

  </div><!-- /.ad-container -->
</main>

<?php include_once '../includes/footer.php'; ?>
<script>
document.getElementById('ad-copy-btn')?.addEventListener('click', function () {
    navigator.clipboard.writeText(location.href).then(() => {
        const label = this.querySelector('span:last-child');
        label.textContent = 'คัดลอกแล้ว!';
        setTimeout(() => { label.textContent = 'คัดลอกลิงก์'; }, 2000);
    });
});
</script>
</body>
</html>
