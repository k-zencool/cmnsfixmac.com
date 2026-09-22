/* =========================================================
   Inventory — QR label picker (labels.php)
   Path: admin/inventory/assets/js/inventory-labels.js

   Picks live in sessionStorage, so a batch can be collected across
   pages, searches and categories before printing once. The bar posts
   the ids to labels.php, which logs the run and opens the print sheet.
   ========================================================= */
(function () {
    'use strict';

    var KEY = 'cmns.plbPick';
    var bar = document.getElementById('lblBar');
    if (!bar) return;
    var idsIn = document.getElementById('lblIds');
    var count = document.getElementById('lblCount');
    var boxes = [].slice.call(document.querySelectorAll('[data-pick]'));

    function load() {
        try { return JSON.parse(sessionStorage.getItem(KEY) || '[]').map(Number).filter(function (n) { return n > 0; }); }
        catch (e) { return []; }
    }
    function save(ids) {
        try { sessionStorage.setItem(KEY, JSON.stringify(ids)); } catch (e) {}
        render();
    }
    function render() {
        var ids = load();
        boxes.forEach(function (cb) { cb.checked = ids.indexOf(Number(cb.value)) !== -1; });
        count.textContent = ids.length;
        idsIn.value = ids.join(',');
        bar.hidden = ids.length === 0;
        document.body.classList.toggle('lbl-picking', ids.length > 0);
    }
    function add(list) {
        var ids = load();
        list.forEach(function (id) { id = Number(id); if (ids.indexOf(id) === -1) ids.push(id); });
        save(ids);
    }

    boxes.forEach(function (cb) {
        cb.addEventListener('change', function () {
            var id = Number(cb.value), ids = load().filter(function (n) { return n !== id; });
            if (cb.checked) ids.push(id);
            save(ids);
        });
    });

    var pageBtn = document.querySelector('[data-pick-page]');
    if (pageBtn) pageBtn.addEventListener('click', function () {
        add(boxes.map(function (cb) { return cb.value; }));
    });
    var allBtn = document.querySelector('[data-pick-all]');
    if (allBtn) allBtn.addEventListener('click', function () {
        add(window.LBL_ALL_IDS || []);
    });
    bar.querySelector('[data-pick-clear]').addEventListener('click', function () { save([]); });

    /* ── Inline rename: ✎ turns the name into an input; Enter saves,
       Escape cancels. The row is a <label>, so every click in here must
       not reach the checkbox. ── */
    document.querySelectorAll('[data-rename]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var row = btn.closest('.lbl-row');
            if (row.querySelector('.lbl-rename')) return;
            var nameEl = row.querySelector('[data-name]');
            var old = nameEl.textContent;

            var box = document.createElement('span');
            box.className = 'lbl-rename';
            box.innerHTML = '<input type="text" maxlength="200" enterkeyhint="done">'
                          + '<button type="button" class="lbl-rename-ok" aria-label="บันทึก"><span class="material-symbols-rounded">check</span></button>'
                          + '<button type="button" class="lbl-rename-x" aria-label="ยกเลิก"><span class="material-symbols-rounded">close</span></button>';
            var input = box.querySelector('input');
            input.value = old;
            row.querySelector('.lbl-row-name').hidden = true;
            row.querySelector('.lbl-row-main').prepend(box);
            box.addEventListener('click', function (ev) { ev.preventDefault(); ev.stopPropagation(); });
            input.focus();
            input.select();

            function done() {
                box.remove();
                row.querySelector('.lbl-row-name').hidden = false;
            }
            function commit() {
                var v = input.value.trim().replace(/\s+/g, ' ');
                if (!v || v === old) { done(); return; }
                input.disabled = true;
                var body = new FormData();
                body.append('action', 'rename');
                body.append('id', row.dataset.id);
                body.append('name', v);
                fetch('labels.php', { method: 'POST', body: body, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (!res.ok) { input.disabled = false; alertMsg(res.msg); return; }
                        nameEl.textContent = res.name;
                        row.querySelector('[data-dup]').hidden = !res.dup;
                        done();
                    })
                    .catch(function () { input.disabled = false; alertMsg('บันทึกไม่สำเร็จ ลองใหม่'); });
            }
            box.querySelector('.lbl-rename-ok').addEventListener('click', commit);
            box.querySelector('.lbl-rename-x').addEventListener('click', done);
            input.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter') { ev.preventDefault(); commit(); }
                else if (ev.key === 'Escape') { done(); }
            });
        });
    });

    function alertMsg(msg) {
        if (window.Swal) Swal.fire({ icon: 'error', title: msg, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
        else console.warn(msg);
    }

    render();
})();
