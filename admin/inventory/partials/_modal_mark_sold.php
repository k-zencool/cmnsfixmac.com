
<!-- ══════════════════════════════════════════════════════
     Mark Sold Modal
═══════════════════════════════════════════════════════ -->
<div id="modal-mark-sold" class="cmns-modal">
    <div class="modal-content" style="max-width:420px; padding:28px;">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); padding-bottom:16px; margin-bottom:20px;">
            <h3 style="margin:0; display:flex; align-items:center; gap:10px; font-size:18px; color:var(--text-main); font-weight:800;">
                <span class="material-symbols-rounded" style="color:#ef4444; font-size:24px;">payments</span>
                ยืนยันขาย
            </h3>
            <button class="modal-close-btn" onclick="closeMarkSoldModal()"><span class="material-symbols-rounded">close</span></button>
        </div>

        <div id="ms-item-info" style="background:var(--bg-surface-alt); border:1px solid var(--border); border-radius:10px; padding:12px 16px; margin-bottom:20px;">
            <div id="ms-item-name" style="font-weight:700; font-size:14px; color:var(--text-main);"></div>
        </div>

        <div style="margin-bottom:20px;">
            <label class="cmns-label">ราคาที่ขายจริง (฿)</label>
            <input type="number" id="ms-sold-price" class="cmns-input" step="1" min="0" style="font-size:20px; font-weight:800; text-align:center;">
        </div>

        <input type="hidden" id="ms-inventory-id">

        <div id="ms-error" style="display:none; margin-bottom:14px; padding:10px 14px; background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.3); border-radius:8px; color:#dc2626; font-size:13px; font-weight:600;"></div>

        <div style="display:flex; justify-content:flex-end; gap:10px; padding-top:16px; border-top:1px solid var(--border);">
            <button type="button" class="cmns-btn cmns-btn-secondary" onclick="closeMarkSoldModal()">ยกเลิก</button>
            <button type="button" id="ms-submit-btn" class="cmns-btn cmns-btn-primary" style="background:#ef4444; border-color:#ef4444;" onclick="submitMarkSold()">
                <span class="material-symbols-rounded">payments</span> ยืนยันขาย
            </button>
        </div>
    </div>
</div>
