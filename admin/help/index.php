<?php
session_start();
require_once '../../includes/db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

$pageTitle = "ศูนย์ช่วยเหลือ";
include '../templates/header_admin.php';

// ── ช่องทางติดต่อผู้พัฒนา/ผู้ดูแลเว็บ (แก้ค่าตรงนี้) ──
$dev_name  = 'นัฐ (ผู้พัฒนาเว็บ)';
$dev_line  = '27042005_natt';
$dev_phone = '0612955236';
$dev_email = 'zencool.xxx@gmail.com';
$dev_fb    = 'khun natt';

// Quick "how do I…" shortcuts into the real admin areas
$shortcuts = [
    ['icon' => 'add_task',          'title' => 'เปิดงานซ่อมใหม่',      'href' => '/admin/tracking/create.php'],
    ['icon' => 'inventory_2',       'title' => 'เพิ่มอะไหล่เข้าคลัง',   'href' => '/admin/inventory/index.php?type=all'],
    ['icon' => 'storefront',        'title' => 'ลงขายสินค้าหน้าร้าน',   'href' => '/admin/shop/'],
    ['icon' => 'verified_user',     'title' => 'ออกใบรับประกัน',        'href' => '/admin/warranty/'],
    ['icon' => 'article',           'title' => 'เขียนบทความใหม่',       'href' => '/admin/articles/'],
    ['icon' => 'account_circle',    'title' => 'แก้ไขโปรไฟล์/รหัสผ่าน', 'href' => '/admin/profile/'],
];
?>

<div class="help-wrap">

    <div class="help-head">
        <span class="material-symbols-rounded help-hero-icon">support_agent</span>
        <h1 class="help-title">ศูนย์ช่วยเหลือ</h1>
        <p class="help-lead">ระบบหลังบ้านนี้พัฒนาและดูแลโดย<?= htmlspecialchars($dev_name) ?><br>ติดปัญหาการใช้งานหรือเจอบั๊ก ทักหาได้เลย</p>

        <div class="help-contact">
            <a href="https://line.me/ti/p/~<?= rawurlencode($dev_line) ?>" target="_blank" rel="noopener">
                <span class="material-symbols-rounded">chat</span> LINE <?= htmlspecialchars($dev_line) ?>
            </a>
            <a href="tel:<?= preg_replace('/[^0-9+]/', '', $dev_phone) ?>">
                <span class="material-symbols-rounded">call</span> <?= htmlspecialchars($dev_phone) ?>
            </a>
            <a href="mailto:<?= htmlspecialchars($dev_email) ?>">
                <span class="material-symbols-rounded">mail</span> <?= htmlspecialchars($dev_email) ?>
            </a>
            <a href="https://www.facebook.com/search/top?q=<?= rawurlencode($dev_fb) ?>" target="_blank" rel="noopener">
                <span class="material-symbols-rounded">group</span> <?= htmlspecialchars($dev_fb) ?>
            </a>
        </div>
    </div>

    <h2 class="help-subhead">ทางลัดงานที่ใช้บ่อย</h2>
    <div class="help-links">
        <?php foreach ($shortcuts as $s): ?>
            <a class="help-link" href="<?= htmlspecialchars($s['href']) ?>">
                <span class="material-symbols-rounded"><?= htmlspecialchars($s['icon']) ?></span>
                <span class="help-link-text"><?= htmlspecialchars($s['title']) ?></span>
                <span class="material-symbols-rounded help-arrow">chevron_right</span>
            </a>
        <?php endforeach; ?>
    </div>

</div>

<style>
.help-wrap { max-width: 540px; margin: 0 auto; }

/* ── หัวเรื่อง: จัดกลางให้สมมาตร ── */
.help-head { text-align: center; margin-bottom: 38px; }
.help-hero-icon { font-size: 52px; color: var(--primary); }
.help-title { margin: 6px 0 8px; font-size: 1.6rem; font-weight: 800; color: var(--text-main); }
.help-lead { margin: 0 0 20px; color: var(--text-muted); font-size: .95rem; line-height: 1.6; }

/* ── ติดต่อ: ลิงก์ข้อความล้วน จัดกลาง ── */
.help-contact { display: flex; flex-wrap: wrap; justify-content: center; gap: 14px 28px; }
.help-contact a { display: inline-flex; align-items: center; gap: 8px; color: var(--primary); font-weight: 700; font-size: .95rem; text-decoration: none; }
.help-contact a:hover { text-decoration: underline; }
.help-contact .material-symbols-rounded { font-size: 20px; }

/* ── ทางลัด: ลิสต์เส้นบาง ไม่มีการ์ด ── */
.help-subhead { margin: 0 0 6px; text-align: center; font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--text-muted); }
.help-links { display: flex; flex-direction: column; }
.help-link { display: flex; align-items: center; gap: 14px; padding: 14px 4px; color: var(--text-main); font-weight: 500; text-decoration: none; border-bottom: 1px solid var(--border); transition: color .15s; }
.help-link:last-child { border-bottom: none; }
.help-link:hover { color: var(--primary); }
.help-link > .material-symbols-rounded:first-child { font-size: 22px; color: var(--primary); }
.help-link-text { flex: 1; }
.help-arrow { font-size: 20px; color: var(--text-muted); }
.help-link:hover .help-arrow { color: var(--primary); }
</style>

<?php include '../templates/footer_admin.php'; ?>
