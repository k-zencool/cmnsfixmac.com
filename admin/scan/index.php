<?php
/* =========================================================
   admin/scan/index.php — QR scanner (mobile-first)

   Scaffold only, by request: it opens the camera, decodes a QR and shows
   what it read. It deliberately does NOT act on the result yet.

   When it does get wired up, the QRs this system already prints encode
   `/warranty/?q=<warranty_no>` (see admin/warranty/print.php + view.php),
   so that is the first format worth routing.

   Camera needs a secure context. https://…:8444 and http://localhost are
   both fine; http://<LAN-IP or .local> is NOT, and scan.js says so in
   plain Thai rather than failing silently.
   ========================================================= */
session_start();
require_once '../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}
require_login();

$pageTitle = "สแกน QR";
include '../templates/header_admin.php';
?>

<link rel="stylesheet" href="<?= $assets_base ?>css/scan.css?v=<?= asset_ver('/admin/templates/assets/css/scan.css') ?>">

<div class="scan-page">

    <div class="scan-stage" id="scanStage">
        <video id="scanVideo" playsinline muted autoplay></video>

        <!-- Reticle: four corner brackets over a dimmed surround -->
        <div class="scan-reticle" aria-hidden="true">
            <span class="c tl"></span><span class="c tr"></span>
            <span class="c bl"></span><span class="c br"></span>
            <span class="scan-laser"></span>
        </div>

        <!-- Covers the stage until the camera is live, or when it fails -->
        <div class="scan-cover" id="scanCover">
            <span class="material-symbols-rounded scan-cover-ico" id="scanCoverIco">photo_camera</span>
            <p class="scan-cover-msg" id="scanCoverMsg">กำลังเปิดกล้อง…</p>
            <button type="button" class="scan-btn scan-btn-ghost" id="scanRetry" hidden>ลองใหม่</button>
        </div>
    </div>

    <p class="scan-hint" id="scanHint">เล็ง QR ให้อยู่ในกรอบ</p>

    <!-- Result panel — appears only after a successful decode -->
    <div class="scan-result" id="scanResult" hidden>
        <div class="scan-result-head">
            <span class="material-symbols-rounded">check_circle</span>
            <span>อ่านได้แล้ว</span>
        </div>
        <pre class="scan-result-value" id="scanValue"></pre>
        <div class="scan-result-actions">
            <button type="button" class="scan-btn scan-btn-primary" id="scanAgain">สแกนอีกครั้ง</button>
            <button type="button" class="scan-btn scan-btn-ghost" id="scanCopy">คัดลอก</button>
        </div>
        <p class="scan-note">ยังไม่ได้ผูกกับระบบ — ตอนนี้แค่อ่านค่าออกมาโชว์</p>
    </div>

</div>

<!-- jsQR: iOS Safari has no BarcodeDetector, so native decoding is not an option -->
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<script src="<?= $assets_base ?>js/scan.js?v=<?= asset_ver('/admin/templates/assets/js/scan.js') ?>"></script>

<?php include '../templates/footer_admin.php'; ?>
