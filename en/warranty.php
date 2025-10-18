<?php
// en/warranty.php — Check warranty by Warranty No / Serial / Repair No
include '../includes/db.php';

// Helpers
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function nb($s){ return $s === '' || $s === null ? '-' : h($s); }

// Hero image (relative from /en)
$HERO_IMG = '../assets/img/hero.webp';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = [];
$msgErr  = '';

if ($q !== '') {
  // 1) Exact match first
  $st = $pdo->prepare("
    SELECT id, warranty_no, repair_no, customer_name, device_model, sn,
           base_date, warranty_until, warranty_status,
           DATEDIFF(warranty_until,CURDATE()) AS days_left
    FROM warranty_jobs
    WHERE warranty_no = :q OR sn = :q OR repair_no = :q
    ORDER BY id DESC
  ");
  $st->execute([':q'=>$q]);
  $results = $st->fetchAll(PDO::FETCH_ASSOC);

  // 2) If nothing, try LIKE (typo/partial)
  if (!$results){
    $st = $pdo->prepare("
      SELECT id, warranty_no, repair_no, customer_name, device_model, sn,
             base_date, warranty_until, warranty_status,
             DATEDIFF(warranty_until,CURDATE()) AS days_left
      FROM warranty_jobs
      WHERE warranty_no LIKE :s OR sn LIKE :s OR repair_no LIKE :s
      ORDER BY id DESC
      LIMIT 50
    ");
    $st->execute([':s'=>"%{$q}%"]);
    $results = $st->fetchAll(PDO::FETCH_ASSOC);
  }

  if (!$results) $msgErr = 'No warranty record was found for your query.';
}

$result = (count($results) === 1) ? $results[0] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Repair Warranty Check | CMNS FixMac</title>
  <meta name="description" content="Enter Warranty No. (WJ-xxxx) / Serial Number / Repair No. (Vxxxx) to view CMNS FixMac warranty details.">
  <link rel="alternate" hreflang="th" href="https://cmnsfixmac.com/warranty.php" />
  <link rel="alternate" hreflang="en" href="https://cmnsfixmac.com/en/warranty.php" />
  <link rel="alternate" hreflang="x-default" href="https://cmnsfixmac.com/en/warranty.php" />

  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/floating-buttons.css">
  <link rel="stylesheet" href="https://unpkg.com/aos@2.3.4/dist/aos.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
  <link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png" />
  <link rel="stylesheet" href="../assets/css/footer-style.css">

  <style>
  :root{
    --ink:#0D1A3E; --muted:#6b7280; --line:#e5e7eb;
    --bg:#f5f5f7; --blue:#000; --card:#fff;
    --shadow:0 1px 2px rgba(0,0,0,.06);
  }
  *{box-sizing:border-box}
  html,body{height:100%}
  body{
    margin:0; background:#fff; color:var(--ink);
    font:16px/1.55 -apple-system,BlinkMacSystemFont,"SF Pro Text","Helvetica Neue","Segoe UI",Arial,sans-serif;
    -webkit-font-smoothing:antialiased;
  }
  .container{max-width:1100px;margin:0 auto;padding:0 16px}

  /* ====== HERO ====== */
  .hero{
    min-height:68vh; display:grid; place-items:center; text-align:center; color:#fff;
    margin-left:calc(50% - 50vw); margin-right:calc(50% - 50vw);
    background:center/cover no-repeat fixed;
    position:relative;
  }
  .hero::before{
    content:""; position:absolute; inset:0;
    background:linear-gradient(180deg, rgba(0,0,0,.45), rgba(0,0,0,.35));
  }
  .hero .hero-content{position:relative; width:min(1000px,92vw); padding:26px 12px}
  .hero h1{margin:8px 0 10px; font-size:clamp(28px,5.6vw,56px); letter-spacing:-.02em; font-weight:800}
  .hero p{margin:0; color:#e9eaee; font-size:clamp(16px,2.2vw,22px);}

  /* Search */
  .search-wrap{display:flex;flex-direction:column;gap:10px;align-items:center;margin-top:18px}
  .search-input{
    height:58px; width:min(900px,92vw); padding:0 16px; font-size:18px;
    border:1px solid rgba(255,255,255,.6); border-radius:14px; outline:none;
    color:#fff; background:rgba(255,255,255,.16); backdrop-filter:blur(6px);
  }
  .search-input::placeholder{color:#f3f3f3}
  .btn{
    display:inline-flex; align-items:center; justify-content:center; gap:8px;
    height:58px; padding:0 24px; border-radius:14px; border:none;
    background:#000; color:#fff; font-weight:800; cursor:pointer;
  }
  .btn:hover{filter:brightness(.95)}

  /* Cards & Tables */
  .section{padding:40px 0}
  .card{background:var(--card); border-radius:16px; box-shadow:var(--shadow); border:1px solid var(--line); overflow:hidden}
  .card-head{display:flex; gap:10px; justify-content:flex-end; align-items:center; padding:12px 14px; background:#fff; border-bottom:1px solid var(--line)}
  .btn-outline{background:#fff; color:#111; border:1px solid var(--line); border-radius:10px; padding:10px 14px; cursor:pointer}
  .table{width:100%; border-collapse:collapse; background:#fff}
  .table th,.table td{padding:14px 16px; border-bottom:1px solid #eee; text-align:left; vertical-align:middle}
  .table th{width:260px; background:#fbfbfd}
  .table tr:hover td{background:#fafafa}
  .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
  .muted{color:var(--muted)}
  .nowrap{white-space:nowrap}
  a.row-link{color:inherit; text-decoration:none}

  /* Multi results table */
  .list-table{width:100%;border-collapse:collapse; background:#fff}
  .list-table th,.list-table td{padding:12px 14px;border-bottom:1px solid #eee}
  .list-table th{background:#fbfbfd;text-align:left}

  /* Print sheet */
  .print-sheet{display:none}
  .print-header{display:flex;flex-direction:column;align-items:center;margin-bottom:10px;text-align:center}
  .print-header img{width:120px;height:auto;margin-bottom:8px}
  .print-header h1{margin:0 0 6px; font-size:20pt; font-weight:800}
  .print-meta{font-size:10pt; color:#333}
  .print-table{width:100%; border-collapse:collapse; font-size:11pt; margin-top:14px}
  .print-table th,.print-table td{border:1px solid #000; padding:8px 10px; text-align:left; vertical-align:top}
  .print-table th{width:34%; background:#f6f6f6}
  .sign-row{display:flex; gap:40px; margin-top:22px}
  .sign-col{flex:1}
  .sign-line{border-bottom:1px solid #000; height:28px; margin-bottom:4px}
  .sign-label{font-size:10pt; color:#333}
  .terms{margin-top:18px; font-size:10pt; line-height:1.5; color:#333}

  @media (max-width:768px){
    .table th{width:40%}
    .card-head{flex-wrap:wrap}
  }

  /* PRINT — show only the print sheet */
  @media print{
    @page{ size:A4; margin:14mm }

    header,
    .hero,
    .no-print,
    .alert,
    .card,
    .list-table,
    .card-head,
    .section .card,
    .section .list-table,
    .container > *:not(.print-sheet) {
      display: none !important;
    }
    .print-sheet{ display:block !important; }

    body{
      background:#fff;
      color:#000;
      font:12pt/1.5 "Sarabun", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
    }
    .container{max-width:100%; padding:0}
    .section{padding:0}
    /* .card{box-shadow:none; border:none} <-- เอาออกเพราะ .card โดนซ่อนหมดแล้ว */
    table, tr, td, th{ page-break-inside:avoid }
  }
  </style>

  <script async src="https://www.googletagmanager.com/gtag/js?id=G-3WXK9GWN7C"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){ dataLayer.push(arguments) }
    gtag('js', new Date());
    gtag('config', 'G-3WXK9GWN7C');
  </script>
</head>
<body>

<?php include_once '../includes/header_en.php'; ?>

<section class="hero" style="background-image:url('<?= h($HERO_IMG) ?>')">
  <div class="hero-content" data-aos="fade-up">
    <h1>Repair Warranty Check</h1>
    <p>Enter your <b>Warranty No. (WJ-xxxx)</b>, <b>Serial Number</b>, or <b>Repair No. (Vxxxx)</b> and press search.</p>

    <form action="#result" method="get" class="search-wrap">
      <input type="text" name="q" class="search-input" placeholder="e.g., WJ-2025-0001 / C02XXXXXXX / V12345" value="<?= h($q) ?>" required>
      <button class="btn" type="submit"><span class="material-symbols-rounded">search</span>Search</button>
    </form>

    <?php if ($msgErr): ?>
      <div class="alert" style="margin-top:12px;background:#fde8e8;color:#a40000;padding:10px 14px;border-radius:10px">
        <?= h($msgErr) ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<div class="section" id="result">
  <div class="container">
  <?php if ($result): ?>
    <?php
      $d  = (int)($result['days_left'] ?? 0);
      $st = (string)($result['warranty_status'] ?? '');
      $label = $st==='in_warranty' ? 'In warranty' : ($st==='expired' ? 'Expired' : ($st==='void' ? 'Void' : $st));
      $note  = $st==='in_warranty' ? "— {$d} day(s) remaining" : ($st==='expired' ? "— expired ".abs($d)." day(s) ago" : '');
    ?>
    <div class="card" data-aos="fade-up">
      <div class="card-head no-print">
        <button class="btn-outline" type="button" onclick="copyWarranty('<?= h($result['warranty_no']) ?>')">
          <span class="material-symbols-rounded" style="vertical-align:middle">content_copy</span> Copy Warranty No.
        </button>
        <button class="btn-outline" type="button" onclick="window.print()">
          <span class="material-symbols-rounded" style="vertical-align:middle">print</span> Print Warranty Slip
        </button>
      </div>
      <table class="table">
        <tbody>
          <tr><th>Warranty No.</th><td class="mono"><strong><?= nb($result['warranty_no']) ?></strong></td></tr>
          <tr><th>Repair No.</th><td class="mono"><?= nb($result['repair_no']) ?></td></tr>
          <tr><th>Customer</th><td><?= nb($result['customer_name']) ?></td></tr>
          <tr><th>Device</th><td><?= nb($result['device_model']) ?></td></tr>
          <tr><th>Serial</th><td class="mono"><?= nb($result['sn']) ?></td></tr>
          <tr><th>Start Date</th><td class="mono"><?= nb($result['base_date']) ?></td></tr>
          <tr><th>Expiry Date</th><td class="mono"><strong><?= nb($result['warranty_until']) ?></strong></td></tr>
          <tr>
            <th>Warranty Status</th>
            <td>
              <span><?= h($label) ?></span>
              <?php if ($note): ?><small class="muted" style="margin-left:6px"><?= h($note) ?></small><?php endif; ?>
            </td>
          </tr>
        </tbody>
      </table>
    </div> <div class="card no-print" data-aos="fade-up" style="margin-top:24px;" data-aos-delay="100">
      <div style="padding: 18px 20px 20px 20px;">
        <strong style="font-size: 1.1rem; font-weight: 700; display: block; margin-bottom: 12px;">Warranty Terms</strong>
        
        <div class="terms-display" style="font-size: 15px; line-height: 1.65; color: var(--ink);">
          <ol style="margin: 0 0 0 18px; padding: 0; list-style-position: outside;">
            <li>Warranty covers only the stated symptom(s) in the repair job and the parts replaced by CMNS FixMac.</li>
            <li>It does not cover damage from liquid, drop/impact, tampering, misuse, or third-party repair.</li>
            <li>Warranty seals/stickers must be intact. If removed/torn, the warranty is void.</li>
            <li>When claiming warranty, please provide your <strong>Warranty No. (WJ-xxxx)</strong> or <strong>Serial Number</strong>.</li>
            <li>Other conditions are subject to CMNS FixMac’s latest policies.</li>
          </ol>
        </div>
      </div>
    </div>
    <section class="print-sheet">
      <div class="print-header">
        <img src="../assets/img/apple-logo.png" alt="CMNS FixMac">
        <h1>Warranty Result</h1>
        <div class="print-meta">Warranty Slip / Warranty Verification</div>
        <div class="print-meta" style="margin-top:4px">
          CMNS FixMac — 482 Moo 8, behind Kad Varune Market, Chiang Mai–Hang Dong Rd., Mae Hia, Mueang, Chiang Mai 50100 · Tel. 084-151-1684 · cmnsfixmac.com
        </div>
      </div>

      <table class="print-table">
        <tr><th>Warranty No.</th><td><?= nb($result['warranty_no']) ?></td></tr>
        <tr><th>Repair No.</th><td><?= nb($result['repair_no']) ?></td></tr>
        <tr><th>Customer</th><td><?= nb($result['customer_name']) ?></td></tr>
        <tr><th>Device</th><td><?= nb($result['device_model']) ?></td></tr>
        <tr><th>Serial</th><td><?= nb($result['sn']) ?></td></tr>
        <tr><th>Start Date</th><td><?= nb($result['base_date']) ?></td></tr>
        <tr><th>Expiry Date</th><td><?= nb($result['warranty_until']) ?></td></tr>
        <tr><th>Warranty Status</th><td><?= h($label) ?><?= $note ? ' '.$note : '' ?></td></tr>
      </table>

      <div class="terms">
        <strong>Warranty Terms</strong>
        <ol style="margin:6px 0 0 18px">
          <li>Warranty covers only the stated symptom(s) in the repair job and the parts replaced by CMNS FixMac.</li>
          <li>It does not cover damage from liquid, drop/impact, tampering, misuse, or third-party repair.</li>
          <li>Warranty seals/stickers must be intact. If removed/torn, the warranty is void.</li>
          <li>When claiming warranty, please provide your <strong>Warranty No. (WJ-xxxx)</strong> or <strong>Serial Number</strong>.</li>
          <li>Other conditions are subject to CMNS FixMac’s latest policies.</li>
        </ol>
      </div>

      <div class="sign-row" style="margin-top:24px">
        <div class="sign-col">
          <div class="sign-line"></div>
          <div class="sign-label">Recipient’s Signature</div>
        </div>
        <div class="sign-col">
          <div class="sign-line"></div>
          <div class="sign-label">Staff Signature</div>
        </div>
      </div>
    </section>

  <?php elseif ($q !== '' && count($results) > 1): ?>
    <div class="card" data-aos="fade-up">
      <div class="card-head no-print" style="justify-content:flex-start">
        <div class="muted" style="padding-left:2px">Multiple results found — choose a device</div>
      </div>
      <div style="overflow:auto">
        <table class="list-table">
          <thead>
            <tr>
              <th>Warranty No.</th>
              <th>Repair No.</th>
              <th>Device</th>
              <th>Serial</th>
              <th>Start</th>
              <th>Expiry</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($results as $r): ?>
              <?php
                $d=(int)($r['days_left']??0);
                $st=(string)($r['warranty_status']??'');
                $label=$st==='in_warranty'?'In warranty':($st==='expired'?'Expired':($st==='void'?'Void':$st));
                $link='?q='.urlencode($r['warranty_no']).'#result';
              ?>
              <tr>
                <td class="mono nowrap"><a class="row-link" href="<?= h($link) ?>"><strong><?= nb($r['warranty_no']) ?></strong></a></td>
                <td class="mono nowrap"><?= nb($r['repair_no']) ?></td>
                <td><?= nb($r['device_model']) ?></td>
                <td class="mono"><?= nb($r['sn']) ?></td>
                <td class="mono nowrap"><?= nb($r['base_date']) ?></td>
                <td class="mono nowrap"><strong><?= nb($r['warranty_until']) ?></strong></td>
                <td class="nowrap"><?= h($label) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
  </div>
</div>

<div class="no-print">
  <?php include_once '../includes/floating-buttons.php'; ?>
  <script src="../assets/js/floating-buttons.js"></script>
</div>

<div class="no-print">
  <?php include_once '../includes/footer.php'; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
<script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
<script>AOS.init();</script>
<script src="../assets/js/main.js"></script>
<script src="../assets/js/swiper-init.js"></script>
<script src="../assets/js/aos-init.js"></script>
<script src="../assets/js/script.js"></script>
<script src="../assets/js/lazy-youtube.js"></script>
<script src="../assets/js/preload-images.js"></script>

<script>
  function copyWarranty(text){
    navigator.clipboard.writeText(text).then(()=>alert('Copied: '+text));
  }

  // If there are results, scroll to them after load
  <?php if ($q !== '' && ($result || count($results) > 1)): ?>
  window.addEventListener('load', () => {
    const el = document.getElementById('result');
    if (el) el.scrollIntoView({behavior:'smooth', block:'start'});
  });
  <?php endif; ?>
</script>
</body>
</html>