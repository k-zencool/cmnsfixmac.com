<?php
/**
 * admin/cron/line_links.php — เชื่อม LINE พนักงาน + กลุ่มของบอท (เฉพาะ super_admin)
 *
 * - ดูคนที่ทักบอทมา (pending) แล้วจับคู่ userId กับบัญชี admin / ปลดการเชื่อม
 * - กลุ่มทั้งหมดที่บอทเคยอยู่: สวิตช์แจ้งเตือน (master) + แยกต่อบอท (งานซ่อม/รายงาน)
 *   สั่งบอทออกจากกลุ่ม / ลบประวัติกลุ่มที่ปิดแล้ว
 * ความปลอดภัย: เฉพาะ line_user_id ที่ถูกอนุมัติเท่านั้นที่บอทจะคุยด้วย/ส่งข้อมูลให้
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/line_helper.php';
require_login();

if (!is_super_admin()) {
    http_response_code(403);
    die('403 Forbidden: เฉพาะ super admin');
}

// ── AJAX: สวิตช์กลุ่ม auto-save (master + per-duty) — ไม่มีปุ่มบันทึก ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax'] ?? '') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    $gid = (int)($_POST['group_id'] ?? 0);
    $val = ($_POST['val'] ?? '') === '1' ? 1 : 0;
    try {
        if ($gid <= 0) throw new Exception('ไม่พบกลุ่ม');
        $which = $_POST['which'] ?? '';
        $col   = ['active' => 'is_active', 'jobs' => 'recv_jobs', 'reports' => 'recv_reports'][$which] ?? '';
        if ($col === '') throw new Exception('สวิตช์ไม่ถูกต้อง');
        $pdo->prepare("UPDATE line_groups SET $col = ?, updated_at = NOW() WHERE id = ?")->execute([$val, $gid]);
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        http_response_code(422);
        $msg = (strpos($e->getMessage(), 'recv_') !== false)
             ? 'ยังไม่ได้รัน migration_line_per_duty_recipients.sql บนฐานข้อมูลนี้'
             : $e->getMessage();
        echo json_encode(['ok' => false, 'err' => $msg]);
    }
    exit;
}

$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'approve') {
        $pending_id = (int)($_POST['pending_id'] ?? 0);
        $admin_id   = (int)($_POST['admin_id'] ?? 0);
        if ($pending_id && $admin_id) {
            $p = $pdo->prepare("SELECT * FROM line_pending_links WHERE id = ?");
            $p->execute([$pending_id]);
            $pend = $p->fetch(PDO::FETCH_ASSOC);
            if ($pend) {
                try {
                    $pdo->prepare("UPDATE admin_users SET line_user_id=?, line_display_name=?, line_linked_at=NOW() WHERE id=?")
                        ->execute([$pend['line_user_id'], $pend['display_name'], $admin_id]);
                    $pdo->prepare("DELETE FROM line_pending_links WHERE id=?")->execute([$pending_id]);
                    // แจ้งผู้ใช้ว่าได้รับสิทธิ์แล้ว (ถ้ามี token)
                    line_push($pdo, $pend['line_user_id'], "✅ ได้รับสิทธิ์เข้าถึงข้อมูลร้านแล้ว พิมพ์ /help เพื่อเริ่มใช้งาน");
                    $flash = 'อนุมัติเรียบร้อย';
                } catch (PDOException $e) {
                    $flash = 'ผิดพลาด: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'reject') {
        $pending_id = (int)($_POST['pending_id'] ?? 0);
        if ($pending_id) {
            $pdo->prepare("DELETE FROM line_pending_links WHERE id=?")->execute([$pending_id]);
            $flash = 'ลบคำขอแล้ว';
        }
    } elseif ($action === 'unlink') {
        $admin_id = (int)($_POST['admin_id'] ?? 0);
        if ($admin_id) {
            $pdo->prepare("UPDATE admin_users SET line_user_id=NULL, line_display_name=NULL, line_linked_at=NULL WHERE id=?")
                ->execute([$admin_id]);
            $flash = 'ปลดการเชื่อมแล้ว';
        }
    } elseif ($action === 'group_leave') {
        $gid = (int)($_POST['group_id'] ?? 0);
        if ($gid) {
            $g = $pdo->prepare("SELECT group_id FROM line_groups WHERE id=?");
            $g->execute([$gid]);
            $grp = $g->fetch(PDO::FETCH_ASSOC);
            if ($grp) {
                line_leave_group($pdo, $grp['group_id']);   // สั่งบอทหลักออกจากกลุ่ม
                $pdo->prepare("UPDATE line_groups SET is_active = 0, updated_at = NOW() WHERE id=?")->execute([$gid]);
                $flash = 'สั่งบอทออกจากกลุ่มแล้ว (ประวัติยังอยู่ — ลบได้จากรายการ)';
            }
        }
    } elseif ($action === 'group_delete') {
        // ลบเฉพาะกลุ่มที่ปิดอยู่ กันลบกลุ่มที่ยังใช้งานพลาด
        $gid = (int)($_POST['group_id'] ?? 0);
        if ($gid) {
            $pdo->prepare("DELETE FROM line_groups WHERE id = ? AND is_active = 0")->execute([$gid]);
            $flash = 'ลบประวัติกลุ่มแล้ว';
        }
    }
    header("Location: line_links.php?ok=" . rawurlencode($flash));
    exit;
}

$pending = $pdo->query("SELECT * FROM line_pending_links ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$linked  = $pdo->query("SELECT id, username, role, line_user_id, line_display_name, line_linked_at FROM admin_users WHERE line_user_id IS NOT NULL ORDER BY line_linked_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$admins  = $pdo->query("SELECT id, username, role FROM admin_users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

// กลุ่มทั้งหมด (รวมที่ปิด/บอทออกไปแล้ว) — คอลัมน์ per-duty ยังไม่ migrate → fallback
$groups = null;
try {
    $groups = $pdo->query("SELECT id, group_id, group_name, is_active, recv_jobs, recv_reports, created_at, updated_at
                           FROM line_groups ORDER BY is_active DESC, created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        $groups = $pdo->query("SELECT id, group_id, group_name, is_active, is_active AS recv_jobs, is_active AS recv_reports, created_at, updated_at
                               FROM line_groups ORDER BY is_active DESC, created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) { /* ตาราง line_groups ยังไม่ migrate */ }
}
$groups_active = is_array($groups) ? count(array_filter($groups, fn($g) => (int)$g['is_active'] === 1)) : 0;

$pageTitle = "เชื่อม LINE & กลุ่ม";
include __DIR__ . '/../templates/header_admin.php';
?>

<div class="lk-wrapper">
    <div style="margin-bottom:4px;">
        <a href="/admin/settings/notifications.php" class="cmns-back-link">
            <span class="material-symbols-rounded">arrow_back</span> การแจ้งเตือน
        </a>
    </div>

    <?php if (!empty($_GET['ok'])): ?>
    <div class="lk-flash"><?= htmlspecialchars($_GET['ok']) ?></div>
    <?php endif; ?>

    <!-- 1) รออนุมัติ -->
    <div class="lk-card">
        <div class="lk-label">
            <span class="material-symbols-rounded" style="color:#f59e0b;">how_to_reg</span>
            รออนุมัติ <span class="lk-count"><?= count($pending) ?></span>
        </div>
        <?php if (empty($pending)): ?>
            <p class="lk-hint">ยังไม่มีคนทักบอทมารอลงทะเบียน — ให้พนักงานแอดบอทหลักแล้วทักอะไรก็ได้ รหัสจะโผล่ที่นี่</p>
        <?php else: foreach ($pending as $p): ?>
            <div class="lk-row">
                <div class="lk-main">
                    <span class="lk-code"><?= htmlspecialchars($p['code']) ?></span>
                    <span class="lk-name"><?= htmlspecialchars($p['display_name'] ?: 'ไม่ทราบชื่อ') ?></span>
                    <span class="lk-sub"><?= htmlspecialchars($p['line_user_id']) ?></span>
                </div>
                <div class="lk-actions">
                    <form method="POST" style="display:flex;gap:8px;align-items:center;margin:0;">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="pending_id" value="<?= (int)$p['id'] ?>">
                        <select name="admin_id" required class="lk-select">
                            <option value="">— เลือกพนักงาน —</option>
                            <?php foreach ($admins as $a): ?>
                                <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['username']) ?> (<?= htmlspecialchars($a['role']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="cmns-btn cmns-btn-primary" style="padding:7px 16px;">อนุมัติ</button>
                    </form>
                    <form method="POST" style="margin:0;" onsubmit="return confirm('ลบคำขอนี้ทิ้ง?');">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="pending_id" value="<?= (int)$p['id'] ?>">
                        <button type="submit" class="lk-btn-ghost" title="ลบคำขอ"><span class="material-symbols-rounded">close</span></button>
                    </form>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- 2) เชื่อมแล้ว -->
    <div class="lk-card">
        <div class="lk-label">
            <span class="material-symbols-rounded" style="color:#10b981;">link</span>
            Admin ที่เชื่อมแล้ว <span class="lk-count"><?= count($linked) ?></span>
        </div>
        <?php if (empty($linked)): ?>
            <p class="lk-hint">ยังไม่มีพนักงานเชื่อม LINE</p>
        <?php else: foreach ($linked as $l): ?>
            <div class="lk-row">
                <div class="lk-main">
                    <span class="lk-name"><?= htmlspecialchars($l['username']) ?>
                        <small class="lk-hint"><?= htmlspecialchars($l['role']) ?></small></span>
                    <span class="lk-sub">
                        <?= htmlspecialchars($l['line_display_name'] ?: '-') ?>
                        <?php if ($l['line_linked_at']): ?> · เชื่อมเมื่อ <?= date('d/m/Y', strtotime($l['line_linked_at'])) ?><?php endif; ?>
                    </span>
                </div>
                <div class="lk-actions">
                    <form method="POST" style="margin:0;" onsubmit="return confirm('ปลดการเชื่อม LINE ของ <?= htmlspecialchars($l['username']) ?>?');">
                        <input type="hidden" name="action" value="unlink">
                        <input type="hidden" name="admin_id" value="<?= (int)$l['id'] ?>">
                        <button type="submit" class="lk-btn-danger">ปลด</button>
                    </form>
                </div>
            </div>
        <?php endforeach; endif; ?>
        <p class="lk-hint" style="margin:10px 0 0;">เปิด/ปิดว่าใครรับแจ้งเตือนจากบอทไหน ไปที่
            <a href="/admin/settings/notifications.php" style="color:var(--primary);">หน้าการแจ้งเตือน</a></p>
    </div>

    <!-- 3) กลุ่มทั้งหมด -->
    <div class="lk-card">
        <div class="lk-label">
            <span class="material-symbols-rounded" style="color:#06c755;">groups</span>
            กลุ่มของบอท <span class="lk-count"><?= $groups_active ?> เปิด / <?= is_array($groups) ? count($groups) : 0 ?> ทั้งหมด</span>
        </div>
        <p class="lk-hint" style="margin:0 0 6px;">
            เชิญบอทเข้ากลุ่ม LINE แล้วกลุ่มจะโผล่ที่นี่อัตโนมัติ · สวิตช์บันทึกทันที ·
            <b>บอทรายงานต้องถูกเชิญเข้ากลุ่มเองด้วย</b> (ระบบเช็คให้ไม่ได้ — บอทรายงานไม่มี webhook)
        </p>

        <?php if ($groups === null): ?>
            <p class="lk-hint" style="color:#dc2626;">⚠️ ยังไม่ได้สร้างตาราง <code>line_groups</code> บน DB (รัน migration_line_groups.sql ก่อน)</p>
        <?php elseif (empty($groups)): ?>
            <p class="lk-hint">ยังไม่มีกลุ่ม — เชิญบอทเข้ากลุ่ม LINE แล้วรีเฟรชหน้านี้</p>
        <?php else: foreach ($groups as $g): $active = (int)$g['is_active'] === 1; ?>
            <div class="lk-group <?= $active ? '' : 'off' ?>">
                <div class="lk-group-head">
                    <span class="lk-name">
                        <span class="material-symbols-rounded" style="font-size:18px;vertical-align:-3px;color:<?= $active ? '#06c755' : 'var(--text-muted)' ?>;">group</span>
                        <?= htmlspecialchars($g['group_name'] ?: 'กลุ่มไม่มีชื่อ') ?>
                    </span>
                    <span class="lk-chip <?= $active ? 'on' : '' ?>"><?= $active ? 'แจ้งเตือนอยู่' : 'ปิดอยู่' ?></span>
                    <span class="lk-sub" style="flex:1;"><?= htmlspecialchars($g['group_id']) ?></span>
                </div>
                <div class="lk-group-ctrl">
                    <label class="lk-toggle" title="ปิด = เงียบทั้งกลุ่ม ทุกบอท">
                        <input type="checkbox" class="lk-auto" data-group="<?= (int)$g['id'] ?>" data-which="active" <?= $active ? 'checked' : '' ?>>
                        <span class="lk-sw"></span><span>แจ้งเตือนกลุ่มนี้</span>
                    </label>
                    <label class="lk-toggle" title="การ์ดงานซ่อมจากบอทหลัก">
                        <input type="checkbox" class="lk-auto" data-group="<?= (int)$g['id'] ?>" data-which="jobs" <?= (int)$g['recv_jobs'] ? 'checked' : '' ?> <?= $active ? '' : 'disabled' ?>>
                        <span class="lk-sw"></span><span>งานซ่อม</span>
                    </label>
                    <label class="lk-toggle" title="รายงานเช้า-เย็นจากบอทรายงาน">
                        <input type="checkbox" class="lk-auto" data-group="<?= (int)$g['id'] ?>" data-which="reports" <?= (int)$g['recv_reports'] ? 'checked' : '' ?> <?= $active ? '' : 'disabled' ?>>
                        <span class="lk-sw"></span><span>รายงาน</span>
                    </label>
                    <span style="margin-left:auto;display:flex;gap:8px;">
                        <?php if ($active): ?>
                        <form method="POST" style="margin:0;" onsubmit="return confirm('ให้บอทหลักออกจากกลุ่มนี้เลยนะ?');">
                            <input type="hidden" name="action" value="group_leave">
                            <input type="hidden" name="group_id" value="<?= (int)$g['id'] ?>">
                            <button type="submit" class="lk-btn-danger">ออกจากกลุ่ม</button>
                        </form>
                        <?php else: ?>
                        <form method="POST" style="margin:0;" onsubmit="return confirm('ลบประวัติกลุ่มนี้ทิ้งถาวร?');">
                            <input type="hidden" name="action" value="group_delete">
                            <input type="hidden" name="group_id" value="<?= (int)$g['id'] ?>">
                            <button type="submit" class="lk-btn-ghost" title="ลบประวัติ"><span class="material-symbols-rounded">delete</span></button>
                        </form>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<style>
.lk-wrapper { max-width:860px; margin:0 auto; padding:24px 16px; display:flex; flex-direction:column; gap:16px; }
.lk-card { background:var(--bg-surface); border:1px solid var(--border); border-radius:14px; padding:20px 22px; box-shadow:var(--shadow); }
.lk-label { display:flex; align-items:center; gap:8px; font-size:.95rem; font-weight:700; color:var(--text-main); margin-bottom:12px; }
.lk-count { font-size:.72rem; font-weight:700; padding:2px 10px; border-radius:99px; background:rgba(148,163,184,.16); color:var(--text-muted); }
.lk-hint { font-size:.78rem; font-weight:400; color:var(--text-muted); margin:0; line-height:1.6; }
.lk-flash { background:rgba(16,185,129,.1); color:#059669; border:1px solid rgba(16,185,129,.25); padding:11px 16px; border-radius:10px; font-size:.88rem; font-weight:600; }

.lk-row { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:11px 0; border-bottom:1px dashed var(--border); flex-wrap:wrap; }
.lk-row:last-of-type { border-bottom:none; }
.lk-main { display:flex; align-items:baseline; gap:10px; flex-wrap:wrap; min-width:0; }
.lk-name { font-weight:700; font-size:.9rem; color:var(--text-main); }
.lk-sub { font-size:.74rem; color:var(--text-muted); word-break:break-all; }
.lk-code { font-family:monospace; font-weight:800; color:var(--primary); font-size:1.05rem; letter-spacing:1px; }
.lk-actions { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.lk-select { padding:7px 10px; border:1.5px solid var(--border); border-radius:9px; background:var(--bg-surface-alt,var(--bg-surface)); color:var(--text-main); font-size:.85rem; font-family:inherit; }
.lk-select:focus { outline:none; border-color:var(--primary); }

.lk-btn-danger { border:1px solid rgba(239,68,68,.35); background:transparent; color:#ef4444; cursor:pointer;
    font-size:.8rem; font-weight:700; padding:6px 14px; border-radius:9px; font-family:inherit; transition:background .15s; }
.lk-btn-danger:hover { background:rgba(239,68,68,.08); }
.lk-btn-ghost { border:none; background:transparent; color:var(--text-muted); cursor:pointer; padding:4px; border-radius:8px; display:inline-flex; }
.lk-btn-ghost:hover { color:#ef4444; background:rgba(239,68,68,.08); }
.lk-btn-ghost .material-symbols-rounded { font-size:19px; }

/* กลุ่ม */
.lk-group { padding:12px 0; border-bottom:1px dashed var(--border); }
.lk-group:last-of-type { border-bottom:none; }
.lk-group.off .lk-name, .lk-group.off .lk-sub { opacity:.55; }
.lk-group-head { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:8px; }
.lk-group-ctrl { display:flex; align-items:center; gap:18px; flex-wrap:wrap; }
.lk-chip { font-size:.68rem; font-weight:700; padding:2px 9px; border-radius:99px; background:rgba(148,163,184,.18); color:var(--text-muted); }
.lk-chip.on { background:rgba(6,199,85,.14); color:#06a34a; }

/* สวิตช์ (สไตล์เดียวกับหน้าการแจ้งเตือน) */
.lk-toggle { display:flex; align-items:center; gap:8px; cursor:pointer; font-size:.82rem; color:var(--text-main); user-select:none; }
.lk-toggle input { display:none; }
.lk-sw { flex:0 0 38px; width:38px; height:22px; border-radius:99px; background:var(--border); position:relative; transition:background .2s; }
.lk-sw::after { content:''; position:absolute; top:2px; left:2px; width:18px; height:18px; border-radius:50%; background:#fff; transition:transform .2s; box-shadow:0 1px 3px rgba(0,0,0,.3); }
.lk-toggle input:checked + .lk-sw { background:var(--primary); }
.lk-toggle input:checked + .lk-sw::after { transform:translateX(16px); }
.lk-toggle input:disabled + .lk-sw { opacity:.4; }
.lk-toggle input:disabled ~ span { opacity:.5; }

/* toast auto-save */
#lk-toast { position:fixed; bottom:24px; left:50%; transform:translateX(-50%) translateY(8px);
    background:#1d1d1f; color:#fff; padding:9px 18px; border-radius:99px;
    font-size:.82rem; font-weight:600; opacity:0; pointer-events:none;
    transition:opacity .2s ease, transform .2s ease; z-index:999; white-space:nowrap; max-width:90vw;
    overflow:hidden; text-overflow:ellipsis; }
#lk-toast.show { opacity:1; transform:translateX(-50%) translateY(0); }
#lk-toast.err { background:#dc2626; }

@media (max-width:600px) {
    .lk-card { padding:16px; border-radius:12px; }
    .lk-actions { width:100%; }
    .lk-actions form { flex:1; }
    .lk-select { flex:1; min-width:0; font-size:16px; }  /* กัน iOS ซูมตอนโฟกัส */
    .lk-group-ctrl { gap:12px; }
}
</style>

<script>
// สวิตช์กลุ่ม auto-save — fail = ดีดกลับ + toast แดง · เปิด/ปิด master รีโหลดเพื่ออัปเดต disabled ของ duty
(function(){
    let toastTimer;
    function toast(msg, err){
        let t = document.getElementById('lk-toast');
        if (!t) { t = document.createElement('div'); t.id = 'lk-toast'; document.body.appendChild(t); }
        t.textContent = msg;
        t.className = err ? 'err' : '';
        requestAnimationFrame(() => t.classList.add('show'));
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => t.classList.remove('show'), err ? 3200 : 1400);
    }
    document.querySelectorAll('.lk-auto').forEach(cb => {
        cb.addEventListener('change', () => {
            const fd = new FormData();
            fd.append('ajax', '1');
            fd.append('group_id', cb.dataset.group);
            fd.append('which', cb.dataset.which);
            fd.append('val', cb.checked ? '1' : '0');
            cb.disabled = true;
            fetch(location.pathname, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(j => {
                    if (!j.ok) throw new Error(j.err || 'บันทึกไม่สำเร็จ');
                    toast(cb.checked ? 'เปิดแล้ว' : 'ปิดแล้ว');
                    if (cb.dataset.which === 'active') { setTimeout(() => location.reload(), 500); return; }
                    cb.disabled = false;
                })
                .catch(e => {
                    cb.checked = !cb.checked;
                    toast(e.message || 'บันทึกไม่สำเร็จ', true);
                    cb.disabled = false;
                });
        });
    });
})();
</script>

<?php include __DIR__ . '/../templates/footer_admin.php'; ?>
