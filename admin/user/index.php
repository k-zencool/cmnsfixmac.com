<?php
// === SETUP ===
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

// ★★★ พระเจ้าเท่านั้นที่เข้าได้! ★★★
require_role(['super_admin']);

// === LOGIC ===
$pageTitle = "จัดการผู้ใช้งาน";

$success_message = '';
if (isset($_GET['delete_success'])) {
    $success_message = "ลบผู้ใช้งานเรียบร้อยแล้ว!";
}

// --- ดึงข้อมูล User ทั้งหมด (ยกเว้นรหัสผ่าน) ---
$stmt = $pdo->query("SELECT id, username, role, created_at FROM admin_users ORDER BY id ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);


// === TEMPLATES ===
include '../../templates/header_admin.php';
include '../../templates/sidebar_admin.php';
?>

<main class="main" id="main-content">
    <div class="topbar">
        <span><?= htmlspecialchars($pageTitle) ?></span>
    </div>

    <div class="section-header">
        <h2><?= htmlspecialchars($pageTitle) ?></h2>
        <a href="form.php" class="btn-primary">+ เพิ่ม User ใหม่</a>
    </div>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Role (ยศ)</th>
                    <th>วันที่สร้าง</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($users) > 0): ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['id']) ?></td>
                            <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                            <td><span class="badge"><?= htmlspecialchars($user['role']) ?></span></td>
                            <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                            <td>
                                <a href="form.php?id=<?= $user['id'] ?>" class="btn-edit">แก้ไข</a>
                                <?php
                                // ★★★ ป้องกันการฆ่าตัวตาย (ลบ User ตัวเอง) ★★★
                                if ($user['id'] !== $_SESSION['admin_id']):
                                ?>
                                    <a href="delete.php?id=<?= $user['id'] ?>" class="btn-delete" onclick="return confirm('แน่ใจนะว่าจะลบ User นี้?')">ลบ</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center;">ไม่มีข้อมูลผู้ใช้งาน</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php include '../../templates/footer_admin.php'; ?>