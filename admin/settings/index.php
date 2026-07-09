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

$role       = $_SESSION['admin_role'] ?? '';
$is_super   = ($role === 'super_admin');

// Settings grouped into sections — each card points at a real, existing destination
$sections = [
    [
        'label' => 'บัญชีของฉัน',
        'desc'  => 'ข้อมูลส่วนตัวและความปลอดภัยของบัญชีคุณ',
        'cards' => [
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
        ],
    ],
    [
        'label' => 'ระบบและข้อมูล',
        'desc'  => 'โครงสร้างข้อมูลหลักของระบบ',
        'cards' => [
            [
                'icon'  => 'category',
                'title' => 'หมวดหมู่คลังอะไหล่',
                'desc'  => 'จัดการหมวดหมู่และโครงสร้างคลังอะไหล่',
                'href'  => '/admin/inventory/categories.php',
                'color' => '#8b5cf6',
            ],
        ],
    ],
];

if ($is_super) {
    $sections[] = [
        'label' => 'การเชื่อมต่อและแจ้งเตือน',
        'desc'  => 'ตั้งค่า LINE Official Account และการแจ้งเตือนอัตโนมัติ',
        'cards' => [
            [
                'icon'  => 'notifications_active',
                'title' => 'การแจ้งเตือน & LINE',
                'desc'  => 'ตั้งค่า LINE OA (token/secret/webhook), รอบเช้า-เย็น, ผู้รับ และทดสอบส่ง',
                'href'  => '/admin/settings/notifications.php',
                'color' => '#06c755',
            ],
            [
                'icon'  => 'link',
                'title' => 'เชื่อม LINE พนักงาน',
                'desc'  => 'อนุมัติพนักงานที่ทักบอท, ปลดการเชื่อม และจัดการกลุ่มที่บอทอยู่',
                'href'  => '/admin/cron/line_links.php',
                'color' => '#0ea5e9',
            ],
        ],
    ];
    $sections[] = [
        'label' => 'ผู้ดูแลระบบ',
        'desc'  => 'จัดการสิทธิ์และบัญชีผู้ดูแล',
        'cards' => [
            [
                'icon'  => 'manage_accounts',
                'title' => 'จัดการผู้ใช้งาน',
                'desc'  => 'เพิ่ม/แก้ไขแอดมินและกำหนดสิทธิ์การเข้าถึง',
                'href'  => '/admin/user/',
                'color' => '#ef4444',
            ],
        ],
    ];
}
?>

<div class="settings-page">
    <p class="settings-intro">จัดการบัญชี ระบบ และการเชื่อมต่อทั้งหมดของ CMNS Fix Mac ได้จากที่นี่</p>

    <div class="settings-sections">
    <?php foreach ($sections as $sec): ?>
    <section class="settings-section">
        <div class="settings-section-head">
            <h2><?= htmlspecialchars($sec['label']) ?></h2>
            <?php if (!empty($sec['desc'])): ?><p><?= htmlspecialchars($sec['desc']) ?></p><?php endif; ?>
        </div>
        <div class="settings-list">
            <?php foreach ($sec['cards'] as $c): ?>
                <a class="settings-row" href="<?= htmlspecialchars($c['href']) ?>">
                    <span class="settings-row-icon" style="--c: <?= htmlspecialchars($c['color']) ?>;">
                        <span class="material-symbols-rounded"><?= htmlspecialchars($c['icon']) ?></span>
                    </span>
                    <div class="settings-row-body">
                        <h3><?= htmlspecialchars($c['title']) ?></h3>
                        <p><?= htmlspecialchars($c['desc']) ?></p>
                    </div>
                    <span class="material-symbols-rounded settings-row-arrow">chevron_right</span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>
    </div>
</div>

<style>
.settings-page { max-width: 1240px; }

/* Page intro */
.settings-intro { margin: 0 0 24px; font-size: .9rem; color: var(--text-muted); }

/* Masonry-style columns — fill horizontal space on wide screens */
.settings-sections { column-gap: 22px; }
@media (min-width: 1000px) { .settings-sections { column-count: 2; } }

/* Section */
.settings-section { margin-bottom: 22px; break-inside: avoid; -webkit-column-break-inside: avoid; }
.settings-section-head { margin: 0 4px 8px; }
.settings-section-head h2 {
    margin: 0; font-size: .74rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em;
    color: var(--text-muted);
}
.settings-section-head p { margin: 2px 0 0; font-size: .8rem; color: var(--text-muted); opacity: .8; }

/* Grouped list — one bordered container, rows split by dividers */
.settings-list {
    background: var(--bg-surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
}
.settings-row {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 16px; text-decoration: none; color: var(--text-main);
    border-top: 1px solid var(--border);
    transition: background .15s ease;
}
.settings-row:first-child { border-top: none; }
.settings-row:hover { background: var(--bg-surface-alt, rgba(127,127,127,.06)); }
.settings-row-icon {
    flex: 0 0 34px; width: 34px; height: 34px; border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    background: color-mix(in srgb, var(--c) 12%, transparent);
    color: var(--c);
}
.settings-row-icon .material-symbols-rounded { font-size: 20px; }
.settings-row-body { flex: 1; min-width: 0; }
.settings-row-body h3 { margin: 0 0 1px; font-size: .93rem; font-weight: 600; }
.settings-row-body p { margin: 0; font-size: .8rem; color: var(--text-muted); line-height: 1.4;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.settings-row-arrow { flex-shrink: 0; font-size: 20px; color: var(--text-muted); opacity: .55;
    transition: transform .15s ease; }
.settings-row:hover .settings-row-arrow { transform: translateX(2px); opacity: .9; }

@media (max-width: 600px) {
    .settings-page { max-width: 100%; }
    .settings-row { padding: 13px 14px; gap: 12px; }
    .settings-row-body p { white-space: normal; }
}
</style>

<?php include '../templates/footer_admin.php'; ?>
