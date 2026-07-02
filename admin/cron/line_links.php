<?php
/**
 * admin/cron/line_links.php — จัดการ whitelist LINE ของพนักงาน (เฉพาะ super_admin)
 *
 * - ดูคนที่ทักบอทมา (pending) แล้วจับคู่ userId กับบัญชี admin
 * - ปลดการเชื่อม (unlink) ได้
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
    } elseif ($action === 'unlink') {
        $admin_id = (int)($_POST['admin_id'] ?? 0);
        if ($admin_id) {
            $pdo->prepare("UPDATE admin_users SET line_user_id=NULL, line_display_name=NULL, line_linked_at=NULL WHERE id=?")
                ->execute([$admin_id]);
            $flash = 'ปลดการเชื่อมแล้ว';
        }
    }
    header("Location: line_links.php?ok=" . rawurlencode($flash));
    exit;
}

$pending = $pdo->query("SELECT * FROM line_pending_links ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$linked  = $pdo->query("SELECT id, username, role, line_user_id, line_display_name, line_linked_at FROM admin_users WHERE line_user_id IS NOT NULL ORDER BY line_linked_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$admins  = $pdo->query("SELECT id, username, role FROM admin_users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "เชื่อม LINE พนักงาน";
include __DIR__ . '/../templates/header_admin.php';
?>
<div style="padding:24px; max-width:900px; margin:0 auto; display:flex; flex-direction:column; gap:20px;">

    <?php if (!empty($_GET['ok'])): ?>
        <div style="background:rgba(16,185,129,.1); color:#10b981; padding:10px 16px; border-radius:10px; font-weight:600;">
            <?= htmlspecialchars($_GET['ok']) ?>
        </div>
    <?php endif; ?>

    <!-- รออนุมัติ -->
    <div style="background:var(--bg-surface); border:1px solid var(--border); border-radius:14px; padding:20px;">
        <h3 style="margin:0 0 14px; font-size:1rem; display:flex; align-items:center; gap:8px;">
            <span class="material-symbols-rounded" style="color:#f59e0b;">how_to_reg</span>
            รออนุมัติ (<?= count($pending) ?>)
        </h3>
        <?php if (empty($pending)): ?>
            <div style="color:var(--text-muted); font-size:.88rem;">ยังไม่มีคนทักบอทมารอลงทะเบียน</div>
        <?php else: foreach ($pending as $p): ?>
            <form method="POST" style="display:flex; align-items:center; gap:10px; padding:10px 0; border-bottom:1px solid var(--border); flex-wrap:wrap;">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="pending_id" value="<?= (int)$p['id'] ?>">
                <span style="font-family:monospace; font-weight:800; color:var(--primary); font-size:1rem;"><?= htmlspecialchars($p['code']) ?></span>
                <span style="font-size:.8rem; color:var(--text-muted); flex:1; min-width:160px; word-break:break-all;"><?= htmlspecialchars($p['line_user_id']) ?></span>
                <select name="admin_id" required style="padding:6px 10px; border:1px solid var(--border); border-radius:8px; background:var(--bg-surface-alt); color:var(--text-main);">
                    <option value="">— เลือกพนักงาน —</option>
                    <?php foreach ($admins as $a): ?>
                        <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['username']) ?> (<?= htmlspecialchars($a['role']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="cmns-btn cmns-btn-primary" style="padding:6px 16px;">อนุมัติ</button>
            </form>
        <?php endforeach; endif; ?>
    </div>

    <!-- เชื่อมแล้ว -->
    <div style="background:var(--bg-surface); border:1px solid var(--border); border-radius:14px; padding:20px;">
        <h3 style="margin:0 0 14px; font-size:1rem; display:flex; align-items:center; gap:8px;">
            <span class="material-symbols-rounded" style="color:#10b981;">link</span>
            เชื่อมแล้ว (<?= count($linked) ?>)
        </h3>
        <?php if (empty($linked)): ?>
            <div style="color:var(--text-muted); font-size:.88rem;">ยังไม่มีพนักงานเชื่อม LINE</div>
        <?php else: foreach ($linked as $l): ?>
            <div style="display:flex; align-items:center; gap:10px; padding:10px 0; border-bottom:1px solid var(--border); flex-wrap:wrap;">
                <span style="font-weight:700;"><?= htmlspecialchars($l['username']) ?></span>
                <span style="font-size:.78rem; color:var(--text-muted);"><?= htmlspecialchars($l['role']) ?></span>
                <span style="font-size:.78rem; color:var(--text-muted); flex:1; min-width:160px; word-break:break-all;"><?= htmlspecialchars($l['line_user_id']) ?></span>
                <form method="POST" onsubmit="return confirm('ปลดการเชื่อม LINE ของ <?= htmlspecialchars($l['username']) ?>?');">
                    <input type="hidden" name="action" value="unlink">
                    <input type="hidden" name="admin_id" value="<?= (int)$l['id'] ?>">
                    <button type="submit" class="cmns-btn cmns-btn-secondary" style="padding:6px 14px; color:#ef4444;">ปลด</button>
                </form>
            </div>
        <?php endforeach; endif; ?>
    </div>

</div>
<?php include __DIR__ . '/../templates/footer_admin.php'; ?>
