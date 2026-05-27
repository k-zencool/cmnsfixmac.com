<?php
date_default_timezone_set('Asia/Bangkok');
require_once '../includes/db.php';

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

if ($q !== '') {
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
        $errMsg = 'ไม่พบข้อมูลประกัน';
    }
}

$days_left = $war ? (int)ceil((strtotime($war['end_date']) - time()) / 86400) : 0;
$page_title = 'ตรวจสอบประกัน — CMNS Fix Mac';
$switch_to_lang_url = '/en/warranty/' . ($q ? '?q=' . urlencode($q) : '');

if ($war) {
    $total_d   = $war['warranty_days'];
    $used_d    = max(0, min((int)ceil((time() - strtotime($war['start_date'])) / 86400), $total_d));
    $pct       = $total_d > 0 ? round(($used_d / $total_d) * 100) : 100;
    $is_active = $war['status'] === 'active' && $days_left > 0;
    $is_voided = $war['status'] === 'voided';
    if ($is_active) {
        $theme    = ['grad'=>'linear-gradient(135deg,#065f46 0%,#059669 100%)','icon'=>'verified','label'=>'ประกันยังไม่หมดอายุ'];
        $days_cls = $days_left > 30 ? 'ok' : 'warn';
    } elseif ($is_voided) {
        $theme    = ['grad'=>'linear-gradient(135deg,#7f1d1d 0%,#dc2626 100%)','icon'=>'block','label'=>'ประกันถูกยกเลิก'];
        $days_cls = 'over';
    } else {
        $theme    = ['grad'=>'linear-gradient(135deg,#374151 0%,#6b7280 100%)','icon'=>'schedule','label'=>'ประกันหมดอายุแล้ว'];
        $days_cls = 'over';
    }
}

$page_css = [];
$page_head_extra = <<<'STYLE'
<style>
/* ── Page ── */
.wp-page  { min-height:78vh; padding:100px 20px 80px; }
.wp-inner { max-width:660px; margin:0 auto; }

/* ── Hero ── */
.wp-hero { text-align:center; margin-bottom:36px; }
.wp-hero-icon { width:72px; height:72px; border-radius:20px; background:linear-gradient(135deg,#1e40af,#2563eb); display:flex; align-items:center; justify-content:center; margin:0 auto 18px; box-shadow:0 8px 24px rgba(37,99,235,.35); }
.wp-hero-icon .material-symbols-rounded { font-size:36px; color:#fff; }
.wp-hero h1 { font-size:1.9rem; font-weight:900; margin:0 0 8px; letter-spacing:-.5px; color:var(--text-primary); }
.wp-hero p  { color:var(--text-secondary); font-size:0.95rem; margin:0; }

/* ── Search ── */
.wp-search-card { background:var(--bg-surface); border:1.5px solid var(--border); border-radius:20px; padding:24px; box-shadow:0 4px 20px rgba(0,0,0,.07); margin-bottom:28px; }
.wp-search-row  { display:flex; gap:10px; }
.wp-search-wrap { flex:1; position:relative; }
.wp-search-wrap .material-symbols-rounded { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--text-tertiary); font-size:22px; pointer-events:none; }
.wp-search-input { width:100%; padding:14px 14px 14px 46px; border:2px solid #e5e7eb; border-radius:12px; font-size:0.97rem; outline:none; transition:.2s; background:var(--bg-surface-alt); color:var(--text-primary); font-family:inherit; }
.wp-search-input:focus { border-color:#2563eb; background:var(--bg-surface); box-shadow:0 0 0 4px rgba(37,99,235,.1); }
.wp-search-btn { padding:0 22px; background:#2563eb; color:#fff; border:none; border-radius:12px; font-size:0.95rem; font-weight:700; cursor:pointer; transition:.15s; white-space:nowrap; display:flex; align-items:center; gap:6px; }
.wp-search-btn:hover { background:#1d4ed8; transform:translateY(-1px); }
.wp-hint { text-align:center; font-size:0.8rem; color:var(--text-tertiary); margin-top:12px; }

/* ── Error ── */
.wp-err { background:#fef2f2; border:1px solid #fecaca; border-radius:12px; padding:14px 18px; color:#dc2626; font-size:0.9rem; margin-bottom:20px; display:flex; align-items:center; gap:10px; }

/* ── Empty ── */
.wp-empty { text-align:center; padding:32px 0; color:var(--text-tertiary); }
.wp-empty .material-symbols-rounded { font-size:56px; opacity:.3; display:block; margin-bottom:10px; }

/* ── Result ── */
.wp-result { border-radius:20px; overflow:hidden; box-shadow:0 8px 32px rgba(0,0,0,.12); margin-bottom:20px; }
.wp-result-header { padding:28px; color:#fff; position:relative; overflow:hidden; }
.wp-result-header::before { content:''; position:absolute; right:-30px; top:-30px; width:180px; height:180px; border-radius:50%; background:rgba(255,255,255,.07); }
.wp-result-header::after  { content:''; position:absolute; right:40px; bottom:-50px; width:120px; height:120px; border-radius:50%; background:rgba(255,255,255,.05); }
.wp-rh-top    { display:flex; align-items:center; gap:14px; margin-bottom:20px; position:relative; z-index:1; }
.wp-rh-icon   { width:52px; height:52px; border-radius:14px; background:rgba(255,255,255,.2); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.wp-rh-icon .material-symbols-rounded { font-size:28px; }
.wp-rh-no     { font-size:0.82rem; font-family:monospace; opacity:.8; letter-spacing:.5px; }
.wp-rh-device { font-size:1.25rem; font-weight:800; line-height:1.2; }
.wp-rh-label  { font-size:0.85rem; opacity:.8; margin-top:2px; }
.wp-days-strip { display:flex; align-items:center; gap:20px; position:relative; z-index:1; background:rgba(0,0,0,.15); border-radius:14px; padding:16px 20px; }
.wp-days-big   { font-size:3rem; font-weight:900; line-height:1; }
.wp-days-unit  { font-size:0.9rem; opacity:.8; }
.wp-days-info  { flex:1; }
.wp-days-bar-bg { background:rgba(255,255,255,.2); border-radius:20px; height:6px; margin:8px 0 4px; overflow:hidden; }
.wp-days-bar    { height:100%; border-radius:20px; background:rgba(255,255,255,.9); }
.wp-days-dates  { display:flex; justify-content:space-between; font-size:0.75rem; opacity:.75; }

/* ── Body ── */
.wp-result-body { background:var(--bg-surface); padding:24px 28px; }
.wp-section-title { font-size:0.72rem; font-weight:800; text-transform:uppercase; letter-spacing:.6px; color:var(--text-tertiary); margin-bottom:12px; display:flex; align-items:center; gap:6px; }
.wp-info-grid { display:grid; grid-template-columns:1fr 1fr; gap:0; margin-bottom:20px; }
.wp-info-row  { padding:10px 0; border-bottom:1px solid var(--border); display:flex; flex-direction:column; gap:2px; }
.wp-info-row:nth-child(odd)  { padding-right:20px; }
.wp-info-row:nth-child(even) { padding-left:20px; border-left:1px solid var(--border); }
.wp-info-row.full { grid-column:1/-1; padding-right:0; padding-left:0; border-left:none; }
.wp-info-label { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:var(--text-tertiary); }
.wp-info-val   { font-size:0.93rem; font-weight:600; color:var(--text-primary); }

/* ── Claims ── */
.wp-claim { background:var(--bg-surface-alt); border:1px solid var(--border); border-radius:12px; padding:14px 16px; margin-bottom:8px; }
.wp-claim-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; gap:8px; flex-wrap:wrap; }
.wp-claim-no   { font-size:0.78rem; font-family:monospace; font-weight:700; color:var(--text-secondary); }
.wp-claim-date { font-size:0.78rem; color:var(--text-tertiary); }
.wp-claim-badge { font-size:0.72rem; font-weight:700; padding:2px 8px; border-radius:20px; }
.wp-claim-badge.resolved { background:#d1fae5; color:#065f46; }
.wp-claim-badge.rejected { background:#fee2e2; color:#991b1b; }
.wp-claim-issue { font-size:0.84rem; color:var(--text-primary); }
.wp-claim-resolution { margin-top:8px; padding:8px 12px; background:var(--bg-surface); border-left:3px solid #10b981; border-radius:4px; font-size:0.82rem; color:var(--text-primary); }

/* ── CTA ── */
.wp-cta { display:flex; gap:10px; margin-top:20px; flex-wrap:wrap; }
.wp-cta-btn { flex:1; min-width:140px; padding:13px 16px; border-radius:12px; border:none; font-size:0.9rem; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; text-decoration:none; transition:.15s; }
.wp-cta-primary  { background:#059669; color:#fff; }
.wp-cta-primary:hover { background:#047857; transform:translateY(-1px); }
.wp-cta-secondary { background:#f3f4f6; color:var(--text-primary); border:1px solid var(--border); }
.wp-cta-secondary:hover { background:#e5e7eb; }

/* ── Store badge ── */
.wp-store-badge { display:flex; align-items:center; gap:12px; padding:14px 18px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; margin-top:16px; }
.wp-store-logo  { width:40px; height:40px; border-radius:10px; background:linear-gradient(135deg,#1e3a5f,#2563eb); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.wp-store-logo .material-symbols-outlined { font-size:20px; color:#fff; }
.wp-store-logo .material-symbols-rounded { font-size:20px; color:#fff; }
.wp-store-name  { font-weight:800; font-size:0.9rem; color:var(--text-primary); }
.wp-store-sub   { font-size:0.78rem; color:var(--text-secondary); }
.wp-void-box    { background:#fef2f2; border:1px solid #fecaca; border-radius:12px; padding:14px 18px; margin-top:16px; display:flex; gap:10px; align-items:flex-start; }

@media(max-width:520px){
    .wp-info-grid { grid-template-columns:1fr; }
    .wp-info-row:nth-child(even) { padding-left:0; border-left:none; }
    .wp-info-row:nth-child(odd)  { padding-right:0; }
    .wp-days-big { font-size:2.2rem; }
    .wp-search-btn span:last-child { display:none; }
}
</style>
STYLE;
include '../includes/header.php';
?>

<section class="wp-page">
<div class="wp-inner">

    <div class="wp-hero">
        <div class="wp-hero-icon">
            <span class="material-symbols-rounded">verified_user</span>
        </div>
        <h1>ตรวจสอบประกัน</h1>
        <p>ค้นหาด้วยเลขใบประกัน (W-YYYY-XXXX) หรือ Serial Number ของเครื่อง</p>
    </div>

    <div class="wp-search-card">
        <form method="get">
            <div class="wp-search-row">
                <div class="wp-search-wrap">
                    <span class="material-symbols-rounded">search</span>
                    <input type="text" name="q" class="wp-search-input"
                           placeholder="W-2026-0001  หรือ  C02XG0JJMD6T"
                           value="<?= h($q) ?>" autofocus autocomplete="off">
                </div>
                <button type="submit" class="wp-search-btn">
                    <span class="material-symbols-rounded">search</span>
                    <span>ค้นหา</span>
                </button>
            </div>
        </form>
        <div class="wp-hint">ค้นหาด้วยเลขประกัน หรือ Serial Number เท่านั้น</div>
    </div>

    <?php if ($errMsg): ?>
    <div class="wp-err">
        <span class="material-symbols-rounded" style="flex-shrink:0;">error_outline</span>
        <div><strong>ไม่พบข้อมูล</strong><br><span style="font-size:.85rem;">ตรวจสอบเลขประกันหรือ Serial อีกครั้ง หากยังไม่พบกรุณาติดต่อร้านโดยตรง</span></div>
    </div>
    <?php endif; ?>

    <?php if (!$q): ?>
    <div class="wp-empty">
        <span class="material-symbols-rounded">manage_search</span>
        กรอกเลขประกันหรือ Serial Number ด้านบนเพื่อตรวจสอบ
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
                    <div><div class="wp-days-big"><?= $days_left ?></div><div class="wp-days-unit">วัน</div></div>
                    <div class="wp-days-info">
                        <div style="font-size:.82rem;opacity:.8;">ประกันเหลืออีก</div>
                        <div class="wp-days-bar-bg"><div class="wp-days-bar" style="width:<?= 100-$pct ?>%;"></div></div>
                        <div class="wp-days-dates"><span>เริ่ม <?= date('d/m/Y',strtotime($war['start_date'])) ?></span><span>หมด <?= date('d/m/Y',strtotime($war['end_date'])) ?></span></div>
                    </div>
                <?php elseif ($is_voided): ?>
                    <div><div class="wp-days-big" style="font-size:1.8rem;">ยกเลิก</div></div>
                    <div class="wp-days-info">
                        <div style="font-size:.82rem;opacity:.8;">ใบประกันถูกยกเลิก</div>
                        <div style="font-size:.78rem;opacity:.7;margin-top:4px;"><?= date('d/m/Y',strtotime($war['start_date'])) ?> — <?= date('d/m/Y',strtotime($war['end_date'])) ?></div>
                    </div>
                <?php else: ?>
                    <div><div class="wp-days-big" style="font-size:1.8rem;">หมดอายุ</div></div>
                    <div class="wp-days-info">
                        <div style="font-size:.82rem;opacity:.8;">หมดอายุเมื่อ <?= date('d/m/Y',strtotime($war['end_date'])) ?></div>
                        <div class="wp-days-bar-bg" style="margin-top:8px;"><div class="wp-days-bar" style="width:100%;opacity:.4;"></div></div>
                        <div class="wp-days-dates"><span>เริ่ม <?= date('d/m/Y',strtotime($war['start_date'])) ?></span><span>หมด <?= date('d/m/Y',strtotime($war['end_date'])) ?></span></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="wp-result-body">
            <div class="wp-section-title"><span class="material-symbols-rounded" style="font-size:15px;">info</span>รายละเอียด</div>
            <div class="wp-info-grid">
                <div class="wp-info-row"><span class="wp-info-label">ชื่อลูกค้า</span><span class="wp-info-val"><?= h($war['customer_name']) ?></span></div>
                <?php if ($war['customer_phone']): ?>
                <div class="wp-info-row"><span class="wp-info-label">เบอร์ติดต่อ</span><span class="wp-info-val"><?= h(mask_phone($war['customer_phone'])) ?></span></div>
                <?php endif; ?>
                <?php if ($war['serial_no']): ?>
                <div class="wp-info-row"><span class="wp-info-label">Serial Number</span><span class="wp-info-val" style="font-family:monospace;"><?= h($war['serial_no']) ?></span></div>
                <?php endif; ?>
                <div class="wp-info-row"><span class="wp-info-label">ระยะประกัน</span><span class="wp-info-val"><?= $war['warranty_days'] ?> วัน</span></div>
                <?php if ($war['repair_summary']): ?>
                <div class="wp-info-row full"><span class="wp-info-label">งานที่ซ่อม</span><span class="wp-info-val" style="white-space:pre-line;"><?= h($war['repair_summary']) ?></span></div>
                <?php endif; ?>
            </div>

            <?php if ($is_voided && $war['void_reason']): ?>
            <div class="wp-void-box">
                <span class="material-symbols-rounded" style="color:#dc2626;flex-shrink:0;">block</span>
                <div><div style="font-weight:700;font-size:.88rem;color:#991b1b;margin-bottom:2px;">เหตุผลยกเลิก</div><div style="font-size:.85rem;color:var(--text-primary);"><?= h($war['void_reason']) ?></div></div>
            </div>
            <?php endif; ?>

            <?php if (!empty($claims)): ?>
            <div style="margin-top:20px;padding-top:20px;border-top:1px solid #f3f4f6;">
                <div class="wp-section-title"><span class="material-symbols-rounded" style="font-size:15px;">history</span>ประวัติการเคลม (<?= count($claims) ?>)</div>
                <?php foreach ($claims as $c): ?>
                <div class="wp-claim">
                    <div class="wp-claim-header">
                        <span class="wp-claim-no"><?= h($c['claim_no']) ?></span>
                        <span class="wp-claim-date"><?= date('d/m/Y',strtotime($c['claim_date'])) ?></span>
                        <span class="wp-claim-badge <?= $c['status'] ?>"><?= $c['status']==='resolved'?'แก้ไขแล้ว':'ปฏิเสธ' ?></span>
                    </div>
                    <?php if ($c['issue_desc']): ?><div class="wp-claim-issue"><?= h($c['issue_desc']) ?></div><?php endif; ?>
                    <?php if ($c['resolution'] && $c['status']==='resolved'): ?>
                    <div class="wp-claim-resolution"><strong>ผล:</strong> <?= h($c['resolution']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($is_active): ?>
            <div class="wp-cta">
                <a href="https://line.me/R/ti/p/@cmns" class="wp-cta-btn wp-cta-primary" target="_blank" rel="noopener">
                    <span class="material-symbols-rounded">chat</span> แจ้งเคลมผ่าน LINE
                </a>
                <a href="tel:0841511684" class="wp-cta-btn wp-cta-secondary">
                    <span class="material-symbols-rounded">call</span> โทรหาร้าน
                </a>
            </div>
            <?php endif; ?>

            <div class="wp-store-badge">
                <div class="wp-store-logo"><span class="material-symbols-rounded">storefront</span></div>
                <div><div class="wp-store-name">CMNS Fix Mac</div><div class="wp-store-sub">ศูนย์ซ่อม Mac & iPhone — cmnsfixmac.com</div></div>
                <a href="/" style="margin-left:auto;font-size:.82rem;color:#2563eb;text-decoration:none;white-space:nowrap;">เว็บไซต์ร้าน →</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>
</section>

<?php include '../includes/footer.php'; ?>

</body>
</html>
