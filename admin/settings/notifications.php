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

/** อ่าน config LINE ปัจจุบัน — 'line' = บอทหลัก · 'line_reports' = บอทรายงานเช้า-เย็น */
function line_cfg_load(PDO $pdo, string $platform = 'line'): array {
    $s = $pdo->prepare("SELECT page_name, access_token, secret_key FROM chat_platform_config WHERE platform = ?");
    $s->execute([$platform]);
    $r = $s->fetch(PDO::FETCH_ASSOC);
    return $r ?: ['page_name' => '', 'access_token' => '', 'secret_key' => ''];
}

$flash = '';
$flash_type = 'ok';
$test = null;      // ['label'=>..., 'ok'=>bool, 'detail'=>string] — ผลทดสอบยิงแจ้งเตือน
$conn = null;      // ผลทดสอบการเชื่อมต่อ LINE API (line_bot_info)

// ── AJAX: สวิตช์ auto-save (สวิตช์รวม / กลุ่ม / รายคน) — ไม่มีปุ่มบันทึก ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax'] ?? '') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? '';
    $val    = ($_POST['val'] ?? '') === '1' ? 1 : 0;
    try {
        if ($action === 'toggle_setting') {
            $allowed = ['notify_line_enabled', 'notify_morning_enabled', 'notify_evening_enabled', 'notify_jobs_enabled'];
            $key = $_POST['key'] ?? '';
            if (!in_array($key, $allowed, true)) throw new Exception('key ไม่ถูกต้อง');
            notif_set($pdo, $key, (string)$val);
        } elseif ($action === 'toggle_group') {
            // ผู้รับแยกต่อบอท: duty 'jobs' (บอทหลัก) / 'reports' (บอทรายงาน)
            $gid = $_POST['group_id'] ?? '';
            $col = (($_POST['duty'] ?? '') === 'reports') ? 'recv_reports' : 'recv_jobs';
            if ($gid === '') throw new Exception('ไม่พบกลุ่ม');
            $pdo->prepare("UPDATE line_groups SET $col = ? WHERE group_id = ?")->execute([$val, $gid]);
        } elseif ($action === 'toggle_admin') {
            $aid = (int)($_POST['admin_id'] ?? 0);
            $col = (($_POST['duty'] ?? '') === 'reports') ? 'line_notify_reports' : 'line_notify_jobs';
            if ($aid <= 0) throw new Exception('ไม่พบ admin');
            $pdo->prepare("UPDATE admin_users SET $col = ? WHERE id = ?")->execute([$val, $aid]);
        } else {
            throw new Exception('action ไม่ถูกต้อง');
        }
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        http_response_code(422);
        $msg = (strpos($e->getMessage(), 'recv_') !== false || strpos($e->getMessage(), 'line_notify_') !== false)
             ? 'ยังไม่ได้รัน migration_line_per_duty_recipients.sql บนฐานข้อมูลนี้'
             : $e->getMessage();
        echo json_encode(['ok' => false, 'err' => $msg]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // channel ที่ฟอร์ม config ส่งมา: 'line' (บอทหลัก) หรือ 'line_reports' (บอทรายงาน)
    $channel = in_array($_POST['channel'] ?? '', ['line', 'line_reports'], true) ? $_POST['channel'] : 'line';

    if ($action === 'save_line') {
        // บันทึก token/secret ของ LINE Messaging API (ตาม channel)
        $page_name = trim($_POST['page_name'] ?? '');
        $token     = trim($_POST['access_token'] ?? '');
        $secret    = trim($_POST['secret_key'] ?? '');
        if ($channel === 'line_reports' && $token === '' && $secret === '') {
            // เว้นว่างทั้งคู่ = ปิดบอทรายงาน กลับไปใช้บอทหลักส่งรายงาน
            $pdo->prepare("DELETE FROM chat_platform_config WHERE platform = 'line_reports'")->execute();
            $flash = 'ปิดบอทรายงานแล้ว — รายงานเช้า-เย็นจะส่งผ่านบอทหลักตามเดิม';
        } elseif ($token === '' || $secret === '') {
            $flash = 'กรุณากรอกทั้ง Channel access token และ Channel secret';
            $flash_type = 'err';
        } else {
            try {
                $pdo->prepare("
                    INSERT INTO chat_platform_config (platform, page_name, access_token, secret_key, updated_at)
                    VALUES (?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                        page_name = VALUES(page_name),
                        access_token = VALUES(access_token),
                        secret_key = VALUES(secret_key),
                        updated_at = NOW()
                ")->execute([$channel, $page_name, $token, $secret]);
                $flash = 'บันทึก token/secret แล้ว — กด "ทดสอบการเชื่อมต่อ" เพื่อยืนยันว่าถูกต้อง';
            } catch (Throwable $e) {
                // platform ยังเป็น ENUM เดิม (ยังไม่รัน migration) → insert 'line_reports' จะพัง
                $flash = ($channel === 'line_reports')
                       ? 'ยังไม่ได้รัน migration_line_reports_channel.sql บนฐานข้อมูลนี้'
                       : 'บันทึกไม่สำเร็จ: ' . $e->getMessage();
                $flash_type = 'err';
            }
        }

    } elseif ($action === 'test_conn') {
        // ทดสอบการเชื่อมต่อ LINE API ด้วย token ที่บันทึกไว้ (ตาม channel)
        $cfg  = line_cfg_load($pdo, $channel);
        $conn = line_bot_info($pdo, $cfg['access_token']);

    } elseif ($action === 'test_line') {
        // ยิงทดสอบผ่านบอทหลัก → ผู้รับชุด jobs (การ์ดงานซ่อม)
        $out = line_alert_send($pdo, [line_text_msg("ทดสอบบอทหลัก — ถ้าเห็นข้อความนี้แปลว่าการ์ดงานซ่อมพร้อมใช้งาน")], 'jobs');
        $ok = ($out['sent'] ?? 0) > 0;
        $test = ['label' => 'บอทหลัก', 'ok' => $ok,
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

// บอทรายงาน (ตัวที่ 2 — แยกโควต้า) : ไม่ตั้ง = รายงานออกบอทหลัก
$rep_cfg   = line_cfg_load($pdo, 'line_reports');
$rep_ready = !empty($rep_cfg['access_token']);

$host        = $_SERVER['HTTP_HOST'];
$webhook_url = 'https://' . $host . '/admin/cron/line_hook.php';

// ผู้รับแยกต่อ duty — คอลัมน์ยังไม่ migrate → fallback ค่าเดิม (สวิตช์จะเซฟไม่ได้ + toast บอก migration)
$groups = [];
try {
    $groups = $pdo->query("SELECT group_id, group_name, is_active, recv_jobs, recv_reports FROM line_groups ORDER BY created_at ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    try {
        $groups = $pdo->query("SELECT group_id, group_name, is_active, is_active AS recv_jobs, is_active AS recv_reports FROM line_groups ORDER BY created_at ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e2) {}
}
try {
    $admins = $pdo->query("SELECT id, username, role, line_user_id, line_notify_jobs, line_notify_reports FROM admin_users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    try {
        $admins = $pdo->query("SELECT id, username, role, line_user_id, line_notify_enabled AS line_notify_jobs, line_notify_enabled AS line_notify_reports FROM admin_users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e2) {
        $admins = $pdo->query("SELECT id, username, role, line_user_id, 1 AS line_notify_jobs, 1 AS line_notify_reports FROM admin_users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
}
$line_linked = count(array_filter($admins, fn($a) => !empty($a['line_user_id'])));

/** รายชื่อผู้รับ (กลุ่ม + admin) ของบอทหนึ่ง — สวิตช์ auto-save พร้อม data-duty */
function nc_render_recipients(array $groups, array $admins, string $duty, int $line_linked): void {
    $gcol = $duty === 'reports' ? 'recv_reports' : 'recv_jobs';
    $acol = $duty === 'reports' ? 'line_notify_reports' : 'line_notify_jobs';

    echo '<div class="ln-sub">กลุ่มที่รับจากบอทนี้</div>';
    if (!$groups) {
        echo '<p class="ln-hint">ยังไม่มีกลุ่ม — เชิญบอทเข้ากลุ่ม LINE แล้วมันจะโผล่ที่นี่</p>';
    } else {
        foreach ($groups as $gr) {
            $name = htmlspecialchars($gr['group_name'] ?: $gr['group_id']);
            echo '<div class="nc-row"><span><span class="material-symbols-rounded" style="font-size:18px;vertical-align:-3px;">group</span> ' . $name . '</span>';
            if ((int)$gr['is_active']) {
                echo '<label class="nc-toggle" style="margin:0;"><input type="checkbox" class="nc-auto" data-action="toggle_group" data-duty="' . $duty . '"'
                   . ' data-group="' . htmlspecialchars($gr['group_id']) . '" ' . ((int)$gr[$gcol] ? 'checked' : '') . '><span class="nc-sw"></span></label>';
            } else {
                echo '<span class="nc-chip">บอทไม่ได้อยู่ในกลุ่มแล้ว</span>';
            }
            echo '</div>';
        }
    }

    echo '<div class="ln-sub" style="margin-top:16px;">Admin รายคน (' . $line_linked . ' จาก ' . count($admins) . ' คนที่เชื่อม LINE)</div>';
    echo '<div class="nc-admins">';
    foreach ($admins as $a) {
        echo '<div class="nc-admin"><span>' . htmlspecialchars($a['username'])
           . ' <small class="ln-hint">' . htmlspecialchars($a['role']) . '</small></span>';
        if (!empty($a['line_user_id'])) {
            echo '<label class="nc-toggle" style="margin:0;"><input type="checkbox" class="nc-auto" data-action="toggle_admin" data-duty="' . $duty . '"'
               . ' data-admin="' . (int)$a['id'] . '" ' . ((int)$a[$acol] ? 'checked' : '') . '><span class="nc-sw"></span></label>';
        } else {
            echo '<span class="nc-chip">ไม่ได้เชื่อม</span>';
        }
        echo '</div>';
    }
    echo '</div>';
}

/** แถบโควต้า push เดือนนี้ของบอทหนึ่ง */
function nc_render_quota(?array $q): void {
    if ($q === null) return;
    echo '<div class="nc-quota">';
    if (!empty($q['ok'])) {
        $limit = !empty($q['limited']) ? (int)$q['limit'] : 0;
        $used  = (int)$q['used'];
        $pct   = $limit > 0 ? min(100, (int)round($used / $limit * 100)) : 0;
        $color = $pct >= 90 ? '#dc2626' : ($pct >= 70 ? '#f59e0b' : '#06c755');
        echo '<div class="nc-quota-head"><span>โควต้าข้อความเดือนนี้</span><b' . ($pct >= 90 ? ' style="color:#dc2626;"' : '') . '>'
           . number_format($used) . ($limit ? ' / ' . number_format($limit) : '') . '</b></div>';
        if ($limit) {
            echo '<div class="nc-quota-bar"><span style="width:' . $pct . '%;background:' . $color . ';"></span></div>';
            if ($used >= $limit) echo '<p class="ln-hint" style="color:#dc2626;margin:8px 0 0;">โควต้าเต็มแล้ว — LINE จะส่งไม่ได้ (429) จนกว่าจะรีเซ็ตวันที่ 1 ของเดือนหน้า</p>';
        } else {
            echo '<p class="ln-hint" style="margin:0;">แพลนนี้ไม่จำกัดจำนวนข้อความ</p>';
        }
    } else {
        echo '<p class="ln-hint" style="margin:0;">ดึงโควต้าไม่สำเร็จ — ' . htmlspecialchars($q['err'] ?? '') . '</p>';
    }
    echo '</div>';
}

// โควต้า push เดือนนี้ (GET calls — ไม่กินโควต้า) แยกต่อบอท
$quota_main = $line_ready ? line_quota_status($pdo, $line_cfg['access_token']) : null;
$quota_rep  = $rep_ready  ? line_quota_status($pdo, $rep_cfg['access_token'])  : null;

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

    <!-- สวิตช์ใหญ่: ช่อง LINE ทั้งระบบ (ปิด = เงียบทุกบอท) -->
    <div class="ln-card nc-master">
        <label class="nc-toggle" style="margin:0;">
            <input type="checkbox" class="nc-auto" data-action="toggle_setting" data-key="notify_line_enabled" <?= $on('notify_line_enabled') ? 'checked' : '' ?>>
            <span class="nc-sw"></span>
            <span>ช่อง <b>LINE</b> ทั้งระบบ</span>
        </label>
        <span id="line-badge" class="nc-badge <?= $on('notify_line_enabled') ? 'on' : 'off' ?>">
            <?= $on('notify_line_enabled') ? 'เปิด' : 'ปิด' ?>
        </span>
        <span class="ln-hint nc-master-hint">ปิด = เงียบทุกบอททุกแจ้งเตือน ·
            admin เชื่อมบัญชีผ่านบอทหลัก ·
            <a href="/admin/cron/line_links.php" style="color:var(--primary);">จัดการการเชื่อม</a>
        </span>
    </div>

    <div class="nc-cards">
    <div class="nc-col">
    <!-- ── แผงบอทหลัก: การ์ดงานซ่อม + ตอบแชต ── -->
    <div class="ln-card">
        <div class="ln-label" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <span class="material-symbols-rounded" style="color:#06c755;">build</span> บอทหลัก
            <span class="ln-hint" style="font-weight:400;">การ์ดงานซ่อม + ตอบแชต</span>
            <?= $line_ready
                ? '<span class="nc-badge on">' . htmlspecialchars($line_cfg['page_name'] ?: 'พร้อม') . '</span>'
                : '<span class="nc-badge off">ยังไม่ตั้ง token</span>' ?>
        </div>

        <?php nc_render_quota($quota_main); ?>

        <div class="nc-duty">
            <label class="nc-toggle">
                <input type="checkbox" class="nc-auto" data-action="toggle_setting" data-key="notify_jobs_enabled" <?= $on('notify_jobs_enabled') ? 'checked' : '' ?>>
                <span class="nc-sw"></span>
                <span>การ์ด<b>งานซ่อม</b> (เปิดงาน/แก้ไข ทันที)</span>
            </label>
        </div>

        <?php nc_render_recipients($groups, $admins, 'jobs', $line_linked); ?>

        <form method="POST" style="margin-top:14px;border-top:1px solid var(--border);padding-top:14px;">
            <input type="hidden" name="action" value="test_line">
            <button type="submit" class="cmns-btn cmns-btn-line" onclick="return confirm('ยิงข้อความทดสอบจากบอทหลัก ไปผู้รับชุดงานซ่อมจริงเลยนะ?');">
                <span class="material-symbols-rounded" style="font-size:16px;">wifi_tethering</span> ยิงทดสอบบอทหลัก
            </button>
        </form>
    </div>

    <!-- config บอทหลัก -->
    <div class="ln-card" id="line-config" style="scroll-margin-top:80px;">
        <div class="ln-label" style="display:flex;align-items:center;gap:8px;">
            <span class="material-symbols-rounded" style="color:#06c755;">key</span> บอทหลัก — การ์ดงานซ่อม + ตอบแชต
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
            <input type="hidden" name="channel" value="line">
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
    </div><!-- /.nc-col ซ้าย (บอทหลัก) -->

    <div class="nc-col">
    <!-- ── แผงบอทรายงาน: สรุปงานซ่อมเช้า-เย็น ── -->
    <div class="ln-card">
        <div class="ln-label" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <span class="material-symbols-rounded" style="color:#06c755;">campaign</span> บอทรายงาน
            <span class="ln-hint" style="font-weight:400;">สรุปงานซ่อมเช้า-เย็น</span>
            <?= $rep_ready
                ? '<span class="nc-badge on">' . htmlspecialchars($rep_cfg['page_name'] ?: 'พร้อม') . '</span>'
                : '<span class="nc-badge off">ใช้บอทหลักส่งแทน</span>' ?>
        </div>

        <?php nc_render_quota($quota_rep); ?>
        <?php if (!$rep_ready): ?>
        <p class="ln-hint" style="margin:0 0 14px;">ยังไม่ตั้ง token บอทรายงาน — รายงานเช้า-เย็นจะออกจาก<b>บอทหลัก</b> (กินโควต้าบอทหลัก) · ตั้งได้ที่การ์ดด้านล่าง</p>
        <?php endif; ?>

        <div class="nc-duty">
            <label class="nc-toggle">
                <input type="checkbox" class="nc-auto" data-action="toggle_setting" data-key="notify_morning_enabled" <?= $on('notify_morning_enabled') ? 'checked' : '' ?>>
                <span class="nc-sw"></span>
                <span>รอบ <b>เช้า</b> (07:00)</span>
            </label>
            <label class="nc-toggle">
                <input type="checkbox" class="nc-auto" data-action="toggle_setting" data-key="notify_evening_enabled" <?= $on('notify_evening_enabled') ? 'checked' : '' ?>>
                <span class="nc-sw"></span>
                <span>รอบ <b>เย็น</b> (19:00)</span>
            </label>
        </div>

        <?php nc_render_recipients($groups, $admins, 'reports', $line_linked); ?>

        <div class="nc-btn-row" style="margin-top:14px;border-top:1px solid var(--border);padding-top:14px;">
            <form method="POST" style="margin:0;">
                <input type="hidden" name="action" value="test_morning">
                <button type="submit" class="cmns-btn cmns-btn-line" onclick="return confirm('ยิงรายงานเช้าไปผู้รับจริงชุดรายงานเลยนะ?');">
                    <span class="material-symbols-rounded" style="font-size:16px;">wb_sunny</span> ยิงรายงานเช้า
                </button>
            </form>
            <form method="POST" style="margin:0;">
                <input type="hidden" name="action" value="test_evening">
                <button type="submit" class="cmns-btn cmns-btn-line" onclick="return confirm('ยิงรายงานเย็นไปผู้รับจริงชุดรายงานเลยนะ?');">
                    <span class="material-symbols-rounded" style="font-size:16px;">nightlight</span> ยิงรายงานเย็น
                </button>
            </form>
        </div>
        <p class="ln-hint" style="margin-top:10px;">ปุ่มยิงเคารพสวิตช์เช้า/เย็น + ช่อง LINE ด้านบน (ปิดอยู่จะข้าม)</p>
    </div>

    <!-- config บอทรายงาน -->
    <div class="ln-card" id="line-reports-config">
        <div class="ln-label" style="display:flex;align-items:center;gap:8px;">
            <span class="material-symbols-rounded" style="color:#06c755;">smart_toy</span> บอทรายงานเช้า-เย็น
            <span class="nc-badge <?= $rep_ready ? 'on' : 'off' ?>"><?= $rep_ready ? 'ใช้งานอยู่' : 'ยังไม่ตั้ง' ?></span>
        </div>

        <p class="ln-hint" style="line-height:1.6;margin:0 0 14px;">
            OA ตัวที่ 2 สำหรับรายงานเช้า-เย็นโดยเฉพาะ — <b>แยกโควต้า</b>จากบอทหลัก
            (สร้าง channel ใหม่ใต้ <b>provider เดิม</b>ใน LINE Developers เพื่อให้ userId เดิมใช้ได้เลย) ·
            ไม่ต้องตั้ง webhook · ทุกคน/ทุกกลุ่มที่รับรายงานต้อง<b>แอดบอทตัวนี้เป็นเพื่อน / เชิญเข้ากลุ่ม</b>ด้วย ·
            เว้นว่างทั้งคู่แล้วบันทึก = ปิด กลับไปใช้บอทหลัก
        </p>

        <form method="POST">
            <input type="hidden" name="channel" value="line_reports">
            <div class="ln-field">
                <label>ชื่อ Channel / OA (ไว้จำ)</label>
                <input type="text" name="page_name" value="<?= htmlspecialchars($rep_cfg['page_name']) ?>" placeholder="เช่น CMNS Reports">
            </div>
            <div class="ln-field">
                <label>Channel access token (long-lived)</label>
                <textarea name="access_token" rows="3" placeholder="วาง token จากแท็บ Messaging API"><?= htmlspecialchars($rep_cfg['access_token']) ?></textarea>
            </div>
            <div class="ln-field">
                <label>Channel secret</label>
                <input type="text" name="secret_key" value="<?= htmlspecialchars($rep_cfg['secret_key']) ?>" placeholder="32 ตัวอักษร">
            </div>

            <div class="nc-btn-row">
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

/* แถบสวิตช์ใหญ่ (ช่อง LINE ทั้งระบบ) */
.nc-master { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.nc-master-hint { margin-left:auto; text-align:right; }

/* สวิตช์ duty ประจำแผงบอท */
.nc-duty { display:flex; gap:20px; flex-wrap:wrap; margin-bottom:14px; padding-bottom:14px; border-bottom:1px solid var(--border); }

.nc-toggle { display:flex; align-items:center; gap:10px; cursor:pointer; font-size:.9rem; color:var(--text-main); user-select:none; }
.nc-toggle input { display:none; }
.nc-sw { flex:0 0 42px; width:42px; height:24px; border-radius:99px; background:var(--border); position:relative; transition:background .2s; }
.nc-sw::after { content:''; position:absolute; top:2px; left:2px; width:20px; height:20px; border-radius:50%; background:#fff; transition:transform .2s; box-shadow:0 1px 3px rgba(0,0,0,.3); }
.nc-toggle input:checked + .nc-sw { background:var(--primary); }
.nc-toggle input:checked + .nc-sw::after { transform:translateX(18px); }

.nc-badge { font-size:.72rem; font-weight:700; padding:2px 9px; border-radius:99px; }
.nc-badge.on  { background:rgba(16,185,129,.14); color:#059669; }
.nc-badge.off { background:rgba(239,68,68,.12); color:#dc2626; }

.nc-quota { margin-bottom:14px; padding-bottom:14px; border-bottom:1px solid var(--border); }
.nc-quota-head { display:flex; align-items:center; justify-content:space-between; gap:12px; font-size:.85rem; color:var(--text-main); margin-bottom:8px; }
.nc-quota-bar { height:8px; border-radius:99px; background:var(--border); overflow:hidden; }
.nc-quota-bar span { display:block; height:100%; border-radius:99px; transition:width .3s ease; }

.nc-row { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:8px 0; border-bottom:1px dashed var(--border); font-size:.88rem; }

/* toast ยืนยัน auto-save (มุมล่างกลางจอ โผล่แป๊บเดียว) */
#nc-toast { position:fixed; bottom:24px; left:50%; transform:translateX(-50%) translateY(8px);
    background:#1d1d1f; color:#fff; padding:9px 18px; border-radius:99px;
    font-size:.82rem; font-weight:600; opacity:0; pointer-events:none;
    transition:opacity .2s ease, transform .2s ease; z-index:999; white-space:nowrap; max-width:90vw;
    overflow:hidden; text-overflow:ellipsis; }
#nc-toast.show { opacity:1; transform:translateX(-50%) translateY(0); }
#nc-toast.err { background:#dc2626; }

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

/* ── iPad landscape ขึ้นไป: แผงบอทข้างละคอลัมน์ ── */
@media (min-width:1024px) {
    .cmns-wrapper { max-width:1180px !important; }
    .nc-cards { display:flex; gap:18px; align-items:flex-start; }
    .nc-col { flex:1; min-width:0; }
    .nc-col > .ln-card:last-child { margin-bottom:0; }
}
/* จอแคบ: hint ของแถบสวิตช์ใหญ่ลงบรรทัดใหม่ */
@media (max-width:700px) {
    .nc-master-hint { margin-left:0; width:100%; text-align:left; }
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
// ── สวิตช์ auto-save: ยิง POST ทันทีตอนกด · fail = ดีดสวิตช์กลับ + toast แดง ──
(function(){
    let toastTimer;
    function toast(msg, err){
        let t = document.getElementById('nc-toast');
        if (!t) { t = document.createElement('div'); t.id = 'nc-toast'; document.body.appendChild(t); }
        t.textContent = msg;
        t.className = err ? 'err' : '';
        requestAnimationFrame(() => t.classList.add('show'));
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => t.classList.remove('show'), err ? 3200 : 1400);
    }
    function updateLineBadge(on){
        const b = document.getElementById('line-badge');
        if (!b) return;
        b.className = 'nc-badge ' + (on ? 'on' : 'off');
        b.textContent = on ? 'เปิด' : 'ปิด';
    }
    document.querySelectorAll('.nc-auto').forEach(cb => {
        cb.addEventListener('change', () => {
            const fd = new FormData();
            fd.append('ajax', '1');
            fd.append('action', cb.dataset.action);
            fd.append('val', cb.checked ? '1' : '0');
            if (cb.dataset.key)   fd.append('key', cb.dataset.key);
            if (cb.dataset.group) fd.append('group_id', cb.dataset.group);
            if (cb.dataset.admin) fd.append('admin_id', cb.dataset.admin);
            if (cb.dataset.duty)  fd.append('duty', cb.dataset.duty);
            cb.disabled = true;
            fetch(location.pathname, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(j => {
                    if (!j.ok) throw new Error(j.err || 'บันทึกไม่สำเร็จ');
                    toast(cb.checked ? 'เปิดแล้ว' : 'ปิดแล้ว');
                    if (cb.dataset.key === 'notify_line_enabled') updateLineBadge(cb.checked);
                })
                .catch(e => {
                    cb.checked = !cb.checked; // ดีดกลับสถานะเดิม
                    toast(e.message || 'บันทึกไม่สำเร็จ', true);
                })
                .finally(() => { cb.disabled = false; });
        });
    });
})();

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
