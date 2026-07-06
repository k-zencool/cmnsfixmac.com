<?php
/**
 * admin/settings/notifications.php — Notification Center (super_admin เท่านั้น)
 * คุมการแจ้งเตือน LINE: เปิด/ปิดช่อง, เปิด/ปิดรอบเช้า-เย็น, จัดการผู้รับ (กลุ่ม/admin),
 * ยิงข้อความทดสอบ และยิงรายงานงานซ่อมจริง
 */
session_start();
require_once '../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notify_settings.php';
require_once __DIR__ . '/../../includes/line_helper.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}
require_perms(['settings.manage']);

// มีแต่ super_admin เท่านั้น
if (($_SESSION['admin_role'] ?? '') !== 'super_admin') {
    header("Location: /admin/settings/");
    exit();
}

$flash = '';
$flash_type = 'ok';
$test = null;      // ['label'=>..., 'ok'=>bool, 'detail'=>string]

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_toggles') {
        notif_set($pdo, 'notify_morning_enabled', isset($_POST['morning']) ? '1' : '0');
        notif_set($pdo, 'notify_evening_enabled', isset($_POST['evening']) ? '1' : '0');
        notif_set($pdo, 'notify_line_enabled',    isset($_POST['line_on']) ? '1' : '0');
        $flash = 'บันทึกสวิตช์แจ้งเตือนแล้ว';

    } elseif ($action === 'toggle_group') {
        $gid = $_POST['group_id'] ?? '';
        if ($gid !== '') {
            $pdo->prepare("UPDATE line_groups SET is_active = 1 - is_active WHERE group_id = ?")->execute([$gid]);
            $flash = 'อัปเดตสถานะกลุ่มแล้ว';
        }

    } elseif ($action === 'test_line') {
        $out = line_alert_send($pdo, [line_text_msg("ทดสอบ Notification Center — ถ้าเห็นข้อความนี้แปลว่า LINE พร้อมใช้งาน")]);
        $ok = ($out['sent'] ?? 0) > 0;
        $test = ['label' => 'LINE', 'ok' => $ok,
                 'detail' => "ผู้รับ {$out['recipients']} · ส่งสำเร็จ {$out['sent']} · ล้มเหลว {$out['failed']}"
                             . (isset($out['err']) ? " · {$out['err']}" : '')];

    } elseif ($action === 'test_morning' || $action === 'test_evening') {
        // ยิงรายงานงานซ่อมจริง (เช้า/เย็น) เดี๋ยวนี้ — เคารพสวิตช์ที่ตั้งไว้ (ช่องปิด = ข้าม)
        $which  = $action === 'test_morning' ? 'morning' : 'evening';
        $script = __DIR__ . "/../cron/{$which}_alert.php";
        // ผ่าน guard ของ cron แบบ authorized (หน้านี้ super_admin แล้ว): เซ็ต key ตรงกันชั่วคราว
        $prev_key = $_ENV['CRON_KEY'] ?? null;
        $_ENV['CRON_KEY'] = '__internal_test__';
        $_GET['key']      = '__internal_test__';
        $lineOut = ['skipped' => true];
        ob_start();
        include $script;              // สร้าง+ส่งรายงาน แล้วเซ็ต $lineOut (LINE)
        ob_end_clean();               // ทิ้ง HTML ที่ cron echo
        if ($prev_key === null) unset($_ENV['CRON_KEY']); else $_ENV['CRON_KEY'] = $prev_key;

        $ln_txt = isset($lineOut['skipped']) ? 'ปิด/ข้าม (เช็คสวิตช์)'
                : "ผู้รับ {$lineOut['recipients']} · ส่งสำเร็จ {$lineOut['sent']} · ล้มเหลว {$lineOut['failed']}"
                  . (isset($lineOut['err']) ? " · {$lineOut['err']}" : '');
        $test = ['label' => $which === 'morning' ? 'รายงานเช้า' : 'รายงานเย็น',
                 'ok' => ($lineOut['sent'] ?? 0) > 0, 'detail' => $ln_txt];
    }
}

// ── โหลดสถานะปัจจุบัน (สดจาก DB) ──
$set = notif_all_fresh($pdo);
$on = function(string $k) use ($set) { return !array_key_exists($k, $set) || $set[$k] === '1'; };

// LINE creds + ผู้รับ
$line_cfg = $pdo->query("SELECT page_name, access_token FROM chat_platform_config WHERE platform='line'")->fetch(PDO::FETCH_ASSOC) ?: [];
$line_ready = !empty($line_cfg['access_token']);

$groups = [];
try { $groups = $pdo->query("SELECT group_id, group_name, is_active FROM line_groups ORDER BY created_at ASC")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}

$admins = $pdo->query("SELECT username, role, line_user_id FROM admin_users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$line_linked   = count(array_filter($admins, fn($a) => !empty($a['line_user_id'])));
$active_groups = count(array_filter($groups, fn($x) => (int)$x['is_active'] === 1));

$pageTitle = 'จัดการการแจ้งเตือน';
include '../templates/header_admin.php';
?>

<div class="cmns-wrapper" style="max-width:820px;">
    <div class="cmns-header-bar" style="margin-bottom:20px;">
        <h1 class="cmns-page-title" style="color:var(--primary);display:flex;align-items:center;gap:8px;">
            <span class="material-symbols-rounded">notifications_active</span> จัดการการแจ้งเตือน
        </h1>
        <a href="/admin/settings/" class="cmns-btn cmns-btn-secondary" style="font-size:14px;">
            <span class="material-symbols-rounded" style="font-size:16px;">arrow_back</span> กลับ
        </a>
    </div>

    <?php if ($flash): ?>
    <div class="ln-flash <?= $flash_type ?>"><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>

    <?php if ($test !== null): ?>
    <div class="ln-flash <?= $test['ok'] ? 'ok' : 'err' ?>">
        <?= $test['ok'] ? '✅' : '❌' ?> ทดสอบ <?= htmlspecialchars($test['label'] ?? '') ?> —
        <?= htmlspecialchars($test['detail']) ?>
    </div>
    <?php endif; ?>

    <!-- 1) สวิตช์รวม -->
    <form method="POST" class="ln-card">
        <input type="hidden" name="action" value="save_toggles">
        <div class="ln-label">สวิตช์การแจ้งเตือน</div>

        <div class="nc-toggle-grid">
            <label class="nc-toggle">
                <input type="checkbox" name="line_on" <?= $on('notify_line_enabled') ? 'checked' : '' ?>>
                <span class="nc-sw"></span>
                <span>ช่อง <b>LINE</b></span>
            </label>
            <label class="nc-toggle">
                <input type="checkbox" name="morning" <?= $on('notify_morning_enabled') ? 'checked' : '' ?>>
                <span class="nc-sw"></span>
                <span>รอบ <b>เช้า</b> (07:00)</span>
            </label>
            <label class="nc-toggle">
                <input type="checkbox" name="evening" <?= $on('notify_evening_enabled') ? 'checked' : '' ?>>
                <span class="nc-sw"></span>
                <span>รอบ <b>เย็น</b> (19:00)</span>
            </label>
        </div>
        <p class="ln-hint" style="margin:12px 0 14px;">ต้องเปิดทั้ง <b>รอบ</b> (เช้า/เย็น) และ <b>ช่อง LINE</b> ถึงจะส่งแจ้งเตือน</p>
        <button type="submit" class="cmns-btn cmns-btn-primary">
            <span class="material-symbols-rounded" style="font-size:16px;">save</span> บันทึกสวิตช์
        </button>
    </form>

    <!-- 1.5) ยิงรายงานจริงทดสอบ -->
    <div class="ln-card">
        <div class="ln-label">ทดสอบยิงรายงานงานซ่อม (ของจริง เดี๋ยวนี้)</div>
        <p class="ln-hint" style="margin:0 0 14px;">ยิงรายงานเหมือน cron รอบเช้า/เย็น ไปยังผู้รับจริงทันที — <b>เคารพสวิตช์ด้านบน</b> (ปิดจะข้าม)</p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <form method="POST" style="margin:0;">
                <input type="hidden" name="action" value="test_morning">
                <button type="submit" class="cmns-btn cmns-btn-primary" onclick="return confirm('ยิงรายงานเช้าไปผู้รับจริงทั้งหมดเลยนะ?');">
                    <span class="material-symbols-rounded" style="font-size:16px;">wb_sunny</span> ยิงรายงานเช้า
                </button>
            </form>
            <form method="POST" style="margin:0;">
                <input type="hidden" name="action" value="test_evening">
                <button type="submit" class="cmns-btn cmns-btn-primary" onclick="return confirm('ยิงรายงานเย็นไปผู้รับจริงทั้งหมดเลยนะ?');">
                    <span class="material-symbols-rounded" style="font-size:16px;">nightlight</span> ยิงรายงานเย็น
                </button>
            </form>
        </div>
    </div>

    <!-- 2) LINE -->
    <div class="ln-card">
        <div class="ln-label" style="display:flex;align-items:center;gap:8px;">
            <span class="material-symbols-rounded" style="color:#06c755;">chat</span> LINE
            <span class="nc-badge <?= $on('notify_line_enabled') ? 'on' : 'off' ?>">
                <?= $on('notify_line_enabled') ? 'เปิด' : 'ปิด' ?>
            </span>
        </div>

        <div class="nc-line-status">
            <span>Channel: <b><?= htmlspecialchars($line_cfg['page_name'] ?? '-') ?></b>
                <?= $line_ready ? '<span class="nc-ok">● พร้อม</span>' : '<span class="nc-bad">● ยังไม่ตั้ง token</span>' ?>
            </span>
            <a href="/admin/settings/line.php" class="cmns-btn cmns-btn-secondary" style="font-size:13px;">
                <span class="material-symbols-rounded" style="font-size:15px;">key</span> แก้ token / secret
            </a>
        </div>

        <!-- กลุ่มที่รับ alert -->
        <div class="ln-sub">กลุ่มที่รับแจ้งเตือน (<?= $active_groups ?> เปิดใช้งาน)</div>
        <?php if (!$groups): ?>
            <p class="ln-hint">ยังไม่มีกลุ่ม — เชิญบอทเข้ากลุ่ม LINE แล้วมันจะโผล่ที่นี่</p>
        <?php else: foreach ($groups as $gr): ?>
            <div class="nc-row">
                <span><span class="material-symbols-rounded" style="font-size:18px;vertical-align:-3px;">group</span>
                    <?= htmlspecialchars($gr['group_name'] ?: $gr['group_id']) ?></span>
                <form method="POST" style="margin:0;">
                    <input type="hidden" name="action" value="toggle_group">
                    <input type="hidden" name="group_id" value="<?= htmlspecialchars($gr['group_id']) ?>">
                    <button type="submit" class="nc-pill <?= (int)$gr['is_active'] ? 'on' : 'off' ?>">
                        <?= (int)$gr['is_active'] ? 'รับอยู่' : 'ปิดรับ' ?>
                    </button>
                </form>
            </div>
        <?php endforeach; endif; ?>

        <!-- admin ที่ link 1:1 -->
        <div class="ln-sub" style="margin-top:16px;">Admin ที่เชื่อม LINE (<?= $line_linked ?> จาก <?= count($admins) ?> คน)</div>
        <div class="nc-admins">
            <?php foreach ($admins as $a): ?>
            <div class="nc-admin">
                <span><?= htmlspecialchars($a['username']) ?> <small class="ln-hint"><?= htmlspecialchars($a['role']) ?></small></span>
                <span class="nc-chip <?= !empty($a['line_user_id']) ? 'on' : '' ?>">LINE</span>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="ln-hint" style="margin-top:8px;">admin เชื่อมบัญชีเองผ่านบอท LINE (พิมพ์ในแชทบอทเพื่อขอรหัสลงทะเบียน)</p>

        <form method="POST" style="margin-top:12px;border-top:1px solid var(--border);padding-top:12px;">
            <input type="hidden" name="action" value="test_line">
            <button type="submit" class="cmns-btn cmns-btn-secondary" onclick="return confirm('ยิงข้อความทดสอบเข้า LINE (ทุกกลุ่มที่เปิด + admin ที่ link) จริงเลยนะ?');">
                <span class="material-symbols-rounded" style="font-size:16px;">wifi_tethering</span> ยิงทดสอบ LINE
            </button>
        </form>
    </div>
</div>

<style>
.ln-card { background:var(--bg-surface); border:1px solid var(--border); border-radius:14px; padding:20px 22px; margin-bottom:16px; box-shadow:var(--shadow); }
.ln-label { font-size:.95rem; font-weight:700; color:var(--text-main); margin-bottom:14px; }
.ln-hint { font-size:.78rem; font-weight:400; color:var(--text-muted); }
.ln-flash { padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:.9rem; line-height:1.5; }
.ln-flash.ok  { background:rgba(16,185,129,.1); color:#059669; border:1px solid rgba(16,185,129,.25); }
.ln-flash.err { background:rgba(239,68,68,.1); color:#dc2626; border:1px solid rgba(239,68,68,.25); }
.ln-sub { font-size:.85rem; font-weight:700; color:var(--text-main); margin:6px 0 10px; }

.nc-toggle-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:12px; }
.nc-toggle { display:flex; align-items:center; gap:10px; cursor:pointer; font-size:.9rem; color:var(--text-main); user-select:none; }
.nc-toggle input { display:none; }
.nc-sw { flex:0 0 42px; width:42px; height:24px; border-radius:99px; background:var(--border); position:relative; transition:background .2s; }
.nc-sw::after { content:''; position:absolute; top:2px; left:2px; width:20px; height:20px; border-radius:50%; background:#fff; transition:transform .2s; box-shadow:0 1px 3px rgba(0,0,0,.3); }
.nc-toggle input:checked + .nc-sw { background:var(--primary); }
.nc-toggle input:checked + .nc-sw::after { transform:translateX(18px); }

.nc-badge { font-size:.72rem; font-weight:700; padding:2px 9px; border-radius:99px; }
.nc-badge.on  { background:rgba(16,185,129,.14); color:#059669; }
.nc-badge.off { background:rgba(239,68,68,.12); color:#dc2626; }

.nc-line-status { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; font-size:.88rem; margin-bottom:14px; padding-bottom:14px; border-bottom:1px solid var(--border); }
.nc-ok  { color:#059669; font-size:.78rem; }
.nc-bad { color:#dc2626; font-size:.78rem; }

.nc-row { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:8px 0; border-bottom:1px dashed var(--border); font-size:.88rem; }
.nc-pill { border:none; cursor:pointer; font-size:.76rem; font-weight:700; padding:5px 14px; border-radius:99px; font-family:inherit; }
.nc-pill.on  { background:rgba(16,185,129,.14); color:#059669; }
.nc-pill.off { background:rgba(148,163,184,.18); color:var(--text-muted); }

.nc-admins { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:8px; }
.nc-admin { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:8px 12px; background:var(--bg-surface-alt,rgba(0,0,0,.03)); border-radius:9px; font-size:.85rem; }
.nc-chip { font-size:.66rem; font-weight:700; padding:2px 7px; border-radius:5px; background:rgba(148,163,184,.18); color:var(--text-muted); }
.nc-chip.on { background:rgba(16,185,129,.16); color:#059669; }
</style>

<?php include '../templates/footer_admin.php'; ?>
