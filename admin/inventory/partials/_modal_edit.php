<!-- Edit Modal -->
<div id="modal-edit" class="cmns-modal">
    <div class="modal-content" style="max-width: 750px; padding: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 20px; margin-bottom: 20px;">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 10px; color: var(--text-main); font-weight: 800; font-size: 20px;">
                <span class="material-symbols-rounded" style="color: var(--primary); font-size: 28px;">edit</span>
                <span id="edit-modal-title-text">แก้ไขข้อมูลสินค้า</span>
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
                <option value="SOLD">SOLD</option><option value="PENDING">PENDING</option>
            </select>

            <!-- ── Tabs: Details / Stock ── -->
            <div class="edit-tabs">
                <button type="button" class="edit-tab-btn active" id="edit-tab-btn-details" onclick="switchEditTab('details')">
                    <span class="material-symbols-rounded">badge</span> รายละเอียด
                </button>
                <button type="button" class="edit-tab-btn" id="edit-tab-btn-stock" onclick="switchEditTab('stock')">
                    <span class="material-symbols-rounded">package_2</span> สต็อก / Lot
                </button>
            </div>

            <!-- ════ TAB 1: DETAILS ════ -->
            <div id="edit-tab-details" class="edit-tab-pane active">

                <!-- Info bar: Type + Status (read-only) -->
                <div id="edit-info-bar" style="display:flex; align-items:center; gap:8px; margin-bottom:16px; flex-wrap:wrap;">
                    <span style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.5px;">ข้อมูลระบบ :</span>
                    <div id="edit-type-badge"></div>
                    <div id="edit-status-badge"></div>
                </div>

                <div id="edit-profile-block" style="display: flex; gap: 25px; flex-wrap: wrap; margin-bottom: 20px;">
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
                <div id="edit-dynamic-fields" style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:8px;"></div>
            </div>

            <!-- ════ TAB 2: STOCK (เฉพาะ type ที่มี lot: new/used) ════ -->
            <div id="edit-tab-stock" class="edit-tab-pane">

                <!-- ปรับสต็อก (JS render เฉพาะ type new) -->
                <div id="edit-adjust-block" style="margin-bottom:8px;"></div>

                <!-- เติมสต็อก lot ใหม่ — ปล่อยว่าง = ไม่เติม -->
                <div id="restock-section" style="border-top:1.5px dashed var(--border); margin-top:4px; padding-top:18px; margin-bottom:4px;">
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:14px;">
                        <span class="material-symbols-rounded" style="font-size:20px; color:#10b981;">add_box</span>
                        <span style="font-weight:700; font-size:14px; color:var(--text-main);">เพิ่มสต็อก Lot ใหม่</span>
                        <span style="font-size:11px; color:var(--text-muted);">(ไม่กรอกจำนวน = ไม่เติม)</span>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div>
                            <label class="cmns-label">จำนวนที่รับเข้า</label>
                            <input type="number" name="qty_received" id="rs-qty" class="cmns-input" min="1" value="" placeholder="เช่น 5">
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
            </div>

            <div style="display:flex; justify-content:flex-end; align-items:center; gap:10px; margin-top:18px; padding-top:16px; border-top:1px solid var(--border);">
                <button type="button" class="cmns-btn cmns-btn-secondary" onclick="closeEditModal()">
                    <span class="material-symbols-rounded">close</span> ยกเลิก
                </button>
                <button type="submit" class="cmns-btn cmns-btn-primary">
                    <span class="material-symbols-rounded">save</span> บันทึก
                </button>
            </div>
        </form>
    </div>
</div>
