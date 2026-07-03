<?php
/**
 * admin/settings/line.php — ตั้งค่า LINE Messaging API (เฉพาะเจ้าของระบบ)
 * แก้ Channel access token + Channel secret ของ LINE OA ที่จะเชื่อมกับเว็บ
 * แล้วทดสอบว่า token ใช้ได้จริง (โชว์ว่าเป็น channel ไหน)
 */
session_start();
require_once '../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/line_helper.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}
require_perms(['settings.manage']); // ตั้งค่าระบบ: เจ้าของเท่านั้น

/** อ่าน config ปัจจุบัน */
function line_cfg_load(PDO $pdo): array {
    $r = $pdo->query("SELECT page_name, access_token, secret_key FROM chat_platform_config WHERE platform='line'")
             ->fetch(PDO::FETCH_ASSOC);
    return $r ?: ['page_name' => '', 'access_token' => '', 'secret_key' => ''];
}

$flash = '';
$flash_type = 'ok';
$test = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $page_name = trim($_POST['page_name'] ?? '');
        $token     = trim($_POST['access_token'] ?? '');
        $secret    = trim($_POST['secret_key'] ?? '');

        if ($token === '' || $secret === '') {
            $flash = 'กรุณากรอกทั้ง Channel access token และ Channel secret';
            $flash_type = 'err';
        } else {
            // upsert (platform เป็น unique key)
            $pdo->prepare("
                INSERT INTO chat_platform_config (platform, page_name, access_token, secret_key, updated_at)
                VALUES ('line', ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    page_name = VALUES(page_name),
                    access_token = VALUES(access_token),
                    secret_key = VALUES(secret_key),
                    updated_at = NOW()
            ")->execute([$page_name, $token, $secret]);
            $flash = 'บันทึกแล้ว — กด "ทดสอบการเชื่อมต่อ" เพื่อยืนยันว่า token ถูกต้อง';
        }
    } elseif ($action === 'test') {
        $cfg  = line_cfg_load($pdo);
        $info = line_bot_info($pdo, $cfg['access_token']);
        $test = $info;
    }
}

$cfg = line_cfg_load($pdo);

$host        = $_SERVER['HTTP_HOST'];
$webhook_url = 'https://' . $host . '/admin/cron/line_hook.php';

$pageTitle = 'ตั้งค่า LINE';
include '../templates/header_admin.php';
?>

<div class="cmns-wrapper" style="max-width:760px;">
    <div class="cmns-header-bar" style="margin-bottom:20px;">
        <h1 class="cmns-page-title" style="color:var(--primary);display:flex;align-items:center;gap:8px;">
            <span class="material-symbols-rounded">chat</span> ตั้งค่า LINE Messaging API
        </h1>
        <a href="/admin/settings/" class="cmns-btn cmns-btn-secondary" style="font-size:14px;">
            <span class="material-symbols-rounded" style="font-size:16px;">arrow_back</span> กลับ
        </a>
    </div>

    <?php if ($flash): ?>
    <div class="ln-flash <?= $flash_type ?>"><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>

    <?php if ($test !== null): ?>
        <?php if (($test['code'] ?? 0) === 200 && !empty($test['body']['basicId'])): ?>
        <div class="ln-flash ok">
            ✅ เชื่อมต่อสำเร็จ — token ใช้งานได้<br>
            Channel: <b><?= htmlspecialchars($test['body']['displayName'] ?? '-') ?></b>
            (<?= htmlspecialchars($test['body']['basicId'] ?? '-') ?>)
        </div>
        <?php else: ?>
        <div class="ln-flash err">
            ❌ ทดสอบไม่ผ่าน (HTTP <?= (int)($test['code'] ?? 0) ?>) —
            <?= htmlspecialchars($test['body']['message'] ?? $test['err'] ?? 'token ไม่ถูกต้อง') ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Webhook URL -->
    <div class="ln-card">
        <div class="ln-label">Webhook URL <span class="ln-hint">(วางค่านี้ใน LINE Developers → Messaging API → Webhook URL)</span></div>
        <div class="ln-copy">
            <input type="text" id="wh" value="<?= htmlspecialchars($webhook_url) ?>" readonly>
            <button type="button" class="cmns-btn cmns-btn-secondary" onclick="cp()">
                <span class="material-symbols-rounded" style="font-size:16px;">content_copy</span> คัดลอก
            </button>
        </div>
    </div>

    <!-- Config form -->
    <form method="POST" class="ln-card">
        <input type="hidden" name="action" value="save">

        <div class="ln-field">
            <label>ชื่อ Channel / OA (ไว้จำ)</label>
            <input type="text" name="page_name" value="<?= htmlspecialchars($cfg['page_name']) ?>" placeholder="เช่น API Test">
        </div>

        <div class="ln-field">
            <label>Channel access token (long-lived)</label>
            <textarea name="access_token" rows="3" placeholder="วาง token จากแท็บ Messaging API" required><?= htmlspecialchars($cfg['access_token']) ?></textarea>
            <span class="ln-hint">LINE Developers → Channel → แท็บ Messaging API → Channel access token → Issue</span>
        </div>

        <div class="ln-field">
            <label>Channel secret</label>
            <input type="text" name="secret_key" value="<?= htmlspecialchars($cfg['secret_key']) ?>" placeholder="32 ตัวอักษร" required>
            <span class="ln-hint">LINE Developers → Channel → แท็บ Basic settings → Channel secret</span>
        </div>

        <div style="display:flex;gap:10px;margin-top:8px;">
            <button type="submit" class="cmns-btn cmns-btn-primary">
                <span class="material-symbols-rounded" style="font-size:16px;">save</span> บันทึก
            </button>
        </div>
    </form>

    <!-- Test connection -->
    <form method="POST" class="ln-card" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <input type="hidden" name="action" value="test">
        <div>
            <div class="ln-label" style="margin:0;">ทดสอบการเชื่อมต่อ</div>
            <span class="ln-hint">เรียก LINE API ด้วย token ที่บันทึกไว้ เพื่อยืนยันว่าถูกต้องและเป็น channel ที่ต้องการ</span>
        </div>
        <button type="submit" class="cmns-btn cmns-btn-secondary" style="flex-shrink:0;">
            <span class="material-symbols-rounded" style="font-size:16px;">wifi_tethering</span> ทดสอบ
        </button>
    </form>

    <p style="font-size:.82rem;color:var(--text-muted);line-height:1.6;margin-top:18px;">
        <b>ขั้นตอน:</b> 1) วาง token + secret ของ channel ที่ต้องการ แล้วกดบันทึก →
        2) กดทดสอบ ต้องขึ้นชื่อ channel ให้ตรง →
        3) เอา Webhook URL ด้านบนไปใส่ใน LINE Developers แล้วกด Verify (ต้องได้ Success)
    </p>
</div>

<style>
.ln-card { background:var(--bg-surface); border:1px solid var(--border); border-radius:14px; padding:20px 22px; margin-bottom:16px; box-shadow:var(--shadow); }
.ln-label { font-size:.9rem; font-weight:700; color:var(--text-main); margin-bottom:10px; }
.ln-hint { font-size:.78rem; font-weight:400; color:var(--text-muted); }
.ln-field { margin-bottom:16px; }
.ln-field label { display:block; font-size:.87rem; font-weight:600; color:var(--text-main); margin-bottom:7px; }
.ln-field input, .ln-field textarea, .ln-copy input {
    width:100%; padding:10px 13px; border:1.5px solid var(--border); border-radius:9px;
    font-size:.9rem; background:var(--bg-surface-alt,var(--bg-surface)); color:var(--text-main);
    box-sizing:border-box; font-family:inherit;
}
.ln-field textarea { resize:vertical; word-break:break-all; }
.ln-field input:focus, .ln-field textarea:focus { outline:none; border-color:var(--primary); }
.ln-copy { display:flex; gap:10px; }
.ln-copy input { flex:1; background:var(--bg-surface-alt,#0000000a); }
.ln-flash { padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:.9rem; line-height:1.5; }
.ln-flash.ok  { background:rgba(16,185,129,.1); color:#059669; border:1px solid rgba(16,185,129,.25); }
.ln-flash.err { background:rgba(239,68,68,.1); color:#dc2626; border:1px solid rgba(239,68,68,.25); }
</style>

<script>
function cp(){
    const el = document.getElementById('wh');
    el.select(); el.setSelectionRange(0,99999);
    navigator.clipboard.writeText(el.value).then(()=>{
        const b = event.currentTarget; const t = b.innerHTML;
        b.innerHTML = '<span class="material-symbols-rounded" style="font-size:16px;">check</span> คัดลอกแล้ว';
        setTimeout(()=>b.innerHTML=t, 1500);
    });
}
</script>

<?php include '../templates/footer_admin.php'; ?>
