/* =========================================================
   CMNS Admin — storage slot sheet (scanned slot label)
   Path: admin/templates/assets/js/bin-view.js
   Used by: admin/scan/index.php — markup is #binModal there, styling
   reuses the job sheet (job-view.css) plus .bv-* rules in scan.css.
   Data + moves: admin/inventory/bin_api.php

   BinView.open(data) shows a slot and what is in it; with parts.manage the
   sheet can search and put items in, take them out, or hand off to the
   scanner's "scan to add" mode (BinView.onScanAdd). BinView.onClose(fn)
   runs however it was closed — ✕, backdrop, Escape, dragged away.
   ========================================================= */
(function () {
    'use strict';

    var m = document.getElementById('binModal');
    if (!m) return;

    var API = '/admin/inventory/bin_api.php';
    var closers = [], scanAdders = [], isOpen = false, bin = null;
    function $(id) { return document.getElementById(id); }
    function el(tag, cls, txt) {
        var e = document.createElement(tag);
        if (cls) e.className = cls;
        if (txt != null) e.textContent = txt;
        return e;
    }
    function toast(icon, title) {
        if (window.Swal) Swal.fire({ icon: icon, title: title, toast: true, position: 'top', showConfirmButton: false, timer: icon === 'error' ? 4000 : 1800 });
    }
    function post(action, itemId) {
        var body = new FormData();
        body.append('action', action);
        body.append('id', bin.id);
        body.append('item', itemId);
        return fetch(API, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (r) { return r.json(); });
    }

    function render(data) {
        bin = data.bin;
        $('bv-code').textContent = bin.code;
        var kind = $('bv-kind');
        kind.className = 'status-badge ' + (bin.ready ? 'st-blue' : 'st-amber');
        kind.textContent = bin.ready ? bin.type_label + ' · ' + bin.category : 'ยังไม่ตั้งหมวด';
        $('bv-where').textContent = bin.shelf + ' · ช่อง ' + bin.slot + (bin.note ? ' · ' + bin.note : '');
        $('bv-count').textContent = 'ในช่องนี้ · ' + data.items.length + ' ชิ้น';

        var list = $('bv-items');
        list.innerHTML = '';
        if (!data.items.length) list.appendChild(el('p', 'bv-empty', 'ช่องว่าง'));
        data.items.forEach(function (it) {
            var row = el('div', 'bv-row');
            // the row opens the item's own sheet (details, เบิก, แก้ไข) over this one
            var main = el('button', 'bv-row-main is-link');
            main.type = 'button';
            main.addEventListener('click', function () {
                if (!window.PartView) return;
                PartView.openId(it.id).then(function (d) {
                    if (!d || !d.ok) toast('error', 'ไม่พบรายการนี้ในคลัง');
                }).catch(function () { toast('error', 'โหลดข้อมูลไม่สำเร็จ'); });
            });
            main.appendChild(el('div', 'bv-row-name', it.name));
            var sub = el('div', 'bv-row-sub');
            sub.appendChild(el('code', null, it.tag || '—'));
            sub.appendChild(document.createTextNode(' · ' + it.status));
            if (it.stripped) sub.appendChild(el('span', 'bv-chip', 'ถูกแกะแล้ว'));
            if (it.mismatch) sub.appendChild(el('span', 'bv-chip is-err', 'ไม่ตรงหมวดช่อง'));
            main.appendChild(sub);
            row.appendChild(main);
            row.appendChild(el('span', 'material-symbols-rounded bv-chev', 'chevron_right'));
            if (bin.can_manage) {
                var out = el('button', 'bv-link is-danger', 'เอาออก');
                out.type = 'button';
                out.addEventListener('click', function () {
                    out.disabled = true;
                    post('remove', it.id).then(function (res) {
                        if (res.ok) { render(res); toast('success', 'เอาออกแล้ว'); } else { out.disabled = false; }
                    }).catch(function () { out.disabled = false; toast('error', 'เอาออกไม่สำเร็จ ลองใหม่'); });
                });
                row.appendChild(out);
            }
            list.appendChild(row);
        });

        var canAdd = bin.can_manage && bin.ready;
        $('bv-add').hidden = !canAdd;
        $('bv-scanadd').hidden = !canAdd;
        $('bv-open').hidden = !bin.can_manage;
        $('bv-open').href = bin.page_url;
        if (!canAdd) $('bv-find').hidden = true;
    }

    /* ── search to add ── */
    var q = $('bv-q'), results = $('bv-results'), timer = 0, seq = 0;
    function search() {
        var my = ++seq;
        fetch(API + '?action=search&id=' + bin.id + '&q=' + encodeURIComponent(q.value.trim()), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (my !== seq || !res.ok) return;
                results.innerHTML = '';
                if (!res.results.length) results.appendChild(el('p', 'bv-empty', 'ไม่พบ' + bin.type_label + ' หมวด ' + bin.category));
                res.results.forEach(function (r) {
                    var row = el('div', 'bv-row');
                    var main = el('div', 'bv-row-main');
                    main.appendChild(el('div', 'bv-row-name', r.name));
                    var sub = el('div', 'bv-row-sub');
                    sub.appendChild(el('code', null, r.tag || '—'));
                    sub.appendChild(document.createTextNode(r.bin_code ? ' · อยู่ ' + r.bin_code
                        : ' · ยังไม่เข้าช่อง' + (r.location ? ' (เดิม: ' + r.location + ')' : '')));
                    main.appendChild(sub);
                    row.appendChild(main);
                    var put = el('button', 'bv-link', r.bin_code ? 'ย้ายมา' : 'ใส่');
                    put.type = 'button';
                    put.addEventListener('click', function () {
                        put.disabled = true;
                        post('add', r.id).then(function (res) {
                            if (!res.ok) { put.disabled = false; toast('error', res.msg); return; }
                            render(res);
                            row.remove();
                            toast('success', 'ใส่เข้า ' + bin.code + ' แล้ว');
                        }).catch(function () { put.disabled = false; toast('error', 'ใส่ไม่สำเร็จ ลองใหม่'); });
                    });
                    row.appendChild(put);
                    results.appendChild(row);
                });
            });
    }
    q.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(search, 250); });
    $('bv-add').addEventListener('click', function () {
        var f = $('bv-find');
        f.hidden = !f.hidden;
        if (!f.hidden) { q.value = ''; search(); q.focus(); }
    });
    $('bv-scanadd').addEventListener('click', function () {
        var b = bin;
        close();
        scanAdders.forEach(function (fn) { fn(b); });
    });

    function open(data) {
        $('bv-find').hidden = true;
        results.innerHTML = '';
        render(data);
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
        if (e.target === m || e.target.closest('[data-bv-close]')) close();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isOpen && !e.defaultPrevented) close();
    });
    m.addEventListener('sheetdismiss', function () {
        m.classList.remove('show');
        m.style.display = 'none';
        hidden();
    });

    window.BinView = {
        API: API,
        open: open,
        close: close,
        isOpen: function () { return isOpen; },
        onClose: function (fn) { closers.push(fn); },
        onScanAdd: function (fn) { scanAdders.push(fn); }
    };
})();
