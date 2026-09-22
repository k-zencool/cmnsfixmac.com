/* =========================================================
   CMNS Admin — part sheet (scanned part label)
   Path: admin/templates/assets/js/part-view.js
   Used by: admin/scan/index.php — markup is #partModal there, styling
   reuses the job sheet (admin/tracking/assets/css/job-view.css) plus a
   few .pv-* rules in scan.css.

   PartView.open(part) fills and shows the sheet from admin/scan/part.php;
   PartView.close() hides it. PartView.onClose(fn) runs fn however it was
   closed — ✕, the backdrop, Escape, or dragged away on a phone.
   ========================================================= */
(function () {
    'use strict';

    var m = document.getElementById('partModal');
    if (!m) return;

    var closers = [], isOpen = false, current = null;
    function $(id) { return document.getElementById(id); }
    function text(id, v) { $(id).textContent = v; }
    function baht(n) { return '฿' + Number(n || 0).toLocaleString('th-TH'); }
    function dmy(s) {
        if (!s) return '—';
        var d = new Date(s);
        return isNaN(d) ? '—' : d.toLocaleDateString('th-TH', { day: '2-digit', month: '2-digit', year: '2-digit' });
    }

    function open(p) {
        current = p;
        text('pv-sku', p.sku || ('#' + p.id));
        text('pv-name', p.name || '—');
        text('pv-cat', p.category || '');

        var st = $('pv-stock');
        var low = p.qty > 0 && p.qty <= (p.min_qty || 0);
        st.className = 'status-badge ' + (p.qty <= 0 ? 'st-red' : low ? 'st-amber' : 'st-green');
        st.textContent = p.qty <= 0 ? 'หมด' : low ? 'ใกล้หมด' : 'มีของ';

        var img = $('pv-img');
        img.innerHTML = '';
        if (p.image) {
            var el = document.createElement('img');
            el.src = p.image; el.alt = '';
            el.onerror = function () { img.innerHTML = '<span class="material-symbols-rounded">image</span>'; };
            img.appendChild(el);
        } else {
            img.innerHTML = '<span class="material-symbols-rounded">image</span>';
        }

        text('pv-qty', p.qty + ' ชิ้น');
        text('pv-price', p.sell_price > 0 ? baht(p.sell_price) : '—');
        text('pv-loc', p.location || '—');
        text('pv-pn', p.part_number || '—');

        var tags = $('pv-compat');
        tags.innerHTML = '';
        (p.compatible || []).forEach(function (t) {
            var s = document.createElement('span'); s.textContent = t; tags.appendChild(s);
        });
        $('pv-sec-compat').hidden = !(p.compatible || []).length;

        var lots = $('pv-lots');
        lots.innerHTML = '';
        (p.lots || []).forEach(function (l) {
            var row = document.createElement('div');
            row.className = 'pv-lot';
            var a = document.createElement('code'); a.textContent = l.lot_number || '—';
            var b = document.createElement('span'); b.textContent = 'ประกัน ' + dmy(l.warranty_end);
            var c = document.createElement('b');    c.textContent = l.qty_remaining + ' ชิ้น';
            row.appendChild(a); row.appendChild(b); row.appendChild(c);
            lots.appendChild(row);
        });
        $('pv-sec-lots').hidden = !(p.lots || []).length;

        $('pv-open').href = p.view_url;
        var take = $('pv-take');
        if (take) take.hidden = !p.can_consume;

        m.style.display = 'flex';
        isOpen = true;
        requestAnimationFrame(function () { m.classList.add('show'); });
    }

    function hidden() {
        if (!isOpen) return;
        isOpen = false;
        closers.forEach(function (fn) { fn(); });
    }
    function close() {
        m.classList.remove('show');
        setTimeout(function () { m.style.display = 'none'; }, 150);
        hidden();
    }

    m.addEventListener('click', function (e) {
        if (e.target === m || e.target.closest('[data-pv-close]')) close();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isOpen && !document.querySelector('.cmns-modal.show')) close();
    });
    m.addEventListener('sheetdismiss', function () {
        m.classList.remove('show');
        m.style.display = 'none';
        hidden();
    });

    /* "เบิกเข้างาน" — the inventory requisition modal, loaded on this page */
    var take = $('pv-take');
    if (take) take.addEventListener('click', function () {
        if (current && typeof window.openRequisitionModal === 'function') {
            window.openRequisitionModal(current.id, 'new');
        }
    });

    window.PartView = {
        open: open,
        close: close,
        isOpen: function () { return isOpen; },
        onClose: function (fn) { closers.push(fn); }
    };
})();
