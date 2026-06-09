<?php
date_default_timezone_set('Asia/Bangkok');
require_once '../../includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function mask_phone($p){
    $p = preg_replace('/\D/', '', $p ?? '');
    if (strlen($p) >= 8) return substr($p,0,3) . '-XXXX-' . substr($p,-3);
    return $p ? str_repeat('X', strlen($p)) : '-';
}

$pdo->exec("UPDATE warranties SET status='expired' WHERE status='active' AND end_date < CURDATE()");

$q      = trim($_GET['q'] ?? '');
$war    = null;
$claims = [];
$errMsg = '';
$rateLimited = false;

/* ── Bot protection: honeypot + per-session rate limit ── */
$botFlagged = isset($_GET['company']) && trim($_GET['company']) !== '';

const WP_MAX_LOOKUPS = 8;
const WP_WINDOW      = 60;
if ($q !== '' && !$botFlagged) {
    $now = time();
    $hits = array_filter($_SESSION['wp_lookups'] ?? [], fn($t) => $t > $now - WP_WINDOW);
    if (count($hits) >= WP_MAX_LOOKUPS) {
        $rateLimited = true;
    } else {
        $hits[] = $now;
    }
    $_SESSION['wp_lookups'] = array_values($hits);
}

if ($q !== '' && !$botFlagged && !$rateLimited) {
    $st = $pdo->prepare("SELECT * FROM warranties WHERE warranty_no = :q OR serial_no = :q ORDER BY id DESC LIMIT 1");
    $st->execute([':q' => $q]);
    $war = $st->fetch(PDO::FETCH_ASSOC);
    if (!$war) {
        $st2 = $pdo->prepare("SELECT * FROM warranties WHERE warranty_no LIKE :s OR serial_no LIKE :s ORDER BY id DESC LIMIT 1");
        $st2->execute([':s' => "%$q%"]);
        $war = $st2->fetch(PDO::FETCH_ASSOC);
    }
    if ($war) {
        $cs = $pdo->prepare("SELECT claim_no, claim_date, issue_desc, resolution, status FROM warranty_claims WHERE warranty_id = ? AND status IN ('resolved','rejected') ORDER BY claim_date DESC");
        $cs->execute([$war['id']]);
        $claims = $cs->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $errMsg = 'Warranty not found';
    }
}

$days_left = $war ? (int)ceil((strtotime($war['end_date']) - time()) / 86400) : 0;
$page_title = 'Warranty Check — CMNS Fix Mac';
$switch_to_lang_url = '/warranty/' . ($q ? '?q=' . urlencode($q) : '');

if ($war) {
    $total_d   = $war['warranty_days'];
    $used_d    = max(0, min((int)ceil((time() - strtotime($war['start_date'])) / 86400), $total_d));
    $pct       = $total_d > 0 ? round(($used_d / $total_d) * 100) : 100;
    $is_active = $war['status'] === 'active' && $days_left > 0;
    $is_voided = $war['status'] === 'voided';
    if ($is_active) {
        $theme = ['grad'=>'linear-gradient(135deg,#065f46 0%,#059669 100%)','icon'=>'verified','label'=>'Warranty active'];
    } elseif ($is_voided) {
        $theme = ['grad'=>'linear-gradient(135deg,#7f1d1d 0%,#dc2626 100%)','icon'=>'block','label'=>'Warranty voided'];
    } else {
        $theme = ['grad'=>'linear-gradient(135deg,#374151 0%,#6b7280 100%)','icon'=>'schedule','label'=>'Warranty expired'];
    }
}

$page_css = ['/assets/css/warranty-style.css?v=5'];
include '../../includes/header_en.php';
?>

<?php $hasResult = ($war || $errMsg || $rateLimited); ?>

<!-- ═══════════ HERO ═══════════ -->
<section class="wg-hero<?= $hasResult ? ' is-compact' : '' ?>" id="wgHero">
  <div class="wg-orb wg-orb-1" aria-hidden="true"></div>
  <div class="wg-orb wg-orb-2" aria-hidden="true"></div>

  <div class="wg-hero-inner">
    <span class="wg-eyebrow"><span class="material-symbols-rounded">verified_user</span> CMNS Warranty Lookup</span>
    <h1 class="wg-title">Check your device warranty</h1>
    <p class="wg-lead">Enter your warranty number or device Serial Number to instantly view the status and expiry date.</p>

    <form class="wg-search" method="get" autocomplete="off">
      <span class="material-symbols-rounded wg-search-ico">search</span>
      <input type="text" name="q" class="wg-search-input" id="wgSearchInput"
             placeholder="W-2026-0001  or  C02XG0JJMD6T"
             data-ph='["Search W-2026-0001…","or Serial: C02XG0JJMD6T…","Check your device warranty…"]'
             value="<?= h($q) ?>" <?= $hasResult ? '' : 'autofocus' ?> autocomplete="off"
             enterkeyhint="search" aria-label="Warranty number or Serial Number">
      <button type="submit" class="wg-search-btn">
        <span class="material-symbols-rounded">search</span><span>Search</span>
      </button>
      <div class="wg-hp" aria-hidden="true">
        <label>Company<input type="text" name="company" tabindex="-1" autocomplete="off"></label>
      </div>
    </form>

    <div class="wg-examples">
      <span class="wg-ex-label">Examples:</span>
      <code class="wg-ex">W-2026-0001</code>
      <span class="wg-ex-sep">Warranty No.</span>
      <code class="wg-ex">C02XG0JJMD6T</code>
      <span class="wg-ex-sep">Serial Number (under device / Settings)</span>
    </div>

    <div class="wg-trust">
      <span><span class="material-symbols-rounded">workspace_premium</span> Genuine store warranty</span>
      <span><span class="material-symbols-rounded">bolt</span> Instant check</span>
      <span><span class="material-symbols-rounded">lock</span> Secure &amp; private</span>
    </div>
  </div>
</section>

<!-- ═══════════ RESULT ═══════════ -->
<?php if ($hasResult): ?>
<section class="wg-results">
  <div class="wg-results-inner">

    <?php if ($rateLimited): ?>
    <div class="wp-rate">
      <span class="material-symbols-rounded">hourglass_top</span>
      <div><strong>Too many lookups</strong><br><span style="font-size:.85rem;">Please wait a moment and try again.</span></div>
    </div>
    <?php endif; ?>

    <?php if ($errMsg): ?>
    <div class="wp-err">
      <span class="material-symbols-rounded">error_outline</span>
      <div><strong>Not found</strong><br><span style="font-size:.85rem;">Please double-check the warranty number or Serial. If it still doesn't appear, contact the store directly.</span></div>
    </div>
    <?php endif; ?>

    <?php if ($war): ?>
    <div class="wp-result">
      <div class="wp-result-header" style="background:<?= $theme['grad'] ?>;">
        <div class="wp-rh-top">
          <div class="wp-rh-icon"><span class="material-symbols-rounded"><?= $theme['icon'] ?></span></div>
          <div>
            <div class="wp-rh-no"><?= h($war['warranty_no']) ?></div>
            <div class="wp-rh-device"><?= h($war['device_model']) ?></div>
            <div class="wp-rh-label"><?= $theme['label'] ?></div>
          </div>
        </div>
        <div class="wp-days-strip">
          <?php if ($is_active): ?>
            <div><div class="wp-days-big"><?= $days_left ?></div><div class="wp-days-unit">days</div></div>
            <div class="wp-days-info">
              <div style="font-size:.82rem;opacity:.8;">Warranty remaining</div>
              <div class="wp-days-bar-bg"><div class="wp-days-bar" style="width:<?= 100-$pct ?>%;"></div></div>
              <div class="wp-days-dates"><span>Start <?= date('d/m/Y',strtotime($war['start_date'])) ?></span><span>End <?= date('d/m/Y',strtotime($war['end_date'])) ?></span></div>
            </div>
          <?php elseif ($is_voided): ?>
            <div><div class="wp-days-big" style="font-size:1.8rem;">Voided</div></div>
            <div class="wp-days-info">
              <div style="font-size:.82rem;opacity:.8;">This warranty has been voided</div>
              <div style="font-size:.78rem;opacity:.7;margin-top:4px;"><?= date('d/m/Y',strtotime($war['start_date'])) ?> — <?= date('d/m/Y',strtotime($war['end_date'])) ?></div>
            </div>
          <?php else: ?>
            <div><div class="wp-days-big" style="font-size:1.8rem;">Expired</div></div>
            <div class="wp-days-info">
              <div style="font-size:.82rem;opacity:.8;">Expired on <?= date('d/m/Y',strtotime($war['end_date'])) ?></div>
              <div class="wp-days-bar-bg" style="margin-top:8px;"><div class="wp-days-bar" style="width:100%;opacity:.4;"></div></div>
              <div class="wp-days-dates"><span>Start <?= date('d/m/Y',strtotime($war['start_date'])) ?></span><span>End <?= date('d/m/Y',strtotime($war['end_date'])) ?></span></div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="wp-result-body">
        <div class="wp-section-title"><span class="material-symbols-rounded" style="font-size:15px;">info</span>Details</div>
        <div class="wp-info-grid">
          <div class="wp-info-row"><span class="wp-info-label">Customer</span><span class="wp-info-val"><?= h($war['customer_name']) ?></span></div>
          <?php if ($war['customer_phone']): ?>
          <div class="wp-info-row"><span class="wp-info-label">Phone</span><span class="wp-info-val"><?= h(mask_phone($war['customer_phone'])) ?></span></div>
          <?php endif; ?>
          <?php if ($war['serial_no']): ?>
          <div class="wp-info-row"><span class="wp-info-label">Serial Number</span><span class="wp-info-val" style="font-family:monospace;"><?= h($war['serial_no']) ?></span></div>
          <?php endif; ?>
          <div class="wp-info-row"><span class="wp-info-label">Warranty period</span><span class="wp-info-val"><?= $war['warranty_days'] ?> days</span></div>
          <?php if ($war['repair_summary']): ?>
          <div class="wp-info-row full"><span class="wp-info-label">Repair summary</span><span class="wp-info-val" style="white-space:pre-line;"><?= h($war['repair_summary']) ?></span></div>
          <?php endif; ?>
        </div>

        <?php if ($is_voided && $war['void_reason']): ?>
        <div class="wp-void-box">
          <span class="material-symbols-rounded" style="color:#dc2626;flex-shrink:0;">block</span>
          <div><div style="font-weight:700;font-size:.88rem;color:#991b1b;margin-bottom:2px;">Reason for voiding</div><div style="font-size:.85rem;color:var(--text-primary);"><?= h($war['void_reason']) ?></div></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($claims)): ?>
        <div style="margin-top:20px;padding-top:20px;border-top:1px solid var(--border);">
          <div class="wp-section-title"><span class="material-symbols-rounded" style="font-size:15px;">history</span>Claim history (<?= count($claims) ?>)</div>
          <?php foreach ($claims as $c): ?>
          <div class="wp-claim">
            <div class="wp-claim-header">
              <span class="wp-claim-no"><?= h($c['claim_no']) ?></span>
              <span class="wp-claim-date"><?= date('d/m/Y',strtotime($c['claim_date'])) ?></span>
              <span class="wp-claim-badge <?= $c['status'] ?>"><?= $c['status']==='resolved'?'Resolved':'Rejected' ?></span>
            </div>
            <?php if ($c['issue_desc']): ?><div class="wp-claim-issue"><?= h($c['issue_desc']) ?></div><?php endif; ?>
            <?php if ($c['resolution'] && $c['status']==='resolved'): ?>
            <div class="wp-claim-resolution"><strong>Result:</strong> <?= h($c['resolution']) ?></div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($is_active): ?>
        <div class="wp-cta">
          <a href="https://line.me/R/ti/p/@cmns" class="wp-cta-btn wp-cta-primary" target="_blank" rel="noopener">
            <span class="material-symbols-rounded">chat</span> Claim via LINE
          </a>
          <a href="tel:0841511684" class="wp-cta-btn wp-cta-secondary">
            <span class="material-symbols-rounded">call</span> Call the store
          </a>
        </div>
        <?php endif; ?>

        <div class="wp-store-badge">
          <div class="wp-store-logo"><span class="material-symbols-rounded">storefront</span></div>
          <div><div class="wp-store-name">CMNS Fix Mac</div><div class="wp-store-sub">Mac &amp; iPhone repair center — cmnsfixmac.com</div></div>
          <a href="/en/" class="wp-store-link">Visit website →</a>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div>
</section>
<?php endif; ?>

<!-- ═══════════ CLAIM STEPS ═══════════ -->
<section class="wg-steps">
  <div class="wg-section-inner">
    <div class="wg-section-head">
      <span class="wg-section-label">How to claim</span>
      <h2>Easy claims in 3 steps</h2>
    </div>
    <div class="wg-flow">
      <div class="wg-flow-line" aria-hidden="true"><span class="wg-flow-progress"></span></div>
      <div class="wg-flow-step">
        <div class="wg-flow-dot"><span class="material-symbols-rounded">chat</span></div>
        <div class="wg-flow-text">
          <span class="wg-flow-k">Step 1</span>
          <h3>Report it</h3>
          <p>Message us on LINE or call the store. Describe the issue and provide your warranty number or Serial.</p>
        </div>
      </div>
      <div class="wg-flow-step">
        <div class="wg-flow-dot"><span class="material-symbols-rounded">build</span></div>
        <div class="wg-flow-text">
          <span class="wg-flow-k">Step 2</span>
          <h3>Inspection</h3>
          <p>Drop off or send the device. Our technician assesses the issue and confirms warranty coverage.</p>
        </div>
      </div>
      <div class="wg-flow-step">
        <div class="wg-flow-dot"><span class="material-symbols-rounded">verified</span></div>
        <div class="wg-flow-text">
          <span class="wg-flow-k">Step 3</span>
          <h3>Repair &amp; return</h3>
          <p>We repair or replace parts under warranty, then arrange the return — no service fee.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════ FAQ ═══════════ -->
<section class="wg-faq">
  <div class="wg-section-inner wg-faq-inner">
    <div class="wg-section-head">
      <span class="wg-section-label">FAQ</span>
      <h2>About the store warranty</h2>
    </div>
    <div class="wg-faq-list">
      <?php foreach ([
        ['What does the warranty cover?', 'It covers faults from the repair work we performed or the parts we replaced, within the warranty period stated on your warranty.'],
        ['What is not covered?', 'Damage from drops, liquid, self-repair, or repairs done elsewhere after pickup — as well as issues unrelated to the original repair.'],
        ['What do I need to claim?', 'Just your warranty number (W-YYYY-XXXX) or device Serial Number, plus a description of the issue. No paper receipt required.'],
        ['How long does a claim take?', 'It depends on the issue and parts. Most can be assessed on the spot; typical repairs are done within 1–3 business days.'],
      ] as $i => [$qq, $aa]): ?>
      <details class="wg-faq-item"<?= $i === 0 ? ' open' : '' ?>>
        <summary><span><?= $qq ?></span><span class="material-symbols-rounded wg-faq-chev">expand_more</span></summary>
        <div class="wg-faq-ans"><?= $aa ?></div>
      </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php include '../../includes/footer_en.php'; ?>

<script>
(function () {
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* Typewriter placeholder */
  var input = document.getElementById('wgSearchInput');
  if (input && !reduce && !input.value) {
    var phrases = [];
    try { phrases = JSON.parse(input.getAttribute('data-ph') || '[]'); } catch (e) {}
    if (phrases.length) {
      var pi = 0, ci = 0, deleting = false, active = true, base = '';
      input.addEventListener('focus', function () { active = false; input.setAttribute('placeholder', base); });
      function tick() {
        if (!active || input.value) { return; }
        var word = phrases[pi];
        ci += deleting ? -1 : 1;
        input.setAttribute('placeholder', word.slice(0, ci) + ' |');
        var delay = deleting ? 35 : 70;
        if (!deleting && ci === word.length) { deleting = true; delay = 1500; }
        else if (deleting && ci === 0) { deleting = false; pi = (pi + 1) % phrases.length; delay = 350; }
        setTimeout(tick, delay);
      }
      setTimeout(tick, 700);
    }
  }
})();
</script>

</body>
</html>
