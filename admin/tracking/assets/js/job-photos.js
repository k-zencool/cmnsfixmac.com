/* =========================================================
   job-photos.js — รูปเครื่องลูกค้า (Google Drive)

   Two pages share this file:
   - [data-job-photos="<job id>"]  the photo card on tracking/edit.php
   - [data-photo-browser]          every job's photos, settings/photos.php

   Shared parts: a Photos-style viewer (pinch / double-tap zoom, filmstrip,
   note, move, download, delete), select-many mode (move / download / delete),
   bottom sheets and toasts. All writes go through photo_api.php.

   Uploads (job card only): re-encoded in the browser — longest side 2048px,
   JPEG, orientation baked in, EXIF/GPS dropped — then sent one at a time.
   ========================================================= */
(function () {
    const API    = '/admin/tracking/photo_api.php';
    const STAGES = { intake: 'รับเครื่อง', repair: 'ระหว่างซ่อม', return: 'ส่งคืน' };
    const MAX_SIDE = 2048;

    const esc = t => String(t ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const icon = n => `<span class="material-symbols-rounded">${n}</span>`;
    const fmtAt = s => {
        const d = new Date(String(s).replace(' ', 'T'));
        return isNaN(d) ? s : d.toLocaleString('th-TH', { day: 'numeric', month: 'short', year: '2-digit', hour: '2-digit', minute: '2-digit' });
    };

    async function post(data) {
        const fd = new FormData();
        for (const [k, v] of Object.entries(data)) {
            if (Array.isArray(v)) v.forEach(x => fd.append(k + '[]', x)); else fd.append(k, v);
        }
        const r = await fetch(API, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' });
        if (r.status === 401) throw new Error('หลุดล็อกอิน — รีเฟรชหน้า');
        let j = null; try { j = await r.json(); } catch (e) {}
        if (!j) throw new Error(`เซิร์ฟเวอร์ตอบผิดพลาด (HTTP ${r.status})`);
        return j;
    }

    /* ── toast ── */
    let toastEl = null, toastT = 0;
    function toast(msg, bad, sticky) {
        if (!toastEl) { toastEl = document.createElement('div'); toastEl.className = 'jp-toast'; document.body.appendChild(toastEl); }
        toastEl.textContent = msg;
        toastEl.classList.toggle('is-bad', !!bad);
        toastEl.classList.add('is-on');
        clearTimeout(toastT);
        if (!sticky) toastT = setTimeout(() => toastEl.classList.remove('is-on'), bad ? 4500 : 2200);
    }

    /* ── bottom sheet (move / note / confirm) — no confirm() dialogs in the PWA ── */
    function sheet(html, onReady, onClose) {
        const wrap = document.createElement('div');
        wrap.className = 'jp-sheet-wrap';
        wrap.innerHTML = `<div class="jp-sheet" role="dialog">${html}</div>`;
        document.body.appendChild(wrap);
        requestAnimationFrame(() => wrap.classList.add('is-on'));
        let closed = false;
        const close = () => {
            if (closed) return;
            closed = true;
            wrap.classList.remove('is-on');
            setTimeout(() => { wrap.remove(); onClose?.(); }, 200);
        };
        wrap.addEventListener('click', e => { if (e.target === wrap || e.target.closest('[data-close]')) close(); });
        onReady?.(wrap.querySelector('.jp-sheet'), close);
        return close;
    }

    /** pick a stage; resolves the key or null */
    function askStage(title, current) {
        return new Promise(res => {
            let picked = null;
            sheet(`<h3>${esc(title)}</h3>
                <div class="jp-stage-pick">${Object.entries(STAGES).map(([k, l], i) => `
                    <button type="button" data-st="${k}" ${k === current ? 'disabled' : ''}>
                        <b>${i + 1}</b><span>${l}</span>${k === current ? '<small>อยู่ที่นี่</small>' : ''}
                    </button>`).join('')}</div>
                <button type="button" class="jp-sheet-cancel" data-close>ยกเลิก</button>`,
            (el, close) => {
                el.querySelectorAll('[data-st]').forEach(b => b.onclick = () => { picked = b.dataset.st; close(); });
            }, () => res(picked));
        });
    }

    function askConfirm(title, text, okLabel) {
        return new Promise(res => {
            let ok = false;
            sheet(`<h3>${esc(title)}</h3><p>${esc(text)}</p>
                <div class="jp-sheet-row">
                    <button type="button" class="jp-sheet-cancel" data-close>ยกเลิก</button>
                    <button type="button" class="jp-sheet-danger" data-ok>${esc(okLabel)}</button>
                </div>`,
            (el, close) => {
                el.querySelector('[data-ok]').onclick = () => { ok = true; close(); };
            }, () => res(ok));
        });
    }

    function askCaption(current) {
        return new Promise(res => {
            let val = null;
            sheet(`<h3>หมายเหตุใต้รูป</h3>
                <textarea class="jp-cap-input" maxlength="500" rows="3" placeholder="เช่น รอยบุบมุมซ้ายบน มีมาก่อนรับเครื่อง">${esc(current)}</textarea>
                <div class="jp-sheet-row">
                    <button type="button" class="jp-sheet-cancel" data-close>ยกเลิก</button>
                    <button type="button" class="jp-sheet-ok" data-ok>บันทึก</button>
                </div>`,
            (el, close) => {
                const ta = el.querySelector('textarea');
                setTimeout(() => { ta.focus(); ta.setSelectionRange(ta.value.length, ta.value.length); }, 220);
                el.querySelector('[data-ok]').onclick = () => { val = ta.value.trim(); close(); };
            }, () => res(val));
        });
    }

    /* ── write actions shared by viewer + select bar; each returns true on success ── */
    /** one write at a time — Drive takes a few seconds per photo */
    let writing = false;
    async function busy(label, fn) {
        if (writing) { toast('รอสักครู่ กำลังทำรายการก่อนหน้า…'); return false; }
        writing = true;
        document.documentElement.classList.add('jp-busy');
        toast(label, false, true);
        try { return await fn(); }
        finally { writing = false; document.documentElement.classList.remove('jp-busy'); }
    }
    async function doMove(store, ids, stage) {
        return busy(`กำลังย้าย ${ids.length} รูป…`, () => moveNow(store, ids, stage));
    }
    async function doDelete(store, ids) {
        return busy(`กำลังลบ ${ids.length} รูป…`, () => deleteNow(store, ids));
    }
    async function moveNow(store, ids, stage) {
        try {
            const j = await post({ action: 'move', ids, stage });
            j.photos.forEach(p => store.replace(p));
            if (!j.ok) toast(j.msg, true); else toast(`ย้ายไป "${STAGES[stage]}" แล้ว ${j.photos.length} รูป`);
            store.changed();
            return j.ok;
        } catch (e) { toast(e.message, true); return false; }
    }
    async function deleteNow(store, ids) {
        try {
            const j = await post({ action: 'delete', ids });
            store.remove(j.deleted);
            if (!j.ok) toast(j.msg, true); else toast(`ลบแล้ว ${j.deleted.length} รูป (อยู่ในถังขยะ Drive 30 วัน)`);
            store.changed();
            return j.ok;
        } catch (e) { toast(e.message, true); return false; }
    }
    async function doCaption(store, p, caption) {
        return busy('กำลังบันทึก…', () => captionNow(store, p, caption));
    }
    async function captionNow(store, p, caption) {
        try {
            const j = await post({ action: 'caption', id: p.id, caption });
            if (!j.ok) throw new Error(j.msg || 'บันทึกไม่สำเร็จ');
            store.replace(j.photo);
            store.changed();
            toast('บันทึกหมายเหตุแล้ว');
            return j.photo;
        } catch (e) { toast(e.message, true); return null; }
    }

    /* ── zip in the browser (JSZip) ── */
    let jszip = null;
    const loadZip = () => jszip || (jszip = new Promise((ok, bad) => {
        if (window.JSZip) return ok(window.JSZip);
        const s = document.createElement('script');
        s.src = 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js';
        s.onload = () => ok(window.JSZip);
        s.onerror = () => { jszip = null; bad(new Error('โหลดตัวทำ zip ไม่ได้')); };
        document.head.appendChild(s);
    }));
    function saveBlob(blob, name) {
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = name;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(a.href), 60000);
    }
    /** one photo → plain download; many → zip with a folder per job/stage */
    async function download(list, zipName, onStep) {
        if (list.length === 1) { location.href = list[0].download; return; }
        const Z = await loadZip();
        const zip = new Z();
        const multiJob = new Set(list.map(p => p.job.id)).size > 1;
        let n = 0;
        for (const p of list) {
            onStep?.(`กำลังรวม ${n}/${list.length}`);
            const r = await fetch(p.full, { credentials: 'same-origin' });
            if (!r.ok) throw new Error(`ดึงรูป ${p.name} ไม่ได้`);
            const dir = (multiJob ? (p.job.ticket || 'job' + p.job.id) + '/' : '') + STAGES[p.stage];
            zip.folder(dir).file(p.name, await r.blob());
            n++;
        }
        onStep?.('กำลังบีบไฟล์…');
        saveBlob(await zip.generateAsync({ type: 'blob' }), zipName);   // JPEGs don't shrink — STORE is fine
    }

    /* =====================================================
       Viewer — Photos style
       ===================================================== */
    const Viewer = (() => {
        const el = document.createElement('div');
        el.className = 'jv';
        el.hidden = true;
        el.innerHTML = `
            <div class="jv-top">
                <button type="button" class="jv-ico" data-x aria-label="ปิด">${icon('close')}</button>
                <div class="jv-title"><b data-title></b><small data-sub></small></div>
                <a class="jv-ico" data-job title="เปิดงานซ่อม" hidden>${icon('receipt_long')}</a>
                <a class="jv-ico" data-drive target="_blank" rel="noopener" title="เปิดใน Google Drive">${icon('add_to_drive')}</a>
            </div>
            <div class="jv-stage" data-stage>
                <img class="jv-img" data-img alt="" draggable="false">
                <div class="jv-spin" data-spin></div>
                <button type="button" class="jv-nav jv-prev" data-prev aria-label="ก่อนหน้า">${icon('chevron_left')}</button>
                <button type="button" class="jv-nav jv-next" data-next aria-label="ถัดไป">${icon('chevron_right')}</button>
            </div>
            <div class="jv-bottom">
                <button type="button" class="jv-cap" data-cap></button>
                <div class="jv-meta" data-meta></div>
                <div class="jv-strip" data-strip></div>
                <div class="jv-bar">
                    <button type="button" data-act="dl">${icon('download')}<span>โหลด</span></button>
                    <button type="button" data-act="move" data-w>${icon('drive_file_move')}<span>ย้าย</span></button>
                    <button type="button" data-act="cap" data-w>${icon('edit_note')}<span>หมายเหตุ</span></button>
                    <button type="button" data-act="del" data-w class="jv-del">${icon('delete')}<span>ลบ</span></button>
                </div>
            </div>`;
        document.body.appendChild(el);
        const $ = s => el.querySelector(s);
        const img = $('[data-img]'), stageEl = $('[data-stage]'), strip = $('[data-strip]');

        let store = null, list = [], idx = 0, opts = {};

        /* ── zoom / pan ── */
        let z = { s: 1, x: 0, y: 0 };
        const apply = (anim) => {
            img.style.transition = anim ? 'transform .22s ease' : 'none';
            img.style.transform = `translate(${z.x}px, ${z.y}px) scale(${z.s})`;
            el.classList.toggle('is-zoomed', z.s > 1.01);
        };
        const clampPan = () => {
            const r = stageEl.getBoundingClientRect();
            const w = img.offsetWidth * z.s, h = img.offsetHeight * z.s;
            const mx = Math.max(0, (w - r.width) / 2), my = Math.max(0, (h - r.height) / 2);
            z.x = Math.min(mx, Math.max(-mx, z.x));
            z.y = Math.min(my, Math.max(-my, z.y));
        };
        const resetZoom = () => { z = { s: 1, x: 0, y: 0 }; apply(false); };
        function zoomAt(cx, cy, s) {
            const r = stageEl.getBoundingClientRect();
            const ox = cx - (r.left + r.width / 2), oy = cy - (r.top + r.height / 2);
            const k = s / z.s;
            z.x = ox - (ox - z.x) * k; z.y = oy - (oy - z.y) * k; z.s = s;
            if (z.s <= 1) { z = { s: 1, x: 0, y: 0 }; }
            clampPan();
        }

        // touch: pinch, pan when zoomed, swipe when not, double-tap, single tap = hide chrome
        let t0 = null, pinch = null, lastTap = 0, tapT = 0, moved = false;
        stageEl.addEventListener('touchstart', e => {
            moved = false;
            if (e.touches.length === 2) {
                const [a, b] = e.touches;
                pinch = { d: Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY), s: z.s,
                          cx: (a.clientX + b.clientX) / 2, cy: (a.clientY + b.clientY) / 2 };
                t0 = null;
            } else if (e.touches.length === 1) {
                t0 = { x: e.touches[0].clientX, y: e.touches[0].clientY, zx: z.x, zy: z.y, t: Date.now() };
            }
        }, { passive: true });
        stageEl.addEventListener('touchmove', e => {
            if (pinch && e.touches.length === 2) {
                e.preventDefault();
                const [a, b] = e.touches;
                const d = Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY);
                zoomAt(pinch.cx, pinch.cy, Math.min(5, Math.max(1, pinch.s * d / pinch.d)));
                apply(false); moved = true;
            } else if (t0 && e.touches.length === 1) {
                const dx = e.touches[0].clientX - t0.x, dy = e.touches[0].clientY - t0.y;
                if (Math.abs(dx) + Math.abs(dy) > 8) moved = true;
                if (z.s > 1) {
                    e.preventDefault();
                    z.x = t0.zx + dx; z.y = t0.zy + dy; clampPan(); apply(false);
                } else if (Math.abs(dx) > Math.abs(dy)) {
                    e.preventDefault();
                    img.style.transition = 'none';
                    img.style.transform = `translateX(${dx}px)`;
                }
            }
        }, { passive: false });
        stageEl.addEventListener('touchend', e => {
            if (pinch) { if (e.touches.length < 2) pinch = null; return; }
            if (!t0) return;
            const ct = e.changedTouches[0];
            const dx = ct.clientX - t0.x, dy = ct.clientY - t0.y;
            const start = t0; t0 = null;
            if (z.s <= 1 && moved) {
                if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy)) { step(dx < 0 ? 1 : -1, true); return; }
                if (dy > 110 && Math.abs(dy) > Math.abs(dx) * 1.5) { close(); return; }   // swipe down to close
                apply(true); return;
            }
            if (moved || Date.now() - start.t > 300) return;
            if (e.target.closest('button')) return;
            const now = Date.now();
            if (now - lastTap < 280) {                // double tap
                clearTimeout(tapT); lastTap = 0;
                zoomAt(ct.clientX, ct.clientY, z.s > 1 ? 1 : 2.5); apply(true);
            } else {
                lastTap = now;
                tapT = setTimeout(() => el.classList.toggle('is-bare'), 280);
            }
        });
        // mouse: double-click zoom, drag to pan, wheel zoom
        stageEl.addEventListener('dblclick', e => { zoomAt(e.clientX, e.clientY, z.s > 1 ? 1 : 2.5); apply(true); });
        stageEl.addEventListener('wheel', e => {
            e.preventDefault();
            zoomAt(e.clientX, e.clientY, Math.min(5, Math.max(1, z.s * (e.deltaY < 0 ? 1.15 : 1 / 1.15))));
            apply(false);
        }, { passive: false });
        let drag = null;
        stageEl.addEventListener('mousedown', e => {
            if (z.s <= 1 || e.button !== 0 || e.target.closest('button')) return;
            drag = { x: e.clientX, y: e.clientY, zx: z.x, zy: z.y }; e.preventDefault();
        });
        window.addEventListener('mousemove', e => {
            if (!drag) return;
            z.x = drag.zx + e.clientX - drag.x; z.y = drag.zy + e.clientY - drag.y; clampPan(); apply(false);
        });
        window.addEventListener('mouseup', () => { drag = null; });
        stageEl.addEventListener('click', e => {
            if (e.pointerType === 'touch' || e.detail === 0) return;
            if (e.target === stageEl) close();        // click the black around the photo
        });

        /* ── show ── */
        function show(anim) {
            const p = list[idx];
            if (!p) return close();
            resetZoom();
            if (anim) { img.style.opacity = '0'; }
            img.src = p.thumb;                        // instant — already cached
            $('[data-spin]').hidden = false;
            const full = new Image();
            full.onload = () => { if (list[idx] === p) { img.src = p.full; $('[data-spin]').hidden = true; } };
            full.onerror = () => { $('[data-spin]').hidden = true; };
            full.src = p.full;
            requestAnimationFrame(() => { img.style.transition = 'opacity .18s'; img.style.opacity = '1'; });

            $('[data-title]').textContent = `${STAGES[p.stage]} · ${idx + 1}/${list.length}`;
            $('[data-sub]').textContent = opts.showJob ? [p.job.ticket, p.job.name, p.job.device].filter(Boolean).join(' · ') : '';
            const jl = $('[data-job]');
            jl.hidden = !opts.showJob;
            jl.href = `/admin/tracking/edit.php?id=${p.job.id}#photos`;
            $('[data-drive]').href = p.drive;

            const cap = $('[data-cap]');
            cap.textContent = p.caption || (store.canWrite ? '+ เพิ่มหมายเหตุ' : '');
            cap.classList.toggle('is-empty', !p.caption);
            cap.disabled = !store.canWrite;
            cap.hidden = !p.caption && !store.canWrite;
            $('[data-meta]').textContent = `${fmtAt(p.at)}${p.by ? ' · ' + p.by : ''}`;
            el.querySelectorAll('[data-w]').forEach(b => b.hidden = !store.canWrite);
            $('[data-prev]').hidden = idx === 0;
            $('[data-next]').hidden = idx >= list.length - 1;

            strip.querySelectorAll('.is-on').forEach(t => t.classList.remove('is-on'));
            const on = strip.children[idx];
            if (on) { on.classList.add('is-on'); on.scrollIntoView({ block: 'nearest', inline: 'center', behavior: anim ? 'smooth' : 'auto' }); }
        }
        function buildStrip() {
            strip.hidden = list.length < 2;
            strip.innerHTML = list.map((p, i) => `<button type="button" data-i="${i}"><img src="${p.thumb}" alt=""></button>`).join('');
        }
        strip.addEventListener('click', e => { const b = e.target.closest('[data-i]'); if (b) { idx = +b.dataset.i; show(true); } });

        function step(n, anim) { const i = idx + n; if (i >= 0 && i < list.length) { idx = i; show(anim); } else apply(true); }
        function open(s, l, id, o = {}) {
            store = s; list = l; opts = o;
            idx = Math.max(0, list.findIndex(p => p.id === id));
            el.hidden = false;
            el.classList.remove('is-bare');
            document.documentElement.classList.add('jp-lock');
            buildStrip();
            show(false);
        }
        function close() {
            el.hidden = true;
            document.documentElement.classList.remove('jp-lock');
            img.removeAttribute('src');
        }
        /** after a write: keep showing the same index (or the next one) from the fresh list */
        function refresh(newList) {
            if (el.hidden) return;
            const cur = list[idx];
            list = newList;
            if (!list.length) return close();
            const at = cur ? list.findIndex(p => p.id === cur.id) : -1;
            idx = at >= 0 ? at : Math.min(idx, list.length - 1);
            buildStrip();
            show(false);
        }

        $('[data-x]').onclick = close;
        $('[data-prev]').onclick = () => step(-1, true);
        $('[data-next]').onclick = () => step(1, true);
        document.addEventListener('keydown', e => {
            if (el.hidden || document.querySelector('.jp-sheet-wrap')) return;
            if (e.key === 'Escape') close();
            if (e.key === 'ArrowLeft') step(-1, true);
            if (e.key === 'ArrowRight') step(1, true);
        });

        el.querySelector('.jv-bar').addEventListener('click', async e => {
            const b = e.target.closest('[data-act]'); if (!b) return;
            const p = list[idx]; if (!p) return;
            const a = b.dataset.act;
            if (a === 'dl') location.href = p.download;
            if (a === 'move') {
                const to = await askStage('ย้ายรูปนี้ไปขั้นตอน', p.stage);
                if (to) await doMove(store, [p.id], to);
            }
            if (a === 'cap') editCap(p);
            if (a === 'del') {
                if (await askConfirm('ลบรูปนี้?', 'รูปจะไปอยู่ในถังขยะของ Drive กู้คืนได้ภายใน 30 วัน', 'ลบรูป')) {
                    await doDelete(store, [p.id]);
                }
            }
        });
        async function editCap(p) {
            const v = await askCaption(p.caption || '');
            if (v !== null && v !== (p.caption || '')) await doCaption(store, p, v);
        }
        $('[data-cap]').onclick = () => { const p = list[idx]; if (p && store.canWrite) editCap(p); };

        return { open, refresh, isOpen: () => !el.hidden };
    })();

    /* =====================================================
       Grid with select-many — used by both pages
       ===================================================== */
    function makeGrid(cfg) {
        // cfg: { root, store, visible(): photo[], showJob, zipName(), renderInto(gridEl, list, tileHtml) }
        const { root, store } = cfg;
        let selecting = false;
        const sel = new Set();

        const bar = document.createElement('div');
        bar.className = 'jp-selbar';
        bar.hidden = true;
        bar.innerHTML = `
            <button type="button" class="jp-selbar-x" data-sx aria-label="เลิกเลือก">${icon('close')}</button>
            <b data-sn>เลือกรูป</b>
            <button type="button" data-sa class="jp-selbar-all">ทั้งหมด</button>
            <span class="jp-selbar-acts">
                <button type="button" data-sact="dl">${icon('download')}<span>โหลด</span></button>
                <button type="button" data-sact="move" data-w>${icon('drive_file_move')}<span>ย้าย</span></button>
                <button type="button" data-sact="del" data-w class="jp-selbar-del">${icon('delete')}<span>ลบ</span></button>
            </span>`;
        document.body.appendChild(bar);

        const tile = (p, withStage) => `
            <button type="button" class="jp-tile ${sel.has(p.id) ? 'is-sel' : ''}" data-id="${p.id}" title="${esc(p.caption || p.name)}">
                <img src="${p.thumb}" alt="" loading="lazy" onerror="this.closest('.jp-tile').classList.add('is-broken')">
                ${withStage ? `<i class="jp-tile-stage jp-st-${p.stage}">${STAGES[p.stage]}</i>` : ''}
                ${p.caption ? `<i class="jp-tile-note">${icon('sticky_note_2')}</i>` : ''}
                <i class="jp-tile-check">${icon('check')}</i>
            </button>`;

        function setSelecting(on) {
            selecting = on;
            if (!on) sel.clear();
            root.classList.toggle('is-selecting', on);
            document.documentElement.classList.toggle('jp-selecting', on);
            bar.hidden = !on;
            paintSel();
        }
        function paintSel() {
            root.querySelectorAll('.jp-tile[data-id]').forEach(t => t.classList.toggle('is-sel', sel.has(+t.dataset.id)));
            const n = sel.size;
            bar.querySelector('[data-sn]').textContent = n ? `เลือก ${n} รูป` : 'แตะรูปเพื่อเลือก';
            bar.querySelectorAll('[data-sact]').forEach(b => b.disabled = !n);
            bar.querySelectorAll('[data-w]').forEach(b => b.hidden = !store.canWrite);
            const vis = cfg.visible();
            bar.querySelector('[data-sa]').textContent = vis.length && vis.every(p => sel.has(p.id)) ? 'ไม่เลือก' : 'ทั้งหมด';
        }
        function toggle(id) { sel.has(id) ? sel.delete(id) : sel.add(id); paintSel(); }

        // tap = open (or toggle while selecting) · long-press = start selecting
        let lp = null, lpFired = false;
        root.addEventListener('pointerdown', e => {
            const t = e.target.closest('.jp-tile[data-id]'); if (!t) return;
            lpFired = false;
            clearTimeout(lp);
            lp = setTimeout(() => {
                lpFired = true;
                if (!selecting) setSelecting(true);
                sel.add(+t.dataset.id); paintSel();
                navigator.vibrate?.(15);
            }, 480);
        });
        ['pointerup', 'pointercancel', 'pointerleave', 'scroll'].forEach(ev =>
            root.addEventListener(ev, () => clearTimeout(lp), true));
        root.addEventListener('contextmenu', e => { if (e.target.closest('.jp-tile')) e.preventDefault(); });
        root.addEventListener('click', e => {
            const t = e.target.closest('.jp-tile[data-id]'); if (!t) return;
            if (lpFired) { lpFired = false; return; }
            const id = +t.dataset.id;
            if (selecting) toggle(id);
            else Viewer.open(store, cfg.visible(), id, { showJob: cfg.showJob });
        });

        bar.querySelector('[data-sx]').onclick = () => setSelecting(false);
        bar.querySelector('[data-sa]').onclick = () => {
            const vis = cfg.visible();
            if (vis.every(p => sel.has(p.id))) vis.forEach(p => sel.delete(p.id)); else vis.forEach(p => sel.add(p.id));
            paintSel();
        };
        bar.addEventListener('click', async e => {
            const b = e.target.closest('[data-sact]'); if (!b || b.disabled) return;
            const ids = [...sel];
            const picked = store.all().filter(p => sel.has(p.id));
            if (b.dataset.sact === 'dl') {
                const lbl = b.querySelector('span');
                b.disabled = true;
                try { await download(picked, cfg.zipName(), s => lbl.textContent = s); }
                catch (err) { toast(err.message, true); }
                lbl.textContent = 'โหลด'; b.disabled = false;
                return;
            }
            if (b.dataset.sact === 'move') {
                const cur = new Set(picked.map(p => p.stage));
                const to = await askStage(`ย้าย ${ids.length} รูปไปขั้นตอน`, cur.size === 1 ? [...cur][0] : null);
                if (to && await doMove(store, ids, to)) setSelecting(false);
            }
            if (b.dataset.sact === 'del') {
                if (await askConfirm(`ลบ ${ids.length} รูป?`, 'รูปจะไปอยู่ในถังขยะของ Drive กู้คืนได้ภายใน 30 วัน', `ลบ ${ids.length} รูป`)
                    && await doDelete(store, ids)) setSelecting(false);
            }
        });
        document.addEventListener('keydown', e => { if (e.key === 'Escape' && selecting && !Viewer.isOpen()) setSelecting(false); });

        return {
            tile,
            start: () => setSelecting(true),
            stop: () => setSelecting(false),
            selecting: () => selecting,
            prune: () => { const ids = new Set(store.all().map(p => p.id)); [...sel].forEach(id => ids.has(id) || sel.delete(id)); paintSel(); },
        };
    }

    /** list holder; changed() repaints the page + an open viewer */
    function makeStore(onChange) {
        let photos = [];
        const s = {
            canWrite: false,
            all: () => photos,
            set: l => { photos = l; },
            add: p => { photos.push(p); },
            replace: p => { const i = photos.findIndex(x => x.id === p.id); if (i >= 0) photos[i] = p; },
            remove: ids => { const k = new Set(ids); photos = photos.filter(p => !k.has(p.id)); },
            changed: () => onChange(),
        };
        return s;
    }

    /* =====================================================
       Page 1 — the job card on edit.php
       ===================================================== */
    function initJob(root) {
        const JOB    = root.dataset.jobPhotos;
        const TICKET = root.dataset.ticket || ('job' + JOB);
        const $ = s => root.querySelector(s);
        const gridEl = $('[data-jp-grid]'), empty = $('[data-jp-empty]'), note = $('[data-jp-note]');
        const actions = $('[data-jp-actions]'), folderLink = $('[data-jp-folder]');
        const zipBtn = $('[data-jp-zip]'), zipLbl = $('[data-jp-zip-lbl]'), selBtn = $('[data-jp-select]');

        let queue = [], busy = false, stage = 'intake';
        try { const s = sessionStorage.getItem('jp-stage-' + JOB); if (STAGES[s]) stage = s; } catch (e) {}

        const store = makeStore(() => { render(); grid.prune(); Viewer.refresh(visible()); });
        const visible = () => store.all().filter(p => p.stage === stage);
        const grid = makeGrid({ root, store, visible, showJob: false, zipName: () => `${TICKET}_photos.zip` });

        root.querySelectorAll('.jp-tab').forEach(t => t.addEventListener('click', () => {
            stage = t.dataset.stage;
            try { sessionStorage.setItem('jp-stage-' + JOB, stage); } catch (e) {}
            render();
        }));

        function render() {
            const all = store.all();
            root.querySelectorAll('.jp-tab').forEach(t => {
                const k = t.dataset.stage, on = k === stage;
                t.classList.toggle('is-on', on);
                t.setAttribute('aria-selected', on);
                const n = all.filter(p => p.stage === k).length + queue.filter(q => q.stage === k).length;
                const c = t.querySelector('[data-count]');
                c.textContent = n; c.hidden = !n;
            });
            const list = visible(), waits = queue.filter(q => q.stage === stage);
            gridEl.innerHTML = list.map(p => grid.tile(p)).join('') + waits.map(q => `
                <div class="jp-tile jp-tile-up ${q.state === 'err' ? 'is-err' : ''}" data-key="${q.key}">
                    ${q.preview ? `<img src="${q.preview}" alt="">` : ''}
                    <div class="jp-up">
                        ${q.state === 'err'
                            ? `${icon('error')}<small>${esc(q.msg)}</small>
                               <span class="jp-up-btns"><button type="button" data-retry="${q.key}">ลองใหม่</button><button type="button" data-drop="${q.key}">ทิ้ง</button></span>`
                            : `<div class="jp-ring"><i style="--p:${q.pct || 0}"></i></div><small>${q.state === 'up' ? (q.pct || 0) + '%' : 'รอคิว'}</small>`}
                    </div>
                </div>`).join('');
            empty.hidden = list.length + waits.length > 0;
            zipBtn.hidden = !all.length;
            zipLbl.textContent = `โหลดทั้งหมด (${all.length})`;
            selBtn.hidden = !all.length;
        }

        async function load() {
            try {
                const r = await fetch(`${API}?action=list&job=${JOB}`, { credentials: 'same-origin' });
                const j = await r.json();
                if (!j.ok) throw new Error(j.msg || 'โหลดรายการรูปไม่ได้');
                store.set(j.photos);
                store.canWrite = j.can_write;
                setFolder(j.folder_url);
                if (!j.connected) {
                    const link = root.dataset.settings;
                    note.innerHTML = `${icon('cloud_off')}<span>ยังไม่ได้เชื่อม Google Drive — ` +
                        (link ? `<a href="${link}">ไปเชื่อมที่หน้าตั้งค่า</a>` : 'ให้เจ้าของร้านเชื่อมในหน้าตั้งค่าก่อน') + '</span>';
                    note.hidden = false;
                }
                const up = j.connected && j.can_write;
                root.querySelectorAll('label.jp-btn').forEach(b => b.hidden = !up);
                actions.hidden = !up && !j.photos.length;
            } catch (e) {
                note.innerHTML = `${icon('error')}<span>${esc(e.message)}</span>`;
                note.hidden = false;
            }
            render();
        }
        function setFolder(url) { folderLink.hidden = !url; if (url) folderLink.href = url; }

        selBtn.addEventListener('click', () => grid.selecting() ? grid.stop() : grid.start());

        /* ── pick → queue → upload ── */
        root.querySelectorAll('[data-jp-input]').forEach(inp => inp.addEventListener('change', () => {
            const files = [...inp.files];
            inp.value = '';
            files.forEach(f => queue.push({ key: Math.random().toString(36).slice(2), stage, file: f, state: 'wait', pct: 0, preview: URL.createObjectURL(f) }));
            render(); pump();
        }));
        gridEl.addEventListener('click', e => {
            const r = e.target.closest('[data-retry]'), d = e.target.closest('[data-drop]');
            if (r) { const q = queue.find(x => x.key === r.dataset.retry); if (q) { q.state = 'wait'; q.pct = 0; render(); pump(); } }
            if (d) { dropQ(d.dataset.drop); render(); }
        });
        function dropQ(key) {
            const q = queue.find(x => x.key === key);
            if (q?.preview) URL.revokeObjectURL(q.preview);
            queue = queue.filter(x => x.key !== key);
        }
        async function pump() {
            if (busy) return;
            const q = queue.find(x => x.state === 'wait');
            if (!q) return;
            busy = true; q.state = 'up'; q.pct = 0; render();
            try {
                const j = await send(q, await shrink(q.file));
                store.add(j.photo);
                setFolder(j.folder_url);
                dropQ(q.key);
            } catch (e) { q.state = 'err'; q.msg = e.message || 'อัปไม่สำเร็จ'; }
            busy = false;
            render(); pump();
        }
        function send(q, blob) {
            return new Promise((ok, bad) => {
                const fd = new FormData();
                fd.append('action', 'upload'); fd.append('job', JOB); fd.append('stage', q.stage);
                fd.append('photo', blob, 'photo.jpg');
                const x = new XMLHttpRequest();
                x.open('POST', API);
                x.setRequestHeader('X-Requested-With', 'fetch');
                x.upload.onprogress = e => {
                    if (!e.lengthComputable) return;
                    q.pct = Math.min(90, Math.round(e.loaded / e.total * 90));   // the rest is our server → Drive
                    const t = gridEl.querySelector(`[data-key="${q.key}"]`);
                    t?.querySelector('.jp-ring i')?.style.setProperty('--p', q.pct);
                    const s = t?.querySelector('.jp-up small'); if (s) s.textContent = q.pct + '%';
                };
                x.onload = () => {
                    let j = null; try { j = JSON.parse(x.responseText); } catch (e) {}
                    if (x.status === 401) return bad(new Error('หลุดล็อกอิน — รีเฟรชหน้า'));
                    j?.ok ? ok(j) : bad(new Error(j?.msg || `อัปไม่สำเร็จ (HTTP ${x.status})`));
                };
                x.onerror = () => bad(new Error('เน็ตหลุด'));
                x.send(fd);
            });
        }
        window.addEventListener('beforeunload', e => {
            if (queue.some(q => q.state !== 'err')) { e.preventDefault(); e.returnValue = ''; }
        });

        zipBtn.addEventListener('click', async () => {
            if (zipBtn.disabled) return;
            zipBtn.disabled = true;
            try { await download(store.all(), `${TICKET}_photos.zip`, s => zipLbl.textContent = s); }
            catch (e) { toast(e.message, true); }
            zipBtn.disabled = false;
            render();
        });

        load();
    }

    /* longest side ≤ 2048, JPEG — keeps uploads well under the host's 2MB limit */
    async function shrink(file) {
        let src;
        try { src = await createImageBitmap(file, { imageOrientation: 'from-image' }); }
        catch (e) {
            src = await new Promise((ok, bad) => {
                const im = new Image();
                im.onload = () => ok(im);
                im.onerror = () => bad(new Error('เปิดไฟล์นี้ไม่ได้ (ไม่ใช่รูป หรือเป็น HEIC ที่ browser นี้อ่านไม่ได้)'));
                im.src = URL.createObjectURL(file);
            });
        }
        const k = Math.min(1, MAX_SIDE / Math.max(src.width, src.height));
        const c = document.createElement('canvas');
        c.width = Math.round(src.width * k); c.height = Math.round(src.height * k);
        c.getContext('2d').drawImage(src, 0, 0, c.width, c.height);
        src.close?.();
        for (const q of [0.85, 0.72, 0.6]) {
            const b = await new Promise(ok => c.toBlob(ok, 'image/jpeg', q));
            if (b && b.size < 1.8 * 1024 * 1024) return b;
        }
        throw new Error('รูปใหญ่เกินไปแม้ย่อแล้ว');
    }

    /* =====================================================
       Page 2 — every job's photos (settings/photos.php)
       ===================================================== */
    function initBrowser(root) {
        const $ = s => root.querySelector(s);
        const gridEl = $('[data-pb-grid]'), empty = $('[data-pb-empty]'), more = $('[data-pb-more]');
        const qIn = $('[data-pb-q]'), note = $('[data-pb-note]'), count = $('[data-pb-count]');
        let stage = '', q = '', more_ = false, loading = false, seq = 0;

        const store = makeStore(() => {
            // a moved photo may no longer match the stage filter
            if (stage) store.remove(store.all().filter(p => p.stage !== stage).map(p => p.id));
            render(); grid.prune(); Viewer.refresh(store.all());
        });
        const grid = makeGrid({ root, store, visible: () => store.all(), showJob: true, zipName: () => 'cmns_photos.zip' });

        function render() {
            const all = store.all();
            let html = '', lastJob = null;
            for (const p of all) {                     // one block per job, newest job first
                if (p.job.id !== lastJob) {
                    if (lastJob !== null) html += '</div></section>';
                    lastJob = p.job.id;
                    html += `<section class="pb-job">
                        <a class="pb-job-hd" href="/admin/tracking/edit.php?id=${p.job.id}#photos">
                            <code>${esc(p.job.ticket)}</code>
                            <span>${esc([p.job.name, p.job.device].filter(Boolean).join(' · '))}</span>
                            ${icon('chevron_right')}
                        </a><div class="jp-grid">`;
                }
                html += grid.tile(p, true);
            }
            if (lastJob !== null) html += '</div></section>';
            gridEl.innerHTML = html;
            empty.hidden = loading || all.length > 0;
            more.hidden = !more_;
            count.textContent = all.length ? `${all.length}${more_ ? '+' : ''} รูป` : '';
        }

        async function load(append) {
            const my = ++seq;
            loading = true;
            more.disabled = true;
            if (!append) { store.set([]); render(); }
            try {
                const u = new URLSearchParams({ action: 'browse', q, stage, offset: append ? store.all().length : 0 });
                const j = await (await fetch(`${API}?${u}`, { credentials: 'same-origin' })).json();
                if (my !== seq) return;               // a newer search took over
                if (!j.ok) throw new Error(j.msg || 'โหลดรูปไม่ได้');
                store.canWrite = j.can_write;
                store.set(append ? store.all().concat(j.photos) : j.photos);
                more_ = j.more;
                note.hidden = j.connected;
            } catch (e) { toast(e.message, true); }
            loading = false; more.disabled = false;
            render();
        }

        root.querySelectorAll('[data-pb-stage]').forEach(b => b.addEventListener('click', () => {
            stage = b.dataset.pbStage;
            root.querySelectorAll('[data-pb-stage]').forEach(x => x.classList.toggle('is-on', x === b));
            load(false);
        }));
        let qt = 0;
        qIn.addEventListener('input', () => { clearTimeout(qt); qt = setTimeout(() => { q = qIn.value.trim(); load(false); }, 300); });
        more.addEventListener('click', () => load(true));
        $('[data-pb-select]').addEventListener('click', () => grid.selecting() ? grid.stop() : grid.start());

        load(false);
    }

    document.querySelectorAll('[data-job-photos]').forEach(initJob);
    document.querySelectorAll('[data-photo-browser]').forEach(initBrowser);
})();
