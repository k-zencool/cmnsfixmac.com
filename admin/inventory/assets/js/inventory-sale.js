// ─── Transfer to SALE ─────────────────────────────────
let _tsInventoryId  = null;
let _tsSourceType   = null;
let _tsTotalQty     = 0;

const _tsTypeIcon  = { new:'fiber_new', used:'build', machine:'computer' };
const _tsTypeColor = { new:'#10b981',  used:'#f59e0b', machine:'#8b5cf6' };
const _tsTypeLabel = { new:'NEW',      used:'USED',     machine:'MACHINE' };

function openToSaleModal(inventoryId, sourceType) {
    _tsInventoryId = inventoryId;
    _tsSourceType  = sourceType;
    _tsTotalQty    = 0;

    // Reset form
    document.getElementById('ts-source-type').value  = sourceType;
    document.getElementById('ts-inventory-id').value = inventoryId;
    document.getElementById('ts-lot-id').value        = '';
    document.getElementById('ts-error').style.display = 'none';
    document.getElementById('form-to-sale').reset();
    document.getElementById('ts-source-type').value  = sourceType;
    document.getElementById('ts-inventory-id').value = inventoryId;

    // Source badge
    const icon  = _tsTypeIcon[sourceType]  || 'inventory_2';
    const color = _tsTypeColor[sourceType] || '#888';
    const el = document.getElementById('ts-source-icon');
    el.textContent     = icon;
    el.style.color     = color;
    document.getElementById('ts-source-name').textContent = 'กำลังโหลด...';
    document.getElementById('ts-source-meta').textContent = '';

    // Lot section: show only for NEW
    const qtyInp = document.getElementById('ts-qty');
    if (qtyInp) { qtyInp.value = 1; qtyInp.max = 1; }
    const maxLabel = document.getElementById('ts-qty-max-label');
    if (maxLabel) maxLabel.textContent = '';

    document.getElementById('ts-lot-section').style.display = sourceType === 'new' ? 'block' : 'none';
    document.getElementById('ts-lots-wrap').innerHTML =
        '<div style="padding:16px; text-align:center; color:var(--text-muted); font-size:13px;"><span class="material-symbols-rounded" style="animation:spin 1s linear infinite; font-size:18px; display:block; margin-bottom:4px;">sync</span>กำลังโหลด lots...</div>';

    document.getElementById('modal-to-sale').classList.add('show');
    document.body.style.overflow = 'hidden';

    // Fetch item data
    const fetches = [
        fetch(`process_to_sale.php?action=get_item&id=${inventoryId}`).then(r => r.json()),
    ];
    if (sourceType === 'new') {
        fetches.push(fetch(`process_requisition.php?action=get_lots&item_id=${inventoryId}`).then(r => r.json()));
    }

    Promise.all(fetches).then(([item, lots]) => {
        if (!item) { document.getElementById('ts-source-name').textContent = 'โหลดไม่สำเร็จ'; return; }
        _tsTotalQty = parseInt(item.total_qty) || 0;

        // Fill source info
        document.getElementById('ts-source-name').textContent = item.name || '—';
        const metaParts = [`${_tsTypeLabel[sourceType]}`];
        if (item.sku)          metaParts.push(`SKU: ${item.sku}`);
        if (item.total_qty > 0) metaParts.push(`คงเหลือ: ${item.total_qty}`);
        document.getElementById('ts-source-meta').textContent = metaParts.join(' · ');

        // Pre-fill SALE fields
        document.getElementById('ts-name').value          = item.name || '';
        document.getElementById('ts-sell-price').value    = item.sell_price || 0;
        document.getElementById('ts-serial').value        = item.serial_number || '';
        document.getElementById('ts-asset-tag').value     = item.asset_tag || '';
        document.getElementById('ts-color').value         = item.color || '';
        document.getElementById('ts-cpu').value           = item.cpu_spec || '';
        document.getElementById('ts-ram').value           = item.ram_spec || '';
        document.getElementById('ts-storage').value       = item.storage_spec || '';
        document.getElementById('ts-condition-note').value = item.condition_note || '';
        if (item.condition_grade) document.getElementById('ts-grade').value = item.condition_grade;
        if (item.apple_warranty_date) document.getElementById('ts-apple-warranty').value = item.apple_warranty_date;
        if (item.store_warranty_days) document.getElementById('ts-store-warranty').value = item.store_warranty_days;
        if (item.battery_health)  document.getElementById('ts-battery-health').value  = item.battery_health;
        if (item.battery_cycles)  document.getElementById('ts-battery-cycles').value  = item.battery_cycles;

        // For USED → default READY (สินค้ามีอยู่แล้ว)
        if (sourceType === 'used' || sourceType === 'machine') {
            document.getElementById('ts-status').value = 'READY';
        }

        // Render lots for NEW
        if (sourceType === 'new' && lots) {
            renderTsLots(lots);
        }
    }).catch(() => {
        document.getElementById('ts-source-name').textContent = 'โหลดไม่สำเร็จ';
    });
}

let _tsLots = [];

function renderTsLots(lots) {
    _tsLots = lots || [];
    const wrap = document.getElementById('ts-lots-wrap');
    if (!_tsLots.length) {
        wrap.innerHTML = '<div style="font-size:13px; color:#ef4444;">ไม่มีสต็อกในระบบ</div>';
        return;
    }

    let html = '';
    _tsLots.forEach((lot, i) => {
        const wEnd   = lot.warranty_end ? new Date(lot.warranty_end) : null;
        const dLeft  = wEnd ? Math.ceil((wEnd - new Date()) / 86400000) : null;
        const wColor = !wEnd ? 'var(--text-muted)' : dLeft < 0 ? '#ef4444' : dLeft < 30 ? '#f59e0b' : '#10b981';
        const wText  = !wEnd ? '—' : wEnd.toLocaleDateString('th-TH', {day:'2-digit', month:'2-digit', year:'2-digit'});

        html += `
            <label class="lot-option" style="margin-bottom:6px;">
                <input type="radio" name="ts_lot_pick" value="${lot.id}" data-max="${lot.qty_remaining}" ${i===0?'checked':''}
                       onchange="tsOnLotChange(this)">
                <div class="lot-opt-body">
                    <div>
                        <div style="font-weight:700; font-size:13px; font-family:monospace;">${_escH(lot.lot_number)}</div>
                        <div style="font-size:11px; color:var(--text-muted);">${lot.supplier_name ? _escH(lot.supplier_name) : 'ไม่ระบุ Supplier'}</div>
                    </div>
                    <div style="display:flex; flex-direction:column; align-items:flex-end; gap:2px;">
                        <span style="font-size:14px; font-weight:800;">${lot.qty_remaining} <span style="font-size:10px; font-weight:400; color:var(--text-muted);">/ ${lot.qty_received}</span></span>
                        <span style="font-size:10px; font-weight:700; color:${wColor};">Warranty: ${wText}</span>
                        ${lot.cost_price > 0 ? `<span style="font-size:11px; color:var(--text-muted);">ทุน ฿${Number(lot.cost_price).toLocaleString()}</span>` : ''}
                    </div>
                </div>
            </label>`;
    });

    wrap.innerHTML = html;
    // Init with first lot
    document.getElementById('ts-lot-id').value = _tsLots[0].id;
    tsSetQtyMax(_tsLots[0].qty_remaining);
}

function tsOnLotChange(radio) {
    document.getElementById('ts-lot-id').value = radio.value;
    tsSetQtyMax(parseInt(radio.dataset.max) || 1);
}

function tsSetQtyMax(max) {
    const inp = document.getElementById('ts-qty');
    if (!inp) return;
    inp.max = max;
    if (parseInt(inp.value) > max) inp.value = max;
    const label = document.getElementById('ts-qty-max-label');
    if (label) label.textContent = `max ${max}`;
}

function tsAdjQty(delta) {
    const inp = document.getElementById('ts-qty');
    if (!inp) return;
    const max = parseInt(inp.max) || 1;
    inp.value = Math.max(1, Math.min(max, (parseInt(inp.value) || 1) + delta));
}

function closeToSaleModal() {
    document.getElementById('modal-to-sale').classList.remove('show');
    document.body.style.overflow = '';
}

function submitToSale() {
    const btn = document.getElementById('ts-submit-btn');
    const err = document.getElementById('ts-error');
    err.style.display = 'none';

    const name = document.getElementById('ts-name').value.trim();
    if (!name) { err.textContent = 'กรุณากรอกชื่อสินค้า'; err.style.display = 'block'; return; }

    if (_tsSourceType === 'new' && !document.getElementById('ts-lot-id').value) {
        err.textContent = 'กรุณาเลือก Lot'; err.style.display = 'block'; return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-rounded" style="animation:spin 1s linear infinite;">sync</span> กำลังย้าย...';

    const fd = new FormData(document.getElementById('form-to-sale'));
    if (_tsSourceType === 'new') {
        fd.append('lot_id', document.getElementById('ts-lot-id').value);
        fd.append('qty', document.getElementById('ts-qty').value || 1);
    }

    fetch('process_to_sale.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                closeToSaleModal();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon:'success', title: res.msg, toast:true, position:'top-end', showConfirmButton:false, timer:3000, timerProgressBar:true });
                }
                setTimeout(() => location.reload(), 500);
            } else {
                err.textContent = res.msg;
                err.style.display = 'block';
                btn.disabled = false;
                btn.innerHTML = '<span class="material-symbols-rounded">sell</span> ย้ายไป SALE';
            }
        })
        .catch(() => {
            err.textContent = 'เกิดข้อผิดพลาด กรุณาลองใหม่';
            err.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-rounded">sell</span> ย้ายไป SALE';
        });
}

document.getElementById('modal-to-sale').addEventListener('click', function(e) {
    if (e.target === this) closeToSaleModal();
});

// ─── SALE Status: PENDING → READY ─────────────────────
function updateSaleStatus(inventoryId, action) {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('inventory_id', inventoryId);

    fetch('process_sale_status.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon:'success', title: res.msg, toast:true, position:'top-end', showConfirmButton:false, timer:2500, timerProgressBar:true });
                }
                setTimeout(() => location.reload(), 400);
            } else {
                alert(res.msg);
            }
        })
        .catch(() => alert('เกิดข้อผิดพลาด'));
}

// ─── Mark Sold Modal ───────────────────────────────────
function openMarkSoldModal(inventoryId, itemName, listPrice) {
    document.getElementById('ms-inventory-id').value = inventoryId;
    document.getElementById('ms-item-name').textContent = itemName;
    document.getElementById('ms-sold-price').value = listPrice;
    document.getElementById('ms-error').style.display = 'none';
    const btn = document.getElementById('ms-submit-btn');
    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-rounded">payments</span> ยืนยันขาย';
    document.getElementById('modal-mark-sold').classList.add('show');
    document.body.style.overflow = 'hidden';
    setTimeout(() => document.getElementById('ms-sold-price').select(), 100);
}

function closeMarkSoldModal() {
    document.getElementById('modal-mark-sold').classList.remove('show');
    document.body.style.overflow = '';
}

function submitMarkSold() {
    const btn = document.getElementById('ms-submit-btn');
    const err = document.getElementById('ms-error');
    err.style.display = 'none';

    const soldPrice = document.getElementById('ms-sold-price').value;
    if (soldPrice === '' || isNaN(parseFloat(soldPrice))) {
        err.textContent = 'กรุณากรอกราคาที่ขายจริง'; err.style.display = 'block'; return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-rounded" style="animation:spin 1s linear infinite;">sync</span> กำลังบันทึก...';

    const fd = new FormData();
    fd.append('action',       'mark_sold');
    fd.append('inventory_id', document.getElementById('ms-inventory-id').value);
    fd.append('sold_price',   soldPrice);

    fetch('process_sale_status.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                closeMarkSoldModal();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon:'success', title: res.msg, toast:true, position:'top-end', showConfirmButton:false, timer:3000, timerProgressBar:true });
                }
                setTimeout(() => location.reload(), 500);
            } else {
                err.textContent = res.msg;
                err.style.display = 'block';
                btn.disabled = false;
                btn.innerHTML = '<span class="material-symbols-rounded">payments</span> ยืนยันขาย';
            }
        })
        .catch(() => {
            err.textContent = 'เกิดข้อผิดพลาด';
            err.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-rounded">payments</span> ยืนยันขาย';
        });
}

document.getElementById('modal-mark-sold').addEventListener('click', function(e) {
    if (e.target === this) closeMarkSoldModal();
});

// ─── Revert SALE → ที่เดิม ─────────────────────────────
function confirmRevertSale(inventoryId, itemName) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'warning',
            title: 'คืนของที่เดิม?',
            html: `<b>${_escH(itemName)}</b><br><span style="font-size:13px;color:#888;">จะถูกย้ายกลับไปยังประเภทเดิม (NEW/USED/MACHINE)</span>`,
            showCancelButton: true,
            confirmButtonText: 'คืนเลย',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#f59e0b',
        }).then(result => { if (result.isConfirmed) _doRevertSale(inventoryId); });
    } else {
        if (confirm(`คืน "${itemName}" กลับที่เดิม?`)) _doRevertSale(inventoryId);
    }
}

function _doRevertSale(inventoryId) {
    const fd = new FormData();
    fd.append('inventory_id', inventoryId);

    fetch('process_revert_sale.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon:'success', title: res.msg, toast:true, position:'top-end', showConfirmButton:false, timer:3000, timerProgressBar:true });
                }
                setTimeout(() => location.reload(), 500);
            } else {
                alert(res.msg);
            }
        })
        .catch(() => alert('เกิดข้อผิดพลาด'));
}
