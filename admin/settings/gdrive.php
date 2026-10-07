<?php
/**
 * admin/settings/gdrive.php — เชื่อม Google Drive สำหรับเก็บรูปเครื่องลูกค้า
 * ทุกยศดูสถานะได้ · ใส่ client id/secret, เชื่อม, ตัดการเชื่อม = super_admin เท่านั้น
 */
session_start();
require_once '../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/gdrive_lib.php';

require_login();
$canEdit = (($_SESSION['admin_role'] ?? '') === 'super_admin');

[$flash, $flashType] = $_SESSION['gd_flash'] ?? ['', 'ok'];
unset($_SESSION['gd_flash']);

$go = function (string $msg, string $type = 'ok') {
    $_SESSION['gd_flash'] = [$msg, $type];
    header('Location: /admin/settings/gdrive.php');
    exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEdit) $go('แก้ได้เฉพาะ super_admin', 'err');
    $action = $_POST['action'] ?? '';

    if ($action === 'save_app') {
        $id  = trim($_POST['client_id'] ?? '');
        $sec = trim($_POST['client_secret'] ?? '');
        if (!preg_match('/\.apps\.googleusercontent\.com$/', $id)) $go('Client ID ต้องลงท้ายด้วย .apps.googleusercontent.com', 'err');
        if ($sec === '') $go('ใส่ Client secret ด้วย', 'err');
        $old = gd_cfg($pdo);
        $fields = ['client_id' => $id, 'client_secret' => $sec];
        // a different OAuth app can't use the old refresh token
        if ($old['client_id'] !== $id && !empty($old['refresh_token'])) {
            gd_disconnect($pdo);
            $go('บันทึกแล้ว — เปลี่ยน Client ID เลยต้องกดเชื่อม Drive ใหม่', 'ok');
        }
        gd_set($pdo, $fields);
        $go('บันทึก Client ID / secret แล้ว');
    }

    if ($action === 'connect') {
        $c = gd_cfg($pdo);
        if ($c['client_id'] === '' || $c['client_secret'] === '') $go('ใส่ Client ID / secret ก่อน', 'err');
        $_SESSION['gd_oauth_state'] = bin2hex(random_bytes(16));
        header('Location: ' . gd_auth_url($pdo, $_SESSION['gd_oauth_state']));
        exit;
    }

    if ($action === 'disconnect') {
        gd_disconnect($pdo);
        $go('ตัดการเชื่อมแล้ว — รูปเดิมยังอยู่ใน Drive แต่หน้างานซ่อมจะเปิดไม่ได้จนกว่าจะเชื่อมบัญชีเดิมกลับ');
    }
    $go('ไม่รู้จักคำสั่งนี้', 'err');
}

$cfg       = gd_cfg($pdo);
$connected = gd_is_connected($pdo);
$quota     = null;
$quotaErr  = '';
if ($connected) {
    try { $quota = gd_quota($pdo); } catch (RuntimeException $e) { $quotaErr = $e->getMessage(); }
}
$photoCount = (int)$pdo->query("SELECT COUNT(*) FROM tracking_photos")->fetchColumn();
$photoBytes = (int)$pdo->query("SELECT COALESCE(SUM(size_bytes), 0) FROM tracking_photos")->fetchColumn();

function gd_fmt_bytes(int $b): string {
    if ($b >= 1073741824) return number_format($b / 1073741824, 2) . ' GB';
    if ($b >= 1048576)    return number_format($b / 1048576, 1) . ' MB';
    return number_format($b / 1024) . ' KB';
}
function gd_mask(string $s): string { return $s === '' ? '' : str_repeat('•', 12) . substr($s, -4); }

$pageTitle = 'Google Drive — รูปเครื่องลูกค้า';
include '../templates/header_admin.php';
?>

<div class="cmns-wrapper" style="max-width:820px;">
    <div style="margin-bottom:20px;">
        <a href="/admin/settings/" class="cmns-back-link">
            <span class="material-symbols-rounded">arrow_back</span> กลับ
        </a>
    </div>

    <div class="gd-head">
        <h1>Google Drive — รูปเครื่องลูกค้า</h1>
        <p>รูปที่ถ่ายในหน้างานซ่อมจะขึ้นไปเก็บที่ Drive ของร้าน โฟลเดอร์ <b><?= GD_ROOT_NAME ?></b> แยกโฟลเดอร์ต่องาน ไม่กินพื้นที่โฮสต์</p>
    </div>

    <?php if ($flash): ?>
    <div class="gd-flash <?= $flashType === 'ok' ? 'ok' : 'err' ?>"><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>
    <?php if (!$connected && !empty($cfg['last_error'])): ?>
    <div class="gd-flash err"><?= htmlspecialchars($cfg['last_error']) ?></div>
    <?php endif; ?>

    <!-- ── status ── -->
    <section class="gd-card">
        <div class="gd-status">
            <span class="material-symbols-rounded gd-status-ico <?= $connected ? 'on' : 'off' ?>"><?= $connected ? 'cloud_done' : 'cloud_off' ?></span>
            <div class="gd-status-txt">
                <b><?= $connected ? 'เชื่อมแล้ว' : 'ยังไม่ได้เชื่อม' ?></b>
                <?php if ($connected): ?>
                <span><?= htmlspecialchars($cfg['account_email'] ?: 'ไม่ทราบอีเมล') ?> · ตั้งแต่ <?= date('d/m/Y H:i', strtotime($cfg['connected_at'])) ?></span>
                <?php else: ?>
                <span>อัปรูปในหน้างานซ่อมไม่ได้จนกว่าจะเชื่อม</span>
                <?php endif; ?>
            </div>
            <a class="cmns-btn cmns-btn-secondary" href="/admin/settings/photos.php">
                <span class="material-symbols-rounded">photo_library</span> จัดการรูป
            </a>
            <?php if ($connected && $cfg['root_folder_id']): ?>
            <a class="cmns-btn cmns-btn-secondary" href="<?= htmlspecialchars(gd_folder_url($cfg['root_folder_id'])) ?>" target="_blank" rel="noopener">
                <span class="material-symbols-rounded">folder_open</span> เปิดใน Drive
            </a>
            <?php endif; ?>
        </div>

        <div class="gd-stats">
            <div><small>รูปในระบบ</small><b><?= number_format($photoCount) ?></b></div>
            <div><small>ขนาดรวม</small><b><?= gd_fmt_bytes($photoBytes) ?></b></div>
            <?php if ($quota): ?>
            <div><small>พื้นที่ Drive ที่ใช้</small><b><?= gd_fmt_bytes($quota['used']) ?><?= $quota['limit'] ? ' / ' . gd_fmt_bytes($quota['limit']) : '' ?></b></div>
            <?php endif; ?>
        </div>
        <?php if ($quota && $quota['limit']): $pct = min(100, $quota['used'] / $quota['limit'] * 100); ?>
        <div class="gd-bar"><i style="width:<?= round($pct, 1) ?>%;<?= $pct > 85 ? 'background:#ef4444;' : '' ?>"></i></div>
        <p class="gd-desc">พื้นที่นี้ใช้ร่วมกับ Gmail และ Google Photos ของบัญชีนี้<?= $pct > 85 ? ' — <b style="color:#dc2626;">ใกล้เต็มแล้ว</b>' : '' ?></p>
        <?php endif; ?>
        <?php if ($quotaErr): ?>
        <p class="gd-desc" style="color:#dc2626;">เช็คพื้นที่ไม่ได้: <?= htmlspecialchars($quotaErr) ?></p>
        <?php endif; ?>

        <?php if ($canEdit): ?>
        <div class="gd-btns">
            <form method="post">
                <button type="submit" name="action" value="connect" class="cmns-btn cmns-btn-primary"
                        <?= ($cfg['client_id'] === '' || $cfg['client_secret'] === '') ? 'disabled' : '' ?>>
                    <span class="material-symbols-rounded">add_link</span> <?= $connected ? 'เชื่อมใหม่ / เปลี่ยนบัญชี' : 'เชื่อม Google Drive' ?>
                </button>
            </form>
            <?php if ($connected): ?>
            <form method="post" id="gdDisconnect">
                <input type="hidden" name="action" value="disconnect">
                <button type="button" class="cmns-btn cmns-btn-secondary gd-danger" id="gdDisconnectBtn">
                    <span class="material-symbols-rounded">link_off</span> ตัดการเชื่อม
                </button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </section>

    <!-- ── OAuth app ── -->
    <section class="gd-card">
        <h2>แอป OAuth (Google Cloud)</h2>

        <div class="gd-field">
            <label>Authorized redirect URI <span class="gd-desc">(ใส่ใน Google Cloud → Credentials → OAuth client)</span></label>
            <div class="gd-copy">
                <input type="text" id="gdRedirect" value="<?= htmlspecialchars(gd_redirect_uri()) ?>" readonly>
                <button type="button" class="cmns-btn cmns-btn-secondary" onclick="navigator.clipboard.writeText(document.getElementById('gdRedirect').value).then(()=>this.textContent='คัดลอกแล้ว')">คัดลอก</button>
            </div>
            <span class="gd-desc">ต้องใส่ทั้งของ localhost และโดเมนจริง — แต่ละเครื่องใช้ URL ของตัวเอง</span>
        </div>

        <form method="post">
            <input type="hidden" name="action" value="save_app">
            <div class="gd-field">
                <label>Client ID</label>
                <input type="text" name="client_id" value="<?= htmlspecialchars($cfg['client_id']) ?>"
                       placeholder="xxxxxxxx.apps.googleusercontent.com" <?= $canEdit ? 'required' : 'disabled' ?>>
            </div>
            <div class="gd-field">
                <label>Client secret</label>
                <input type="text" name="client_secret" value="<?= $canEdit ? htmlspecialchars($cfg['client_secret']) : gd_mask($cfg['client_secret']) ?>"
                       placeholder="GOCSPX-…" autocomplete="off" <?= $canEdit ? 'required' : 'disabled' ?>>
            </div>
            <?php if ($canEdit): ?>
            <button type="submit" class="cmns-btn cmns-btn-primary">
                <span class="material-symbols-rounded">save</span> บันทึก
            </button>
            <?php endif; ?>
        </form>

        <details class="gd-help">
            <summary>วิธีสร้าง Client ID (ทำครั้งเดียว)</summary>
            <ol>
                <li>เข้า <b>console.cloud.google.com</b> ด้วยบัญชีที่จะเก็บรูป → สร้าง project ใหม่</li>
                <li>APIs &amp; Services → Library → เปิด <b>Google Drive API</b></li>
                <li>OAuth consent screen → External → ใส่ชื่อแอป + อีเมล → scope ไม่ต้องเพิ่ม</li>
                <li><b>Publish app → In production</b> (ถ้าค้างที่ Testing สิทธิ์จะหมดทุก 7 วัน)</li>
                <li>Credentials → Create credentials → OAuth client ID → <b>Web application</b> → ใส่ redirect URI ด้านบน</li>
                <li>เอา Client ID + secret มาใส่ที่นี่ → บันทึก → กด "เชื่อม Google Drive"</li>
                <li>หน้า Google จะเตือนว่า "Google hasn't verified this app" → Advanced → Go to … (ปกติ เพราะแอปเราใช้เองในร้าน)</li>
            </ol>
        </details>
    </section>
</div>

<!-- disconnect confirm (no confirm() dialogs in the PWA) -->
<?php if ($canEdit && $connected): ?>
<div class="gd-confirm" id="gdConfirm" hidden>
    <div class="gd-confirm-box">
        <b>ตัดการเชื่อม Google Drive?</b>
        <p>รูปเดิมยังอยู่ใน Drive ไม่หาย แต่หน้างานซ่อมจะดู/อัปรูปไม่ได้จนกว่าจะเชื่อมกลับด้วยบัญชีเดิม</p>
        <div class="gd-btns">
            <button type="button" class="cmns-btn cmns-btn-secondary" id="gdConfirmNo">ยกเลิก</button>
            <button type="button" class="cmns-btn cmns-btn-primary gd-danger-solid" id="gdConfirmYes">ตัดการเชื่อม</button>
        </div>
    </div>
</div>
<script>
(function () {
    const box = document.getElementById('gdConfirm');
    document.getElementById('gdDisconnectBtn').onclick = () => { box.hidden = false; };
    document.getElementById('gdConfirmNo').onclick = () => { box.hidden = true; };
    document.getElementById('gdConfirmYes').onclick = () => document.getElementById('gdDisconnect').submit();
})();
</script>
<?php endif; ?>

<style>
.gd-head { margin-bottom:22px; }
.gd-head h1 { font-size:1.32rem; font-weight:700; color:var(--text-main); margin:0 0 5px; }
.gd-head p { font-size:.85rem; color:var(--text-muted); margin:0; line-height:1.55; }
.gd-flash { padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:.9rem; line-height:1.5; }
.gd-flash.ok  { background:rgba(16,185,129,.1); color:#059669; border:1px solid rgba(16,185,129,.25); }
.gd-flash.err { background:rgba(239,68,68,.1); color:#dc2626; border:1px solid rgba(239,68,68,.25); }
.gd-card { background:var(--bg-surface); border:1px solid var(--border); border-radius:14px; padding:18px; margin-bottom:18px; }
.gd-card h2 { font-size:1rem; font-weight:700; color:var(--text-main); margin:0 0 14px; }
.gd-status { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.gd-status-ico { font-size:30px; }
.gd-status-ico.on { color:#10b981; }
.gd-status-ico.off { color:var(--text-muted); }
.gd-status-txt { flex:1; min-width:180px; display:flex; flex-direction:column; gap:2px; }
.gd-status-txt b { color:var(--text-main); }
.gd-status-txt span { font-size:.82rem; color:var(--text-muted); word-break:break-all; }
.gd-stats { display:flex; gap:22px; flex-wrap:wrap; margin:16px 0 10px; }
.gd-stats small { display:block; font-size:.75rem; color:var(--text-muted); }
.gd-stats b { font-size:1.05rem; color:var(--text-main); }
.gd-bar { height:8px; border-radius:99px; background:var(--border); overflow:hidden; }
.gd-bar i { display:block; height:100%; background:var(--primary); }
.gd-desc { font-size:.78rem; color:var(--text-muted); line-height:1.55; font-weight:400; }
.gd-btns { display:flex; gap:10px; flex-wrap:wrap; margin-top:16px; }
.gd-btns form { margin:0; }
.gd-danger { color:#dc2626 !important; }
.gd-danger-solid { background:#dc2626 !important; border-color:#dc2626 !important; }
.gd-field { margin-bottom:16px; }
.gd-field label { display:block; font-size:.87rem; font-weight:600; color:var(--text-main); margin-bottom:7px; }
.gd-field input, .gd-copy input {
    width:100%; padding:10px 13px; border:1.5px solid var(--border); border-radius:9px; font-size:.9rem;
    background:var(--bg-surface-alt, var(--bg-surface)); color:var(--text-main); box-sizing:border-box; font-family:inherit;
}
.gd-field input:focus { outline:none; border-color:var(--primary); }
.gd-copy { display:flex; gap:10px; margin-bottom:4px; }
.gd-copy input { flex:1; min-width:0; }
.gd-help { margin-top:18px; font-size:.85rem; color:var(--text-main); }
.gd-help summary { cursor:pointer; font-weight:600; }
.gd-help ol { margin:10px 0 0; padding-left:20px; line-height:1.75; color:var(--text-muted); }
.gd-confirm { position:fixed; inset:0; background:rgba(0,0,0,.45); display:flex; align-items:center; justify-content:center; z-index:2000; padding:16px; }
.gd-confirm-box { background:var(--bg-surface); border-radius:14px; padding:20px; max-width:380px; width:100%; }
.gd-confirm-box b { color:var(--text-main); font-size:1rem; }
.gd-confirm-box p { font-size:.86rem; color:var(--text-muted); line-height:1.55; margin:8px 0 0; }
.gd-confirm-box .gd-btns { justify-content:flex-end; }
</style>

<?php include '../templates/footer_admin.php'; ?>
