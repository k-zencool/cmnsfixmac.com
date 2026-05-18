<?php
include '../includes/db.php';

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$slug = trim(filter_input(INPUT_GET, 'slug', FILTER_DEFAULT) ?? '');
$id   = (int)(filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? 0);

if ($slug && !$id) {
    $st = $pdo->prepare("SELECT id FROM articles WHERE slug = ? OR slug_en = ? LIMIT 1");
    $st->execute([$slug, $slug]);
    $id = (int)($st->fetchColumn() ?: 0);
}
if (!$id) { header("Location: /articles/"); exit; }

$pdo->prepare("UPDATE articles SET views = views + 1 WHERE id = ?")->execute([$id]);

$stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
$stmt->execute([$id]);
$article = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$article || !(int)$article['status']) { http_response_code(404); echo "<h1>ไม่พบบทความ</h1>"; exit; }

// Gallery — ordered by sort_order
$imgs = $pdo->prepare("SELECT * FROM article_images WHERE article_id = ? ORDER BY sort_order ASC, id ASC");
$imgs->execute([$id]);
$images = $imgs->fetchAll(PDO::FETCH_ASSOC);

$related = $pdo->prepare("SELECT id,title,slug,image,og_image_width,og_image_height FROM articles WHERE category=? AND id!=? AND status=1 ORDER BY created_at DESC LIMIT 4");
$related->execute([$article['category'], $id]);
$related = $related->fetchAll(PDO::FETCH_ASSOC);

$popular = $pdo->prepare("SELECT id,title,slug,image FROM articles WHERE id!=? AND status=1 ORDER BY views DESC LIMIT 4");
$popular->execute([$id]);
$popular = $popular->fetchAll(PDO::FETCH_ASSOC);

$prev = $pdo->prepare("SELECT id,slug,title FROM articles WHERE id<? AND status=1 ORDER BY id DESC LIMIT 1");
$prev->execute([$id]); $prev = $prev->fetch(PDO::FETCH_ASSOC);

$next = $pdo->prepare("SELECT id,slug,title FROM articles WHERE id>? AND status=1 ORDER BY id ASC LIMIT 1");
$next->execute([$id]); $next = $next->fetch(PDO::FETCH_ASSOC);

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$base     = "$protocol://$host";

// Canonical: prefer slug URL
$canonical = $article['slug'] ? "$base/article/" . e($article['slug']) : "$base/articles/detail.php?id=$id";

// OG image
$ogImg    = $article['image'] ? "$base" . e($article['image']) : "$base/assets/img/placeholder.png";
$ogImgW   = $article['og_image_width']  ?? null;
$ogImgH   = $article['og_image_height'] ?? null;

$description = mb_substr(strip_tags($article['excerpt'] ?: $article['content'] ?? ''), 0, 160);
$datePublished = $article['created_at'] ? date('c', strtotime($article['created_at'])) : '';
$dateModified  = $article['updated_at'] ? date('c', strtotime($article['updated_at'])) : $datePublished;

// JSON-LD image array: cover + gallery
$jImages = [];
if ($article['image']) {
    $jImg = ['@type' => 'ImageObject', 'url' => "$base" . $article['image']];
    if ($ogImgW) $jImg['width']  = (int)$ogImgW;
    if ($ogImgH) $jImg['height'] = (int)$ogImgH;
    $jImages[] = $jImg;
}
foreach ($images as $gi) {
    $entry = ['@type' => 'ImageObject', 'url' => "$base" . $gi['image_path']];
    if (!empty($gi['alt']))   $entry['name'] = $gi['alt'];
    if (!empty($gi['width'])) $entry['width']  = (int)$gi['width'];
    if (!empty($gi['height']))$entry['height'] = (int)$gi['height'];
    $jImages[] = $entry;
}

$jsonLd = [
    '@context'        => 'https://schema.org',
    '@type'           => 'Article',
    'headline'        => $article['title'],
    'description'     => $description,
    'image'           => $jImages ?: ["$base/assets/img/placeholder.png"],
    'datePublished'   => $datePublished,
    'dateModified'    => $dateModified ?: $datePublished,
    'url'             => $canonical,
    'inLanguage'      => 'th-TH',
    'author'          => ['@type' => 'Organization', 'name' => 'CMNS FixMac', 'url' => $base],
    'publisher'       => [
        '@type'  => 'Organization',
        'name'   => 'CMNS FixMac',
        'url'    => $base,
        'logo'   => ['@type' => 'ImageObject', 'url' => "$base/assets/img/favicon1.png"],
    ],
    'breadcrumb' => [
        '@type'           => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'หน้าแรก',    'item' => $base . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'บทความ',     'item' => $base . '/articles/'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $article['title'], 'item' => $canonical],
        ],
    ],
];

$switch_to_lang_url = "/en/articles/detail.php?id=" . $id;
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($article['title']) ?> | CMNS FixMac</title>
  <meta name="description" content="<?= e($description) ?>">
  <link rel="canonical" href="<?= $canonical ?>">

  <link rel="alternate" hreflang="th"      href="<?= $base ?>/article/<?= e($article['slug'] ?? '') ?>">
  <link rel="alternate" hreflang="en"      href="<?= $base ?>/en/article/<?= e($article['slug_en'] ?? ($article['slug'] ?? '')) ?>">
  <link rel="alternate" hreflang="x-default" href="<?= $base ?>/en/article/<?= e($article['slug_en'] ?? ($article['slug'] ?? '')) ?>">

  <!-- Open Graph -->
  <meta property="og:type"        content="article">
  <meta property="og:title"       content="<?= e($article['title']) ?> | CMNS FixMac">
  <meta property="og:description" content="<?= e($description) ?>">
  <meta property="og:url"         content="<?= $canonical ?>">
  <meta property="og:image"       content="<?= $ogImg ?>">
  <meta property="og:image:alt"   content="<?= e($article['title']) ?>">
  <meta property="og:site_name"   content="CMNS FixMac">
  <meta property="article:published_time" content="<?= $datePublished ?>">
  <?php if ($ogImgW): ?><meta property="og:image:width"  content="<?= (int)$ogImgW ?>"><?php endif; ?>
  <?php if ($ogImgH): ?><meta property="og:image:height" content="<?= (int)$ogImgH ?>"><?php endif; ?>

  <!-- Twitter -->
  <meta name="twitter:card"        content="summary_large_image">
  <meta name="twitter:title"       content="<?= e($article['title']) ?>">
  <meta name="twitter:description" content="<?= e($description) ?>">
  <meta name="twitter:image"       content="<?= $ogImg ?>">

  <!-- JSON-LD -->
  <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

  <link rel="stylesheet" href="/assets/css/navbar-style.css">
  <link rel="stylesheet" href="/assets/css/article-detail-style.css">
  <link rel="stylesheet" href="/assets/css/footer-style.css">
  <link rel="shortcut icon" href="/assets/img/favicon1.png">

  <script async src="https://www.googletagmanager.com/gtag/js?id=G-3WXK9GWN7C"></script>
  <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','G-3WXK9GWN7C');</script>
</head>
<body>
  <?php include '../includes/header.php'; ?>

  <main class="article-detail container">
    <!-- Breadcrumb -->
    <div class="breadcrumb-bar">
      <a href="/articles/" class="breadcrumb-home">บทความทั้งหมด</a>
      <span class="breadcrumb-separator">›</span>
      <span class="breadcrumb-current"><?= e($article['title']) ?></span>
    </div>

    <h1><?= e($article['title']) ?></h1>
    <p class="date">เผยแพร่เมื่อ <?= date('d F Y', strtotime($article['created_at'])) ?></p>
    <p class="views">รับชม <?= number_format($article['views']) ?> ครั้ง</p>

    <?php if (!empty($article['image'])): ?>
      <img class="main-image"
           src="<?= e($article['image']) ?>"
           alt="<?= e($article['title']) ?>"
           loading="eager"
           <?php if ($ogImgW && $ogImgH): ?>width="<?= (int)$ogImgW ?>" height="<?= (int)$ogImgH ?>"<?php endif; ?>>
    <?php endif; ?>

    <article class="article-content">
      <?= $article['content'] ?>
    </article>

    <?php if ($images || !empty($article['youtube_url'])): ?>
      <section class="article-gallery">
        <?php if ($images): ?>
        <h2>ภาพเพิ่มเติม</h2>
        <div class="gallery-grid">
          <?php foreach ($images as $img):
            $alt = $img['alt'] ?: $article['title'];
          ?>
            <figure>
              <img loading="lazy"
                   src="<?= e($img['image_path']) ?>"
                   alt="<?= e($alt) ?>"
                   <?php if (!empty($img['width']) && !empty($img['height'])): ?>
                   width="<?= (int)$img['width'] ?>" height="<?= (int)$img['height'] ?>"
                   <?php endif; ?>>
              <?php if (!empty($img['caption'])): ?>
                <figcaption><?= e($img['caption']) ?></figcaption>
              <?php endif; ?>
            </figure>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($article['youtube_url'])): ?>
        <h2>วิดีโอเพิ่มเติม</h2>
        <div class="article-video">
          <iframe src="https://www.youtube.com/embed/<?= e($article['youtube_url']) ?>"
                  loading="lazy" allowfullscreen title="<?= e($article['title']) ?>"></iframe>
        </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <div class="article-actions">
      <button class="share-btn native" onclick="shareNative()">
        <img src="/assets/img/icons/Share.png" alt="แชร์บทความ" loading="lazy"> แชร์บทความ
      </button>
      <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($canonical) ?>"
         target="_blank" rel="noopener noreferrer" class="share-btn facebook desktop-only">
        <img src="/assets/img/icons/facebook.png" alt="แชร์ Facebook" loading="lazy"> แชร์ Facebook
      </a>
      <a href="https://social-plugins.line.me/lineit/share?url=<?= urlencode($canonical) ?>"
         target="_blank" rel="noopener noreferrer" class="share-btn line desktop-only">
        <img src="/assets/img/icons/Line.png" alt="แชร์ LINE" loading="lazy"> แชร์ LINE
      </a>
      <button class="share-btn copy" onclick="copyArticleLink()">
        <img src="/assets/img/icons/Link.png" alt="คัดลอกลิงก์" loading="lazy"> คัดลอกลิงก์
      </button>
    </div>

    <?php if ($related): ?>
      <section class="related-articles">
        <h2>บทความที่เกี่ยวข้อง</h2>
        <div class="related-list">
          <?php foreach ($related as $item):
            $rImg = $item['image'] ?: '/assets/img/placeholder.png';
            $rUrl = $item['slug'] ? '/article/' . e($item['slug']) : '/articles/detail.php?id=' . (int)$item['id'];
          ?>
            <a href="<?= $rUrl ?>" class="related-item">
              <img src="<?= e($rImg) ?>" alt="<?= e($item['title']) ?>" loading="lazy"
                   <?php if (!empty($item['og_image_width']) && !empty($item['og_image_height'])): ?>
                   width="<?= (int)$item['og_image_width'] ?>" height="<?= (int)$item['og_image_height'] ?>"
                   <?php endif; ?>>
              <div class="related-item-content">
                <h3><?= e($item['title']) ?></h3>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($popular): ?>
      <section class="popular-articles">
        <h2>บทความยอดนิยม</h2>
        <div class="popular-list">
          <?php foreach ($popular as $pop):
            $pImg = $pop['image'] ?: '/assets/img/placeholder.png';
            $pUrl = $pop['slug'] ? '/article/' . e($pop['slug']) : '/articles/detail.php?id=' . (int)$pop['id'];
          ?>
            <a href="<?= $pUrl ?>" class="popular-item">
              <img src="<?= e($pImg) ?>" alt="<?= e($pop['title']) ?>" loading="lazy">
              <div class="popular-item-content"><h3><?= e($pop['title']) ?></h3></div>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($prev || $next): ?>
      <nav class="article-nav short">
        <?php if ($prev): ?>
          <a class="prev-article" href="<?= $prev['slug'] ? '/article/' . e($prev['slug']) : '/articles/detail.php?id=' . (int)$prev['id'] ?>">← ก่อนหน้า</a>
        <?php endif; ?>
        <?php if ($next): ?>
          <a class="next-article" href="<?= $next['slug'] ? '/article/' . e($next['slug']) : '/articles/detail.php?id=' . (int)$next['id'] ?>">ถัดไป →</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  </main>

  <?php include_once '../includes/footer.php'; ?>

  <script>
    function shareNative() {
      if (navigator.share) {
        navigator.share({ title: document.title, url: "<?= $canonical ?>" });
      } else {
        alert("อุปกรณ์ของคุณไม่รองรับการแชร์อัตโนมัติ");
      }
    }
    function copyArticleLink() {
      navigator.clipboard.writeText("<?= $canonical ?>")
        .then(() => alert("คัดลอกลิงก์เรียบร้อยแล้ว!"))
        .catch(() => alert("ไม่สามารถคัดลอกลิงก์ได้"));
    }
  </script>
</body>
</html>
