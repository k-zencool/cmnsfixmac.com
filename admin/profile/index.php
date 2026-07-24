<?php
session_start();

/**
 * =================================================================
 * 1. DATABASE CONNECTION & AUTH CHECK
 * =================================================================
 */
require_once '../../includes/db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];

/**
 * =================================================================
 * 2. FETCH USER DATA (PDO)
 * =================================================================
 */
try {
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = :id");
    $stmt->execute([':id' => $admin_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        header("Location: ../login.php");
        exit();
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

/**
 * =================================================================
 * 3. FETCH REPAIR STATS & ACTIVITIES (ดึงสถิติและกิจกรรมจากตาราง tracking)
 * =================================================================
 */
$stats = [
    'total' => 0,
    'completed' => 0,
    'pending' => 0
];
$recent_activities = []; // เตรียมตัวแปรไว้เก็บกิจกรรม

try {
    // 1. นับงานทั้งหมดที่แอดมินคนนี้รับผิดชอบ
    $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM tracking WHERE updated_by = :id");
    $stmtTotal->execute([':id' => $admin_id]);
    $stats['total'] = $stmtTotal->fetchColumn();

    // 2. นับงานที่เสร็จสิ้นแล้ว
    $stmtCompleted = $pdo->prepare("SELECT COUNT(*) FROM tracking WHERE updated_by = :id AND status = 'completed'");
    $stmtCompleted->execute([':id' => $admin_id]);
    $stats['completed'] = $stmtCompleted->fetchColumn();

    // 3. นับงานที่กำลังดำเนินการ
    $stmtPending = $pdo->prepare("SELECT COUNT(*) FROM tracking WHERE updated_by = :id AND status != 'completed'");
    $stmtPending->execute([':id' => $admin_id]);
    $stats['pending'] = $stmtPending->fetchColumn();

    // 4. ✅ ดึงประวัติกิจกรรมล่าสุด 5 รายการ
    $stmtActivity = $pdo->prepare("SELECT ticket_number, customer_name, status, updated_at 
                                   FROM tracking 
                                   WHERE updated_by = :id 
                                   ORDER BY updated_at DESC 
                                   LIMIT 5");
    $stmtActivity->execute([':id' => $admin_id]);
    $recent_activities = $stmtActivity->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Stats Tracking DB Error: " . $e->getMessage());
}

/**
 * =================================================================
 * 4. PREPARE DATA FOR DISPLAY
 * =================================================================
 */
$display_name = !empty($user['full_name']) ? $user['full_name'] : $user['username'];
$email_show   = !empty($user['email']) ? $user['email'] : '-';
$phone_show   = !empty($user['phone']) ? $user['phone'] : '-';
$bio_show     = !empty($user['bio']) ? $user['bio'] : 'ยังไม่มีข้อมูลแนะนำตัว';
$join_date    = isset($user['created_at']) ? date('d M Y', strtotime($user['created_at'])) : '-';

$avatar_text = strtoupper(mb_substr($display_name, 0, 2));
$pageTitle = "โปรไฟล์ส่วนตัว";

include '../templates/header_admin.php';
?>

<link rel="stylesheet" href="../templates/assets/css/profile.css?v=<?= time() ?>">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="profile-banner">
    <div class="banner-overlay"></div>
</div>

<div class="content-padding profile-container">
    <div class="profile-grid">

        <div class="profile-menu-card">
            <div class="profile-header-info">
                <form id="avatarForm" action="upload_avatar.php" method="POST" enctype="multipart/form-data" style="display: none;">
                    <input type="file" id="avatarInput" name="avatar" accept="image/jpeg, image/png, image/webp" onchange="document.getElementById('avatarForm').submit();">
                </form>
                <div style="position: relative; width: 100px; height: 100px; margin: -80px auto 15px; z-index: 30; cursor: pointer;" onclick="document.getElementById('avatarInput').click();">

                    <div class="big-avatar" style="margin: 0; width: 100%; height: 100%; padding: 0; overflow: hidden; background: var(--bg-surface); border: 4px solid var(--bg-surface); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <?php if (!empty($user['avatar']) && file_exists('../../uploads/avatars/' . $user['avatar'])): ?>
                            <img src="../../uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        <?php else: ?>
                            <?= $avatar_text ?>
                        <?php endif; ?>
                    </div>

                    <div style="position: absolute; bottom: 0; right: 0; z-index: 40; background: var(--primary); color: white; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border: 3px solid var(--bg-surface); box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                        <span class="material-symbols-rounded" style="font-size: 16px;">photo_camera</span>
                    </div>
                </div>

                <h3 style="margin:0; font-size:1.3rem; margin-bottom:5px;"><?= htmlspecialchars($display_name) ?></h3>
                <span class="user-role-badge"><?= ucfirst(htmlspecialchars($user['role'] ?? 'Staff')) ?></span>
                <div style="color:var(--text-muted); font-size:0.9rem; margin-top:15px; display:flex; align-items:center; justify-content:center; gap:5px;">
                    <span class="material-symbols-rounded" style="font-size:16px;">calendar_today</span>
                    สมาชิกเมื่อ: <?= $join_date ?>
                </div>
            </div>

            <div onclick="switchTab('overview')" class="tab-item active" id="btn-overview">
                <span class="material-symbols-rounded">dashboard</span> ภาพรวม
            </div>
            <div onclick="switchTab('edit')" class="tab-item" id="btn-edit">
                <span class="material-symbols-rounded">edit_square</span> แก้ไขข้อมูล
            </div>
            <div onclick="switchTab('security')" class="tab-item" id="btn-security">
                <span class="material-symbols-rounded">lock_reset</span> ความปลอดภัย
            </div>
        </div>

        <div class="profile-content">

            <div id="tab-overview" class="tab-content active">
                <div class="form-card">
                    <h4 class="form-section-header">ข้อมูลเกี่ยวกับฉัน</h4>

                    <div style="margin-bottom: 25px;">
                        <label style="color:var(--text-muted); font-size:0.9rem; font-weight:500;">Bio / คำแนะนำตัว</label>
                        <div style="margin-top:8px; line-height:1.6; font-size:1rem; color:var(--text-main);">
                            <?= nl2br(htmlspecialchars($bio_show)) ?>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                        <div class="info-box">
                            <span class="material-symbols-rounded" style="color:var(--primary); font-size:28px;">mail</span>
                            <div>
                                <div style="font-size:0.85rem; color:var(--text-muted);">อีเมลติดต่อ</div>
                                <div style="font-weight:600; font-size:1rem;"><?= htmlspecialchars($email_show) ?></div>
                            </div>
                        </div>
                        <div class="info-box">
                            <span class="material-symbols-rounded" style="color:var(--primary); font-size:28px;">call</span>
                            <div>
                                <div style="font-size:0.85rem; color:var(--text-muted);">เบอร์โทรศัพท์</div>
                                <div style="font-weight:600; font-size:1rem;"><?= htmlspecialchars($phone_show) ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="stats-grid">
                        <div class="stat-card">
                            <span class="material-symbols-rounded" style="color:var(--text-muted);">build_circle</span>
                            <div class="stat-value"><?= number_format($stats['total']) ?></div>
                            <div class="stat-label">งานซ่อมทั้งหมด</div>
                        </div>
                        <div class="stat-card">
                            <span class="material-symbols-rounded" style="color:var(--text-muted);">check_circle</span>
                            <div class="stat-value" style="color:#10b981;"><?= number_format($stats['completed']) ?></div>
                            <div class="stat-label">งานเสร็จสิ้น</div>
                        </div>
                        <div class="stat-card">
                            <span class="material-symbols-rounded" style="color:var(--text-muted);">pending</span>
                            <div class="stat-value" style="color:#f59e0b;"><?= number_format($stats['pending']) ?></div>
                            <div class="stat-label">กำลังดำเนินการ</div>
                        </div>
                    </div>

                    <h5 class="section-title">กิจกรรมล่าสุด (Recent Activity)</h5>
                    <div class="activity-list">
                        <?php if (!empty($recent_activities)): ?>
                            <?php foreach ($recent_activities as $act):
                                $icon = 'edit_document';
                                $action_text = 'อัปเดตงานซ่อม';

                                if ($act['status'] == 'completed') {
                                    $icon = 'task_alt';
                                    $action_text = 'ปิดงานซ่อม';
                                }
                            ?>
                                <div class="activity-item">
                                    <div class="activity-icon" <?= $act['status'] == 'completed' ? 'style="color: #10b981; background: #d1fae5; border-color: #10b981;"' : '' ?>>
                                        <span class="material-symbols-rounded"><?= $icon ?></span>
                                    </div>
                                    <div class="activity-info">
                                        <h5><?= $action_text ?> #<?= htmlspecialchars($act['ticket_number']) ?></h5>
                                        <p>ลูกค้า: <?= htmlspecialchars($act['customer_name']) ?> (สถานะ: <?= htmlspecialchars($act['status']) ?>)</p>
                                    </div>
                                    <div class="activity-time">
                                        <?= date('d/m/Y H:i', strtotime($act['updated_at'])) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="activity-item" style="justify-content: center; background: transparent; border: 1px dashed var(--border);">
                                <p style="color: var(--text-muted); margin: 0;">ยังไม่มีประวัติการอัปเดตงานซ่อม...</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div id="tab-edit" class="tab-content">
                <div class="form-card">
                    <h4 class="form-section-header">แก้ไขข้อมูลส่วนตัว</h4>

                    <form action="update_profile.php" method="POST">
                        <div class="row" style="display:flex; flex-wrap:wrap; margin:0 -10px;">
                            <div class="col-md-6" style="padding:0 10px; flex:1 1 300px; margin-bottom:20px;">
                                <label style="display:block; margin-bottom:8px; font-weight:600;">ชื่อที่แสดง (Full Name) <span style="color:red;">*</span></label>
                                <input type="text" class="form-control" name="full_name" value="<?= htmlspecialchars($display_name) ?>" required>
                            </div>
                            <div class="col-md-6" style="padding:0 10px; flex:1 1 300px; margin-bottom:20px;">
                                <label style="display:block; margin-bottom:8px; font-weight:600;">เบอร์โทรศัพท์</label>
                                <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="08x-xxx-xxxx">
                            </div>
                        </div>

                        <div style="margin-bottom:20px;">
                            <label style="display:block; margin-bottom:8px; font-weight:600;">อีเมล</label>
                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" placeholder="name@example.com">
                        </div>

                        <div style="margin-bottom:25px;">
                            <label style="display:block; margin-bottom:8px; font-weight:600;">Bio / คำแนะนำตัว</label>
                            <textarea class="form-control" name="bio" rows="4" placeholder="แนะนำตัวเองสั้นๆ..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                        </div>

                        <div style="text-align:right; margin-top: auto;">
                            <button type="submit" class="save-btn">
                                <span class="material-symbols-rounded">save</span> บันทึกการเปลี่ยนแปลง
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="tab-security" class="tab-content">
                <div class="form-card">
                    <h4 class="form-section-header">เปลี่ยนรหัสผ่าน</h4>

                    <div style="background:#fff7ed; color:#c2410c; padding:15px; border-radius:8px; margin-bottom:25px; border:1px solid #fdba74; display:flex; gap:10px; align-items:center;">
                        <span class="material-symbols-rounded">info</span>
                        <div style="font-size:0.9rem;">ควรใช้รหัสผ่านที่มีทั้งตัวอักษรและตัวเลขผสมกันอย่างน้อย 8 ตัว</div>
                    </div>

                    <form action="update_password.php" method="POST">
                        <div style="margin-bottom:25px;">
                            <label style="display:block; margin-bottom:8px; font-weight:600;">รหัสผ่านปัจจุบัน <span style="color:red;">*</span></label>
                            <input type="password" class="form-control" name="current_password" required placeholder="••••••••">
                        </div>

                        <div class="row" style="display:flex; flex-wrap:wrap; margin:0 -10px;">
                            <div class="col-md-6" style="padding:0 10px; flex:1 1 300px; margin-bottom:20px;">
                                <label style="display:block; margin-bottom:8px; font-weight:600;">รหัสผ่านใหม่ <span style="color:red;">*</span></label>
                                <input type="password" class="form-control" name="new_password" required minlength="6" placeholder="••••••••">
                            </div>
                            <div class="col-md-6" style="padding:0 10px; flex:1 1 300px; margin-bottom:20px;">
                                <label style="display:block; margin-bottom:8px; font-weight:600;">ยืนยันรหัสผ่านใหม่ <span style="color:red;">*</span></label>
                                <input type="password" class="form-control" name="confirm_password" required minlength="6" placeholder="••••••••">
                            </div>
                        </div>

                        <div style="text-align:right; margin-top: auto;">
                            <button type="submit" class="save-btn" style="background:#ef4444; box-shadow: 0 4px 6px rgba(239, 68, 68, 0.2);">
                                <span class="material-symbols-rounded">key</span> อัปเดตรหัสผ่าน
                            </button>
                        </div>
                    </form>
                </div>

                <!-- แจ้งเตือนผ่านแอป (Web Push — ต่ออุปกรณ์ ต่อคน) -->
                <div class="form-card" style="margin-top:20px;">
                    <h4 class="form-section-header">แจ้งเตือนผ่านแอป</h4>
                    <p style="font-size:.85rem; color:var(--text-muted, #6b7280); margin:0 0 16px; line-height:1.6;">
                        รับแจ้งเตือนงานซ่อม/รายงานเด้งบนเครื่องนี้โดยตรง (ฟรี ไม่ผ่าน LINE) —
                        เปิดทีละเครื่องที่ใช้งานจริง · iPhone/iPad ต้องเพิ่มแอปลงหน้าจอโฮมก่อน
                    </p>
                    <?php
                    require_once __DIR__ . '/../../includes/push_helper.php';
                    include __DIR__ . '/../templates/push_ui.php';
                    ?>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include '../templates/footer_admin.php'; ?>

<script>
    function switchTab(tabName) {
        document.querySelectorAll('.tab-item').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(el => {
            el.classList.remove('active');
            el.style.display = 'none';
        });

        const btn = document.getElementById('btn-' + tabName);
        if (btn) btn.classList.add('active');

        const content = document.getElementById('tab-' + tabName);
        if (content) {
            content.style.display = 'flex';
            setTimeout(() => content.classList.add('active'), 10);
        }
    }
</script>

<script>
    <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'สำเร็จ!',
            text: '<?= $_SESSION['success'] ?>',
            timer: 2000,
            showConfirmButton: false,
            background: 'var(--bg-surface)',
            color: 'var(--text-main)'
        });
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        Swal.fire({
            icon: 'error',
            title: 'มีบางอย่างผิดพลาด!',
            text: '<?= $_SESSION['error'] ?>',
            confirmButtonColor: '#ef4444',
            background: 'var(--bg-surface)',
            color: 'var(--text-main)'
        });
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
</script>