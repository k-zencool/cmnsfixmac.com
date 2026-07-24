<?php
session_start();
require_once '../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}
require_login(); // ทุกยศเข้าหน้านี้ได้ — การ์ด/สิทธิ์เขียนจริงกรองต่อรายการด้านล่าง

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

// การแจ้งเตือน & LINE — ทุกยศดูได้ (หน้านั้นเองล็อกส่วนที่แก้ไข/ยิงจริงไว้ให้ super_admin แล้ว)
$connCards = [
    [
        'icon'  => 'notifications_active',
        'title' => 'การแจ้งเตือน & LINE',
        'desc'  => 'แผงบอทหลัก/บอทรายงาน: สวิตช์ ผู้รับ โควต้า token และทดสอบส่ง',
        'href'  => '/admin/settings/notifications.php',
        'color' => '#06c755',
    ],
];
if ($is_super) {
    $connCards[] = [
        'icon'  => 'link',
        'title' => 'เชื่อม LINE & กลุ่ม',
        'desc'  => 'อนุมัติพนักงานที่ทักบอท, ปลดการเชื่อม และจัดการกลุ่มของบอท',
        'href'  => '/admin/cron/line_links.php',
        'color' => '#0ea5e9',
    ];
}
$sections[] = [
    'label' => 'การเชื่อมต่อและแจ้งเตือน',
    'desc'  => 'บอท LINE 2 ตัว (งานซ่อม + รายงาน) และการแจ้งเตือนอัตโนมัติ',
    'icon'  => 'notifications_active',
    'iconc' => '#06c755',
    'cards' => $connCards,
];

if ($is_super) {
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
    <div class="stg-head">
        <h1>การตั้งค่า</h1>
        <p>จัดการบัญชี ระบบ และการเชื่อมต่อทั้งหมดของ CMNS Fix Mac ได้จากที่นี่</p>
    </div>

    <div class="settings-sections">
    <?php foreach ($sections as $sec): ?>
    <section class="stg-section">
        <div class="stg-section-title">
            <span class="material-symbols-rounded" style="color:<?= htmlspecialchars($sec['iconc']) ?>;"><?= htmlspecialchars($sec['icon']) ?></span>
            <?= htmlspecialchars($sec['label']) ?>
        </div>
        <?php if (!empty($sec['desc'])): ?><p class="stg-desc" style="margin:2px 0 10px;"><?= htmlspecialchars($sec['desc']) ?></p><?php endif; ?>
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
/* ความกว้างคงที่มาตรฐานเดียวกับ dashboard ทั่วไป — ไม่ยืดเต็มจอ ultrawide ตั้งใจเว้นขอบสองข้างไว้ */
.settings-page { max-width: 1100px; }

/* หัวหน้า — ภาษาเดียวกับหน้าการแจ้งเตือน & LINE */
.stg-head { margin-bottom: 30px; }
.stg-head h1 { font-size: 1.32rem; font-weight: 700; color: var(--text-main); margin: 0 0 5px; letter-spacing: -.01em; }
.stg-head p { font-size: .85rem; color: var(--text-muted); margin: 0; line-height: 1.5; }

/* จอกว้าง (เดสก์ท็อป): 2 คอลัมน์แบบหนังสือพิมพ์ คั่นด้วยเส้นบาง ไม่ใช่กล่อง */
@media (min-width: 860px) {
    .settings-sections { column-count: 2; column-gap: 56px; column-rule: 1px solid var(--border); }
}

/* หัวข้อ section — ไม่มีกล่อง ไม่มีเงา แค่ตัวหนา + แถวคั่นเส้นบางด้านล่าง */
.stg-section { margin-bottom: 34px; break-inside: avoid; -webkit-column-break-inside: avoid; }
.stg-section:last-child { margin-bottom: 0; }
.stg-section-title { display: flex; align-items: center; gap: 8px; font-size: 1rem; font-weight: 700;
    color: var(--text-main); }
.stg-section-title .material-symbols-rounded { font-size: 20px; }
.stg-desc { font-size: .8rem; font-weight: 400; color: var(--text-muted); line-height: 1.6; }

/* แถวลิงก์ — เรียบ เต็มความกว้าง เส้นคั่นบางระหว่างแถว ไม่มีกล่องล้อมรอบ */
.stg-section > .settings-row:first-of-type { border-top: 1px solid var(--border); }
.settings-row {
    display: flex; align-items: center; gap: 14px;
    padding: 15px 0; text-decoration: none; color: var(--text-main);
    border-bottom: 1px solid var(--border);
    transition: background .15s ease;
}
.settings-row:hover { background: var(--bg-surface-alt, rgba(127,127,127,.05)); }
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
    .settings-row { padding: 13px 0; gap: 12px; }
    .settings-row-body p { white-space: normal; }
}
</style>

<?php include '../templates/footer_admin.php'; ?>
