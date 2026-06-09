<?php
require_once '../../includes/db.php';

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function wd_img(string $raw): string {
    if (!$raw) return '/assets/img/placeholder.png';
    if ($raw[0] === '/' || str_starts_with($raw, 'http')) return $raw;
    if (str_contains($raw, '/')) return '/' . ltrim($raw, '/');
    return '/assets/img/placeholder.png';
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { header('Location: /en/works/'); exit; }

$stmt = $pdo->prepare("SELECT * FROM repairs WHERE id = ? AND status = 'published'");
$stmt->execute([$id]);
$data = $stmt->fetch();
if (!$data) { http_response_code(404); header('Location: /en/works/'); exit; }

// unique view: 1 IP per day
$ip  = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')[0]);
$log = $pdo->prepare("INSERT IGNORE INTO repair_views (repair_id, ip, viewed_date) VALUES (?, ?, ?)");
$log->execute([$id, $ip, date('Y-m-d')]);
if ($log->rowCount() > 0) {
    $pdo->prepare("UPDATE repairs SET views = views + 1 WHERE id = ?")->execute([$id]);
    $data['views']++;
}

$gallery = $pdo->prepare("SELECT image_path, caption FROM repair_images WHERE repair_id = ? ORDER BY sort_order, id");
$gallery->execute([$id]);
$gallery = $gallery->fetchAll();

$relStmt = $pdo->prepare(
    "SELECT id, COALESCE(NULLIF(title_en,''), title) AS title, model, image, category FROM repairs
     WHERE status='published' AND TRIM(LOWER(category))=TRIM(LOWER(?)) AND id != ?
     ORDER BY views DESC LIMIT 4"
);
$relStmt->execute([trim($data['category']), $id]);
$related = $relStmt->fetchAll();

// EN with TH fallback
$title     = trim($data['title_en'] ?? '') ?: trim($data['title'] ?? '');
$issue     = trim($data['issue_en'] ?? '') ?: trim($data['issue'] ?? '');
$fixDetail = trim($data['fix_detail_en'] ?? '') ?: trim($data['fix_detail'] ?? '');
$issueLang = trim($data['issue_en'] ?? '') ? '' : ' <em class="wd-lang-note">(Thai)</em>';
$fixLang   = trim($data['fix_detail_en'] ?? '') ? '' : ' <em class="wd-lang-note">(Thai)</em>';

$mainImg  = wd_img($data['image'] ?? '');
$ogImg    = str_starts_with($mainImg, 'http') ? $mainImg : 'https://cmnsfixmac.com' . $mainImg;
$catLabel = trim($data['category'] ?? '');
$catSlug  = strtolower(preg_replace('/\s+/', '', $catLabel));
$dateISO  = date('c', strtotime($data['created_at']));
$dateDisp = date('d M Y', strtotime($data['created_at']));
$catIcons = ['MacBook'=>'laptop_mac','iMac'=>'desktop_mac','iPhone'=>'smartphone','iPad'=>'tablet_mac','AppleWatch'=>'watch','AirPods'=>'headphones','Software'=>'terminal'];
$catIcon  = $catIcons[$catLabel] ?? 'build';

$metaRaw      = $data['meta_desc'] ?: mb_substr(strip_tags($fixDetail), 0, 155);
$canonicalUrl = 'https://cmnsfixmac.com/en/works/detail.php?id=' . $id;

$page_title       = e($title) . ' — ' . ($catLabel ? e($catLabel) . ' ' : '') . 'Repair | CMNS FixMac';
$page_css         = ['/assets/css/works-detail-style.css?v=1'];
$switch_to_lang_url = "/works/detail.php?id=$id";

ob_start(); ?>
<meta name="description" content="<?= e($metaRaw) ?>">
<meta name="robots"      content="index, follow">
<link rel="canonical"    href="<?= e($canonicalUrl) ?>">
<link rel="shortcut icon" href="/assets/img/favicon1.png">
<link rel="alternate" hreflang="th"        href="<?= e('https://cmnsfixmac.com/works/detail.php?id=' . $id) ?>">
<link rel="alternate" hreflang="en"        href="<?= e($canonicalUrl) ?>">
<link rel="alternate" hreflang="x-default" href="<?= e($canonicalUrl) ?>">
<meta property="og:title"       content="<?= e($title) ?> | CMNS FixMac">
<meta property="og:description" content="<?= e($metaRaw) ?>">
<meta property="og:type"        content="article">
<meta property="og:image"       content="<?= e($ogImg) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:url"         content="<?= e($canonicalUrl) ?>">
<meta property="og:locale"      content="en_US">
<meta name="twitter:card"       content="summary_large_image">
<meta name="twitter:image"      content="<?= e($ogImg) ?>">
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@graph'   => [
        ['@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home',     'item' => 'https://cmnsfixmac.com/en/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Our Work', 'item' => 'https://cmnsfixmac.com/en/works/'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $title,     'item' => $canonicalUrl],
        ]],
        ['@type'           => 'Article',
         'headline'        => $title,
         'description'     => $metaRaw,
         'image'           => $ogImg,
         'datePublished'   => $dateISO,
         'author'          => ['@type' => 'Organization', 'name' => 'CMNS FixMac'],
         'publisher'       => ['@type' => 'Organization', 'name' => 'CMNS FixMac',
                               'logo' => ['@type' => 'ImageObject', 'url' => 'https://cmnsfixmac.com/assets/img/Logo1.png']],
         'mainEntityOfPage'=> ['@type' => 'WebPage', '@id' => $canonicalUrl],
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php
$page_head_extra = ob_get_clean();
include_once '../../includes/header_en.php';
?>

<main class="wd-page">

  <div class="wd-breadcrumb-wrap">
    <nav class="wd-breadcrumb" aria-label="breadcrumb">
      <a href="/en/">Home</a>
      <span class="material-symbols-rounded">chevron_right</span>
      <a href="/en/works/">Our Work</a>
      <span class="material-symbols-rounded">chevron_right</span>
      <?php if ($catLabel): ?>
      <a href="/en/works/?category=<?= e(urlencode($catLabel)) ?>"><?= e($catLabel) ?></a>
      <span class="material-symbols-rounded">chevron_right</span>
      <?php endif; ?>
      <span class="wd-bc-current" aria-current="page"><?= e($title) ?></span>
    </nav>
  </div>

  <div class="wd-container">

    <header class="wd-hero">
      <div class="wd-hero-meta">
        <?php if ($catLabel): ?>
        <span class="wd-cat-badge" data-cat="<?= e($catSlug) ?>">
          <span class="material-symbols-rounded"><?= e($catIcon) ?></span>
          <?= e($catLabel) ?>
        </span>
        <?php endif; ?>
        <?php if ($data['model']): ?>
        <span class="wd-model-pill"><?= e($data['model']) ?></span>
        <?php endif; ?>
        <time class="wd-date" datetime="<?= e($dateISO) ?>">
          <span class="material-symbols-rounded">calendar_today</span>
          <?= $dateDisp ?>
        </time>
        <span class="wd-views">
          <span class="material-symbols-rounded">visibility</span>
          <?= number_format((int)$data['views']) ?> views
        </span>
      </div>
      <h1 class="wd-title"><?= e($title) ?></h1>
    </header>

    <div class="wd-main-img-wrap">
      <img
        src="<?= e($mainImg) ?>"
        alt="Repair: <?= e($title) ?> — <?= e($data['model']) ?>"
        class="wd-main-img"
        fetchpriority="high"
        loading="eager"
        decoding="async"
        onerror="this.src='/assets/img/placeholder.png'">
    </div>

    <div class="wd-body">

      <?php if ($issue): ?>
      <section class="wd-section">
        <div class="wd-section-label">
          <span class="material-symbols-rounded">report_problem</span>
          <h2>Problem Found<?= $issueLang ?></h2>
        </div>
        <div class="wd-prose"><?= nl2br(e($issue)) ?></div>
      </section>
      <?php endif; ?>

      <?php if ($fixDetail): ?>
      <section class="wd-section">
        <div class="wd-section-label">
          <span class="material-symbols-rounded">build_circle</span>
          <h2>How We Fixed It<?= $fixLang ?></h2>
        </div>
        <div class="wd-prose"><?= nl2br(e($fixDetail)) ?></div>
      </section>
      <?php endif; ?>

      <?php if ($gallery): ?>
      <section class="wd-gallery-section">
        <div class="wd-section-label">
          <span class="material-symbols-rounded">photo_library</span>
          <h2>Photo Gallery</h2>
        </div>
        <div class="wd-gallery-grid">
          <?php foreach ($gallery as $img): ?>
          <figure class="wd-gallery-item">
            <img src="<?= e(wd_img($img['image_path'])) ?>"
                 alt="<?= e($img['caption'] ?? '') ?>"
                 loading="lazy" decoding="async"
                 onerror="this.src='/assets/img/placeholder.png'">
            <?php if (trim($img['caption'] ?? '')): ?>
            <figcaption><?= e(trim($img['caption'])) ?></figcaption>
            <?php endif; ?>
          </figure>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <div class="wd-share-row">
        <span class="wd-share-label">
          <span class="material-symbols-rounded">share</span> Share
        </span>
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($canonicalUrl) ?>"
           target="_blank" rel="noopener" class="wd-share-btn wd-share-fb">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
          Facebook
        </a>
        <a href="https://social-plugins.line.me/lineit/share?url=<?= urlencode($canonicalUrl) ?>"
           target="_blank" rel="noopener" class="wd-share-btn wd-share-line">
          <img src="/assets/img/line-icon.png" width="16" height="16" alt="LINE">
          LINE
        </a>
        <button id="wd-copy-btn" class="wd-share-btn wd-share-copy" type="button">
          <span class="material-symbols-rounded">link</span>
          <span>Copy link</span>
        </button>
      </div>

    </div><!-- /.wd-body -->

    <?php if ($related): ?>
    <section class="wd-related">
      <div class="wd-section-label">
        <span class="material-symbols-rounded">auto_awesome</span>
        <h2>Related Repairs</h2>
      </div>
      <div class="wd-rel-grid">
        <?php foreach ($related as $r):
            $rImg     = wd_img($r['image'] ?? '');
            $rCatSlug = strtolower(preg_replace('/\s+/', '', trim($r['category'] ?? '')));
        ?>
        <a href="/en/works/detail.php?id=<?= (int)$r['id'] ?>" class="wd-rel-card">
          <div class="wd-rel-thumb">
            <img src="<?= e($rImg) ?>"
                 alt="<?= e($r['title']) ?>"
                 loading="lazy" decoding="async"
                 onerror="this.src='/assets/img/placeholder.png'">
            <span class="wd-cat-badge wd-cat-badge--sm" data-cat="<?= e($rCatSlug) ?>"><?= e(trim($r['category'])) ?></span>
          </div>
          <div class="wd-rel-body">
            <h3><?= e($r['title']) ?></h3>
            <span><?= e($r['model']) ?></span>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <div class="wd-cta">
      <span class="material-symbols-rounded wd-cta-icon"><?= e($catIcon) ?></span>
      <div class="wd-cta-text">
        <h3>Have a similar issue?</h3>
        <p>Free diagnosis before every repair · Genuine parts · Up to 1-year warranty</p>
      </div>
      <div class="wd-cta-btns">
        <a href="tel:0841511684" class="wd-btn-accent">
          <span class="material-symbols-rounded">call</span> 084-151-1684
        </a>
        <a href="https://line.me/R/ti/p/@cmns" target="_blank" rel="noopener" class="wd-btn-line">
          <img src="/assets/img/line-icon.png" alt="LINE" width="16" height="16"> LINE: @cmns
        </a>
      </div>
    </div>

  </div><!-- /.wd-container -->
</main>

<?php include_once '../../includes/footer_en.php'; ?>
<script>
document.getElementById('wd-copy-btn')?.addEventListener('click', function () {
    navigator.clipboard.writeText(location.href).then(() => {
        const label = this.querySelector('span:last-child');
        label.textContent = 'Copied!';
        setTimeout(() => { label.textContent = 'Copy link'; }, 2000);
    });
});
</script>
</body>
</html>
