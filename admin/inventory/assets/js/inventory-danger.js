// ── HARD DELETE (super_admin id=1 only) — ลบทั้งก้อน ถาวร ไม่เก็บประวัติ ──
let _hdId = null;
function confirmHardDelete(inventoryId, itemName) {
    _hdId = inventoryId;
    document.getElementById('hd-title').textContent = itemName;
    const input = document.getElementById('hd-input');
    const btn   = document.getElementById('hd-btn');
    input.value = '';
    btn.disabled = true;
    document.getElementById('hd-overlay').style.display = 'flex';
    setTimeout(() => input.focus(), 50);
}
function closeHardDelete() { document.getElementById('hd-overlay').style.display = 'none'; }
function _hdCheck() {
    document.getElementById('hd-btn').disabled = (document.getElementById('hd-input').value !== 'DELETE');
}
function doHardDelete() {
    if (!_hdId || document.getElementById('hd-input').value !== 'DELETE') return;
    const btn = document.getElementById('hd-btn');
    btn.disabled = true; btn.textContent = 'กำลังลบ...';
    const fd = new FormData();
    fd.append('inventory_id', _hdId);
    fetch('process_delete.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            closeHardDelete();
            if (res.ok) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon:'success', title: res.msg, toast:true, position:'top-end', showConfirmButton:false, timer:2500, timerProgressBar:true });
                }
                setTimeout(() => location.reload(), 400);
            } else {
                Swal.fire({ icon:'error', title:'ลบไม่สำเร็จ', text: res.msg||'', toast:true, position:'top-end', showConfirmButton:false, timer:3000 });
            }
            btn.textContent = 'ลบถาวร';
        })
        .catch(() => { btn.disabled = false; btn.textContent = 'ลบถาวร'; alert('เกิดข้อผิดพลาด'); });
}
