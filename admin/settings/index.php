<?php
session_start();
require_once '../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}
require_perms(['settings.manage']); // ตั้งค่าระบบ: เจ้าของเท่านั้น

$pageTitle = "การตั้งค่า";
include '../templates/header_admin.php';

$role = $_SESSION['admin_role'] ?? '';

// Settings hubs — each points at a real, existing destination
$cards = [
    [
        'icon'  => 'account_circle',
        'title' => 'โปรไฟล์ของฉัน',
        'desc'  => 'แก้ไขชื่อ รูปโปรไฟล์ และข้อมูลส่วนตัว',
        'href'  => '/admin/profile/',
        'color' => '#2563eb',
    ],
    [
        'icon'  => 'lock',
        'title' => 'เปลี่ยนรหัสผ่าน',
        'desc'  => 'ตั้งรหัสผ่านใหม่เพื่อความปลอดภัยของบัญชี',
        'href'  => '/admin/profile/#security',
        'color' => '#f59e0b',
    ],
    [
        'icon'  => 'category',
        'title' => 'หมวดหมู่คลังอะไหล่',
        'desc'  => 'จัดการหมวดหมู่และโครงสร้างคลังอะไหล่',
        'href'  => '/admin/inventory/categories.php',
        'color' => '#8b5cf6',
    ],
];

if ($role === 'super_admin') {
    $cards[] = [
        'icon'  => 'manage_accounts',
        'title' => 'จัดการผู้ใช้งาน',
        'desc'  => 'เพิ่ม/แก้ไขแอดมินและกำหนดสิทธิ์การเข้าถึง',
        'href'  => '/admin/user/',
        'color' => '#ef4444',
    ];
}
?>

<div class="settings-grid">
    <?php foreach ($cards as $c): ?>
        <a class="settings-card" href="<?= htmlspecialchars($c['href']) ?>">
            <span class="settings-card-icon" style="--c: <?= htmlspecialchars($c['color']) ?>;">
                <span class="material-symbols-rounded"><?= htmlspecialchars($c['icon']) ?></span>
            </span>
            <div class="settings-card-body">
                <h3><?= htmlspecialchars($c['title']) ?></h3>
                <p><?= htmlspecialchars($c['desc']) ?></p>
            </div>
            <span class="material-symbols-rounded settings-card-arrow">chevron_right</span>
        </a>
    <?php endforeach; ?>
</div>

<style>
.settings-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px; margin-top: 4px;
}
.settings-card {
    display: flex; align-items: center; gap: 16px;
    background: var(--bg-surface); border: 1px solid var(--border);
    border-radius: 14px; padding: 18px 20px; text-decoration: none;
    color: var(--text-main); box-shadow: var(--shadow);
    transition: transform .2s var(--ease-out), box-shadow .2s, border-color .2s;
}
.settings-card:hover {
    transform: translateY(-3px);
    border-color: var(--primary);
    box-shadow: 0 12px 28px -10px rgba(0,0,0,.2);
}
.settings-card-icon {
    flex: 0 0 48px; width: 48px; height: 48px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    background: color-mix(in srgb, var(--c) 14%, transparent);
    color: var(--c);
}
.settings-card-icon .material-symbols-rounded { font-size: 26px; }
.settings-card-body { flex: 1; min-width: 0; }
.settings-card-body h3 { margin: 0 0 3px; font-size: 1rem; font-weight: 700; }
.settings-card-body p { margin: 0; font-size: .85rem; color: var(--text-muted); line-height: 1.4; }
.settings-card-arrow { color: var(--text-muted); transition: transform .2s var(--ease-out); }
.settings-card:hover .settings-card-arrow { transform: translateX(4px); color: var(--primary); }
</style>

<?php include '../templates/footer_admin.php'; ?>
