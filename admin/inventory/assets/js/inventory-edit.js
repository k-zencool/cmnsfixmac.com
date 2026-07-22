function switchEditTab(name) {
    ['details', 'stock'].forEach(t => {
        document.getElementById(`edit-tab-${t}`).classList.toggle('active', t === name);
        document.getElementById(`edit-tab-btn-${t}`).classList.toggle('active', t === name);
    });
}

function openEditModal(id) {
    const modal = document.getElementById('modal-edit');
    const form  = document.getElementById('form-edit-item');
    const loading = document.getElementById('edit-loading');
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    form.style.display = 'none';
    loading.style.display = 'block';
    switchEditTab('details');

    fetch(`process_edit.php?action=get_item&id=${id}`)
        .then(r => {
            if (r.status === 403) throw new Error('no-perm');
            if (!r.ok) throw new Error('http-' + r.status);
            return r.json();
        })
        .then(item => {
            if (!item || item.ok === false) { alert('ไม่พบข้อมูล'); closeEditModal(); return; }

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
        .catch(err => {
            const noPerm = err && err.message === 'no-perm';
            const msg = noPerm ? 'ไม่มีสิทธิ์แก้ไขสินค้า (parts.manage)' : 'โหลดข้อมูลล้มเหลว';
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: msg, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
            } else { alert(msg); }
            closeEditModal();
        });
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
        let opts = type === 'used'
            ? ['GOOD','TEST','DEAD']
            : type === 'new'  ? ['STOCK','OOS']
            : type === 'machine' ? ['READY','PARTIAL','DISCOUNT']
            : ['READY','SOLD','PENDING'];
        // ข้อมูลเก่าบางตัวมี status นอกชุดตัวเลือกของ type นี้ (เช่นของ legacy) —
        // ใส่ค่าปัจจุบันเข้าไปด้วยเพื่อไม่ให้ dropdown เด้งไปเลือกตัวอื่นเงียบๆ ตอน save
        if (st && !opts.includes(st)) opts = [st, ...opts];
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

    // ── Stock tab: adjust block (เฉพาะ new) + โชว์ tab เฉพาะ type ที่มี lot ──
    const adjBlock  = document.getElementById('edit-adjust-block');
    const stockBtn  = document.getElementById('edit-tab-btn-stock');
    const hasStock  = (type === 'new' || type === 'used');
    if (stockBtn) stockBtn.style.display = hasStock ? '' : 'none';
    if (!hasStock) switchEditTab('details');

    if (adjBlock) {
        if (type === 'new') {
            const curQty = parseInt(item.total_qty) || 0;
            adjBlock.innerHTML = `
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
                <div id="adj-preview" style="font-size:11px;color:var(--text-muted);margin-top:4px;min-height:16px;"></div>`;
        } else {
            adjBlock.innerHTML = '';
        }
    }

    if (type === 'new') {
        html = `
            <div><label class="cmns-label">เลขพาร์ท (Part No.)</label><input type="text" name="part_number" class="cmns-input" value="${esc(item.part_number)}" placeholder="เช่น 661-123"></div>
            <div><label class="cmns-label">รุ่นรองรับ (Model)</label><input type="text" name="compatible_models" class="cmns-input" value="${esc(item.compatible_models)}" placeholder="เช่น A2337"></div>
            <div>
                <label class="cmns-label" style="color:#f59e0b;">เตือนของหมด (Min Qty)</label>
                <input type="number" name="min_qty" class="cmns-input" value="${item.min_qty || 1}" min="0">
            </div>
        `;
    } else if (type === 'used') {
        let machineOpts = '<option value="">-- ไม่ทราบที่มา / ไม่ผูกเครื่องซาก --</option>';
        (typeof machinesList !== 'undefined' ? machinesList : []).forEach(m => {
            machineOpts += `<option value="${m.id}" ${String(item.source_machine_id)===String(m.id)?'selected':''}>[${m.asset_tag || 'NO-TAG'}] ${esc(m.name)}</option>`;
        });
        html = `
            <div style="grid-column:span 2;">
                <label class="cmns-label" style="color:#ef4444;"><span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">link</span> แกะมาจากเครื่องซากตัวไหน?</label>
                <select name="source_machine_id" class="cmns-input" style="border-color:#ef4444;">${machineOpts}</select>
            </div>
            <div><label class="cmns-label">Serial Number</label><input type="text" name="serial_number" class="cmns-input" value="${esc(item.serial_number)}" placeholder="S/N ของชิ้นส่วน"></div>
            <div><label class="cmns-label">เลขพาร์ท (Part No.)</label><input type="text" name="part_number" class="cmns-input" value="${esc(item.part_number)}"></div>
            <div><label class="cmns-label">Min Qty</label><input type="number" name="min_qty" class="cmns-input" value="${item.min_qty || 1}" min="0"></div>
            <div style="grid-column:span 2;">
                <label class="cmns-label">หมายเหตุสภาพ (Condition Note)</label>
                <input type="text" name="condition_note" class="cmns-input" value="${esc(item.condition_note)}" placeholder="เช่น มีรอยขีดข่วนเล็กน้อย, ทดสอบจอแล้วปกติ...">
            </div>
        `;
    } else if (type === 'machine') {
        const mGradeOpts = ['A','B','C','D'].map(g =>
            `<option value="${g}" ${item.condition_grade===g?'selected':''}>${g}</option>`).join('');
        html = `
            <div><label class="cmns-label">รหัสเครื่อง (Asset Tag)</label><input type="text" name="asset_tag" class="cmns-input" value="${esc(item.asset_tag)}"></div>
            <div><label class="cmns-label">Serial Number</label><input type="text" name="serial_number" class="cmns-input" value="${esc(item.serial_number)}"></div>
            <div><label class="cmns-label">สี (Color)</label><input type="text" name="color" class="cmns-input" value="${esc(item.color)}" placeholder="เช่น Space Gray, Silver, Midnight"></div>
            <div>
                <label class="cmns-label" style="color:#8b5cf6;">เกรดสภาพ (Grade)</label>
                <select name="condition_grade" class="cmns-input" style="border-color:#8b5cf6;font-weight:700;">
                    <option value="">-- เลือกเกรด --</option>
                    ${mGradeOpts}
                </select>
            </div>
            <div style="grid-column:span 2;"><label class="cmns-label" style="color:#10b981;">สถานะการแยกอะไหล่</label>
                <select name="disassembly_status" class="cmns-input" style="border-color:#10b981;">
                    <option value="intact" ${item.disassembly_status=='intact'?'selected':''}>ยังไม่แกะ</option>
                    <option value="partially_stripped" ${item.disassembly_status=='partially_stripped'?'selected':''}>แกะไปบางส่วน</option>
                    <option value="stripped" ${item.disassembly_status=='stripped'?'selected':''}>แกะหมดแล้ว</option>
                </select>
            </div>
            <div><label class="cmns-label">CPU / Chip</label><input type="text" name="cpu_spec" class="cmns-input" value="${esc(item.cpu_spec)}" placeholder="เช่น Apple M1, Intel Core i7"></div>
            <div><label class="cmns-label">RAM</label><input type="text" name="ram_spec" class="cmns-input" value="${esc(item.ram_spec)}" placeholder="เช่น 16GB LPDDR5"></div>
            <div><label class="cmns-label">Storage</label><input type="text" name="storage_spec" class="cmns-input" value="${esc(item.storage_spec)}" placeholder="เช่น 512GB SSD"></div>
            <div><label class="cmns-label">GPU</label><input type="text" name="gpu_spec" class="cmns-input" value="${esc(item.gpu_spec)}" placeholder="เช่น 8-core GPU, Radeon Pro 5500M"></div>
            <div style="grid-column:span 2;">
                <label class="cmns-label">รายละเอียดเพิ่มเติม / ตำหนิ</label>
                <textarea name="condition_note" class="cmns-input" rows="2" style="resize:vertical;" placeholder="เช่น จอมีรอยขีดข่วน, แบตพอง, หลุดมาจากงานซ่อม...">${esc(item.condition_note)}</textarea>
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
    // reset restock inputs (ว่าง = backend ไม่เติม lot)
    const rs = document.getElementById('restock-section');
    if (rs) rs.querySelectorAll('input').forEach(i => { i.value = i.defaultValue || ''; });
    switchEditTab('details');
}
