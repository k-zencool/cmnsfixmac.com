<?php
/********************************************************************
 * admin/manager/index.php
 * ศูนย์ควบคุมผู้จัดการ — Manager Control Center
 * เห็นทุก sensitive action ของ staff/admin + ย้อนกลับได้
 ********************************************************************/
session_start();
require_once '../../includes/db.php';
require_once '../../includes/manager_lib.php';

require_login();
require_perms(['manager.center']);

$can_reverse = mgr_can_control();

// ── Filters ──
$f_type   = trim($_GET['type']   ?? '');
$f_status = trim($_GET['status'] ?? '');
$f_q      = trim($_GET['q']      ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$per      = 40;
$offset   = ($page - 1) * $per;

$where  = [];
$params = [];
if ($f_type !== '')   { $where[] = 'action_type = ?'; $params[] = $f_type; }
if ($f_status !== '') { $where[] = 'status = ?';      $params[] = $f_status; }
if ($f_q !== '') {
    $where[] = '(summary LIKE ? OR actor_name LIKE ?)';
    $params[] = "%$f_q%"; $params[] = "%$f_q%";
}
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ── KPIs ──
$kpi = $pdo->query("
    SELECT
        COUNT(*)                                                        AS total_all,
        SUM(action_type IS NOT NULL AND DATE(created_at)=CURDATE())     AS today_cnt,
        SUM(CASE WHEN DATE(created_at)=CURDATE() THEN amount ELSE 0 END) AS today_amount,
        SUM(status='active' AND reversible=1)                           AS active_reversible,
        SUM(status='reversed')                                          AS reversed_cnt
    FROM manager_actions
")->fetch(PDO::FETCH_ASSOC);

// ── Total for pagination ──
$cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM manager_actions $where_sql");
$cnt_stmt->execute($params);
$total_rows = (int)$cnt_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per));

// ── Rows ──
$rows_stmt = $pdo->prepare("
    SELECT * FROM manager_actions
    $where_sql
    ORDER BY created_at DESC
    LIMIT $per OFFSET $offset
");
$rows_stmt->execute($params);
$rows = $rows_stmt->fetchAll(PDO::FETCH_ASSOC);

$type_options = ['requisition','price_set','stock_delete','stock_edit','donor_strip','to_sale','sale_status'];

$pageTitle = "ศูนย์ควบคุมผู้จัดการ";
include '../templates/header_admin.php';
?>

<style>
.mgr-wrap { padding: 4px 2px 40px; }
.mgr-head { display:flex; align-items:center; gap:12px; margin-bottom:20px; flex-wrap:wrap; }
.mgr-head h1 { font-size:1.5rem; font-weight:700; color:var(--text-main); margin:0; display:flex; align-items:center; gap:10px; }
.mgr-head .sub { color:var(--text-muted); font-size:.9rem; }

.mgr-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:14px; margin-bottom:22px; }
.kpi { background:var(--bg-surface); border:1px solid var(--border); border-radius:16px; padding:16px 18px; }
.kpi .k-label { font-size:.8rem; color:var(--text-muted); display:flex; align-items:center; gap:6px; }
.kpi .k-val { font-size:1.6rem; font-weight:700; color:var(--text-main); margin-top:6px; }
.kpi .k-val small { font-size:.85rem; font-weight:500; color:var(--text-muted); }

.mgr-filters { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; }
.mgr-filters select, .mgr-filters input { background:var(--bg-surface); border:1px solid var(--border); color:var(--text-main); border-radius:10px; padding:9px 12px; font-family:inherit; font-size:.9rem; }
.mgr-filters button { background:var(--primary); color:#fff; border:none; border-radius:10px; padding:9px 18px; font-weight:600; cursor:pointer; }
.mgr-filters .reset { background:var(--bg-surface-alt); color:var(--text-muted); text-decoration:none; display:inline-flex; align-items:center; padding:9px 14px; border-radius:10px; border:1px solid var(--border); }

.mgr-table-wrap { background:var(--bg-surface); border:1px solid var(--border); border-radius:16px; overflow:hidden; }
.mgr-table { width:100%; border-collapse:collapse; font-size:.9rem; }
.mgr-table th { text-align:left; padding:12px 14px; color:var(--text-muted); font-weight:600; font-size:.78rem; text-transform:uppercase; letter-spacing:.03em; border-bottom:1px solid var(--border); background:var(--bg-surface-alt); }
.mgr-table td { padding:13px 14px; border-bottom:1px solid var(--border); color:var(--text-main); vertical-align:middle; }
.mgr-table tr:last-child td { border-bottom:none; }
.mgr-table tr.reversed td { opacity:.55; }

.a-badge { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:20px; font-size:.78rem; font-weight:600; color:#fff; }
.a-badge .material-symbols-rounded { font-size:15px; }
.role-chip { display:inline-block; padding:2px 8px; border-radius:6px; font-size:.72rem; font-weight:600; background:var(--bg-surface-alt); color:var(--text-muted); border:1px solid var(--border); }
.st-active   { color:#10b981; font-weight:600; }
.st-reversed { color:#ef4444; font-weight:600; }
.amt { font-variant-numeric:tabular-nums; font-weight:600; }
.btn-reverse { background:rgba(239,68,68,.12); color:#ef4444; border:1px solid rgba(239,68,68,.3); border-radius:8px; padding:6px 12px; font-weight:600; font-size:.82rem; cursor:pointer; display:inline-flex; align-items:center; gap:4px; }
.btn-reverse:hover { background:rgba(239,68,68,.2); }
.btn-reverse[disabled] { opacity:.4; cursor:not-allowed; }
.actor-cell small { color:var(--text-muted); display:block; font-size:.75rem; }
.mgr-empty { padding:50px; text-align:center; color:var(--text-muted); }
.mgr-pager { display:flex; justify-content:center; gap:6px; margin-top:18px; }
.mgr-pager a, .mgr-pager span { padding:7px 12px; border-radius:8px; border:1px solid var(--border); color:var(--text-main); text-decoration:none; font-size:.85rem; }
.mgr-pager .cur { background:var(--primary); color:#fff; border-color:var(--primary); }
</style>

<div class="mgr-wrap">

    <div class="mgr-head">
        <h1><span class="material-symbols-rounded" style="color:var(--primary);">shield_person</span> ศูนย์ควบคุมผู้จัดการ</h1>
        <span class="sub">ทุกความเคลื่อนไหวด้านสต็อก/การเงินของทีม — ตรวจสอบและย้อนกลับได้</span>
    </div>

    <div class="mgr-kpis">
        <div class="kpi">
            <div class="k-label"><span class="material-symbols-rounded" style="font-size:16px;">bolt</span> วันนี้</div>
            <div class="k-val"><?= (int)$kpi['today_cnt'] ?> <small>รายการ</small></div>
        </div>
        <div class="kpi">
            <div class="k-label"><span class="material-symbols-rounded" style="font-size:16px;">payments</span> มูลค่าวันนี้</div>
            <div class="k-val">฿<?= number_format((float)$kpi['today_amount'], 0) ?></div>
        </div>
        <div class="kpi">
            <div class="k-label"><span class="material-symbols-rounded" style="font-size:16px;">undo</span> ย้อนได้</div>
            <div class="k-val"><?= (int)$kpi['active_reversible'] ?> <small>รายการ</small></div>
        </div>
        <div class="kpi">
            <div class="k-label"><span class="material-symbols-rounded" style="font-size:16px;">history</span> ถูกย้อนไปแล้ว</div>
            <div class="k-val"><?= (int)$kpi['reversed_cnt'] ?> <small>รายการ</small></div>
        </div>
    </div>

    <form class="mgr-filters" method="get">
        <select name="type">
            <option value="">— ทุกประเภท —</option>
            <?php foreach ($type_options as $t): $m = mgr_action_meta($t); ?>
                <option value="<?= $t ?>" <?= $f_type === $t ? 'selected' : '' ?>><?= htmlspecialchars($m['label']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status">
            <option value="">— ทุกสถานะ —</option>
            <option value="active"   <?= $f_status === 'active'   ? 'selected' : '' ?>>ยังใช้งาน</option>
            <option value="reversed" <?= $f_status === 'reversed' ? 'selected' : '' ?>>ถูกย้อน</option>
        </select>
        <input type="text" name="q" placeholder="ค้นหา รายการ / ชื่อคนทำ" value="<?= htmlspecialchars($f_q) ?>">
        <button type="submit"><span class="material-symbols-rounded" style="font-size:16px;vertical-align:-3px;">search</span> ค้นหา</button>
        <a class="reset" href="/admin/manager/">ล้าง</a>
    </form>

    <div class="mgr-table-wrap">
        <table class="mgr-table">
            <thead>
                <tr>
                    <th>เวลา</th>
                    <th>ประเภท</th>
                    <th>รายการ</th>
                    <th>คนทำ</th>
                    <th style="text-align:right;">มูลค่า</th>
                    <th>สถานะ</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="7" class="mgr-empty">ยังไม่มีความเคลื่อนไหว</td></tr>
            <?php else: foreach ($rows as $r):
                $meta = mgr_action_meta($r['action_type']);
                $is_rev = $r['status'] === 'reversed';
            ?>
                <tr class="<?= $is_rev ? 'reversed' : '' ?>" data-id="<?= (int)$r['id'] ?>">
                    <td style="white-space:nowrap; color:var(--text-muted); font-size:.82rem;">
                        <?= date('d/m/y', strtotime($r['created_at'])) ?><br><?= date('H:i', strtotime($r['created_at'])) ?>
                    </td>
                    <td>
                        <span class="a-badge" style="background:<?= $meta['color'] ?>;">
                            <span class="material-symbols-rounded"><?= $meta['icon'] ?></span><?= htmlspecialchars($meta['label']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($r['summary']) ?>
                        <?php if ($is_rev && $r['reverse_note']): ?>
                            <small style="display:block;color:#ef4444;">↩ <?= htmlspecialchars($r['reverse_note']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="actor-cell">
                        <?= htmlspecialchars($r['actor_name'] ?: '—') ?>
                        <small><span class="role-chip"><?= htmlspecialchars(role_label($r['actor_role'] ?? '')) ?></span></small>
                    </td>
                    <td style="text-align:right;" class="amt"><?= $r['amount'] !== null ? '฿' . number_format((float)$r['amount'], 0) : '—' ?></td>
                    <td>
                        <?php if ($is_rev): ?>
                            <span class="st-reversed">ถูกย้อน</span>
                            <small style="display:block;color:var(--text-muted);"><?= htmlspecialchars($r['reversed_name'] ?: '') ?></small>
                        <?php else: ?>
                            <span class="st-active">ใช้งาน</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;">
                        <?php if (!$is_rev && $can_reverse && $r['reversible']): ?>
                            <button class="btn-reverse" onclick="reverseAction(<?= (int)$r['id'] ?>, this)">
                                <span class="material-symbols-rounded" style="font-size:16px;">undo</span> ย้อน
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="mgr-pager">
        <?php
        $qs = function($p) use ($f_type, $f_status, $f_q) {
            return '?' . http_build_query(array_filter(['type'=>$f_type,'status'=>$f_status,'q'=>$f_q,'page'=>$p]));
        };
        for ($p = 1; $p <= $total_pages; $p++):
            if ($p == $page): ?>
                <span class="cur"><?= $p ?></span>
            <?php else: ?>
                <a href="<?= $qs($p) ?>"><?= $p ?></a>
            <?php endif;
        endfor; ?>
    </div>
    <?php endif; ?>

</div>

<script>
function reverseAction(id, btn) {
    Swal.fire({
        title: 'ย้อนรายการนี้?',
        text: 'ระบบจะคืนสต็อก/ยกเลิกผลของรายการนี้',
        icon: 'warning',
        input: 'text',
        inputPlaceholder: 'เหตุผล (ไม่บังคับ)',
        showCancelButton: true,
        confirmButtonText: 'ย้อนกลับ',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#ef4444',
    }).then((res) => {
        if (!res.isConfirmed) return;
        btn.disabled = true;
        const fd = new FormData();
        fd.append('action_id', id);
        fd.append('note', res.value || '');
        fetch('/admin/manager/process_reverse.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                Swal.fire({ icon: d.ok ? 'success' : 'error', title: d.msg, timer: d.ok ? 1400 : undefined, showConfirmButton: !d.ok });
                if (d.ok) setTimeout(() => location.reload(), 1200);
                else btn.disabled = false;
            })
            .catch(() => { Swal.fire('ผิดพลาด', 'เชื่อมต่อไม่ได้', 'error'); btn.disabled = false; });
    });
}
</script>

<?php include '../templates/footer_admin.php'; ?>
