<?php
/********************************************************************
 * admin/warranty/policy_form.php — Create/Edit/Delete Warranty Policy
 * [GEMINI BUILT v1.1 - Corrected Version]
 * - Handles Create, Update, and Delete in one file.
 * - Fixes 'is_default' logic (unsets others).
 * - Includes CSRF protection.
 * - Retains form data on validation error (Fixes UX bug).
 ********************************************************************/
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/warranty_lib.php'; // กูassume มึงมี w_h() หรือ h() ในนี้
require_login();
require_perms(['warranty.policy.update']); // ต้องมีสิทธิ์นี้เท่านั้น

// Helper function (เผื่อมึงยังไม่มี)
if (!function_exists('w_h')) {
  function w_h($s)
  {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
  }
}
if (!function_exists('w_getv')) {
  function w_getv($k, $d = null)
  {
    return isset($_GET[$k]) ? trim($_GET[$k]) : $d;
  }
}

/* ---------------- STATE ---------------- */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;
$pageTitle = $isEdit ? "แก้ไขนโยบาย (ID: $id)" : "เพิ่มนโยบายใหม่";
$err = w_getv('err', '');
$msg = w_getv('msg', '');

// Data structure for the form
$data = [
  'version' => '',
  'title' => '',
  'body' => '',
  'effective_from' => date('Y-m-d'), // Default to today
  'effective_to' => '',
  'is_default' => 0
];

/* ---------------- HANDLE POST (SAVE/DELETE) ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // 1. CSRF Check (โคตรสำคัญ)
  if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    $err = 'Token ไม่ถูกต้อง, กรุณาลองใหม่';
    $data = $_POST; // Repopulate form
  } else {
    $action = $_POST['action'] ?? 'save';

    try {
      /* ===== DELETE ACTION ===== */
      if ($action === 'delete' && $isEdit) {
        $st_check = $pdo->prepare("SELECT is_default FROM warranty_terms WHERE id = :id");
        $st_check->execute([':id' => $id]);
        $policy = $st_check->fetch(PDO::FETCH_ASSOC);

        if ($policy && $policy['is_default'] == 1) {
          // ป้องกันการลบตัว Default
          header("Location: policy_form.php?id=$id&err=" . urlencode('ลบเวอร์ชันที่เป็น "ค่าเริ่มต้น" ไม่ได้!'));
          exit;
        }

        $st = $pdo->prepare("DELETE FROM warranty_terms WHERE id = :id");
        $st->execute([':id' => $id]);

        unset($_SESSION['csrf_token']); // ใช้ token แล้วลบทิ้ง
        header("Location: index.php?tab=policy&msg=" . urlencode('ลบนโยบาย (ID: ' . $id . ') สำเร็จ'));
        exit;
      }

      /* ===== SAVE ACTION (CREATE/UPDATE) ===== */
      // 1. Get data from POST
      $version = trim($_POST['version']);
      $title = trim($_POST['title']);
      $body = trim($_POST['body']);
      $effective_from = empty($_POST['effective_from']) ? null : $_POST['effective_from'];
      $effective_to = empty($_POST['effective_to']) ? null : $_POST['effective_to'];
      $is_default = isset($_POST['is_default']) ? 1 : 0;

      // 2. Validation
      $errors = [];
      if (empty($version)) $errors[] = 'ต้องระบุ "เวอร์ชัน"';
      if (empty($title)) $errors[] = 'ต้องระบุ "ชื่อเรื่อง"';
      // if (empty($body)) $errors[] = 'ต้องระบุ "เนื้อหานโยบาย"'; // กูเอาออก เผื่อมึงอยากเว้นว่าง

      if (!empty($errors)) {
        $err = implode(', ', $errors);
        // [FIX UX] คืนค่าที่มึงกรอกพลาดไป
        $data = $_POST;
      } else {
        // 3. Database Operation
        $pdo->beginTransaction();

        // 3a. [FIX LOGIC BUG] ถ้าติ๊ก 'is_default', ต้องล้างค่า default เก่าทั้งหมดออกก่อน
        if ($is_default == 1) {
          $st_clear = $pdo->prepare("UPDATE warranty_terms SET is_default = 0 WHERE is_default = 1 AND id != :current_id");
          $st_clear->execute([':current_id' => $id]); // กันเหนียว ไม่ล้างตัวเอง
        }

        // 3b. Prepare SQL
        if ($isEdit) {
          // UPDATE
          $sql = "UPDATE warranty_terms SET 
                    version = :v, 
                    title = :t, 
                    body = :b, 
                    effective_from = :f, 
                    effective_to = :t2, 
                    is_default = :d 
                  WHERE id = :id";
          $params = [
            ':v' => $version,
            ':t' => $title,
            ':b' => $body,
            ':f' => $effective_from,
            ':t2' => $effective_to,
            ':d' => $is_default,
            ':id' => $id
          ];
        } else {
          // INSERT
          $sql = "INSERT INTO warranty_terms 
                    (version, title, body, effective_from, effective_to, is_default, created_at) 
                  VALUES 
                    (:v, :t, :b, :f, :t2, :d, NOW())";
          $params = [
            ':v' => $version,
            ':t' => $title,
            ':b' => $body,
            ':f' => $effective_from,
            ':t2' => $effective_to,
            ':d' => $is_default
          ];
        }

        // 3c. Execute
        $st = $pdo->prepare($sql);
        $st->execute($params);
        if (!$isEdit) $id = $pdo->lastInsertId(); // เอา ID ใหม่มาใช้ต่อ

        $pdo->commit();
        unset($_SESSION['csrf_token']); // ใช้ token แล้วลบทิ้ง
        
        // ส่งกลับไปหน้า index หรือหน้า edit ตัวเองก็ได้ (กูเลือกกลับ index)
        header("Location: index.php?tab=policy&msg=" . urlencode($isEdit ? 'บันทึกการแก้ไขแล้ว' : 'เพิ่มนโยบายใหม่สำเร็จ'));
        exit;
      }
    } catch (PDOException $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $err = "Database Error: " . $e->getMessage();
      // [FIX UX] คืนค่าที่มึงกรอกพลาดไป
      $data = $_POST;
    }
  }
}

/* ---------------- LOAD DATA FOR EDIT (GET) ---------------- */
// ต้องเช็คด้วยว่าไม่ใช่ POST ที่ error (ไม่งั้น $data ที่กรอกพลาดจะโดนทับ)
if ($isEdit && $_SERVER['REQUEST_METHOD'] === 'GET') {
  $st = $pdo->prepare("SELECT * FROM warranty_terms WHERE id = :id");
  $st->execute([':id' => $id]);
  $policyData = $st->fetch(PDO::FETCH_ASSOC);

  if (!$policyData) {
    header("Location: index.php?tab=policy&err=" . urlencode('ไม่พบนโยบาย ID: ' . $id));
    exit;
  }
  // Load fetched data into form array
  $data = $policyData;
}

// Generate CSRF token if it doesn't exist or was used
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

/* ---------------- TEMPLATE ---------------- */
include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>
<main class="main" id="main-content">
  <div class="topbar">
    <span><?= w_h($pageTitle) ?></span>
    <a href="../../" class="view-site" target="_blank">ดูเว็บไซต์</a>
  </div>

  <div class="page-header">
    <h2 class="page-title"><?= w_h($pageTitle) ?></h2>
    <a href="index.php?tab=policy" class="btn-secondary">‹ กลับไปหน้ารายการ</a>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= w_h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= w_h($err) ?></div><?php endif; ?>

  <div class="form-card">
    <form action="policy_form.php<?= $isEdit ? '?id=' . $id : '' ?>" method="POST" id="policyForm">
      <input type="hidden" name="csrf_token" value="<?= w_h($csrf_token) ?>">

      <div class="form-grid-2">
        <div class="form-group">
          <label for="version">เวอร์ชัน (เช่น 1.0, 2025.11) <span class="req">*</span></label>
          <input type="text" id="version" name="version" value="<?= w_h($data['version']) ?>" required>
        </div>
        <div class="form-group">
          <label for="title">ชื่อเรื่อง / หัวข้อ <span class="req">*</span></label>
          <input type="text" id="title" name="title" value="<?= w_h($data['title']) ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label for="body">เนื้อหานโยบาย (ใส่ข้อความเป็น text ธรรมดา)</label>
        <textarea id="body" name="body" rows="15" class="mono-font"><?= w_h($data['body']) ?></textarea>
      </div>

      <div class="form-grid-2">
        <div class="form-group">
          <label for="effective_from">มีผลตั้งแต่ (YYYY-MM-DD)</label>
          <input type="date" id="effective_from" name="effective_from" value="<?= w_h($data['effective_from']) ?>">
          <div class="check-hint">ว่างไว้ = มีผลย้อนหลังได้ทั้งหมด</div>
        </div>
        <div class="form-group">
          <label for="effective_to">สิ้นสุด (เว้นว่าง = ไม่มีกำหนด)</label>
          <input type="date" id="effective_to" name="effective_to" value="<?= w_h($data['effective_to']) ?>">
           <div class="check-hint">ว่างไว้ = ไม่มีวันหมดอายุ</div>
        </div>
      </div>

      <div class="form-group-check">
        <label>
          <input type="checkbox" name="is_default" value="1" <?= !empty($data['is_default']) ? 'checked' : '' ?>>
          <span>ตั้งเป็นนโยบาย "ค่าเริ่มต้น"</span>
        </label>
        <div class="check-hint">
          หากเลือก, ระบบจะยกเลิก "ค่าเริ่มต้น" จากเวอร์ชันอื่นทันที<br>
          "ค่าเริ่มต้น" จะถูกใช้เมื่อไม่มีนโยบายใดที่ตรงกับช่วงเวลาปัจจุบัน
        </div>
      </div>

      <hr class="form-divider">

      <div class="form-actions">
        <button type="submit" name="action" value="save" class="btn-primary">
          <?= $isEdit ? 'บันทึกการแก้ไข' : 'สร้างนโยบาย' ?>
        </button>
        <a href="index.php?tab=policy" class="btn-secondary">ยกเลิก</a>

        <?php if ($isEdit): ?>
          <button type="button" class="btn-danger" id="btn-delete" style="margin-left: auto;">
            ลบนโยบายนี้
          </button>
        <?php endif; ?>
      </div>

    </form>
  </div>
</main>

<style>
  .req { color: #ef4444; }
  .page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
  }
  .page-title {
    margin: 0;
  }
  .form-card {
    background: #fff;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, .06);
    max-width: 900px;
    margin: 0 auto;
  }
  .form-group {
    margin-bottom: 16px;
  }
  .form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 6px;
  }
  .form-group input[type="text"],
  .form-group input[type="date"],
  .form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    box-sizing: border-box; /* Important */
  }
  .form-group textarea {
    line-height: 1.6;
  }
  .mono-font {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  }
  .form-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
  }
  .form-group-check {
    margin: 16px 0;
    background: #f8fafc;
    border: 1px solid #eef2f7;
    padding: 12px;
    border-radius: 8px;
  }
  .form-group-check label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
  }
  .form-group-check input[type="checkbox"] {
    width: 16px;
    height: 16px;
  }
  .check-hint {
    font-size: 13px;
    color: #64748b;
    margin-top: 6px;
    padding-left: 2px;
    line-height: 1.5;
  }
  .form-divider {
    border: 0;
    border-top: 1px solid #e5e7eb;
    margin: 24px 0;
  }
  .form-actions {
    display: flex;
    align-items: center;
    gap: 8px;
  }
  
  /* Buttons from your code */
  .btn-primary{background:#2563eb;border:none;color:#fff;padding:8px 12px;border-radius:8px;cursor:pointer}
  .btn-secondary{background:#f3f4f6;border:1px solid #e5e7eb;color:#374151;padding:8px 12px;border-radius:8px;cursor:pointer;text-decoration:none}
  .btn-danger{background:#dc2626;border:none;color:#fff;padding:8px 12px;border-radius:8px;cursor:pointer}
  
  /* Alerts from your code */
  .alert{margin:12px 0;padding:10px;border-radius:8px}
  .alert-success{background:#e7f7ef;color:#065f46;border:1px solid #a7f3d0}
  .alert-danger{background:#fde8e8;color:#7f1d1d;border:1px solid #fecaca}

  @media (max-width: 768px) {
    .form-grid-2 {
      grid-template-columns: 1fr;
      gap: 0;
    }
  }
</style>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    var deleteBtn = document.getElementById('btn-delete');
    var form = document.getElementById('policyForm');

    if (deleteBtn) {
      deleteBtn.addEventListener('click', function(e) {
        e.preventDefault();
        
        var version = document.getElementById('version').value || '???';
        if (confirm('มึงแน่ใจนะ? \nจะลบนโยบาย v' + version + ' นี้ทิ้งจริงๆ เหรอ? \nกู้คืนไม่ได้นะสัส!')) {
          // สร้าง input 'action' = 'delete' แล้ว submit form
          var input = document.createElement('input');
          input.type = 'hidden';
          input.name = 'action';
          input.value = 'delete';
          form.appendChild(input);
          form.submit();
        }
      });
    }
  });
</script>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>