<?php
// === SETUP ===
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin']); // พระเจ้าเท่านั้นที่เข้าได้!

// === LOGIC ===
$pageTitle = "เพิ่ม User ใหม่";
$errors = [];
$user = [ // ค่าเริ่มต้นสำหรับฟอร์มเปล่า
    'id' => null,
    'username' => '',
    'role' => 'staff' // กำหนด role เริ่มต้นเป็น staff
];
$is_edit_mode = false;

// --- ตรวจสอบว่าเป็นโหมด "แก้ไข" หรือไม่ ---
if (isset($_GET['id'])) {
    $is_edit_mode = true;
    $user_id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT id, username, role FROM admin_users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) die("ไม่พบ User ที่ต้องการแก้ไข");
    $pageTitle = "แก้ไข User: " . htmlspecialchars($user['username']);
}

// --- จัดการตอนกด Submit ฟอร์ม ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. รับข้อมูลจากฟอร์ม
    $username = trim($_POST['username']);
    $role = $_POST['role'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // 2. Validation
    if (empty($username)) $errors[] = "กรุณากรอก Username";
    if (empty($role)) $errors[] = "กรุณาเลือก Role";

    // --- Logic การจัดการรหัสผ่าน ---
    $password_to_save = null;
    if (!empty($password)) { // เช็คว่ามีการกรอกรหัสผ่านใหม่หรือไม่
        if ($password !== $confirm_password) {
            $errors[] = "รหัสผ่านทั้งสองช่องไม่ตรงกัน";
        } elseif (strlen($password) < 6) {
            $errors[] = "รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร";
        } else {
            // เข้ารหัสผ่าน
            $password_to_save = password_hash($password, PASSWORD_BCRYPT);
        }
    }
    // ถ้าเป็นการ "เพิ่มใหม่" แต่ "ไม่กรอกรหัสผ่าน" -> บังคับให้กรอก
    if (!$is_edit_mode && empty($password)) {
        $errors[] = "กรุณากรอกรหัสผ่านสำหรับ User ใหม่";
    }

    // 3. บันทึกลง Database
    if (empty($errors)) {
        try {
            if ($is_edit_mode) {
                // --- โหมดแก้ไข: UPDATE ---
                if ($password_to_save) { // ถ้ามีรหัสผ่านใหม่ ให้เซฟรหัสผ่านด้วย
                    $sql = "UPDATE admin_users SET username = ?, role = ?, password = ? WHERE id = ?";
                    $params = [$username, $role, $password_to_save, $user['id']];
                } else { // ถ้าไม่มีรหัสผ่านใหม่ ไม่ต้องไปยุ่งกับรหัสผ่านเก่า
                    $sql = "UPDATE admin_users SET username = ?, role = ? WHERE id = ?";
                    $params = [$username, $role, $user['id']];
                }
            } else {
                // --- โหมดเพิ่มใหม่: INSERT ---
                $sql = "INSERT INTO admin_users (username, role, password) VALUES (?, ?, ?)";
                $params = [$username, $role, $password_to_save];
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $errors[] = "Username '" . htmlspecialchars($username) . "' นี้มีผู้ใช้งานแล้ว";
            } else {
                $errors[] = "Database Error: " . $e->getMessage();
            }
        }
    }
}


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
    </div>

    <div class="form-container">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error) echo "<p>" . htmlspecialchars($error) . "</p>"; ?>
            </div>
        <?php endif; ?>

        <form action="form.php<?= $is_edit_mode ? '?id=' . $user['id'] : '' ?>" method="POST">
            <div class="form-group">
                <label for="username">Username <span style="color:red;">*</span></label>
                <input type="text" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
            </div>

            <div class="form-group">
                <label for="role">Role (ยศ)</label>
                <?php
                // ★★★ เช็คว่า user ที่กำลังแก้ไข คือคนเดียวกับที่ล็อกอินอยู่หรือไม่ ★★★
                if ($is_edit_mode && isset($user['id']) && isset($_SESSION['admin_id']) && $user['id'] === $_SESSION['admin_id']):
                ?>
                    <input type="text" id="role" value="<?= htmlspecialchars($user['role']) ?>" readonly class="form-control-disabled">
                    <small style="display:block; margin-top: 5px; color: #6c757d;">ไม่สามารถเปลี่ยน Role ของตัวเองได้</small>
                <?php else: ?>
                    <select id="role" name="role">
                        <?php
                        $roles = ['staff', 'manager', 'super_admin'];
                        foreach ($roles as $r):
                        ?>
                            <option value="<?= $r ?>" <?= (($user['role'] ?? 'staff') == $r) ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <?php if ($is_edit_mode): ?>
                    <small style="display:block; margin-bottom: 5px;">(ปล่อยว่างถ้าไม่ต้องการเปลี่ยน)</small>
                <?php endif; ?>
                <input type="password" id="password" name="password" <?= !$is_edit_mode ? 'required' : '' ?>>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" <?= !$is_edit_mode ? 'required' : '' ?>>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">บันทึกข้อมูล</button>
                <a href="index.php" class="btn-secondary">ยกเลิก</a>
            </div>
        </form>
    </div>
</main>

<?php include '../../templates/footer_admin.php'; ?>