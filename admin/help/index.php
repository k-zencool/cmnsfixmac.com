<?php
session_start();
require_once '../../includes/db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

$pageTitle = "ศูนย์ช่วยเหลือ";
include '../templates/header_admin.php';

// Quick "how do I…" shortcuts into the real admin areas
$shortcuts = [
    ['icon' => 'add_task',          'title' => 'เปิดงานซ่อมใหม่',     'href' => '/admin/tracking/create.php'],
    ['icon' => 'inventory_2',       'title' => 'เพิ่มอะไหล่เข้าคลัง',  'href' => '/admin/inventory/index.php?type=all'],
    ['icon' => 'storefront',        'title' => 'ลงขายสินค้าหน้าร้าน',  'href' => '/admin/shop/'],
    ['icon' => 'verified_user',     'title' => 'ออกใบรับประกัน',       'href' => '/admin/warranty/'],
    ['icon' => 'article',           'title' => 'เขียนบทความใหม่',      'href' => '/admin/articles/'],
    ['icon' => 'account_circle',    'title' => 'แก้ไขโปรไฟล์/รหัสผ่าน', 'href' => '/admin/profile/'],
];
?>

<div class="help-wrap">

    <div class="help-hero card">
        <span class="material-symbols-rounded help-hero-icon">support_agent</span>
        <div>
            <h2>ต้องการความช่วยเหลือ?</h2>
            <p>ติดปัญหาการใช้งานระบบหลังบ้าน หรือเจอบั๊ก ทักหาทีมงานได้เลย</p>
            <div class="help-contact">
                <a class="help-btn help-btn-line" href="https://line.me/R/ti/p/@cmns" target="_blank" rel="noopener">
                    <span class="material-symbols-rounded">chat</span> LINE @cmns
                </a>
                <a class="help-btn help-btn-call" href="tel:0841511684">
                    <span class="material-symbols-rounded">call</span> 084-151-1684
                </a>
            </div>
        </div>
    </div>

    <h3 class="help-subhead">ทางลัดงานที่ใช้บ่อย</h3>
    <div class="help-grid">
        <?php foreach ($shortcuts as $s): ?>
            <a class="help-card" href="<?= htmlspecialchars($s['href']) ?>">
                <span class="material-symbols-rounded"><?= htmlspecialchars($s['icon']) ?></span>
                <span><?= htmlspecialchars($s['title']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>

</div>

<style>
.help-wrap { max-width: 980px; }
.help-hero {
    display: flex; align-items: center; gap: 22px;
    background: linear-gradient(135deg, var(--primary), var(--primary-hover));
    border: none; color: #fff;
}
.help-hero h2 { margin: 0 0 4px; font-size: 1.3rem; font-weight: 800; color: #fff; }
.help-hero p  { margin: 0 0 14px; font-size: .92rem; opacity: .9; }
.help-hero-icon { font-size: 56px; opacity: .9; flex: 0 0 auto; }
.help-contact { display: flex; flex-wrap: wrap; gap: 10px; }
.help-btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 9px 16px; border-radius: 10px; text-decoration: none;
    font-weight: 700; font-size: .9rem; background: rgba(255,255,255,.18); color: #fff;
    transition: background .2s, transform .2s;
}
.help-btn:hover { background: rgba(255,255,255,.3); transform: translateY(-2px); }
.help-btn .material-symbols-rounded { font-size: 20px; }

.help-subhead { margin: 26px 0 12px; font-size: 1rem; font-weight: 700; color: var(--text-main); }
.help-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px;
}
.help-card {
    display: flex; align-items: center; gap: 13px;
    background: var(--bg-surface); border: 1px solid var(--border); border-radius: 12px;
    padding: 16px 18px; text-decoration: none; color: var(--text-main); font-weight: 600;
    box-shadow: var(--shadow);
    transition: transform .2s var(--ease-out), border-color .2s, box-shadow .2s;
}
.help-card:hover { transform: translateY(-3px); border-color: var(--primary); box-shadow: 0 12px 26px -10px rgba(0,0,0,.2); }
.help-card .material-symbols-rounded { font-size: 24px; color: var(--primary); }
</style>

<?php include '../templates/footer_admin.php'; ?>
