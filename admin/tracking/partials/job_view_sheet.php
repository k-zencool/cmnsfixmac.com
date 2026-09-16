<?php
/* =========================================================
   admin/tracking/partials/job_view_sheet.php — read-only job sheet markup

   Filled by admin/tracking/assets/js/job-view.js from jv_payload()
   (includes/job_view_lib.php); styled by assets/css/job-view.css.
   Set $jvCanDelete = true before including to show the phone-only delete
   button (the page must then define deleteFromView()).
   ========================================================= */
?>
<div id="viewModal" class="trk-modal-overlay">
    <div class="trk-view sheet-on-mobile">
        <header class="trk-view-hd">
            <div class="trk-view-hd-l">
                <span class="trk-view-ticket" id="vm-ticket"></span>
                <span class="status-badge" id="vm-status"></span>
            </div>
            <button type="button" class="trk-view-close" data-jv-close aria-label="ปิด">
                <span class="material-symbols-rounded">close</span>
            </button>
        </header>

        <div class="trk-view-body">

            <!-- meta tiles -->
            <div class="trk-view-meta">
                <div><label>วันที่รับ</label><b id="vm-created"></b></div>
                <div><label>นัดหมาย</label><b><span id="vm-appt"></span> <span id="vm-time"></span></b></div>
                <div><label>รับเครื่องคืน</label><b id="vm-pickup"></b></div>
                <div><label>ราคาประเมิน</label><b id="vm-cost" class="trk-view-cost"></b></div>
            </div>

            <section class="trk-view-sec">
                <label>ลูกค้า</label>
                <div class="trk-view-line"><b id="vm-name"></b> · <a id="vm-phone" href="#"></a></div>
            </section>

            <section class="trk-view-sec">
                <label>อุปกรณ์</label>
                <div class="trk-view-line"><b id="vm-device"></b> <span id="vm-model" class="trk-view-dim"></span></div>
                <div class="trk-view-line trk-view-mono">SN: <span id="vm-sn"></span></div>
                <div class="trk-view-line trk-view-mono trk-view-pass">Pass: <span id="vm-pass"></span></div>
            </section>

            <section class="trk-view-sec" id="vm-sec-problem">
                <label>อาการเสีย</label>
                <div class="trk-view-tags trk-tags-red" id="vm-symptoms"></div>
                <p class="trk-view-p" id="vm-detail"></p>
            </section>

            <section class="trk-view-sec" id="vm-sec-recv">
                <label>ตรวจรับเครื่อง</label>
                <div class="trk-view-tags" id="vm-accs"></div>
                <div class="trk-view-tags trk-tags-amber" id="vm-states"></div>
                <p class="trk-view-p" id="vm-note"></p>
            </section>

        </div>

        <footer class="trk-view-ft">
            <!-- Redundant next to the header's ✕ on a phone; desktop keeps it. -->
            <button type="button" class="trk-view-cancel trk-d-only" data-jv-close>ปิด</button>
            <?php if (!empty($jvCanDelete) && can('jobs.write')): ?>
            <!-- Mobile only: the desktop table has its own delete button in the
                 actions column, which is not rendered below 992px. -->
            <button type="button" class="trk-view-delbtn trkm-only" onclick="deleteFromView()">
                <span class="material-symbols-rounded">delete</span> ลบ
            </button>
            <?php endif; ?>
            <?php if (can('jobs.write')): ?>
            <a id="vm-edit" href="#" class="trk-view-editbtn" onclick="var l=document.getElementById('global-loader'); if (l) l.style.display='flex';">
                <span class="material-symbols-rounded">edit</span> แก้ไขงานนี้
            </a>
            <?php endif; ?>
        </footer>
    </div>
</div>
