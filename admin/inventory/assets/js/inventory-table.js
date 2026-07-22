// 1. ระบบกางแถว (cache fragment ต่อแถว — กางซ้ำไม่ยิง AJAX ใหม่)
const _lotCache = {};

function toggleLotDetails(id) {
    const detailRow = document.getElementById(`lot-detail-${id}`);
    const contentDiv = document.getElementById(`lot-content-${id}`);
    const mainRow = document.getElementById(`row-${id}`);

    if (!detailRow || !contentDiv || !mainRow) return;

    if (detailRow.style.display === 'table-row') {
        detailRow.style.display = 'none';
        mainRow.classList.remove('active');
        return;
    }

    document.querySelectorAll('.lot-detail-row').forEach(row => row.style.display = 'none');
    document.querySelectorAll('.inventory-row').forEach(row => row.classList.remove('active'));

    detailRow.style.display = 'table-row';
    mainRow.classList.add('active');

    if (_lotCache[id]) {
        contentDiv.innerHTML = _lotCache[id];
        return;
    }

    contentDiv.innerHTML = '<div class="lot-skeleton"><div class="sk-line"></div><div class="sk-line"></div><div class="sk-line"></div></div>';

    fetch(`ajax.php?action=get_lots_inline&item_id=${id}`)
        .then(res => { if (!res.ok) throw new Error(res.status); return res.text(); })
        .then(data => { _lotCache[id] = data; contentDiv.innerHTML = data; })
        .catch(err => { contentDiv.innerHTML = '<div style="padding:20px; text-align:center; color:#ef4444;">โหลดข้อมูลไม่สำเร็จ</div>'; });
}

// เรียกหลัง action ที่แก้ lot (เบิก/เติม/ย้าย) เพื่อให้กางรอบหน้าโหลดสด
function invalidateLotCache(id) {
    if (id) { delete _lotCache[id]; } else { Object.keys(_lotCache).forEach(k => delete _lotCache[k]); }
}

const style = document.createElement('style');
style.innerHTML = `@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }`;
document.head.appendChild(style);

// 2. ระบบค้นหา — พิมพ์ให้จบก่อน แล้วกด Enter หรือปุ่มค้นหาเอง (ไม่ auto-submit ระหว่างพิมพ์
//    เพราะบน iPad การพิมพ์ปกติ/autocorrect ทำให้ input event ยิงถี่ กดค้นหาเองรัว ๆ)
document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.querySelector('.view-search-input');
    if (searchInput) {

        // เลื่อนเคอร์เซอร์ไปท้ายสุด
        const val = searchInput.value;
        if (val) {
            searchInput.focus();
            searchInput.value = '';
            searchInput.value = val;
        }

        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const form = this.closest('form');
                if (form) form.submit();
            }
        });
    }
});
