<?php
/**
 * admin/settings/notifications.php — Notification Center + LINE Messaging API (super_admin เท่านั้น)
 * รวมสองหน้าเข้าด้วยกัน:
 *  - คุมการแจ้งเตือน LINE: เปิด/ปิดช่อง, รอบเช้า-เย็น, จัดการผู้รับ (กลุ่ม/admin), ยิงทดสอบ + ยิงรายงานจริง
 *  - ตั้งค่า LINE Messaging API: token/secret/webhook + ทดสอบการเชื่อมต่อ
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

/** อ่าน config LINE ปัจจุบัน */
function line_cfg_load(PDO $pdo): array {
    $r = $pdo->query("SELECT page_name, access_token, secret_key FROM chat_platform_config WHERE platform='line'")
             ->fetch(PDO::FETCH_ASSOC);
    return $r ?: ['page_name' => '', 'access_token' => '', 'secret_key' => ''];
}

$flash = '';
$flash_type = 'ok';
$test = null;      // ['label'=>..., 'ok'=>bool, 'detail'=>string] — ผลทดสอบยิงแจ้งเตือน
$conn = null;      // ผลทดสอบการเชื่อมต่อ LINE API (line_bot_info)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_toggles') {
        notif_set($pdo, 'notify_morning_enabled', isset($_POST['morning']) ? '1' : '0');
        notif_set($pdo, 'notify_evening_enabled', isset($_POST['evening']) ? '1' : '0');
        notif_set($pdo, 'notify_line_enabled',    isset($_POST['line_on']) ? '1' : '0');
        $flash = 'บันทึกสวิตช์แจ้งเตือนแล้ว';

    } elseif ($action === 'save_line') {
        // บันทึก token/secret ของ LINE Messaging API
        $page_name = trim($_POST['page_name'] ?? '');
        $token     = trim($_POST['access_token'] ?? '');
        $secret    = trim($_POST['secret_key'] ?? '');
        if ($token === '' || $secret === '') {
            $flash = 'กรุณากรอกทั้ง Channel access token และ Channel secret';
            $flash_type = 'err';
        } else {
            $pdo->prepare("
                INSERT INTO chat_platform_config (platform, page_name, access_token, secret_key, updated_at)
                VALUES ('line', ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    page_name = VALUES(page_name),
                    access_token = VALUES(access_token),
                    secret_key = VALUES(secret_key),
                    updated_at = NOW()
            ")->execute([$page_name, $token, $secret]);
            $flash = 'บันทึก token/secret แล้ว — กด "ทดสอบการเชื่อมต่อ" เพื่อยืนยันว่าถูกต้อง';
        }

    } elseif ($action === 'test_conn') {
        // ทดสอบการเชื่อมต่อ LINE API ด้วย token ที่บันทึกไว้
        $cfg  = line_cfg_load($pdo);
        $conn = line_bot_info($pdo, $cfg['access_token']);

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
$line_cfg   = line_cfg_load($pdo);
$line_ready = !empty($line_cfg['access_token']);

$host        = $_SERVER['HTTP_HOST'];
$webhook_url = 'https://' . $host . '/admin/cron/line_hook.php';

$groups = [];
try { $groups = $pdo->query("SELECT group_id, group_name, is_active FROM line_groups ORDER BY created_at ASC")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}

$admins = $pdo->query("SELECT username, role, line_user_id FROM admin_users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$line_linked   = count(array_filter($admins, fn($a) => !empty($a['line_user_id'])));
$active_groups = count(array_filter($groups, fn($x) => (int)$x['is_active'] === 1));

$pageTitle = 'การแจ้งเตือน & LINE';
include '../templates/header_admin.php';
?>

<div class="cmns-wrapper" style="max-width:820px;">
    <div style="margin-bottom:20px;">
        <a href="/admin/settings/" class="cmns-back-link">
            <span class="material-symbols-rounded">arrow_back</span> กลับ
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

    <?php if ($conn !== null): ?>
        <?php if (($conn['code'] ?? 0) === 200 && !empty($conn['body']['basicId'])): ?>
        <div class="ln-flash ok">
            ✅ เชื่อมต่อสำเร็จ — token ใช้งานได้<br>
            Channel: <b><?= htmlspecialchars($conn['body']['displayName'] ?? '-') ?></b>
            (<?= htmlspecialchars($conn['body']['basicId'] ?? '-') ?>)
        </div>
        <?php else: ?>
        <div class="ln-flash err">
            ❌ ทดสอบไม่ผ่าน (HTTP <?= (int)($conn['code'] ?? 0) ?>) —
            <?= htmlspecialchars($conn['body']['message'] ?? $conn['err'] ?? 'token ไม่ถูกต้อง') ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="nc-cards">
    <div class="nc-col">
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
        <div class="nc-btn-row">
            <form method="POST" style="margin:0;">
                <input type="hidden" name="action" value="test_morning">
                <button type="submit" class="cmns-btn cmns-btn-line" onclick="return confirm('ยิงรายงานเช้าไปผู้รับจริงทั้งหมดเลยนะ?');">
                    <span class="material-symbols-rounded" style="font-size:16px;">wb_sunny</span> ยิงรายงานเช้า
                </button>
            </form>
            <form method="POST" style="margin:0;">
                <input type="hidden" name="action" value="test_evening">
                <button type="submit" class="cmns-btn cmns-btn-line" onclick="return confirm('ยิงรายงานเย็นไปผู้รับจริงทั้งหมดเลยนะ?');">
                    <span class="material-symbols-rounded" style="font-size:16px;">nightlight</span> ยิงรายงานเย็น
                </button>
            </form>
        </div>
    </div>

    <!-- 2) LINE ผู้รับ -->
    <div class="ln-card">
        <div class="ln-label" style="display:flex;align-items:center;gap:8px;">
            <span class="material-symbols-rounded" style="color:#06c755;">chat</span> LINE
            <span class="nc-badge <?= $on('notify_line_enabled') ? 'on' : 'off' ?>">
                <?= $on('notify_line_enabled') ? 'เปิด' : 'ปิด' ?>
            </span>
        </div>

        <div class="nc-line-status">
            <span>Channel: <b><?= htmlspecialchars($line_cfg['page_name'] ?: '-') ?></b>
                <?= $line_ready ? '<span class="nc-ok">● พร้อม</span>' : '<span class="nc-bad">● ยังไม่ตั้ง token</span>' ?>
            </span>
            <a href="#line-config" class="cmns-btn cmns-btn-secondary" style="font-size:13px;">
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
        <p class="ln-hint" style="margin-top:8px;">admin เชื่อมบัญชีเองผ่านบอท LINE (พิมพ์ในแชทบอทเพื่อขอรหัสลงทะเบียน) ·
            <a href="/admin/cron/line_links.php" style="color:var(--primary);">จัดการการเชื่อม</a>
        </p>

        <form method="POST" style="margin-top:12px;border-top:1px solid var(--border);padding-top:12px;">
            <input type="hidden" name="action" value="test_line">
            <button type="submit" class="cmns-btn cmns-btn-line" onclick="return confirm('ยิงข้อความทดสอบเข้า LINE (ทุกกลุ่มที่เปิด + admin ที่ link) จริงเลยนะ?');">
                <span class="material-symbols-rounded" style="font-size:16px;">wifi_tethering</span> ยิงทดสอบ LINE
            </button>
        </form>
    </div>
    </div><!-- /.nc-col ซ้าย -->

    <div class="nc-col">
    <!-- 3) LINE Messaging API config -->
    <div class="ln-card" id="line-config" style="scroll-margin-top:80px;">
        <div class="ln-label" style="display:flex;align-items:center;gap:8px;">
            <span class="material-symbols-rounded" style="color:#06c755;">key</span> ตั้งค่า LINE Messaging API
        </div>

        <!-- Webhook URL -->
        <div class="ln-field">
            <label>Webhook URL <span class="ln-hint">(วางใน LINE Developers → Messaging API → Webhook URL)</span></label>
            <div class="ln-copy">
                <input type="text" id="wh" value="<?= htmlspecialchars($webhook_url) ?>" readonly>
                <button type="button" class="cmns-btn cmns-btn-secondary" onclick="cp()">
                    <span class="material-symbols-rounded" style="font-size:16px;">content_copy</span> คัดลอก
                </button>
            </div>
        </div>

        <!-- Config form -->
        <form method="POST">
            <div class="ln-field">
                <label>ชื่อ Channel / OA (ไว้จำ)</label>
                <input type="text" name="page_name" value="<?= htmlspecialchars($line_cfg['page_name']) ?>" placeholder="เช่น API Test">
            </div>
            <div class="ln-field">
                <label>Channel access token (long-lived)</label>
                <textarea name="access_token" rows="3" placeholder="วาง token จากแท็บ Messaging API" required><?= htmlspecialchars($line_cfg['access_token']) ?></textarea>
                <span class="ln-hint">LINE Developers → Channel → แท็บ Messaging API → Channel access token → Issue</span>
            </div>
            <div class="ln-field">
                <label>Channel secret</label>
                <input type="text" name="secret_key" value="<?= htmlspecialchars($line_cfg['secret_key']) ?>" placeholder="32 ตัวอักษร" required>
                <span class="ln-hint">LINE Developers → Channel → แท็บ Basic settings → Channel secret</span>
            </div>

            <p class="ln-hint" style="line-height:1.6;margin:4px 0 0;">
                <b>ขั้นตอน:</b> 1) วาง token + secret แล้วกดบันทึก →
                2) กดทดสอบการเชื่อมต่อ ต้องขึ้นชื่อ channel ให้ตรง →
                3) เอา Webhook URL ด้านบนไปใส่ใน LINE Developers แล้วกด Verify (ต้องได้ Success)
            </p>

            <div class="nc-btn-row nc-config-btns">
                <button type="submit" name="action" value="save_line" class="cmns-btn cmns-btn-primary">
                    <span class="material-symbols-rounded" style="font-size:16px;">save</span> บันทึก token/secret
                </button>
                <button type="submit" name="action" value="test_conn" class="cmns-btn cmns-btn-secondary">
                    <span class="material-symbols-rounded" style="font-size:16px;">wifi_tethering</span> ทดสอบการเชื่อมต่อ
                </button>
            </div>
        </form>
    </div>
    </div><!-- /.nc-col ขวา -->
    </div><!-- /.nc-cards -->
</div><!-- /.cmns-wrapper -->

<style>
.ln-card { background:var(--bg-surface); border:1px solid var(--border); border-radius:14px; padding:20px 22px; margin-bottom:16px; box-shadow:var(--shadow); }
.ln-label { font-size:.95rem; font-weight:700; color:var(--text-main); margin-bottom:14px; }
.ln-hint { font-size:.78rem; font-weight:400; color:var(--text-muted); }
.ln-flash { padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:.9rem; line-height:1.5; }
.ln-flash.ok  { background:rgba(16,185,129,.1); color:#059669; border:1px solid rgba(16,185,129,.25); }
.ln-flash.err { background:rgba(239,68,68,.1); color:#dc2626; border:1px solid rgba(239,68,68,.25); }
.ln-sub { font-size:.85rem; font-weight:700; color:var(--text-main); margin:6px 0 10px; }

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

.nc-btn-row { display:flex; gap:10px; flex-wrap:wrap; }

/* ปุ่มกลับ — มาตรฐานเดียวกับหน้าอื่น (.cmns-back-link ใน inventory-dashboard.css) */
.cmns-back-link { display:inline-flex; align-items:center; gap:6px; color:var(--text-muted);
    text-decoration:none; font-size:13px; font-weight:800; padding:4px 8px; background:transparent;
    border:none; letter-spacing:.5px; transition:all .2s cubic-bezier(.4,0,.2,1); }
.cmns-back-link .material-symbols-rounded { font-size:18px; transition:transform .2s ease; }
.cmns-back-link:hover { color:var(--primary); text-shadow:0 0 10px rgba(37,99,235,.3); }
.cmns-back-link:hover .material-symbols-rounded { transform:translateX(-4px); }
.cmns-back-link:active { transform:scale(.95); opacity:.7; }

/* ── ปุ่มทั้งหน้า (คุมให้สวย/สม่ำเสมอ ทั้ง <button> และ <a>) ── */
.cmns-wrapper .cmns-btn {
    display:inline-flex; align-items:center; justify-content:center; gap:7px;
    padding:9px 16px; border-radius:10px; font-size:.88rem; font-weight:600;
    font-family:inherit; line-height:1; cursor:pointer; text-decoration:none;
    border:1px solid transparent; white-space:nowrap;
    transition:background .18s ease, border-color .18s ease, box-shadow .18s ease, transform .05s ease;
}
.cmns-wrapper .cmns-btn:active { transform:translateY(1px); }
.cmns-wrapper .cmns-btn .material-symbols-rounded { font-size:18px !important; line-height:1; }

.cmns-wrapper .cmns-btn-primary {
    background:var(--primary); color:#fff;
    box-shadow:0 1px 2px rgba(0,0,0,.12);
}
.cmns-wrapper .cmns-btn-primary:hover {
    background:color-mix(in srgb, var(--primary) 88%, #000);
    box-shadow:0 4px 12px -4px color-mix(in srgb, var(--primary) 60%, transparent);
}

.cmns-wrapper .cmns-btn-secondary {
    background:var(--bg-surface); color:var(--text-main);
    border-color:var(--border);
}
.cmns-wrapper .cmns-btn-secondary:hover {
    background:var(--bg-surface-alt, rgba(127,127,127,.06));
    border-color:var(--text-muted);
}

/* ปุ่ม "ยิง" ส่งออก LINE จริง — เขียว LINE ให้เด่นแยกจากปุ่มบันทึก */
.cmns-wrapper .cmns-btn-line {
    background:#06c755; color:#fff;
    box-shadow:0 1px 2px rgba(6,199,85,.25);
}
.cmns-wrapper .cmns-btn-line:hover {
    background:#05b34c;
    box-shadow:0 4px 12px -4px rgba(6,199,85,.55);
}

/* ── iPad landscape ขึ้นไป: 2 คอลัมน์ ความสูงเท่ากัน ── */
@media (min-width:1024px) {
    .cmns-wrapper { max-width:1180px !important; }
    .nc-cards { display:flex; gap:18px; align-items:stretch; }
    .nc-col { flex:1; min-width:0; display:flex; flex-direction:column; }
    /* กล่องขวา (คอลัมน์เดียว) ยืดเต็มความสูงให้เท่าซ้าย */
    .nc-col:last-child > .ln-card { flex:1; display:flex; flex-direction:column; }
    /* ฟอร์ม config ยืดเต็มกล่อง แล้วดันปุ่มชุดล่างลงชิดก้น */
    #line-config form { flex:1; display:flex; flex-direction:column; }
    #line-config .nc-config-btns { margin-top:auto; padding-top:16px; }
    /* กล่องสุดท้ายของแต่ละคอลัมน์ไม่ต้องมี margin ล่าง ให้ก้นตรงกัน */
    .nc-col > .ln-card:last-child { margin-bottom:0; }
}

/* ── iPad / mobile ── */
@media (max-width:900px) {
    .cmns-wrapper { max-width:100% !important; }
}
@media (max-width:600px) {
    .ln-card { padding:16px; border-radius:12px; }
    .ln-label { font-size:.9rem; }
    /* กันไม่ให้ iOS ซูมเองตอนโฟกัส input (ต้อง >=16px) */
    .ln-field input, .ln-field textarea, .ln-copy input { font-size:16px; padding:11px 13px; }
    /* copy webhook: วางปุ่มใต้ช่อง URL แทนข้างกัน */
    .ln-copy { flex-direction:column; }
    .ln-copy input { text-overflow:ellipsis; }
    /* ปุ่ม action เต็มความกว้าง แตะง่าย */
    .nc-btn-row { flex-direction:column; }
    .nc-btn-row form { width:100%; }
    .ln-card .cmns-btn { width:100%; justify-content:center; padding-top:11px; padding-bottom:11px; }
    /* สถานะ channel: ปุ่มแก้ token ลงบรรทัดใหม่เต็มกว้าง */
    .nc-line-status { align-items:stretch; }
    .nc-line-status > a { justify-content:center; }
}
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
