<?php
// Shared action modals (เบิก/แก้ไข) — extracted from view.php so it can be reused
// on index.php parts-search results. Self-contained: no PHP var dependencies.
// NOTE: view.php still keeps its own inline copies; if you refactor view.php to
//       include this partial, drop those copies to avoid double definitions.
?>

<!-- ===== Requisition (เบิกอะไหล่) Modal ===== -->
    <div id="modal-requisition" class="cmns-modal">
        <div class="modal-content" style="max-width:520px; padding:30px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; padding-bottom:16px; border-bottom:1px solid var(--border);">
                <h3 style="margin:0; display:flex; align-items:center; gap:10px; font-size:18px; color:var(--text-main);">
                    <span class="material-symbols-rounded" style="color:#10b981; font-size:26px;">output</span>
                    เบิกอะไหล่ใหม่
                </h3>
                <button class="modal-close-btn" onclick="closeRequisitionModal()"><span class="material-symbols-rounded">close</span></button>
            </div>

            <!-- Item info -->
            <div id="req-item-info" style="background:var(--bg-surface-alt); border:1px solid var(--border); border-radius:10px; padding:14px 16px; margin-bottom:16px;">
                <div id="req-item-name" style="font-weight:700; font-size:14px; color:var(--text-main);"></div>
                <div style="display:flex; gap:16px; margin-top:6px;">
                    <span style="font-size:12px; color:var(--text-muted);">SKU: <code id="req-item-sku"></code></span>
                    <span style="font-size:12px; color:var(--text-muted);">คงเหลือรวม: <b id="req-item-qty" style="color:var(--text-main);"></b> ชิ้น</span>
                </div>
            </div>

            <!-- Lot Selector -->
            <div style="margin-bottom:16px;">
                <label style="font-size:11px; font-weight:800; color:var(--text-muted); text-transform:uppercase; letter-spacing:.6px; display:block; margin-bottom:8px;">เลือก Lot</label>
                <div id="req-lots-wrap" style="display:flex; flex-direction:column; gap:6px;">
                    <div style="padding:24px; text-align:center; color:var(--text-muted); font-size:13px;">
                        <span class="material-symbols-rounded" style="animation:spin 1s linear infinite; font-size:20px; display:block; margin-bottom:6px;">sync</span>
                        กำลังโหลด lots...
                    </div>
                </div>
                <input type="hidden" id="req-lot-id" value="">
            </div>

            <div style="display:grid; gap:16px;">

                <!-- Qty -->
                <div>
                    <label style="font-size:11px; font-weight:800; color:var(--text-muted); text-transform:uppercase; letter-spacing:.6px; display:block; margin-bottom:6px;">จำนวนที่เบิก</label>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <button type="button" onclick="adjustQty(-1)" style="width:36px; height:36px; border-radius:8px; border:1px solid var(--border); background:var(--bg-surface-alt); font-size:20px; cursor:pointer; color:var(--text-main); display:flex; align-items:center; justify-content:center;">−</button>
                        <input type="number" id="req-qty" value="1" min="1" max="99"
                               style="width:72px; text-align:center; padding:8px; border:1.5px solid var(--border); border-radius:8px; background:var(--bg-surface-alt); color:var(--text-main); font-size:16px; font-weight:800; outline:none;">
                        <button type="button" onclick="adjustQty(1)" style="width:36px; height:36px; border-radius:8px; border:1px solid var(--border); background:var(--bg-surface-alt); font-size:20px; cursor:pointer; color:var(--text-main); display:flex; align-items:center; justify-content:center;">+</button>
                        <span id="req-qty-max-label" style="font-size:12px; color:var(--text-muted);"></span>
                    </div>
                </div>

                <!-- Link to job -->
                <div>
                    <label style="font-size:11px; font-weight:800; color:var(--text-muted); text-transform:uppercase; letter-spacing:.6px; display:block; margin-bottom:6px;">ผูกกับงานซ่อม (ไม่บังคับ)</label>
                    <div style="position:relative;">
                        <span class="material-symbols-rounded" style="position:absolute; left:11px; top:50%; transform:translateY(-50%); font-size:18px; color:var(--text-muted); pointer-events:none;">build_circle</span>
                        <input type="text" id="req-job-search" placeholder="พิมพ์ Job No. / ชื่อลูกค้า..."
                               autocomplete="off"
                               style="width:100%; padding:10px 12px 10px 38px; border:1.5px solid var(--border); border-radius:10px; background:var(--bg-surface-alt); color:var(--text-main); font-size:13px; font-family:inherit; outline:none;"
                               oninput="searchJobs(this.value)">
                        <div id="req-job-results" style="display:none; position:absolute; top:calc(100%+4px); left:0; right:0; background:var(--bg-surface); border:1.5px solid var(--border); border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,.12); z-index:200; max-height:220px; overflow-y:auto;"></div>
                    </div>
                    <div id="req-job-selected" style="display:none; margin-top:8px; background:rgba(16,185,129,.08); border:1px solid rgba(16,185,129,.25); border-radius:8px; padding:8px 12px; font-size:12px;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <span style="font-weight:700; color:#059669;" id="req-job-label"></span>
                            </div>
                            <button type="button" onclick="clearJobSelection()" style="background:none; border:none; cursor:pointer; color:var(--text-muted); font-size:16px; padding:0 4px;">✕</button>
                        </div>
                    </div>
                    <input type="hidden" id="req-tracking-id" value="">
                    <input type="hidden" id="req-ticket-number" value="">
                </div>

                <!-- Remarks -->
                <div>
                    <label style="font-size:11px; font-weight:800; color:var(--text-muted); text-transform:uppercase; letter-spacing:.6px; display:block; margin-bottom:6px;">หมายเหตุ</label>
                    <input type="text" id="req-remarks" placeholder="เช่น เปลี่ยนหน้าจอแตก..."
                           style="width:100%; padding:10px 12px; border:1.5px solid var(--border); border-radius:10px; background:var(--bg-surface-alt); color:var(--text-main); font-size:13px; font-family:inherit; outline:none;">
                </div>
            </div>

            <div id="req-error" style="display:none; margin-top:14px; padding:10px 14px; background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.3); border-radius:8px; color:#dc2626; font-size:13px; font-weight:600;"></div>

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:24px; padding-top:16px; border-top:1px solid var(--border);">
                <button type="button" class="cmns-btn cmns-btn-secondary" onclick="closeRequisitionModal()">ยกเลิก</button>
                <button type="button" id="req-submit-btn" class="cmns-btn cmns-btn-primary" style="background:#10b981; border-color:#10b981;" onclick="submitRequisition()">
                    <span class="material-symbols-rounded">output</span> ยืนยันเบิก
                </button>
            </div>
        </div>
    </div>

<!-- ===== Edit (แก้ไข) Modal ===== -->
<div id="modal-edit" class="cmns-modal">
    <div class="modal-content" style="max-width: 750px; padding: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 20px; margin-bottom: 25px;">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 10px; color: var(--text-main); font-weight: 800; font-size: 20px;">
                <span class="material-symbols-rounded" style="color: var(--primary); font-size: 28px;">edit</span>
                แก้ไขข้อมูลสินค้า
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeEditModal()"><span class="material-symbols-rounded">close</span></button>
        </div>

        <div id="edit-loading" style="text-align:center; padding: 60px; color:var(--text-muted);">
            <span class="material-symbols-rounded" style="font-size:36px; animation: spin 1s linear infinite;">sync</span>
        </div>

        <form id="form-edit-item" action="process_edit.php" method="POST" enctype="multipart/form-data" style="display:none;">
            <input type="hidden" name="id" id="edit-id">
            <input type="hidden" name="type" id="edit-type" value="new">
            <select name="status" id="edit-status" style="display:none;" disabled>
                <option value="STOCK">STOCK</option><option value="OOS">OOS</option>
                <option value="GOOD">GOOD</option><option value="TEST">TEST</option><option value="DEAD">DEAD</option>
                <option value="READY">READY</option><option value="PARTIAL">PARTIAL</option>
                <option value="DISCOUNT">DISCOUNT</option>
            </select>

            <!-- Info bar: Type + Status (read-only) -->
            <div id="edit-info-bar" style="display:flex; align-items:center; gap:8px; margin-bottom:16px; flex-wrap:wrap;">
                <span style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.5px;">ข้อมูลระบบ :</span>
                <div id="edit-type-badge"></div>
                <div id="edit-status-badge"></div>
            </div>

            <div style="display: flex; gap: 25px; flex-wrap: wrap; margin-bottom: 20px;">
                <!-- Image -->
                <div style="flex-shrink: 0;">
                    <label class="cmns-label">รูปสินค้า</label>
                    <div style="width: 120px; aspect-ratio:1; border: 2px dashed var(--border); border-radius:12px; display:flex; flex-direction:column; align-items:center; justify-content:center; position:relative; overflow:hidden; cursor:pointer;" onclick="document.getElementById('edit-image').click()">
                        <div id="edit-img-placeholder" style="display:flex; flex-direction:column; align-items:center; color:var(--text-muted); opacity:0.5;">
                            <span class="material-symbols-rounded" style="font-size:30px;">add_photo_alternate</span>
                            <span style="font-size:10px; font-weight:700;">เปลี่ยนรูป</span>
                        </div>
                        <img id="edit-img-preview" src="" style="width:100%; height:100%; object-fit:contain; display:none; position:absolute; top:0; left:0; background:var(--bg-surface-alt);">
                    </div>
                    <input type="file" name="image" id="edit-image" accept="image/*" hidden onchange="previewEditImage(this)">
                </div>

                <!-- Main fields -->
                <div style="flex:1; min-width: 280px;">
                    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:12px; margin-bottom:12px;">
                        <div>
                            <label class="cmns-label">ชื่อสินค้า <span style="color:red">*</span></label>
                            <input type="text" name="name" id="edit-name" class="cmns-input" required>
                        </div>
                        <div>
                            <label class="cmns-label">SKU</label>
                            <input type="text" name="sku" id="edit-sku" class="cmns-input">
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                        <div>
                            <label class="cmns-label">ราคาขาย (฿)</label>
                            <input type="number" name="sell_price" id="edit-sell-price" class="cmns-input" step="0.01">
                        </div>
                        <div>
                            <label class="cmns-label">ตำแหน่งเก็บ</label>
                            <input type="text" name="location" id="edit-location" class="cmns-input" placeholder="ตู้ A ชั้น 2">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dynamic fields ตาม type -->
            <div id="edit-dynamic-fields" style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:20px;"></div>

            <!-- ── เติมสต็อก section (toggle) ── -->
            <div id="restock-section" style="display:none; border-top:1.5px dashed var(--border); margin-top:4px; padding-top:18px; margin-bottom:4px;">
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:14px;">
                    <span class="material-symbols-rounded" style="font-size:20px; color:#10b981;">add_box</span>
                    <span style="font-weight:700; font-size:14px; color:var(--text-main);">เพิ่มสต็อก Lot ใหม่</span>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <label class="cmns-label">จำนวนที่รับเข้า <span style="color:red">*</span></label>
                        <input type="number" name="qty_received" id="rs-qty" class="cmns-input" min="1" value="" placeholder="เช่น 5" disabled>
                    </div>
                    <div>
                        <label class="cmns-label">ราคาทุน / ชิ้น (฿)</label>
                        <input type="number" name="cost_price" id="rs-cost" class="cmns-input" min="0" step="0.01" value="0">
                    </div>
                    <div>
                        <label class="cmns-label">Supplier / แหล่งที่มา</label>
                        <input type="text" name="supplier_name" id="rs-supplier" class="cmns-input" placeholder="เช่น Apple Parts TH">
                    </div>
                    <div>
                        <label class="cmns-label">ประกันหมดอายุ</label>
                        <input type="date" name="warranty_end" id="rs-warranty" class="cmns-input">
                    </div>
                </div>
                <div style="margin-top:4px;">
                    <label class="cmns-label">Lot Number <span style="font-size:11px; color:var(--text-muted);">(ว่างเว้น = ออโต้)</span></label>
                    <input type="text" name="lot_number" id="rs-lot" class="cmns-input" placeholder="เช่น LOT-2026-001">
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; align-items:center; gap:10px; margin-top:18px; padding-top:16px; border-top:1px solid var(--border);">
                    <button type="button" class="cmns-btn cmns-btn-secondary" onclick="closeEditModal()">
                        <span class="material-symbols-rounded">close</span> ยกเลิก
                    </button>
                    <button type="button" id="btn-toggle-restock"
                            onclick="toggleRestockSection()"
                            class="cmns-btn cmns-btn-warranty">
                        <span class="material-symbols-rounded">add_box</span> เติมสต็อก
                    </button>
                <button type="submit" class="cmns-btn cmns-btn-primary">
                    <span class="material-symbols-rounded">save</span> บันทึก
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ===== Shared action-button styles ===== -->
<style>
/* ── Warranty / restock button ── */
.cmns-btn-warranty {
    background: rgba(16,185,129,.1);
    color: #059669;
    border: 1px solid rgba(16,185,129,.35) !important;
}
.cmns-btn-warranty:hover {
    background: #10b981;
    color: #fff;
    border-color: #10b981 !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16,185,129,.35);
}
[data-theme="dark"] .cmns-btn-warranty { background: rgba(16,185,129,.1); color: #34d399; }

/* ── Lot option cards ── */
.lot-option {
    display:flex; align-items:center; gap:10px;
    border:1.5px solid var(--border); border-radius:10px;
    padding:10px 14px; cursor:pointer; transition:border-color .15s, background .15s;
}
.lot-option:has(input:checked) { border-color:#10b981; background:rgba(16,185,129,.06); }
.lot-option:hover:not(.lot-expired) { border-color:#10b981; }
.lot-option input[type="radio"] { accent-color:#10b981; width:16px; height:16px; flex-shrink:0; cursor:pointer; }
.lot-opt-body { display:flex; align-items:center; justify-content:space-between; gap:12px; flex:1; min-width:0; }
.lot-expired { opacity:.45; cursor:not-allowed; }
.lot-expired input { cursor:not-allowed; }

/* ── NEW parts action buttons ── */
.inv-btn {
    width:32px; height:32px; border-radius:7px; border:1px solid var(--border);
    background:var(--bg-surface-alt); color:var(--text-muted);
    display:inline-flex; align-items:center; justify-content:center;
    cursor:pointer; transition:all .18s; padding:0;
}
.inv-btn .material-symbols-rounded { font-size:16px; }
.inv-btn:hover { transform:translateY(-1px); box-shadow:0 3px 8px rgba(0,0,0,.1); }
.inv-btn-edit:hover { color:var(--primary); background:rgba(37,99,235,.07); border-color:var(--primary); }
.inv-btn-requisition:hover { color:#10b981; background:rgba(16,185,129,.07); border-color:#10b981; }
.inv-btn-requisition.disabled { opacity:.35; cursor:not-allowed; }
.inv-btn-requisition.disabled:hover { transform:none; box-shadow:none; color:var(--text-muted); background:var(--bg-surface-alt); border-color:var(--border); }
.inv-btn-to-sale:hover { color:#ef4444; background:rgba(239,68,68,.08); border-color:#ef4444; }
.inv-btn-to-sale.disabled { opacity:.35; cursor:not-allowed; }
.inv-btn-to-sale.disabled:hover { transform:none; box-shadow:none; color:var(--text-muted); background:var(--bg-surface-alt); border-color:var(--border); }

/* ── OOS row ── */
.row-oos td { opacity:.55; }
.row-oos:hover td { opacity:.75; }
.row-dead td { opacity:.5; background: rgba(239,68,68,.04); }
.row-dead:hover td { opacity:.7; }
</style>

<!-- ===== Requisition JS ===== -->
<script>
let _reqInventoryId = null;
let _jobSearchTimer = null;

/* ── Open modal ── */
function openRequisitionModal(inventoryId, itemType = 'new') {
    _reqInventoryId = inventoryId;

    // อัปเดต title ตาม type
    const titleEl = document.querySelector('#modal-requisition h3');
    if (titleEl) {
        if (itemType === 'used') {
            titleEl.innerHTML = '<span class="material-symbols-rounded" style="color:#f59e0b;font-size:26px;">build</span> ใช้อะไหล่ USED';
        } else {
            titleEl.innerHTML = '<span class="material-symbols-rounded" style="color:#10b981;font-size:26px;">output</span> เบิกอะไหล่ใหม่';
        }
    }

    // Reset
    document.getElementById('req-qty').value           = 1;
    document.getElementById('req-qty').max             = 99;
    document.getElementById('req-job-search').value    = '';
    document.getElementById('req-remarks').value       = '';
    document.getElementById('req-tracking-id').value   = '';
    document.getElementById('req-ticket-number').value = '';
    document.getElementById('req-lot-id').value        = '';
    document.getElementById('req-qty-max-label').textContent = '';
    document.getElementById('req-job-results').style.display  = 'none';
    document.getElementById('req-job-selected').style.display = 'none';
    document.getElementById('req-error').style.display        = 'none';
    document.getElementById('req-item-name').textContent = 'กำลังโหลด...';
    document.getElementById('req-item-sku').textContent  = '';
    document.getElementById('req-item-qty').textContent  = '';
    document.getElementById('req-lots-wrap').innerHTML  =
        '<div style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px;"><span class="material-symbols-rounded" style="animation:spin 1s linear infinite;font-size:20px;display:block;margin-bottom:6px;">sync</span>กำลังโหลด lots...</div>';

    document.getElementById('modal-requisition').classList.add('show');
    document.body.style.overflow = 'hidden';

    // Load item + lots in parallel
    Promise.all([
        fetch(`process_requisition.php?action=get_item&id=${inventoryId}`).then(r => r.json()),
        fetch(`process_requisition.php?action=get_lots&item_id=${inventoryId}`).then(r => r.json())
    ]).then(([item, lots]) => {
        document.getElementById('req-item-name').textContent = item.name || '—';
        document.getElementById('req-item-sku').textContent  = item.sku  || '—';
        document.getElementById('req-item-qty').textContent  = item.total_qty ?? 0;
        renderLots(lots, item.total_qty ?? 0);
    }).catch(() => {
        document.getElementById('req-item-name').textContent = 'โหลดไม่สำเร็จ';
    });
}

function renderLots(lots, totalQty) {
    const wrap = document.getElementById('req-lots-wrap');
    if (!lots.length) {
        wrap.innerHTML = '<div style="padding:14px;font-size:13px;color:#ef4444;">ไม่มีสต็อกในระบบ</div>';
        return;
    }

    let html = '';

    // FIFO option
    html += `
        <label class="lot-option" id="lot-opt-auto">
            <input type="radio" name="lot_pick" value="auto" checked onchange="onLotChange('auto',${totalQty})">
            <div class="lot-opt-body">
                <div style="display:flex;align-items:center;gap:8px;">
                    <span class="material-symbols-rounded" style="font-size:16px;color:var(--primary);">auto_mode</span>
                    <span style="font-weight:700;font-size:13px;color:var(--text-main);">FIFO อัตโนมัติ</span>
                    <span style="font-size:11px;color:var(--text-muted);">(ตัดจากล็อตเก่าสุดก่อน)</span>
                </div>
                <span style="font-size:12px;color:var(--primary);font-weight:700;">รวม ${totalQty} ชิ้น</span>
            </div>
        </label>`;

    lots.forEach(lot => {
        const wEnd = lot.warranty_end ? new Date(lot.warranty_end) : null;
        const daysLeft = wEnd ? Math.ceil((wEnd - new Date()) / 86400000) : null;
        const wColor = !wEnd ? 'var(--text-muted)' : daysLeft < 0 ? '#ef4444' : daysLeft < 30 ? '#f59e0b' : '#10b981';
        const wText  = !wEnd ? '—' : wEnd.toLocaleDateString('th-TH',{day:'2-digit',month:'2-digit',year:'2-digit'});
        const expired = daysLeft !== null && daysLeft < 0;

        html += `
            <label class="lot-option ${expired ? 'lot-expired' : ''}" id="lot-opt-${lot.id}">
                <input type="radio" name="lot_pick" value="${lot.id}" ${expired?'disabled':''} onchange="onLotChange(${lot.id},${lot.qty_remaining})">
                <div class="lot-opt-body">
                    <div style="min-width:0;flex:1;">
                        <div style="font-weight:700;font-size:13px;color:var(--text-main);font-family:monospace;">${_escH(lot.lot_number)}</div>
                        <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">${lot.supplier_name ? _escH(lot.supplier_name) : 'ไม่ระบุ Supplier'}</div>
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:3px;flex-shrink:0;">
                        <span style="font-size:15px;font-weight:800;color:var(--text-main);">${lot.qty_remaining} <span style="font-size:10px;font-weight:500;color:var(--text-muted);">/ ${lot.qty_received}</span></span>
                        <span style="font-size:10px;font-weight:700;color:${wColor};">ประกัน ${wText}${expired?' (หมดแล้ว)':''}</span>
                        ${lot.cost_price > 0 ? `<span style="font-size:11px;color:var(--text-muted);">฿${Number(lot.cost_price).toLocaleString()}</span>` : ''}
                    </div>
                </div>
            </label>`;
    });

    wrap.innerHTML = html;

    // Set default max to total
    setQtyMax(totalQty);
}

function onLotChange(val, maxQty) {
    document.getElementById('req-lot-id').value = val === 'auto' ? '' : val;
    setQtyMax(maxQty);
}

function setQtyMax(max) {
    const inp = document.getElementById('req-qty');
    inp.max = max;
    if (parseInt(inp.value) > max) inp.value = max;
    document.getElementById('req-qty-max-label').textContent = `max ${max}`;
}

function _escH(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function closeRequisitionModal() {
    document.getElementById('modal-requisition').classList.remove('show');
    document.body.style.overflow = '';
    document.getElementById('req-job-results').style.display = 'none';
}

/* ── Qty +/- ── */
function adjustQty(delta) {
    const input = document.getElementById('req-qty');
    const maxQ  = parseInt(document.getElementById('req-item-qty').textContent) || 1;
    input.value = Math.max(1, Math.min(maxQ, (parseInt(input.value) || 1) + delta));
}

/* ── Job search ── */
function searchJobs(q) {
    clearTimeout(_jobSearchTimer);
    const results = document.getElementById('req-job-results');

    if (q.length < 2) { results.style.display = 'none'; return; }

    _jobSearchTimer = setTimeout(() => {
        fetch(`process_requisition.php?action=search_jobs&q=${encodeURIComponent(q)}`)
            .then(r => r.json())
            .then(jobs => {
                if (!jobs.length) {
                    results.innerHTML = `<div style="padding:14px 16px; font-size:13px; color:var(--text-muted);">ไม่พบงานซ่อมที่ตรงกัน</div>`;
                } else {
                    results.innerHTML = jobs.map(j => `
                        <div class="req-job-item" onclick="selectJob(${j.id},'${escJ(j.ticket_number)}','${escJ(j.customer_name)}','${escJ(j.device_type)}','${escJ(j.device_model)}')"
                             style="padding:10px 14px; cursor:pointer; border-bottom:1px solid var(--border); transition:background .12s;">
                            <div style="font-weight:700; font-size:13px; color:var(--text-main);">
                                <code style="color:var(--primary); font-size:12px;">${escH(j.ticket_number)}</code>
                                &nbsp;${escH(j.customer_name)}
                            </div>
                            <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">
                                ${escH(j.device_type)} ${escH(j.device_model)} · ${escH(j.customer_phone)}
                            </div>
                        </div>
                    `).join('');
                }
                results.style.display = 'block';
            });
    }, 300);
}

function selectJob(id, ticket, name, dtype, dmodel) {
    document.getElementById('req-tracking-id').value   = id;
    document.getElementById('req-ticket-number').value = ticket;
    document.getElementById('req-job-search').value    = '';
    document.getElementById('req-job-results').style.display = 'none';
    document.getElementById('req-job-label').innerHTML =
        `<code style="color:var(--primary);">${escH(ticket)}</code> &nbsp;${escH(name)} · ${escH(dtype)} ${escH(dmodel)}`;
    document.getElementById('req-job-selected').style.display = 'block';
}

function clearJobSelection() {
    document.getElementById('req-tracking-id').value   = '';
    document.getElementById('req-ticket-number').value = '';
    document.getElementById('req-job-selected').style.display = 'none';
}

/* ── Submit ── */
function submitRequisition() {
    const btn = document.getElementById('req-submit-btn');
    const err = document.getElementById('req-error');
    err.style.display = 'none';
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-rounded" style="animation:spin 1s linear infinite;">sync</span> กำลังเบิก...';

    const body = new FormData();
    body.append('action',         'requisition');
    body.append('inventory_id',   _reqInventoryId);
    body.append('qty',            document.getElementById('req-qty').value);
    body.append('lot_id',         document.getElementById('req-lot-id').value);
    body.append('tracking_id',    document.getElementById('req-tracking-id').value);
    body.append('ticket_number',  document.getElementById('req-ticket-number').value);
    body.append('remarks',        document.getElementById('req-remarks').value);

    fetch('process_requisition.php', { method: 'POST', body })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                closeRequisitionModal();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon:'success', title: res.msg, toast:true, position:'top-end', showConfirmButton:false, timer:3000, timerProgressBar:true });
                }
                setTimeout(() => location.reload(), 500);
            } else {
                err.textContent = res.msg;
                err.style.display = 'block';
                btn.disabled = false;
                btn.innerHTML = '<span class="material-symbols-rounded">output</span> ยืนยันเบิก';
            }
        })
        .catch(() => {
            err.textContent = 'เกิดข้อผิดพลาด กรุณาลองใหม่';
            err.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-rounded">output</span> ยืนยันเบิก';
        });
}

/* ── Helpers ── */
function escH(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function escJ(s) { return String(s||'').replace(/'/g,"\\'"); }

// Close on backdrop click
document.getElementById('modal-requisition').addEventListener('click', function(e) {
    if (e.target === this) closeRequisitionModal();
});

// Close job results on outside click
document.addEventListener('click', function(e) {
    if (!e.target.closest('#req-job-search') && !e.target.closest('#req-job-results')) {
        document.getElementById('req-job-results').style.display = 'none';
    }
});

// Hover effect for job items
document.getElementById('req-job-results').addEventListener('mouseover', e => {
    const item = e.target.closest('.req-job-item');
    if (item) item.style.background = 'var(--bg-surface-alt)';
});
document.getElementById('req-job-results').addEventListener('mouseout', e => {
    const item = e.target.closest('.req-job-item');
    if (item) item.style.background = '';
});
</script>

<!-- ===== Edit JS (edit-only, strip helpers excluded) ===== -->
<script>
function openEditModal(id) {
    const modal = document.getElementById('modal-edit');
    const form  = document.getElementById('form-edit-item');
    const loading = document.getElementById('edit-loading');
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    form.style.display = 'none';
    loading.style.display = 'block';

    fetch(`process_edit.php?action=get_item&id=${id}`)
        .then(r => r.json())
        .then(item => {
            if (!item) { alert('ไม่พบข้อมูล'); closeEditModal(); return; }

            document.getElementById('edit-id').value         = item.id;
            document.getElementById('edit-name').value       = item.name || '';
            document.getElementById('edit-sku').value        = item.sku || '';
            document.getElementById('edit-sell-price').value = item.sell_price || '';
            document.getElementById('edit-location').value   = item.location || '';

            // ── Type badge (info only) ──
            const typeInput = document.getElementById('edit-type');
            const typeVal   = item.type || 'new';
            typeInput.value = typeVal;
            const typeColor = {new:'#10b981', used:'#f59e0b', machine:'#8b5cf6', sale:'#ef4444'};
            const tc = typeColor[typeVal] || 'var(--primary)';
            document.getElementById('edit-type-badge').innerHTML =
                `<span style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:800;background:${tc}18;color:${tc};border:1px solid ${tc}33;">`+
                `<span style="width:6px;height:6px;border-radius:50%;background:${tc};"></span>`+
                `${typeVal.toUpperCase()}</span>`;

            // ── Status badge (info only) — actual value set by applyStatusOptions ──
            applyStatusOptions(typeVal, item.total_qty ?? 0, item.status);

            // รูปเดิม
            const preview = document.getElementById('edit-img-preview');
            const placeholder = document.getElementById('edit-img-placeholder');
            if (item.image) {
                preview.src = `../../uploads/inventory/${item.image}`;
                preview.style.display = 'block';
                placeholder.style.display = 'none';
            } else {
                preview.style.display = 'none';
                placeholder.style.display = 'flex';
            }

            form.dataset.item = JSON.stringify(item);
            toggleEditTypeFields();

            loading.style.display = 'none';
            form.style.display = 'block';
        })
        .catch(() => { alert('โหลดข้อมูลล้มเหลว'); closeEditModal(); });
}

function applyStatusOptions(type, totalQty, currentStatus) {
    const sel     = document.getElementById('edit-status');
    const badgeEl = document.getElementById('edit-status-badge');
    const SC = {STOCK:'#10b981',OOS:'#ef4444',GOOD:'#10b981',TEST:'#f59e0b',DEAD:'#ef4444',READY:'#10b981',SOLD:'#6b7280',PENDING:'#f59e0b'};

    if (type === 'new') {
        const st = totalQty > 0 ? 'STOCK' : 'OOS';
        sel.value = st; sel.disabled = true;
        const c = SC[st];
        badgeEl.innerHTML =
            `<span style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;`+
            `font-size:12px;font-weight:800;background:${c}18;color:${c};border:1px solid ${c}33;">`+
            `<span class="material-symbols-rounded" style="font-size:12px;">auto_mode</span>`+
            `${st}${totalQty>0?` · ${totalQty} ชิ้น`:''}</span>`;
    } else {
        const st = currentStatus || 'STOCK';
        sel.value = st; sel.disabled = false;
        const c = SC[st] || '#888';
        const opts = type === 'used'
            ? ['GOOD','TEST','DEAD']
            : type === 'new'  ? ['STOCK','OOS']
            : type === 'machine' ? ['READY','PARTIAL','stripped']
            : ['READY','SOLD','PENDING'];
        badgeEl.innerHTML =
            `<select onchange="syncStatusSelect(this)" `+
            `style="padding:3px 10px;border-radius:20px;border:1px solid ${c}44;background:${c}18;`+
            `color:${c};font-size:12px;font-weight:800;outline:none;cursor:pointer;">`+
            opts.map(o=>
                `<option value="${o}" ${o===st?'selected':''}>${o}</option>`).join('')+`</select>`;
    }
}

function updateAdjPreview(curQty) {
    const mode   = document.getElementById('adj-mode');
    const qtyInp = document.getElementById('adj-qty');
    const prev   = document.getElementById('adj-preview');
    if (!mode || !qtyInp || !prev) return;

    const modeVal = mode.value;
    qtyInp.style.opacity = modeVal ? '1' : '.35';
    qtyInp.style.pointerEvents = modeVal ? 'auto' : 'none';
    if (!modeVal) { prev.textContent = ''; qtyInp.value = ''; return; }

    const n = parseInt(qtyInp.value) || 0;
    let result, color;
    if (modeVal === 'add')      { result = curQty + n; color = '#10b981'; prev.textContent = `${curQty} + ${n} = ${result} ชิ้น`; }
    else if (modeVal === 'sub') { result = Math.max(0, curQty - n); color = '#ef4444'; prev.textContent = `${curQty} − ${n} = ${result} ชิ้น`; }
    else if (modeVal === 'set') { result = n; color = '#3b82f6'; prev.textContent = `ตั้งค่าเป็น ${n} ชิ้น`; }
    prev.style.color = color;
    prev.style.fontWeight = '600';
}

function syncStatusSelect(miniSel) {
    const val = miniSel.value;
    document.getElementById('edit-status').value = val;
    const SC = {STOCK:'#10b981',OOS:'#ef4444',GOOD:'#10b981',TEST:'#f59e0b',DEAD:'#ef4444',READY:'#10b981',SOLD:'#6b7280',PENDING:'#f59e0b'};
    const c = SC[val] || '#888';
    miniSel.style.borderColor = c+'44'; miniSel.style.background = c+'18'; miniSel.style.color = c;
}

function toggleEditTypeFields() {
    const type = document.getElementById('edit-type').value; // hidden input

    const form = document.getElementById('form-edit-item');
    let item = {};
    try { item = JSON.parse(form.dataset.item || '{}'); } catch(e) {}
    const totalQty = item.total_qty ?? 0;
    applyStatusOptions(type, totalQty, document.getElementById('edit-status').value);

    const container = document.getElementById('edit-dynamic-fields');
    try { item = JSON.parse(form.dataset.item || '{}'); } catch(e) {}
    let html = '';

    if (type === 'new') {
        const curQty = parseInt(item.total_qty) || 0;
        html = `
            <div><label class="cmns-label">เลขพาร์ท (Part No.)</label><input type="text" name="part_number" class="cmns-input" value="${esc(item.part_number)}" placeholder="เช่น 661-123"></div>
            <div><label class="cmns-label">รุ่นรองรับ (Model)</label><input type="text" name="compatible_models" class="cmns-input" value="${esc(item.compatible_models)}" placeholder="เช่น A2337"></div>
            <div>
                <label class="cmns-label" style="color:#f59e0b;">เตือนของหมด (Min Qty)</label>
                <input type="number" name="min_qty" class="cmns-input" value="${item.min_qty || 1}" min="0">
            </div>
            <div>
                <label class="cmns-label" style="display:flex;justify-content:space-between;">
                    <span>ปรับสต็อก (Adjust)</span>
                    <span style="color:var(--text-muted);font-weight:400;">ปัจจุบัน: <b style="color:var(--text-main);">${curQty}</b></span>
                </label>
                <div style="display:flex;gap:6px;align-items:center;">
                    <select name="adjust_mode" id="adj-mode"
                            style="padding:9px 10px;border:1.5px solid var(--border);border-radius:8px;background:var(--bg-surface-alt);color:var(--text-main);font-size:13px;outline:none;flex-shrink:0;"
                            onchange="updateAdjPreview(${curQty})">
                        <option value="">— ไม่ปรับ —</option>
                        <option value="add">+ เพิ่ม</option>
                        <option value="sub">− ลด</option>
                        <option value="set">= ตั้งค่า</option>
                    </select>
                    <input type="number" name="adjust_qty" id="adj-qty" min="0" placeholder="0"
                           class="cmns-input" style="flex:1; opacity:.35; pointer-events:none;"
                           oninput="updateAdjPreview(${curQty})">
                </div>
                <div id="adj-preview" style="font-size:11px;color:var(--text-muted);margin-top:4px;min-height:16px;"></div>
            </div>
        `;
    } else if (type === 'used') {
        html = `
            <div><label class="cmns-label">เลขพาร์ท (Part No.)</label><input type="text" name="part_number" class="cmns-input" value="${esc(item.part_number)}"></div>
            <div><label class="cmns-label">Min Qty</label><input type="number" name="min_qty" class="cmns-input" value="${item.min_qty || 1}" min="0"></div>
        `;
    } else if (type === 'machine') {
        html = `
            <div><label class="cmns-label">รหัสเครื่อง (Asset Tag)</label><input type="text" name="asset_tag" class="cmns-input" value="${esc(item.asset_tag)}"></div>
            <div><label class="cmns-label">Serial Number</label><input type="text" name="serial_number" class="cmns-input" value="${esc(item.serial_number)}"></div>
            <div style="grid-column:span 2;"><label class="cmns-label" style="color:#10b981;">สถานะการแยกอะไหล่</label>
                <select name="disassembly_status" class="cmns-input" style="border-color:#10b981;">
                    <option value="intact" ${item.disassembly_status=='intact'?'selected':''}>ยังไม่แกะ</option>
                    <option value="partially_stripped" ${item.disassembly_status=='partially_stripped'?'selected':''}>แกะไปบางส่วน</option>
                    <option value="stripped" ${item.disassembly_status=='stripped'?'selected':''}>แกะหมดแล้ว</option>
                </select>
            </div>
        `;
    } else if (type === 'sale') {
        const gradeOpts = ['A','B','C'].map(g =>
            `<option value="${g}" ${item.condition_grade===g?'selected':''}>${g}</option>`).join('');
        html = `
            <div>
                <label class="cmns-label" style="color:#ef4444;">Asset Tag</label>
                <input type="text" name="asset_tag" class="cmns-input" value="${esc(item.asset_tag)}" style="border-color:#ef4444;">
            </div>
            <div>
                <label class="cmns-label">Serial Number</label>
                <input type="text" name="serial_number" class="cmns-input" value="${esc(item.serial_number)}">
            </div>
            <div>
                <label class="cmns-label">สี (Color)</label>
                <input type="text" name="color" class="cmns-input" value="${esc(item.color)}" placeholder="เช่น Space Gray, Midnight">
            </div>
            <div>
                <label class="cmns-label" style="color:#ef4444;">เกรดสภาพ (Grade)</label>
                <select name="condition_grade" class="cmns-input" style="border-color:#ef4444;font-weight:700;">
                    <option value="">-- เลือกเกรด --</option>
                    ${gradeOpts}
                </select>
            </div>
            <div>
                <label class="cmns-label">CPU / Chip</label>
                <input type="text" name="cpu_spec" class="cmns-input" value="${esc(item.cpu_spec)}" placeholder="เช่น Apple M3 Pro">
            </div>
            <div>
                <label class="cmns-label">RAM</label>
                <input type="text" name="ram_spec" class="cmns-input" value="${esc(item.ram_spec)}" placeholder="เช่น 16GB">
            </div>
            <div>
                <label class="cmns-label">Storage</label>
                <input type="text" name="storage_spec" class="cmns-input" value="${esc(item.storage_spec)}" placeholder="เช่น 512GB SSD">
            </div>
            <div>
                <label class="cmns-label">GPU</label>
                <input type="text" name="gpu_spec" class="cmns-input" value="${esc(item.gpu_spec)}" placeholder="เช่น 18-core GPU">
            </div>
            <div>
                <label class="cmns-label" style="color:#10b981;">ประกัน Apple ศูนย์หมด</label>
                <input type="date" name="apple_warranty_date" class="cmns-input" value="${esc(item.apple_warranty_date)}" style="border-color:#10b981;">
            </div>
            <div>
                <label class="cmns-label" style="color:#3b82f6;">ประกันร้าน (วัน)</label>
                <input type="number" name="store_warranty_days" class="cmns-input" value="${item.store_warranty_days??''}" placeholder="เช่น 90, 180" min="0" style="border-color:#3b82f6;">
            </div>
            <div>
                <label class="cmns-label">สุขภาพแบต (%)</label>
                <input type="number" name="battery_health" class="cmns-input" value="${item.battery_health??''}" placeholder="เช่น 89" min="0" max="100">
            </div>
            <div>
                <label class="cmns-label">รอบชาร์จ</label>
                <input type="number" name="battery_cycles" class="cmns-input" value="${item.battery_cycles??''}" placeholder="เช่น 142" min="0">
            </div>
            <div style="grid-column:span 2;">
                <label class="cmns-label">ตำหนิ / รายละเอียดสภาพ</label>
                <textarea name="condition_note" class="cmns-input" rows="2" style="resize:vertical;" placeholder="เช่น มีรอยขีดข่วนด้านล่าง จอสมบูรณ์...">${esc(item.condition_note)}</textarea>
            </div>
        `;
    }
    container.innerHTML = html;
}

function esc(v) {
    if (!v) return '';
    return String(v).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function previewEditImage(input) {
    const preview = document.getElementById('edit-img-preview');
    const placeholder = document.getElementById('edit-img-placeholder');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { preview.src = e.target.result; preview.style.display='block'; placeholder.style.display='none'; };
        reader.readAsDataURL(input.files[0]);
    }
}

function closeEditModal() {
    const modal = document.getElementById('modal-edit');
    if (modal) { modal.classList.remove('show'); document.body.style.overflow = 'auto'; }
    // reset restock section
    const rs = document.getElementById('restock-section');
    const btn = document.getElementById('btn-toggle-restock');
    if (rs)  { rs.style.display = 'none'; rs.querySelectorAll('input').forEach(i => { if(i.name !== 'qty_received') i.value = i.defaultValue || ''; else i.value = 1; }); }
    if (btn) { btn.style.background = 'rgba(16,185,129,.1)'; btn.style.color = '#059669'; }
}

function toggleRestockSection() {
    const rs   = document.getElementById('restock-section');
    const btn  = document.getElementById('btn-toggle-restock');
    const open = rs.style.display === 'block';
    rs.style.display = open ? 'none' : 'block';
    rs.querySelectorAll('input').forEach(i => i.disabled = open);
    btn.classList.toggle('cmns-btn-warranty', open);
    btn.classList.toggle('cmns-btn-primary',  !open);
    if (!open) {
        btn.style.background = '#10b981';
        btn.style.borderColor = '#10b981';
        document.getElementById('rs-qty').focus();
    } else {
        btn.style.background = '';
        btn.style.borderColor = '';
    }
}
</script>
