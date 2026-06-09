<?php
require_once '../../includes/db.php';

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function ad_img(string $raw): string {
    if (!$raw) return '/assets/img/placeholder.png';
    if ($raw[0] === '/' || str_starts_with($raw, 'http')) return $raw;
    return '/assets/img/placeholder.png';
}

$slug = trim((string)(filter_input(INPUT_GET, 'slug', FILTER_DEFAULT) ?? ''));
$id   = (int)(filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? 0);

if ($slug && !$id) {
    $st = $pdo->prepare("SELECT id FROM articles WHERE slug_en = ? OR slug = ? LIMIT 1");
    $st->execute([$slug, $slug]);
    $id = (int)($st->fetchColumn() ?: 0);
}
if (!$id) { header('Location: /en/articles/'); exit; }

$stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ? AND status = 1");
$stmt->execute([$id]);
$article = $stmt->fetch();
if (!$article) { http_response_code(404); header('Location: /en/articles/'); exit; }

// unique view: 1 IP per day (shares article_views with TH page — same article_id)
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
    "SELECT id,
            COALESCE(NULLIF(title_en,''), title) AS title,
            COALESCE(NULLIF(slug_en,''), slug)   AS slug,
            image, category, views, created_at
     FROM articles WHERE category = ? AND id != ? AND status = 1 ORDER BY views DESC LIMIT 4"
);
$related->execute([$article['category'], $id]);
$related = $related->fetchAll();

$prev = $pdo->prepare("SELECT id, slug, slug_en, title_en, title FROM articles WHERE id < ? AND status = 1 ORDER BY id DESC LIMIT 1");
$prev->execute([$id]);
$prev = $prev->fetch();

$next = $pdo->prepare("SELECT id, slug, slug_en, title_en, title FROM articles WHERE id > ? AND status = 1 ORDER BY id ASC LIMIT 1");
$next->execute([$id]);
$next = $next->fetch();

// EN content with TH fallback
$title     = trim($article['title_en'] ?? '') ?: trim($article['title'] ?? '');
$content   = trim($article['content_en'] ?? '') ?: trim($article['content'] ?? '');
$excerpt   = trim($article['excerpt_en'] ?? '') ?: trim($article['excerpt'] ?? '');
$isLangTH  = empty(trim($article['content_en'] ?? ''));

$catIcons  = ['tip' => 'lightbulb', 'repair' => 'build_circle', 'update' => 'system_update'];
$catLabels = ['tip' => 'Tips & Tricks', 'repair' => 'Repair Insights', 'update' => 'Updates'];
$catSlug   = strtolower($article['category'] ?? '');
$catIcon   = $catIcons[$catSlug] ?? 'article';
$catLabel  = $catLabels[$catSlug] ?? $article['category'];

$mainImg   = ad_img($article['image'] ?? '');
$ogImg     = str_starts_with($mainImg, 'http') ? $mainImg : 'https://cmnsfixmac.com' . $mainImg;
$dateISO   = date('c', strtotime($article['created_at']));
$dateDisp  = date('d M Y', strtotime($article['created_at']));

$slugEn    = trim($article['slug_en'] ?? '') ?: trim($article['slug'] ?? '');
$canonical = $slugEn
    ? 'https://cmnsfixmac.com/en/article/' . $slugEn
    : 'https://cmnsfixmac.com/en/articles/detail.php?id=' . $id;
$canonicalTH = trim($article['slug'] ?? '')
    ? 'https://cmnsfixmac.com/article/' . $article['slug']
    : 'https://cmnsfixmac.com/articles/detail.php?id=' . $id;

$description = mb_substr(strip_tags($excerpt ?: $content), 0, 160);

$switch_to_lang_url = '/articles/detail.php?id=' . $id;
$page_title         = e($title) . ' | CMNS FixMac';
$page_css           = ['/assets/css/article-detail-style.css?v=2'];

ob_start(); ?>
<meta name="description" content="<?= e($description) ?>">
<meta name="robots"      content="index, follow">
<link rel="canonical"    href="<?= e($canonical) ?>">
<link rel="shortcut icon" href="/assets/img/favicon1.png">
<link rel="alternate" hreflang="th"        href="<?= e($canonicalTH) ?>">
<link rel="alternate" hreflang="en"        href="<?= e($canonical) ?>">
<link rel="alternate" hreflang="x-default" href="<?= e($canonical) ?>">
<meta property="og:type"        content="article">
<meta property="og:title"       content="<?= e($title) ?> | CMNS FixMac">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:url"         content="<?= e($canonical) ?>">
<meta property="og:image"       content="<?= e($ogImg) ?>">
<meta property="og:locale"      content="en_US">
<meta property="og:site_name"   content="CMNS FixMac">
<meta property="article:published_time" content="<?= e($dateISO) ?>">
<meta name="twitter:card"       content="summary_large_image">
<meta name="twitter:title"      content="<?= e($title) ?>">
<meta name="twitter:description" content="<?= e($description) ?>">
<meta name="twitter:image"      content="<?= e($ogImg) ?>">
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'Article',
    'headline' => $title,
    'description' => $description,
    'image'    => $ogImg,
    'datePublished' => $dateISO,
    'dateModified'  => $article['updated_at'] ? date('c', strtotime($article['updated_at'])) : $dateISO,
    'inLanguage'    => 'en-US',
    'url'      => $canonical,
    'author'   => ['@type' => 'Organization', 'name' => 'CMNS FixMac', 'url' => 'https://cmnsfixmac.com'],
    'publisher'=> ['@type' => 'Organization', 'name' => 'CMNS FixMac',
                   'logo' => ['@type' => 'ImageObject', 'url' => 'https://cmnsfixmac.com/assets/img/Logo1.png']],
    'breadcrumb' => ['@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home',     'item' => 'https://cmnsfixmac.com/en/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Articles', 'item' => 'https://cmnsfixmac.com/en/articles/'],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $title,     'item' => $canonical],
    ]],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php
$page_head_extra = ob_get_clean();
include_once '../../includes/header_en.php';
?>

<main class="ad-page">

  <div class="ad-breadcrumb-wrap">
    <nav class="ad-breadcrumb" aria-label="breadcrumb">
      <a href="/en/">Home</a>
      <span class="material-symbols-rounded">chevron_right</span>
      <a href="/en/articles/">Articles</a>
      <?php if ($catLabel): ?>
      <span class="material-symbols-rounded">chevron_right</span>
      <a href="/en/articles/?cat=<?= e(urlencode($catSlug)) ?>"><?= e($catLabel) ?></a>
      <?php endif; ?>
      <span class="material-symbols-rounded">chevron_right</span>
      <span class="ad-bc-current" aria-current="page"><?= e($title) ?></span>
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
        <?php if ($isLangTH): ?>
        <span class="ad-lang-note">Content in Thai</span>
        <?php endif; ?>
        <time class="ad-date" datetime="<?= e($dateISO) ?>">
          <span class="material-symbols-rounded">calendar_today</span>
          <?= $dateDisp ?>
        </time>
        <span class="ad-views">
          <span class="material-symbols-rounded">visibility</span>
          <?= number_format((int)$article['views']) ?> views
        </span>
      </div>
      <h1 class="ad-title"><?= e($title) ?></h1>
    </header>

    <?php if ($mainImg !== '/assets/img/placeholder.png'): ?>
    <div class="ad-main-img-wrap">
      <img
        src="<?= e($mainImg) ?>"
        alt="<?= e($title) ?>"
        class="ad-main-img"
        fetchpriority="high"
        loading="eager"
        decoding="async"
        onerror="this.src='/assets/img/placeholder.png'">
    </div>
    <?php endif; ?>

    <div class="ad-content">
      <?= $content ?>
    </div>

    <?php if ($images): ?>
    <section class="ad-gallery-section">
      <div class="ad-gallery-label">
        <span class="material-symbols-rounded">photo_library</span>
        <h2>Additional Photos</h2>
      </div>
      <div class="ad-gallery-grid">
        <?php foreach ($images as $img):
            $caption = trim($img['caption_en'] ?? '') ?: trim($img['caption'] ?? '');
            $alt     = $img['alt'] ?? $title;
        ?>
        <figure class="ad-gallery-item">
          <img src="<?= e($img['image_path']) ?>"
               alt="<?= e($alt) ?>"
               loading="lazy" decoding="async"
               onerror="this.src='/assets/img/placeholder.png'">
          <?php if ($caption): ?>
          <figcaption><?= $caption ?></figcaption>
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
        <h2>Video</h2>
      </div>
      <div class="ad-video-wrap">
        <iframe src="https://www.youtube.com/embed/<?= e($article['youtube_url']) ?>"
                loading="lazy" allowfullscreen title="<?= e($title) ?>"></iframe>
      </div>
    </section>
    <?php endif; ?>

    <div class="ad-share-row">
      <span class="ad-share-label">
        <span class="material-symbols-rounded">share</span> Share
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
        <span>Copy link</span>
      </button>
    </div>

    <?php if ($related): ?>
    <section class="ad-related">
      <div class="ad-section-label">
        <span class="material-symbols-rounded">auto_awesome</span>
        <h2>Related Articles</h2>
      </div>
      <div class="ad-rel-grid">
        <?php foreach ($related as $r):
            $rImg = ad_img($r['image'] ?? '');
            $rSlug = trim($r['slug'] ?? '');
            $rUrl  = $rSlug ? '/en/article/' . $rSlug : '/en/articles/detail.php?id=' . (int)$r['id'];
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
          $pSlug = trim($prev['slug_en'] ?? '') ?: trim($prev['slug'] ?? '');
          $prevUrl = $pSlug ? '/en/article/' . $pSlug : '/en/articles/detail.php?id=' . (int)$prev['id'];
      ?>
      <a href="<?= e($prevUrl) ?>" class="ad-nav-link">
        <span class="material-symbols-rounded">chevron_left</span>
        Previous
      </a>
      <?php else: ?><span></span><?php endif; ?>
      <?php if ($next):
          $nSlug = trim($next['slug_en'] ?? '') ?: trim($next['slug'] ?? '');
          $nextUrl = $nSlug ? '/en/article/' . $nSlug : '/en/articles/detail.php?id=' . (int)$next['id'];
      ?>
      <a href="<?= e($nextUrl) ?>" class="ad-nav-link">
        Next
        <span class="material-symbols-rounded">chevron_right</span>
      </a>
      <?php endif; ?>
    </nav>
    <?php endif; ?>

    <div class="ad-cta">
      <span class="material-symbols-rounded ad-cta-icon">build_circle</span>
      <div class="ad-cta-text">
        <h3>Apple device acting up?</h3>
        <p>Free diagnosis before every repair · Genuine parts · Up to 1-year warranty</p>
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

<?php include_once '../../includes/footer_en.php'; ?>
<script>
document.getElementById('ad-copy-btn')?.addEventListener('click', function () {
    navigator.clipboard.writeText(location.href).then(() => {
        const label = this.querySelector('span:last-child');
        label.textContent = 'Copied!';
        setTimeout(() => { label.textContent = 'Copy link'; }, 2000);
    });
});
</script>
</body>
</html>
