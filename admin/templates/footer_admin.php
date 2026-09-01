<?php
// Path Config (เหมือนเดิม)
if (!isset($assets_base)) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $project_root = ''; // ⚠️ แก้ตรงนี้ให้ตรงกับ Header นะเว้ย
    $assets_base = $protocol . "://" . $host . $project_root . "/admin/templates/assets/";
}

// ✅ ตั้งค่าเวอร์ชั่นระบบของมึงตรงนี้
$app_version = "1.0.1 (Beta)"; 
?>

        <footer style="margin-top: auto; padding: 20px 25px; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; color: var(--text-muted); width: 100%;">
            <div>
                &copy; <?= date('Y') ?> <strong>CMNS Fix Mac</strong>. All rights reserved.
            </div>
            
            <div style="display: flex; align-items: center;">
                <span style="background: var(--bg-surface-alt); padding: 5px 12px; border-radius: 20px; border: 1px solid var(--border); font-weight: 600; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); color: var(--text-main);">
                    <span class="material-symbols-rounded" style="font-size: 14px; color: var(--primary);">code</span>
                    v<?= $app_version ?>
                </span>
            </div>
        </footer>
        </div> </main> </div> <div id="pull-refresh" aria-hidden="true"><span class="ptr-spinner"></span></div>

    <div id="global-loader" style="display:none;">
        <div class="loader-spinner"></div>
        <div class="loader-text">กำลังโหลด...</div>
        <div class="loader-text loader-text-slow" id="loaderSlowText">ใช้เวลานานกว่าปกติ กรุณารอสักครู่...</div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        const flashData = {
            success: "<?= isset($_SESSION['success']) ? addslashes($_SESSION['success']) : '' ?>",
            error:   "<?= isset($_SESSION['error']) ? addslashes($_SESSION['error']) : '' ?>"
        };
    </script>
    
    <?php 
    // เคลียร์ค่าทิ้งทันทีหลังจากส่งให้ JS แล้ว (จะได้ไม่เด้งซ้ำ)
    unset($_SESSION['success']);
    unset($_SESSION['error']);
    ?>

    <script src="<?= $assets_base ?>js/admin.js?v=<?= time() ?>"></script>

</body>
</html>