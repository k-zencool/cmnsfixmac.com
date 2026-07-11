<!-- ══════════════════════════════════════════════════════
     Transfer to SALE Modal
═══════════════════════════════════════════════════════ -->
<div id="modal-to-sale" class="cmns-modal">
    <div class="modal-content" style="max-width:820px; padding:30px; max-height:92vh; overflow-y:auto;">

        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); padding-bottom:18px; margin-bottom:22px;">
            <h3 style="margin:0; display:flex; align-items:center; gap:10px; font-weight:800; font-size:20px; color:var(--text-main);">
                <span class="material-symbols-rounded" style="color:#ef4444; font-size:26px;">sell</span>
                ย้ายไป SALE
            </h3>
            <button class="modal-close-btn" onclick="closeToSaleModal()"><span class="material-symbols-rounded">close</span></button>
        </div>

        <!-- Source info badge -->
        <div id="ts-source-info" style="display:flex; align-items:center; gap:12px; padding:12px 16px; border-radius:10px; border:1px solid var(--border); background:var(--bg-surface-alt); margin-bottom:20px;">
            <span id="ts-source-icon" class="material-symbols-rounded" style="font-size:22px;"></span>
            <div>
                <div id="ts-source-name" style="font-weight:700; font-size:14px; color:var(--text-main);">กำลังโหลด...</div>
                <div id="ts-source-meta" style="font-size:11px; color:var(--text-muted); margin-top:2px;"></div>
            </div>
        </div>

        <!-- Lot selector + Qty (NEW only) -->
        <div id="ts-lot-section" style="display:none; margin-bottom:20px; padding:16px; border:1px solid rgba(37,99,235,.25); border-radius:10px; background:rgba(37,99,235,.04);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <div style="font-size:11px; font-weight:800; color:var(--primary); text-transform:uppercase; letter-spacing:.8px; display:flex; align-items:center; gap:6px;">
                    <span class="material-symbols-rounded" style="font-size:16px;">account_tree</span>
                    เลือก LOT
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <label style="font-size:11px; font-weight:800; color:var(--primary); text-transform:uppercase; letter-spacing:.8px;">จำนวน</label>
                    <button type="button" onclick="tsAdjQty(-1)" style="width:28px;height:28px;border-radius:6px;border:1px solid rgba(37,99,235,.35);background:rgba(37,99,235,.08);color:var(--primary);font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1;">−</button>
                    <input type="number" id="ts-qty" name="qty" value="1" min="1" max="1"
                           style="width:56px;text-align:center;padding:5px 6px;border:1.5px solid rgba(37,99,235,.4);border-radius:8px;background:var(--bg-surface);color:var(--text-main);font-size:15px;font-weight:800;outline:none;">
                    <button type="button" onclick="tsAdjQty(1)" style="width:28px;height:28px;border-radius:6px;border:1px solid rgba(37,99,235,.35);background:rgba(37,99,235,.08);color:var(--primary);font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1;">+</button>
                    <span id="ts-qty-max-label" style="font-size:11px;color:var(--text-muted);"></span>
                </div>
            </div>
            <div id="ts-lots-wrap"></div>
            <input type="hidden" id="ts-lot-id" value="">
        </div>

        <form id="form-to-sale">
            <input type="hidden" id="ts-source-type"  name="source_type">
            <input type="hidden" id="ts-inventory-id" name="inventory_id">

            <!-- Row 1: Name + Sell Price + Status + Grade -->
            <div style="display:grid; grid-template-columns:2fr 1fr 1fr 1fr; gap:12px; margin-bottom:14px;">
                <div>
                    <label class="cmns-label">ชื่อสินค้า <span style="color:red">*</span></label>
                    <input type="text" name="name" id="ts-name" class="cmns-input" required>
                </div>
                <div>
                    <label class="cmns-label">ราคาขาย (฿)</label>
                    <input type="number" name="sell_price" id="ts-sell-price" class="cmns-input" step="1" value="0" min="0">
                </div>
                <div>
                    <label class="cmns-label">Status</label>
                    <select name="status" id="ts-status" class="cmns-input" style="font-weight:700;">
                        <option value="PENDING">PENDING</option>
                        <option value="READY">READY</option>
                    </select>
                </div>
                <div>
                    <label class="cmns-label">Grade</label>
                    <select name="condition_grade" id="ts-grade" class="cmns-input" style="font-weight:700;">
                        <option value="">— ไม่ระบุ —</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                        <option value="D">D</option>
                    </select>
                </div>
            </div>

            <!-- Row 2: Serial + Asset Tag + Color -->
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-bottom:14px;">
                <div>
                    <label class="cmns-label">Serial No.</label>
                    <input type="text" name="serial_number" id="ts-serial" class="cmns-input" placeholder="XXXXXXXXXXXXX">
                </div>
                <div>
                    <label class="cmns-label">Asset Tag</label>
                    <input type="text" name="asset_tag" id="ts-asset-tag" class="cmns-input" placeholder="CMNS-0001">
                </div>
                <div>
                    <label class="cmns-label">สี (Color)</label>
                    <input type="text" name="color" id="ts-color" class="cmns-input" placeholder="Space Gray">
                </div>
            </div>

            <!-- Row 3: Specs -->
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-bottom:14px;">
                <div>
                    <label class="cmns-label">CPU / Chip</label>
                    <input type="text" name="cpu_spec" id="ts-cpu" class="cmns-input" placeholder="M2 Pro 12-core">
                </div>
                <div>
                    <label class="cmns-label">RAM</label>
                    <input type="text" name="ram_spec" id="ts-ram" class="cmns-input" placeholder="16GB">
                </div>
                <div>
                    <label class="cmns-label">Storage</label>
                    <input type="text" name="storage_spec" id="ts-storage" class="cmns-input" placeholder="512GB SSD">
                </div>
            </div>

            <!-- Row 4: Warranty + Battery -->
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:12px; margin-bottom:14px;">
                <div>
                    <label class="cmns-label">ประกัน Apple (วันหมด)</label>
                    <input type="date" name="apple_warranty_date" id="ts-apple-warranty" class="cmns-input">
                </div>
                <div>
                    <label class="cmns-label">ประกันร้าน (วัน)</label>
                    <input type="number" name="store_warranty_days" id="ts-store-warranty" class="cmns-input" min="0" placeholder="90">
                </div>
                <div>
                    <label class="cmns-label">Battery Health (%)</label>
                    <input type="number" name="battery_health" id="ts-battery-health" class="cmns-input" min="0" max="100" placeholder="89">
                </div>
                <div>
                    <label class="cmns-label">Battery Cycles</label>
                    <input type="number" name="battery_cycles" id="ts-battery-cycles" class="cmns-input" min="0" placeholder="150">
                </div>
            </div>

            <!-- Row 5: Condition note -->
            <div style="margin-bottom:6px;">
                <label class="cmns-label">ตำหนิ / สภาพ</label>
                <input type="text" name="condition_note" id="ts-condition-note" class="cmns-input" placeholder="มีรอยขีดข่วนเล็กน้อยด้านล่าง...">
            </div>
        </form>

        <div id="ts-error" style="display:none; margin-top:14px; padding:10px 14px; background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.3); border-radius:8px; color:#dc2626; font-size:13px; font-weight:600;"></div>

        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:22px; padding-top:16px; border-top:1px solid var(--border);">
            <button type="button" class="cmns-btn cmns-btn-secondary" onclick="closeToSaleModal()">ยกเลิก</button>
            <button type="button" id="ts-submit-btn" class="cmns-btn cmns-btn-primary" style="background:#ef4444; border-color:#ef4444;" onclick="submitToSale()">
                <span class="material-symbols-rounded">sell</span> ย้ายไป SALE
            </button>
        </div>
    </div>
</div>
