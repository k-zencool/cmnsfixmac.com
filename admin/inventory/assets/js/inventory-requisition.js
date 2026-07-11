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
