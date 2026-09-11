<?php
session_start();
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/warranty_lib.php';
require_login();

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

w_sync_expired($pdo);

$w = $pdo->prepare("SELECT w.*, t.ticket_number FROM warranties w LEFT JOIN tracking t ON t.id = w.tracking_id WHERE w.id = ?");
$w->execute([$id]);
$war = $w->fetch(PDO::FETCH_ASSOC);
if (!$war) { header('Location: index.php'); exit; }

$claims = $pdo->prepare("SELECT c.*, u.username AS handler_name
                          FROM warranty_claims c
                          LEFT JOIN admin_users u ON u.id = c.handled_by
                          WHERE c.warranty_id = ? ORDER BY c.claim_date DESC, c.id DESC");
$claims->execute([$id]);
$claims = $claims->fetchAll(PDO::FETCH_ASSOC);

$days_left = w_days_left($war['end_date']);
$pageTitle = "ใบประกัน " . $war['warranty_no'];

$flash = $_SESSION['success'] ?? null;
unset($_SESSION['success']);

include __DIR__ . '/../templates/header_admin.php';
?>
<link rel="stylesheet" href="<?= $assets_base ?>css/inventory-dashboard.css?v=<?= asset_ver('/admin/templates/assets/css/inventory-dashboard.css') ?>">
<link rel="stylesheet" href="<?= $assets_base ?>css/modal.css?v=<?= asset_ver('/admin/templates/assets/css/modal.css') ?>">
<link rel="stylesheet" href="assets/css/warranty-mobile.css?v=<?= asset_ver('/admin/warranty/assets/css/warranty-mobile.css') ?>">
<style>
/* ── shared form components ── */
.cmns-label { font-size:11px; font-weight:800; color:var(--text-muted); margin-bottom:6px; display:block; text-transform:uppercase; letter-spacing:.5px; }
.cmns-input { width:100%; background:var(--bg-surface-alt); border:1px solid var(--border); color:var(--text-main); padding:11px 13px; border-radius:10px; font-size:13px; outline:none; transition:all .2s; font-family:inherit; }
.cmns-input:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(37,99,235,.1); background:var(--bg-surface); }
textarea.cmns-input { resize:vertical; min-height:72px; }
.cmns-alert { display:flex; align-items:center; gap:10px; padding:10px 16px; border-radius:10px; font-size:.88rem; margin-bottom:14px; }
.cmns-alert-success { background:rgba(16,185,129,.1); color:#065f46; border:1px solid rgba(16,185,129,.3); }
/* ── view layout ── */
.view-wrap { display:grid; grid-template-columns:1fr 360px; gap:20px; align-items:start; }
.war-card { background:var(--bg-surface); border:1px solid var(--border); border-radius:14px; padding:24px; margin-bottom:16px; }
.war-card-title { font-size:0.8rem; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--text-muted); margin-bottom:16px; display:flex; align-items:center; gap:8px; }
.detail-row { display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid var(--border); gap:12px; }
.detail-row:last-child { border-bottom:none; }
.detail-label { font-size:0.82rem; color:var(--text-muted); flex-shrink:0; }
.detail-val   { font-size:0.88rem; font-weight:600; color:var(--text-main); text-align:right; }
.status-badge { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:20px; font-size:0.78rem; font-weight:600; border:1px solid transparent; }
.war-no-big { font-size:1.6rem; font-weight:800; font-family:monospace; color:var(--primary); letter-spacing:1px; }
.claim-item { background:var(--bg-surface-alt); border:1px solid var(--border); border-radius:10px; padding:14px 16px; margin-bottom:10px; }
.claim-item:last-child { margin-bottom:0; }
.claim-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; flex-wrap:wrap; gap:8px; }
.claim-no { font-weight:700; font-family:monospace; font-size:0.85rem; color:var(--primary); }
.progress-bar-wrap { background:var(--border); border-radius:20px; height:8px; overflow:hidden; margin:12px 0 4px; }
.progress-bar { height:100%; border-radius:20px; transition:width .4s; }
@media(max-width:900px){ .view-wrap { grid-template-columns:1fr; } }
</style>

<div class="main-content">
<?php if ($flash): ?>
    <div class="cmns-alert cmns-alert-success" style="margin-bottom:16px;">
        <span class="material-symbols-rounded">check_circle</span> <?= h($flash) ?>
    </div>
<?php endif; ?>

<?php
/* ── MOBILE (<992px) ──────────────────────────────────────────────
   The desktop header + .view-wrap below carry .wv-d and switch off at
   that width; .wv-m switches on. Same data, two layouts — see
   warranty-mobile.css. The claim / edit / void sheets are shared. */
$m_st    = $war['status'];
$m_total = (int)$war['warranty_days'];
$m_used  = max(0, min((int)ceil((time() - strtotime($war['start_date'])) / 86400), $m_total));
$m_pct   = $m_total > 0 ? (int)round($m_used / $m_total * 100) : 100;
$m_label = ['active' => 'ใช้งานได้', 'expired' => 'หมดอายุ', 'voided' => 'ยกเลิก'][$m_st] ?? $m_st;
// Still 'active' with 0 days left = the end date is today (w_sync_expired only
// flips it tomorrow), so that is the last day, not "expired" under an active pill
$m_live  = $m_st === 'active';
$m_soon  = $m_live && $days_left <= 30;
$m_phone = preg_replace('/[^0-9+]/', '', $war['customer_phone'] ?? '');
?>
<div class="wv-m">

    <!-- Hero: the one number that matters — days left — on a slab whose
         colour is the status (blue live / slate expired / red voided) -->
    <section class="wv-hero st-<?= h($m_st) ?><?= $m_soon ? ' is-soon' : '' ?>">
        <div class="wv-hero-top">
            <span class="wv-hero-no"><?= h($war['warranty_no']) ?></span>
            <span class="wv-hero-pill"><?= h($m_label) ?></span>
        </div>
        <?php if ($m_live && $days_left > 0): ?>
            <div class="wv-hero-fig"><b><?= $days_left ?></b><span>วันที่เหลือ</span></div>
        <?php elseif ($m_live): ?>
            <div class="wv-hero-fig is-word"><b>วันสุดท้าย</b></div>
        <?php else: ?>
            <div class="wv-hero-fig is-word"><b><?= $m_st === 'voided' ? 'ยกเลิกแล้ว' : 'หมดประกันแล้ว' ?></b></div>
        <?php endif; ?>
        <div class="wv-hero-bar" role="img" aria-label="ใช้ไป <?= $m_pct ?>%"><i style="width:<?= $m_pct ?>%"></i></div>
        <div class="wv-hero-dates">
            <span><?= date('d/m/y', strtotime($war['start_date'])) ?> – <?= date('d/m/y', strtotime($war['end_date'])) ?></span>
            <span><?= $m_total ?> วัน · ใช้ไป <?= $m_pct ?>%</span>
        </div>
    </section>

    <!-- One row instead of four wrapping buttons; the claim button lives
         with the claims below -->
    <div class="wv-actions">
        <a href="print.php?id=<?= $id ?>" target="_blank" class="wv-act">
            <span class="material-symbols-rounded">print</span>พิมพ์
        </a>
        <?php if (can('content.write')): ?>
        <a href="edit.php?id=<?= $id ?>" class="wv-act" onclick="showGlobalLoader()">
            <span class="material-symbols-rounded">edit</span>แก้ไข
        </a>
        <?php endif; ?>
        <?php if ($m_st === 'active'): ?>
        <button type="button" class="wv-act is-danger" onclick="confirmVoid()">
            <span class="material-symbols-rounded">block</span>ยกเลิกประกัน
        </button>
        <?php endif; ?>
    </div>

    <section class="wv-card">
        <h3 class="wv-card-t">ลูกค้าและเครื่อง</h3>
        <div class="wv-row"><span>ลูกค้า</span><b><?= h($war['customer_name']) ?></b></div>
        <?php if ($m_phone !== ''): ?>
        <div class="wv-row"><span>เบอร์โทร</span><a href="tel:<?= h($m_phone) ?>"><?= h($war['customer_phone']) ?></a></div>
        <?php endif; ?>
        <div class="wv-row"><span>เครื่อง</span><b><?= h($war['device_model']) ?></b></div>
        <?php if ($war['serial_no']): ?>
        <div class="wv-row"><span>Serial</span><b class="wv-mono"><?= h($war['serial_no']) ?></b></div>
        <?php endif; ?>
        <?php if ($war['ticket_number']): ?>
        <!-- same tab: target=_blank would throw the PWA out to Safari -->
        <div class="wv-row"><span>งานซ่อม</span><a href="../tracking/edit.php?id=<?= (int)$war['tracking_id'] ?>" onclick="showGlobalLoader()"><?= h($war['ticket_number']) ?></a></div>
        <?php endif; ?>
        <?php if ($war['repair_summary']): ?>
        <div class="wv-sum"><span>สรุปงานซ่อม</span><p><?= h($war['repair_summary']) ?></p></div>
        <?php endif; ?>
    </section>

    <?php if ($war['void_reason']): ?>
    <section class="wv-card wv-void">
        <h3 class="wv-card-t">เหตุผลยกเลิก</h3>
        <p><?= h($war['void_reason']) ?></p>
    </section>
    <?php endif; ?>

    <section class="wv-card">
        <h3 class="wv-card-t">การเคลม <span class="wv-count"><?= count($claims) ?></span></h3>
        <?php if (empty($claims)): ?>
            <p class="wv-empty">ยังไม่มีการเคลม</p>
        <?php else: foreach ($claims as $c): ?>
            <div class="wv-claim">
                <div class="wv-claim-hd">
                    <span class="wv-claim-no"><?= h($c['claim_no']) ?></span>
                    <?= w_claim_badge($c['status']) ?>
                </div>
                <div class="wv-claim-date"><?= date('d/m/Y', strtotime($c['claim_date'])) ?></div>
                <?php if ($c['issue_desc']): ?><p class="wv-claim-p"><?= h($c['issue_desc']) ?></p><?php endif; ?>
                <?php if ($c['resolution']): ?><p class="wv-claim-res"><?= h($c['resolution']) ?></p><?php endif; ?>
                <button type="button" class="wv-claim-edit" aria-label="แก้ไขการเคลม"
                        onclick="editClaim(<?= (int)$c['id'] ?>, <?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)">
                    <span class="material-symbols-rounded">edit</span>
                </button>
            </div>
        <?php endforeach; endif; ?>
        <?php if ($m_st !== 'voided'): ?>
        <button type="button" class="wv-add" onclick="openClaimModal()">
            <span class="material-symbols-rounded">add_circle</span> บันทึกการเคลม
        </button>
        <?php endif; ?>
    </section>

    <section class="wv-card wv-qr">
        <h3 class="wv-card-t">QR เช็คประกัน</h3>
        <canvas id="qr-canvas-m"></canvas>
    </section>
</div><!-- .wv-m -->

<!-- Header -->
<div class="wv-d" style="display:flex; align-items:center; gap:12px; margin-bottom:20px; flex-wrap:wrap;">
    <a href="index.php" class="cmns-btn cmns-btn-secondary" style="padding:8px 12px;">
        <span class="material-symbols-rounded">arrow_back</span>
    </a>
    <div class="war-no-big"><?= h($war['warranty_no']) ?></div>
    <?= w_status_badge($war['status']) ?>
    <div style="margin-left:auto; display:flex; gap:8px; flex-wrap:wrap;">
        <?php if (can('content.write')): ?>
        <a href="edit.php?id=<?= $id ?>" class="cmns-btn cmns-btn-secondary">
            <span class="material-symbols-rounded">edit</span> แก้ไขข้อมูล
        </a>
        <?php endif; ?>
        <a href="print.php?id=<?= $id ?>" target="_blank" class="cmns-btn cmns-btn-secondary">
            <span class="material-symbols-rounded">print</span> พิมพ์ใบประกัน
        </a>
        <?php if ($war['status'] !== 'voided'): ?>
        <button class="cmns-btn cmns-btn-secondary" onclick="openClaimModal()">
            <span class="material-symbols-rounded">report_problem</span> บันทึกการเคลม
        </button>
        <?php endif; ?>
        <?php if ($war['status'] === 'active'): ?>
        <button class="cmns-btn" style="background:rgba(239,68,68,.1);color:#dc2626;border:1px solid rgba(239,68,68,.3);" onclick="confirmVoid()">
            <span class="material-symbols-rounded">block</span> ยกเลิกประกัน
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="view-wrap wv-d">
<!-- Left: Details + Claims -->
<div>
    <div class="war-card">
        <div class="war-card-title"><span class="material-symbols-rounded">person</span> ข้อมูลลูกค้าและเครื่อง</div>
        <div class="detail-row"><span class="detail-label">ลูกค้า</span><span class="detail-val"><?= h($war['customer_name']) ?></span></div>
        <div class="detail-row"><span class="detail-label">เบอร์โทร</span><span class="detail-val"><?= $war['customer_phone'] ? h($war['customer_phone']) : '-' ?></span></div>
        <div class="detail-row"><span class="detail-label">เครื่อง</span><span class="detail-val"><?= h($war['device_model']) ?></span></div>
        <div class="detail-row"><span class="detail-label">Serial</span><span class="detail-val" style="font-family:monospace;"><?= $war['serial_no'] ? h($war['serial_no']) : '-' ?></span></div>
        <?php if ($war['ticket_number']): ?>
        <div class="detail-row">
            <span class="detail-label">งานซ่อม</span>
            <span class="detail-val">
                <a href="../tracking/edit.php?id=<?= $war['tracking_id'] ?>" target="_blank" style="color:var(--primary);">
                    <?= h($war['ticket_number']) ?>
                </a>
            </span>
        </div>
        <?php endif; ?>
        <?php if ($war['repair_summary']): ?>
        <div class="detail-row" style="flex-direction:column; gap:6px; align-items:flex-start;">
            <span class="detail-label">สรุปงานซ่อม</span>
            <span class="detail-val" style="text-align:left; white-space:pre-line;"><?= h($war['repair_summary']) ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Claims -->
    <div class="war-card">
        <div class="war-card-title"><span class="material-symbols-rounded">report_problem</span> ประวัติการเคลม (<?= count($claims) ?>)</div>
        <?php if (empty($claims)): ?>
            <p style="color:var(--text-muted); font-size:0.88rem; text-align:center; padding:16px 0;">ยังไม่มีการเคลม</p>
        <?php else: foreach ($claims as $c): ?>
        <div class="claim-item">
            <div class="claim-header">
                <span class="claim-no"><?= h($c['claim_no']) ?></span>
                <?= w_claim_badge($c['status']) ?>
                <span style="font-size:0.8rem; color:var(--text-muted);"><?= date('d/m/Y', strtotime($c['claim_date'])) ?></span>
            </div>
            <?php if ($c['issue_desc']): ?>
                <div style="font-size:0.85rem; color:var(--text-main); white-space:pre-line;"><?= h($c['issue_desc']) ?></div>
            <?php endif; ?>
            <?php if ($c['resolution']): ?>
                <div style="margin-top:8px; padding:8px 12px; background:rgba(16,185,129,.06); border-left:3px solid #10b981; border-radius:4px; font-size:0.83rem;">
                    <strong>ผลการดำเนินการ:</strong> <?= h($c['resolution']) ?>
                </div>
            <?php endif; ?>
            <div style="display:flex; gap:8px; margin-top:10px; justify-content:flex-end;">
                <button class="cmns-btn cmns-btn-secondary" style="padding:4px 10px; font-size:0.78rem;"
                        onclick="editClaim(<?= $c['id'] ?>, <?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)">
                    <span class="material-symbols-rounded" style="font-size:14px;">edit</span> แก้ไข
                </button>
            </div>
        </div>
        <?php endforeach; endif; ?>

        <?php if ($war['status'] !== 'voided'): ?>
        <div style="margin-top:16px; text-align:center;">
            <button class="cmns-btn cmns-btn-secondary" onclick="openClaimModal()" style="width:100%;">
                <span class="material-symbols-rounded">add_circle</span> บันทึกการเคลมใหม่
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Right: Status card + QR -->
<div>
    <div class="war-card" style="text-align:center;">
        <div class="war-card-title" style="justify-content:center;"><span class="material-symbols-rounded">calendar_month</span> ระยะประกัน</div>

        <?php
        $total  = $war['warranty_days'];
        $used   = (int)ceil((time() - strtotime($war['start_date'])) / 86400);
        $used   = max(0, min($used, $total));
        $pct    = $total > 0 ? round(($used / $total) * 100) : 100;
        $bar_color = $war['status'] === 'active'
                        ? ($pct < 70 ? '#10b981' : '#f59e0b')
                        : ($war['status'] === 'voided' ? '#ef4444' : 'var(--border)');
        ?>
        <div style="font-size:2.2rem; font-weight:900; color:var(--text-main);"><?= $total ?> <span style="font-size:1rem; font-weight:400;">วัน</span></div>
        <div style="font-size:0.82rem; color:var(--text-muted); margin-bottom:14px;">
            <?= date('d/m/Y', strtotime($war['start_date'])) ?> — <?= date('d/m/Y', strtotime($war['end_date'])) ?>
        </div>
        <div class="progress-bar-wrap">
            <div class="progress-bar" style="width:<?= $pct ?>%; background:<?= $bar_color ?>;"></div>
        </div>
        <div style="font-size:0.78rem; color:var(--text-muted); margin-bottom:16px;">ใช้ไปแล้ว <?= $pct ?>%</div>

        <?php if ($war['status'] === 'active'): ?>
            <?php if ($days_left > 0): ?>
                <div style="font-size:1.3rem; font-weight:800; color:<?= $days_left > 30 ? '#059669' : '#b45309' ?>;">
                    เหลือ <?= $days_left ?> วัน
                </div>
            <?php else: ?>
                <div style="font-size:1rem; font-weight:700; color:#dc2626;">หมดอายุแล้ว</div>
            <?php endif; ?>
        <?php else: ?>
            <div style="font-size:1rem; color:var(--text-muted);"><?= $war['status'] === 'voided' ? 'ยกเลิกแล้ว' : 'หมดอายุ' ?></div>
        <?php endif; ?>
    </div>

    <!-- QR Code -->
    <div class="war-card" style="text-align:center;">
        <div class="war-card-title" style="justify-content:center;"><span class="material-symbols-rounded">qr_code_2</span> QR เช็คประกัน</div>
        <canvas id="qr-canvas" style="max-width:180px; width:100%;"></canvas>
        <div style="font-size:0.76rem; color:var(--text-muted); margin-top:8px; word-break:break-all;">
            <?= h((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/warranty/?q=' . urlencode($war['warranty_no'])) ?>
        </div>
    </div>

    <?php if ($war['void_reason']): ?>
    <div class="war-card" style="border-color:rgba(239,68,68,.3); background:rgba(239,68,68,.04);">
        <div class="war-card-title" style="color:#dc2626;"><span class="material-symbols-rounded">block</span> เหตุผลยกเลิก</div>
        <p style="font-size:0.88rem; margin:0;"><?= h($war['void_reason']) ?></p>
    </div>
    <?php endif; ?>
</div>
</div><!-- .view-wrap -->
</div>

<!-- ── Modal: Add Claim ── -->
<div id="modal-add-claim" class="cmns-modal">
    <div class="modal-content sheet-on-mobile war-sheet" style="max-width:480px; padding:28px;">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); padding-bottom:16px; margin-bottom:20px;">
            <h3 style="margin:0; display:flex; align-items:center; gap:8px; font-weight:800; font-size:1.05rem;">
                <span class="material-symbols-rounded" style="color:#f59e0b; font-size:22px;">report_problem</span>
                บันทึกการเคลม
            </h3>
            <button class="modal-close-btn" onclick="closeClaimModal()">
                <span class="material-symbols-rounded">close</span>
            </button>
        </div>
        <form method="post" action="ajax.php">
            <input type="hidden" name="action" value="add_claim">
            <input type="hidden" name="warranty_id" value="<?= $id ?>">
            <input type="hidden" name="_redirect" value="view.php?id=<?= $id ?>">
            <div style="display:grid; gap:14px;">
                <div>
                    <label class="cmns-label">วันที่เคลม</label>
                    <input type="date" name="claim_date" class="cmns-input" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div>
                    <label class="cmns-label">อาการที่แจ้ง</label>
                    <textarea name="issue_desc" class="cmns-input" rows="3" placeholder="ลูกค้าแจ้งว่า..."></textarea>
                </div>
                <div>
                    <label class="cmns-label">สถานะ</label>
                    <select name="status" class="cmns-input">
                        <option value="pending">รอดำเนินการ</option>
                        <option value="approved">อนุมัติ</option>
                        <option value="rejected">ปฏิเสธ</option>
                        <option value="resolved">แก้ไขแล้ว</option>
                    </select>
                </div>
                <div>
                    <label class="cmns-label">ผลการดำเนินการ</label>
                    <textarea name="resolution" class="cmns-input" rows="2" placeholder="สรุปการแก้ไข..."></textarea>
                </div>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px; border-top:1px solid var(--border); padding-top:16px;">
                <button type="button" class="cmns-btn cmns-btn-secondary" onclick="closeClaimModal()">ยกเลิก</button>
                <button type="submit" class="cmns-btn cmns-btn-primary">
                    <span class="material-symbols-rounded">save</span> บันทึก
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal: Edit Claim ── -->
<div id="modal-edit-claim" class="cmns-modal">
    <div class="modal-content sheet-on-mobile war-sheet" style="max-width:480px; padding:28px;">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); padding-bottom:16px; margin-bottom:20px;">
            <h3 style="margin:0; display:flex; align-items:center; gap:8px; font-weight:800; font-size:1.05rem;">
                <span class="material-symbols-rounded" style="color:var(--primary); font-size:22px;">edit</span>
                แก้ไขการเคลม
            </h3>
            <button class="modal-close-btn" onclick="closeEditClaimModal()">
                <span class="material-symbols-rounded">close</span>
            </button>
        </div>
        <form method="post" action="ajax.php">
            <input type="hidden" name="action" value="edit_claim">
            <input type="hidden" name="claim_id" id="edit_claim_id">
            <input type="hidden" name="_redirect" value="view.php?id=<?= $id ?>">
            <div style="display:grid; gap:14px;">
                <div>
                    <label class="cmns-label">อาการที่แจ้ง</label>
                    <textarea name="issue_desc" id="edit_issue" class="cmns-input" rows="3"></textarea>
                </div>
                <div>
                    <label class="cmns-label">สถานะ</label>
                    <select name="status" id="edit_status" class="cmns-input">
                        <option value="pending">รอดำเนินการ</option>
                        <option value="approved">อนุมัติ</option>
                        <option value="rejected">ปฏิเสธ</option>
                        <option value="resolved">แก้ไขแล้ว</option>
                    </select>
                </div>
                <div>
                    <label class="cmns-label">ผลการดำเนินการ</label>
                    <textarea name="resolution" id="edit_resolution" class="cmns-input" rows="2"></textarea>
                </div>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px; border-top:1px solid var(--border); padding-top:16px;">
                <button type="button" class="cmns-btn cmns-btn-secondary" onclick="closeEditClaimModal()">ยกเลิก</button>
                <button type="submit" class="cmns-btn cmns-btn-primary">บันทึก</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal: Void Warranty ── -->
<div id="modal-void" class="cmns-modal">
    <div class="modal-content sheet-on-mobile war-sheet" style="max-width:420px; padding:28px;">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); padding-bottom:16px; margin-bottom:20px;">
            <h3 style="margin:0; display:flex; align-items:center; gap:8px; font-weight:800; font-size:1.05rem; color:#dc2626;">
                <span class="material-symbols-rounded" style="font-size:22px;">block</span>
                ยกเลิกใบประกัน
            </h3>
            <button class="modal-close-btn" onclick="closeVoidModal()">
                <span class="material-symbols-rounded">close</span>
            </button>
        </div>
        <p style="font-size:0.88rem; color:var(--text-muted); margin-bottom:16px;">การยกเลิกไม่สามารถย้อนกลับได้ กรุณาระบุเหตุผลให้ชัดเจน</p>
        <form method="post" action="ajax.php">
            <input type="hidden" name="action" value="void_warranty">
            <input type="hidden" name="warranty_id" value="<?= $id ?>">
            <input type="hidden" name="_redirect" value="view.php?id=<?= $id ?>">
            <div style="margin-bottom:20px;">
                <label class="cmns-label">เหตุผล <span style="color:#ef4444;">*</span></label>
                <textarea name="void_reason" class="cmns-input" rows="3" placeholder="ระบุเหตุผล..." required></textarea>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; border-top:1px solid var(--border); padding-top:16px;">
                <button type="button" class="cmns-btn cmns-btn-secondary" onclick="closeVoidModal()">ยกเลิก</button>
                <button type="submit" class="cmns-btn" style="background:#ef4444;color:#fff;border:1px solid #ef4444;">
                    <span class="material-symbols-rounded">block</span> ยืนยันยกเลิก
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.1/build/qrcode.min.js"></script>
<!-- QR init lives in its own block: if the CDN ever 404s again, the throw
     stays contained here instead of killing the modal wiring below. -->
<script>
if (window.QRCode) {
    const qrUrl = `${location.protocol}//${location.host}/warranty/?q=<?= urlencode($war['warranty_no']) ?>`;
    // desktop card + phone card — CSS shows one of them
    ['qr-canvas', 'qr-canvas-m'].forEach(cid => {
        const c = document.getElementById(cid);
        if (c) QRCode.toCanvas(c, qrUrl, {width:200, margin:1}, function(err){ if(err) console.error(err); });
    });
} else {
    console.error('QRCode library failed to load');
}
</script>

<script>
function openClaimModal()     { document.getElementById('modal-add-claim').classList.add('show'); }
function closeClaimModal()    { document.getElementById('modal-add-claim').classList.remove('show'); }
function closeEditClaimModal(){ document.getElementById('modal-edit-claim').classList.remove('show'); }
function confirmVoid()        { document.getElementById('modal-void').classList.add('show'); }
function closeVoidModal()     { document.getElementById('modal-void').classList.remove('show'); }

function editClaim(id, data) {
    document.getElementById('edit_claim_id').value   = id;
    document.getElementById('edit_issue').value      = data.issue_desc || '';
    document.getElementById('edit_status').value     = data.status;
    document.getElementById('edit_resolution').value = data.resolution || '';
    document.getElementById('modal-edit-claim').classList.add('show');
}

['modal-add-claim','modal-edit-claim','modal-void'].forEach(id => {
    const m = document.getElementById(id);
    m.addEventListener('click', function(e){
        if (e.target === this) this.classList.remove('show');
    });
    // Dragged down past the threshold — admin-mobile.js already threw it off-screen
    m.addEventListener('sheetdismiss', () => m.classList.remove('show'));
});
</script>

<?php include __DIR__ . '/../templates/footer_admin.php'; ?>
