<?php
include '../../includes/db.php';
$page_title = 'Warranty Check';
include '../../includes/header_en.php';
?>
<main style="min-height:60vh; display:flex; align-items:center; justify-content:center; padding:80px 20px;">
    <div style="text-align:center; max-width:480px;">
        <span class="material-symbols-rounded" style="font-size:64px; color:var(--primary); opacity:.5;">verified_user</span>
        <h1 style="font-size:1.5rem; margin:16px 0 8px;">Warranty Check</h1>
        <p style="color:var(--text-muted);">System under maintenance. Please contact the store directly.</p>
        <a href="/en/" class="cmns-btn cmns-btn-primary" style="margin-top:24px; display:inline-flex;">
            <span class="material-symbols-rounded">home</span> Back to Home
        </a>
    </div>
</main>
<?php include '../../includes/footer_en.php'; ?>
