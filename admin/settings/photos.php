<?php
/**
 * admin/settings/photos.php — รูปเครื่องลูกค้าทุกงาน (Google Drive)
 * ค้นตามเลขงาน/ชื่อ/เบอร์/รุ่น/หมายเหตุ, กรองขั้นตอน, ดูเต็มจอ, เลือกหลายรูป → ย้าย/โหลด/ลบ, แก้หมายเหตุ
 * ทุกยศดูได้ · แก้/ย้าย/ลบ = jobs.write (enforced in photo_api.php)
 * Grid, viewer and writes all live in admin/tracking/assets/js/job-photos.js
 */
session_start();
require_once '../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/gdrive_lib.php';

require_login();

$pageTitle = 'รูปเครื่องลูกค้า';
include '../templates/header_admin.php';
?>
<link rel="stylesheet" href="/admin/templates/assets/css/inventory-dashboard.css?v=<?= asset_ver('/admin/templates/assets/css/inventory-dashboard.css') ?>">
<link rel="stylesheet" href="/admin/tracking/assets/css/job-photos.css?v=<?= asset_ver('/admin/tracking/assets/css/job-photos.css') ?>">

<div class="cmns-wrapper pb" data-photo-browser style="max-width:1100px;">
    <div style="margin-bottom:16px;">
        <a href="/admin/settings/" class="cmns-back-link">
            <span class="material-symbols-rounded">arrow_back</span> กลับ
        </a>
    </div>

    <div class="pb-head">
        <div>
            <h1>รูปเครื่องลูกค้า</h1>
            <p>รูปจากทุกงานซ่อม ล่าสุดขึ้นก่อน — แตะรูปเพื่อดูเต็มจอ กดค้างหรือกด "เลือก" เพื่อจัดการทีละหลายรูป</p>
        </div>
        <a class="cmns-btn cmns-btn-secondary" href="/admin/settings/gdrive.php">
            <span class="material-symbols-rounded">add_to_drive</span> ตั้งค่า Drive
        </a>
    </div>

    <div class="jp-note" data-pb-note hidden style="margin-bottom:12px;">
        <span class="material-symbols-rounded">cloud_off</span>
        <span>ยังไม่ได้เชื่อม Google Drive — รูปจะเปิดไม่ขึ้นจนกว่าจะ<a href="/admin/settings/gdrive.php">เชื่อมที่หน้าตั้งค่า</a></span>
    </div>

    <div class="pb-tools">
        <label class="pb-search">
            <span class="material-symbols-rounded">search</span>
            <input type="search" data-pb-q placeholder="เลขงาน ชื่อลูกค้า เบอร์ รุ่น หรือหมายเหตุ" autocomplete="off">
        </label>
        <div class="pb-chips">
            <button type="button" data-pb-stage="" class="is-on">ทั้งหมด</button>
            <?php foreach (gd_stages() as $k => $l): ?>
            <button type="button" data-pb-stage="<?= $k ?>"><?= $l ?></button>
            <?php endforeach; ?>
        </div>
        <button type="button" class="pb-select" data-pb-select>
            <span class="material-symbols-rounded">check_circle</span> เลือก
        </button>
        <span class="pb-count" data-pb-count></span>
    </div>

    <div data-pb-grid></div>
    <div class="pb-empty" data-pb-empty hidden>ไม่พบรูป</div>
    <button type="button" class="pb-more" data-pb-more hidden>โหลดเพิ่ม</button>
</div>

<script src="/admin/tracking/assets/js/job-photos.js?v=<?= asset_ver('/admin/tracking/assets/js/job-photos.js') ?>"></script>

<?php include '../templates/footer_admin.php'; ?>
