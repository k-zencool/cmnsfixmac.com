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
        'icon'  => 'person',
        'iconc' => '#2563eb',
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
        'icon'  => 'database',
        'iconc' => '#8b5cf6',
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
        'desc'  => 'บอท LINE 2 ตัว (งานซ่อม + รายงาน) และการแจ้งเตือนอัตโนมัติ',
        'icon'  => 'notifications_active',
        'iconc' => '#06c755',
        'cards' => [
            [
                'icon'  => 'notifications_active',
                'title' => 'การแจ้งเตือน & LINE',
                'desc'  => 'แผงบอทหลัก/บอทรายงาน: สวิตช์ ผู้รับ โควต้า token และทดสอบส่ง',
                'href'  => '/admin/settings/notifications.php',
                'color' => '#06c755',
            ],
            [
                'icon'  => 'link',
                'title' => 'เชื่อม LINE & กลุ่ม',
                'desc'  => 'อนุมัติพนักงานที่ทักบอท, ปลดการเชื่อม และจัดการกลุ่มของบอท',
                'href'  => '/admin/cron/line_links.php',
                'color' => '#0ea5e9',
            ],
        ],
    ];
    $sections[] = [
        'label' => 'ผู้ดูแลระบบ',
        'desc'  => 'จัดการสิทธิ์และบัญชีผู้ดูแล',
        'icon'  => 'shield_person',
        'iconc' => '#ef4444',
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
    <section class="lk-card">
        <div class="lk-label">
            <span class="material-symbols-rounded" style="color:<?= htmlspecialchars($sec['iconc']) ?>;"><?= htmlspecialchars($sec['icon']) ?></span>
            <?= htmlspecialchars($sec['label']) ?>
            <span class="lk-count"><?= count($sec['cards']) ?></span>
        </div>
        <?php if (!empty($sec['desc'])): ?><p class="lk-hint" style="margin:-6px 0 8px;"><?= htmlspecialchars($sec['desc']) ?></p><?php endif; ?>
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
    </section>
    <?php endforeach; ?>
    </div>
</div>

<style>
.settings-page { max-width: 1240px; }

/* Page intro */
.settings-intro { margin: 0 0 20px; font-size: .9rem; color: var(--text-muted); }

/* Masonry-style columns — fill horizontal space on wide screens */
.settings-sections { column-gap: 18px; }
@media (min-width: 1000px) { .settings-sections { column-count: 2; } }

/* การ์ด section — ภาษาเดียวกับหน้าการแจ้งเตือน / เชื่อม LINE (lk-card) */
.lk-card { background: var(--bg-surface); border: 1px solid var(--border); border-radius: 14px;
    padding: 20px 22px; box-shadow: var(--shadow); margin-bottom: 18px;
    break-inside: avoid; -webkit-column-break-inside: avoid; }
.lk-label { display: flex; align-items: center; gap: 8px; font-size: .95rem; font-weight: 700;
    color: var(--text-main); margin-bottom: 12px; }
.lk-count { font-size: .72rem; font-weight: 700; padding: 2px 10px; border-radius: 99px;
    background: rgba(148,163,184,.16); color: var(--text-muted); }
.lk-hint { font-size: .78rem; font-weight: 400; color: var(--text-muted); line-height: 1.6; }

/* แถวลิงก์ในการ์ด — เส้นคั่น dashed แบบเดียวกับรายชื่อผู้รับ */
.settings-row {
    display: flex; align-items: center; gap: 14px;
    padding: 13px 0; text-decoration: none; color: var(--text-main);
    border-bottom: 1px dashed var(--border);
    transition: background .15s ease;
    border-radius: 8px;
}
.settings-row:last-of-type { border-bottom: none; padding-bottom: 4px; }
.settings-row:hover { background: var(--bg-surface-alt, rgba(127,127,127,.06)); }
.settings-row-icon {
    flex: 0 0 34px; width: 34px; height: 34px; border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    background: color-mix(in srgb, var(--c) 12%, transparent);
    color: var(--c);
}
.settings-row-icon .material-symbols-rounded { font-size: 20px; }
.settings-row-body { flex: 1; min-width: 0; }
.settings-row-body h3 { margin: 0 0 1px; font-size: .9rem; font-weight: 700; }
.settings-row-body p { margin: 0; font-size: .78rem; color: var(--text-muted); line-height: 1.4;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.settings-row-arrow { flex-shrink: 0; font-size: 20px; color: var(--text-muted); opacity: .55;
    transition: transform .15s ease; }
.settings-row:hover .settings-row-arrow { transform: translateX(2px); opacity: .9; }

@media (max-width: 600px) {
    .settings-page { max-width: 100%; }
    .lk-card { padding: 16px; border-radius: 12px; }
    .settings-row { padding: 12px 0; gap: 12px; }
    .settings-row-body p { white-space: normal; }
}
</style>

<?php include '../templates/footer_admin.php'; ?>
