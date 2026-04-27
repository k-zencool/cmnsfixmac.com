<?php
/********************************************************************
 * admin/warranty/policy_form.php
 * เพิ่ม / แก้ไข / ปิดการใช้งาน / เปิดใช้งาน / ลบนโยบาย
 ********************************************************************/
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_perms(['warranty.policy.update']);

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';
$msg = $err = '';

/* ---------- โหลดของเดิม ---------- */
$policy = null;
if ($id) {
    $st = $pdo->prepare("SELECT * FROM warranty_terms WHERE id=?");
    $st->execute([$id]);
    $policy = $st->fetch(PDO::FETCH_ASSOC);
    if (!$policy) { $err = 'ไม่พบนโยบาย'; $id = 0; }
}

/* ---------- ACTION ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // บันทึก/แก้ไข
    if ($action === 'save') {
        $ver   = trim(isset($_POST['version']) ? $_POST['version'] : '');
        $title = trim(isset($_POST['title']) ? $_POST['title'] : '');
        $body  = trim(isset($_POST['body'])   ? $_POST['body']   : '');
        $from  = isset($_POST['effective_from']) && $_POST['effective_from'] !== '' ? $_POST['effective_from'] : null;
        $to    = isset($_POST['effective_to'])   && $_POST['effective_to']   !== '' ? $_POST['effective_to']   : null;
        $def   = isset($_POST['is_default']) ? 1 : 0;

        if ($ver === '' || $title === '') {
            $err = 'กรอกเวอร์ชันและชื่อเรื่อง';
        } else {
            if ($id) {
                $sql = "UPDATE warranty_terms
                           SET version=?, title=?, body=?, effective_from=?, effective_to=?, is_default=?
                         WHERE id=?";
                $pdo->prepare($sql)->execute([$ver,$title,$body,$from,$to,$def,$id]);
                $msg = 'บันทึกการแก้ไขแล้ว';
            } else {
                $sql = "INSERT INTO warranty_terms(version,title,body,effective_from,effective_to,is_default)
                        VALUES (?,?,?,?,?,?)";
                $pdo->prepare($sql)->execute([$ver,$title,$body,$from,$to,$def]);
                $id  = (int)$pdo->lastInsertId();
                $msg = 'เพิ่มนโยบายใหม่แล้ว';
            }
        }
    }

    // ปิดการใช้งาน: ตั้ง effective_to เมื่อวาน และยกเลิก default
    if ($action === 'close' && $policy) {
        $pdo->prepare("
            UPDATE warranty_terms
               SET effective_to = DATE_SUB(CURDATE(), INTERVAL 1 DAY),
                   is_default   = 0
             WHERE id=?
        ")->execute([$policy['id']]);
        $msg = 'ปิดการใช้งานแล้ว';
    }

    // เปิดใช้งาน: ตั้ง effective_from = วันนี้, effective_to = NULL
    // พร้อมปิดฉบับอื่นที่กำลังมีผลอยู่ให้สิ้นสุดเมื่อวาน เพื่อไม่ให้ช่วงเวลาทับกัน
    if ($action === 'activate' && $policy) {
        $pdo->beginTransaction();
        try {
            // ปิดฉบับอื่นที่กำลังมีผลอยู่
            $pdo->exec("
                UPDATE warranty_terms
                   SET effective_to = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                 WHERE COALESCE(effective_from,'1000-01-01') <= CURDATE()
                   AND COALESCE(effective_to,'9999-12-31') >= CURDATE()
                   AND id <> ".(int)$policy['id']."
            ");
            // เปิดฉบับนี้
            $st = $pdo->prepare("
                UPDATE warranty_terms
                   SET effective_from = CURDATE(),
                       effective_to   = NULL
                 WHERE id=?
            ");
            $st->execute([$policy['id']]);
            $pdo->commit();
            $msg = 'เปิดใช้งานนโยบายแล้ว';
        } catch (Throwable $e) {
            $pdo->rollBack();
            $err = 'ไม่สามารถเปิดใช้งานได้: '.$e->getMessage();
        }
    }

    // ลบ (ห้ามลบถ้ากำลังมีผลหรือเป็นค่าเริ่มต้น)
    if ($action === 'delete' && $policy) {
        $chk = $pdo->prepare("
            SELECT
              (COALESCE(effective_from,'1000-01-01') <= CURDATE()
               AND COALESCE(effective_to,'9999-12-31') >= CURDATE()) AS is_active,
              is_default
            FROM warranty_terms WHERE id=?");
        $chk->execute([$policy['id']]);
        $row = $chk->fetch(PDO::FETCH_ASSOC);
        if (!empty($row['is_active'])) {
            $err = 'ห้ามลบนโยบายที่กำลังมีผลอยู่';
        } elseif (!empty($row['is_default'])) {
            $err = 'ห้ามลบนโยบายที่เป็นค่าเริ่มต้น';
        } else {
            $pdo->prepare("DELETE FROM warranty_terms WHERE id=?")->execute([$policy['id']]);
            header('Location: index.php?tab=policy&msg=ลบนโยบายแล้ว');
            exit;
        }
    }

    // reload after action
    if ($id) {
        $st = $pdo->prepare("SELECT * FROM warranty_terms WHERE id=?");
        $st->execute([$id]);
        $policy = $st->fetch(PDO::FETCH_ASSOC);
    }
}

/* ---------- สถานะปัจจุบันเพื่อโชว์ปุ่ม ---------- */
$is_active = false;
if ($policy) {
    $st = $pdo->prepare("
        SELECT (COALESCE(effective_from,'1000-01-01') <= CURDATE()
            AND COALESCE(effective_to,'9999-12-31') >= CURDATE()) AS a
        FROM warranty_terms WHERE id=?");
    $st->execute([$policy['id']]);
    $is_active = (bool)$st->fetchColumn();
}
?>
<?php include __DIR__ . '/../../templates/header_admin.php'; ?>
<?php include __DIR__ . '/../../templates/sidebar_admin.php'; ?>

<main class="main" id="main-content">
  <div class="topbar">
    <span><?= $id ? 'แก้ไขนโยบาย' : 'เพิ่มนโยบาย' ?></span>
    <a href="index.php?tab=policy" class="view-site">กลับรายการนโยบาย</a>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>

  <form method="post" class="policy-form card">
    <input type="hidden" name="action" value="save">

    <div class="form-grid">
      <div class="form-row">
        <label>เวอร์ชัน <span class="req">*</span></label>
        <input class="input" type="text" name="version" value="<?= h(isset($policy['version'])?$policy['version']:'') ?>" placeholder="เช่น 1.0 / 2025.01">
      </div>
      <div class="form-row">
        <label>ชื่อเรื่อง <span class="req">*</span></label>
        <input class="input" type="text" name="title" value="<?= h(isset($policy['title'])?$policy['title']:'') ?>" placeholder="เช่น เงื่อนไขรับประกันสินค้า">
      </div>

      <div class="form-row full">
        <label>เนื้อหา</label>
        <textarea class="textarea" name="body" rows="10" placeholder="พิมพ์เงื่อนไขนโยบาย..."><?= h(isset($policy['body'])?$policy['body']:'') ?></textarea>
      </div>

      <div class="form-row">
        <label>มีผลตั้งแต่</label>
        <input class="input" type="date" name="effective_from" value="<?= h(isset($policy['effective_from'])?$policy['effective_from']:'') ?>">
        <div class="hint">ว่างไว้ = ใช้ย้อนหลังไม่ได้</div>
      </div>
      <div class="form-row">
        <label>สิ้นสุด</label>
        <input class="input" type="date" name="effective_to" value="<?= h(isset($policy['effective_to'])?$policy['effective_to']:'') ?>">
        <div class="hint">ว่างไว้ = ไม่มีกำหนด</div>
      </div>

      <div class="form-row full">
        <label class="checkline">
          <input type="checkbox" name="is_default" <?= !empty($policy['is_default'])?'checked':'' ?>>
          <span>ตั้งเป็น “ฉบับค่าเริ่มต้น”</span>
        </label>
      </div>
    </div>

    <div class="card-foot actions">
      <div></div>
      <div>
        <button class="btn-primary" type="submit">บันทึก</button>
      </div>
    </div>
  </form>

  <?php if ($policy): ?>
    <div class="card actions-row">
      <div class="btn-group">
        <?php if ($is_active): ?>
          <form method="post" class="inline" onsubmit="return confirm('ยืนยันปิดการใช้งานนโยบายนี้?');">
            <input type="hidden" name="action" value="close">
            <button type="submit" class="btn-secondary">ปิดการใช้งาน</button>
          </form>
        <?php else: ?>
          <form method="post" class="inline" onsubmit="return confirm('ยืนยันเปิดใช้งานนโยบายนี้ตั้งแต่วันนี้?');">
            <input type="hidden" name="action" value="activate">
            <button type="submit" class="btn-secondary">เปิดใช้งาน</button>
          </form>
        <?php endif; ?>

        <form method="post" class="inline" onsubmit="return confirm('ลบนโยบายนี้ถาวร? ระบบจะไม่ให้ลบหากกำลังมีผลหรือเป็นค่าเริ่มต้น');">
          <input type="hidden" name="action" value="delete">
          <button type="submit" class="btn-danger">ลบ</button>
        </form>
      </div>
      <div class="muted small">
        เปิดใช้งานจะตั้ง “มีผลตั้งแต่วันนี้” และปิดฉบับอื่นที่กำลังมีผลโดยอัตโนมัติ
      </div>
    </div>
  <?php endif; ?>
</main>

<style>
/* layout basics */
.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.04);margin:12px 0}
.policy-form .input,.policy-form .textarea{width:100%;border:1px solid #e5e7eb;border-radius:8px;padding:10px 12px;background:#fff}
.policy-form .textarea{resize:vertical}
.req{color:#ef4444}
.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;padding:12px}
.form-row.full{grid-column:1/-1}
.checkline{display:inline-flex;align-items:center;gap:8px}
.hint{color:#6b7280;font-size:12px;margin-top:4px}
.card-foot.actions{display:flex;align-items:center;justify-content:space-between;padding:12px;border-top:1px solid #eee;background:#fafafa}
.inline{display:inline}
.actions-row{display:flex;align-items:center;justify-content:space-between;padding:12px}
.btn-group{display:flex;gap:8px}
.btn-primary{background:#2563eb;border:none;color:#fff;padding:8px 12px;border-radius:8px;cursor:pointer}
.btn-secondary{background:#f3f4f6;border:1px solid #e5e7eb;color:#374151;padding:8px 12px;border-radius:8px;cursor:pointer;text-decoration:none}
.btn-danger{background:#dc2626;border:none;color:#fff;padding:8px 12px;border-radius:8px;cursor:pointer}
.alert{margin:12px 0;padding:10px;border-radius:8px}
.alert-success{background:#e7f7ef;color:#065f46;border:1px solid #a7f3d0}
.alert-danger{background:#fde8e8;color:#7f1d1d;border:1px solid #fecaca}
.muted{color:#6b7280}
.small{font-size:12px}
@media (max-width: 900px){ .form-grid{grid-template-columns:1fr} }
</style>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>
