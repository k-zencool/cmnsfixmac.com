// 1. ระบบกางแถว
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
    
    contentDiv.innerHTML = '<div style="padding:40px; text-align:center; color:var(--text-muted);"><span class="material-symbols-rounded spin-icon" style="font-size: 24px;">sync</span></div>';

    fetch(`ajax.php?action=get_lots_inline&item_id=${id}`)
        .then(res => res.text())
        .then(data => { contentDiv.innerHTML = data; })
        .catch(err => { contentDiv.innerHTML = '<div style="padding:20px; text-align:center; color:#ef4444;">โหลดข้อมูลไม่สำเร็จ</div>'; });
}

const style = document.createElement('style');
style.innerHTML = `@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }`;
document.head.appendChild(style);

// 2. ระบบค้นหา Real-time (รอ 0.5 วิแล้ว Submit)
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

        let typingTimer;
        searchInput.addEventListener('input', function() {
            clearTimeout(typingTimer);
            typingTimer = setTimeout(() => {
                const form = this.closest('form');
                if (form) form.submit();
            }, 500); 
        });

        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(typingTimer);
                const form = this.closest('form');
                if (form) form.submit();
            }
        });
    }
});
