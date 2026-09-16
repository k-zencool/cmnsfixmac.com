/* =========================================================
   Repair job detail sheet (read-only)
   Path: admin/tracking/assets/js/job-view.js
   Used by: admin/tracking/index.php, admin/scan/index.php
   Markup: admin/tracking/partials/job_view_sheet.php
   Data:   jv_payload() in includes/job_view_lib.php

   JobView.open(job) fills and shows the sheet; JobView.close() hides it.
   JobView.onClose(fn) runs fn however it was closed — ✕, the footer
   button, the backdrop, Escape, or dragged away on a phone.
   ========================================================= */
(function () {
    'use strict';

    var m = document.getElementById('viewModal');
    if (!m) return;
    var closers = [];
    var isOpen  = false;

    function $(id) { return document.getElementById(id); }
    function text(id, v) { $(id).textContent = v; }

    function tags(id, items) {
        var box = $(id);
        box.innerHTML = '';
        (items || []).forEach(function (t) {
            var s = document.createElement('span');
            s.textContent = t;
            box.appendChild(s);
        });
        box.style.display = (items && items.length) ? '' : 'none';
    }
    function para(id, v) {
        var p = $(id);
        p.textContent = v || '';
        p.style.display = v ? '' : 'none';
    }

    function open(j) {
        text('vm-ticket', j.ticket);
        var st = $('vm-status');
        st.textContent = j.stLabel;
        st.className = 'status-badge ' + j.stClass;

        text('vm-created', j.created);
        text('vm-appt', j.appt || '—');
        var tm = $('vm-time');
        tm.textContent = (j.appt && j.timeText !== '—') ? '(' + j.timeText + ')' : '';
        tm.className   = j.timeClass || '';
        text('vm-pickup', j.pickup || '—');
        text('vm-cost', '฿' + j.cost);

        text('vm-name', j.name);
        var ph = $('vm-phone');
        ph.textContent = j.phone;
        ph.href = 'tel:' + (j.phone || '').replace(/[^0-9+]/g, '');

        text('vm-device', j.device || '—');
        text('vm-model', j.model || '');
        text('vm-sn', j.sn || '—');
        text('vm-pass', j.pass || '—');

        tags('vm-symptoms', j.symptoms);
        para('vm-detail', j.detail);
        $('vm-sec-problem').style.display = (j.symptoms.length || j.detail) ? '' : 'none';

        tags('vm-accs', j.accs);
        tags('vm-states', j.states);
        para('vm-note', j.note);
        $('vm-sec-recv').style.display = (j.accs.length || j.states.length || j.note) ? '' : 'none';

        var edit = $('vm-edit');
        if (edit) edit.href = '/admin/tracking/edit.php?id=' + j.id;

        m.style.display = 'flex';
        requestAnimationFrame(function () { m.classList.add('show'); });
        isOpen = true;
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
        if (e.target === m || e.target.closest('[data-jv-close]')) close();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isOpen) close();
    });
    // Dragged away on a phone — admin-mobile.js already threw it off-screen
    m.addEventListener('sheetdismiss', function () {
        m.classList.remove('show');
        m.style.display = 'none';
        hidden();
    });

    window.JobView = {
        open: open,
        close: close,
        isOpen: function () { return isOpen; },
        onClose: function (fn) { closers.push(fn); }
    };
})();
