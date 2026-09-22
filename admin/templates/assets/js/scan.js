/* =========================================================
   CMNS Admin — QR scanner
   Path: admin/templates/assets/js/scan.js
   Used by: admin/scan/index.php only.

   Decodes with zxing-wasm (ZXing C++ in WebAssembly) — iOS Safari ships no
   BarcodeDetector and the admin runs as an iOS PWA. jsQR stays as the
   fallback if the wasm cannot load; it is far weaker on a 14 mm sticker. A decode that looks like a warranty slip or a
   repair-number sticker is handed to resolve.php (a part label opens its
   sheet via part.php), which does the lookup and
   the redirect; anything else just shows its value. The check below only
   decides whether to make that round-trip — resolve.php re-validates and
   is the real gate.
   ========================================================= */
(function () {
    'use strict';

    var stage   = document.getElementById('scanStage');
    var video   = document.getElementById('scanVideo');
    var cover   = document.getElementById('scanCover');
    var coverIco = document.getElementById('scanCoverIco');
    var coverMsg = document.getElementById('scanCoverMsg');
    var retryBtn = document.getElementById('scanRetry');
    var hint    = document.getElementById('scanHint');
    var result  = document.getElementById('scanResult');
    var valueEl = document.getElementById('scanValue');
    var againBtn = document.getElementById('scanAgain');
    var copyBtn = document.getElementById('scanCopy');
    var noteEl  = document.getElementById('scanNote');

    if (!video) return;

    /* Decode only what is inside the on-screen reticle, at native resolution
       (capped at 720px). Reading the whole frame grabbed whatever QR drifted
       into view first — the neighbouring sticker on a sheet — before the
       user had aimed. Cropping also gives a 14 mm sticker far more pixels
       per module than a downscaled full frame. */
    var MAX_EDGE = 720;
    var busy = false;   // zxing decodes async; never queue a second frame behind it
    var reticle = stage ? stage.querySelector('.scan-reticle') : null;

    /* Aim guards: nothing is accepted for a moment after the camera goes
       live, and a value must be read twice in a row — a QR swept across the
       reticle on the way to the right one reads once, not twice. */
    var WARMUP_MS = 600;
    var liveAt    = 0;
    var lastRead  = '';

    /* zxing-wasm fetches its .wasm from jsDelivr on first use. Warm it up
       now so the first frames are not spent waiting; if it fails, jsQR. */
    var zx = window.ZXingWASM || null;
    var ZX_OPTS = { formats: ['QRCode'], tryHarder: true, tryInvert: false, maxNumberOfSymbols: 1 };
    if (zx) {
        zx.prepareZXingModule({ fireImmediately: true }).catch(function (e) {
            console.warn('zxing-wasm unavailable, using jsQR', e);
            zx = null;
        });
    }

    function decode(img, w, h) {
        if (zx) {
            busy = true;
            zx.readBarcodes(img, ZX_OPTS).then(function (rs) {
                busy = false;
                for (var i = 0; i < rs.length; i++) {
                    if (rs[i].text) { accept(rs[i].text); return; }
                }
                lastRead = '';
            }, function (e) {
                busy = false;
                console.warn('zxing decode failed, using jsQR', e);
                zx = null;
            });
            return;
        }
        var code = window.jsQR ? window.jsQR(img.data, w, h, { inversionAttempts: 'dontInvert' }) : null;
        if (code && code.data) accept(code.data); else lastRead = '';
    }

    function accept(text) {
        if (!running || Date.now() - liveAt < WARMUP_MS) return;
        if (text !== lastRead) { lastRead = text; return; }
        found(text);
    }

    /* The reticle's square in video pixels. The video is object-fit: cover,
       so map through the same scale and centring the browser used. */
    function reticleCrop(vw, vh) {
        if (!reticle) {
            var s = Math.min(vw, vh) * 0.62;
            return { x: (vw - s) / 2, y: (vh - s) / 2, size: s };
        }
        var st = stage.getBoundingClientRect(), r = reticle.getBoundingClientRect();
        var k  = Math.max(st.width / vw, st.height / vh);
        var ox = (st.width - vw * k) / 2, oy = (st.height - vh * k) / 2;
        var size = Math.min(r.width / k, vw, vh);
        var x = Math.max(0, Math.min(vw - size, (r.left - st.left - ox) / k));
        var y = Math.max(0, Math.min(vh - size, (r.top - st.top - oy) / k));
        return { x: x, y: y, size: size };
    }

    /* Web cameras open at the lens' widest; iPhone Pro main cameras will not
       focus closer than ~20 cm, where a 14 mm sticker is tiny (the native
       Camera app switches to macro — a web page cannot). 2× zoom lets the
       sticker be held at focus distance and still fill the reticle. */
    function tuneTrack(track) {
        if (!track || !track.getCapabilities) return;
        var caps = track.getCapabilities(), adv = {};
        if (caps.zoom && caps.zoom.max > 1) adv.zoom = Math.min(2, caps.zoom.max);
        if (caps.focusMode && caps.focusMode.indexOf('continuous') !== -1) adv.focusMode = 'continuous';
        if (Object.keys(adv).length) {
            track.applyConstraints({ advanced: [adv] }).catch(function () {});
        }
    }
    var canvas = document.createElement('canvas');
    var ctx    = canvas.getContext('2d', { willReadFrequently: true });

    var stream  = null;
    var running = false;
    var frame   = 0;
    var rafId   = 0;

    function fail(icon, msg) {
        running = false;
        cover.hidden = false;
        coverIco.textContent = icon;
        coverMsg.textContent = msg;
        retryBtn.hidden = false;
    }

    function start() {
        cover.hidden = false;
        retryBtn.hidden = true;
        coverIco.textContent = 'photo_camera';
        coverMsg.textContent = 'กำลังเปิดกล้อง…';

        /* localhost and https are secure contexts; http://192.168.x.x or
           http://host.local is not, and getUserMedia is simply absent there.
           Say so — otherwise this looks like a broken camera. */
        if (!window.isSecureContext) {
            fail('lock', 'ต้องเปิดผ่าน HTTPS ถึงจะใช้กล้องได้ (ตอนนี้เปิดผ่าน ' + location.protocol + '//' + location.host + ')');
            return;
        }
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            fail('videocam_off', 'เบราว์เซอร์นี้ไม่รองรับการใช้กล้อง');
            return;
        }
        if (!zx && typeof window.jsQR !== 'function') {
            fail('cloud_off', 'โหลดตัวถอดรหัส QR ไม่สำเร็จ — เช็คอินเทอร์เน็ตแล้วลองใหม่');
            return;
        }

        /* Every getUserMedia call can put the permission prompt back up
           (always, in an iOS home-screen app). A stream we paused is still
           live — resume it instead of asking again. */
        if (stream && stream.getVideoTracks().some(function (t) { return t.readyState === 'live'; })) {
            clearTimeout(releaseTimer);
            stream.getTracks().forEach(function (t) { t.enabled = true; });
            video.play().then(live, function () { stop(); start(); });
            return;
        }

        navigator.mediaDevices.getUserMedia({
            audio: false,
            // ideal, not exact: a laptop with only a front camera still works
            video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1080 } }
        }).then(function (s) {
            stream = s;
            tuneTrack(s.getVideoTracks()[0]);
            video.srcObject = s;
            return video.play();
        }).then(live).catch(function (err) {
            var name = err && err.name;
            if (name === 'NotAllowedError' || name === 'SecurityError') {
                fail('no_photography', 'ไม่ได้รับอนุญาตให้ใช้กล้อง — เปิดสิทธิ์กล้องให้เว็บนี้ในตั้งค่าเบราว์เซอร์');
            } else if (name === 'NotFoundError' || name === 'OverconstrainedError') {
                fail('videocam_off', 'ไม่พบกล้องบนเครื่องนี้');
            } else if (name === 'NotReadableError') {
                fail('error', 'กล้องถูกแอปอื่นใช้อยู่ ปิดแอปนั้นแล้วลองใหม่');
            } else {
                fail('error', 'เปิดกล้องไม่สำเร็จ' + (name ? ' (' + name + ')' : ''));
            }
        });
    }

    function live() {
        cover.hidden = true;
        running = true;
        frame = 0;
        liveAt = Date.now();
        lastRead = '';
        rafId = requestAnimationFrame(tick);
    }

    /* Stop decoding but keep the camera stream, so scanning again on this
       page does not ask for permission again. Released after a few idle
       minutes (e.g. a job sheet left open) so the camera indicator does not
       stay on forever. */
    var RELEASE_MS   = 5 * 60000;
    var releaseTimer = 0;
    function pause() {
        running = false;
        if (rafId) { cancelAnimationFrame(rafId); rafId = 0; }
        if (stream) stream.getTracks().forEach(function (t) { t.enabled = false; });
        clearTimeout(releaseTimer);
        releaseTimer = setTimeout(stop, RELEASE_MS);
    }

    function stop() {
        clearTimeout(releaseTimer);
        running = false;
        if (rafId) { cancelAnimationFrame(rafId); rafId = 0; }
        if (stream) {
            stream.getTracks().forEach(function (t) { t.stop(); });
            stream = null;
        }
        video.srcObject = null;
    }

    function tick() {
        if (!running) return;
        rafId = requestAnimationFrame(tick);

        // Decode every other frame — 30fps of decoding is wasted work.
        if ((frame++ & 1) === 0 || busy) return;
        if (video.readyState !== video.HAVE_ENOUGH_DATA) return;

        var vw = video.videoWidth, vh = video.videoHeight;
        if (!vw || !vh) return;

        var c = reticleCrop(vw, vh);
        var w = Math.round(Math.min(MAX_EDGE, c.size)), h = w;
        if (canvas.width !== w || canvas.height !== h) { canvas.width = w; canvas.height = h; }
        ctx.drawImage(video, c.x, c.y, c.size, c.size, 0, 0, w, h);
        decode(ctx.getImageData(0, 0, w, h), w, h);
    }

    /* Two live warranty formats: W-2026-0055 and WJ-202606-0292. */
    var WARRANTY_RE = /\bWJ?-\d{4,6}-\d{1,6}\b/i;

    /* A repair-number sticker (tracking/stickers.php) decodes to
       "CMNS:<ticket>" — not a URL, so only this scanner can open it.
       Stickers printed before that carry a URL (…/T/<ticket> or
       …/admin/scan/resolve.php?t=<ticket>); still accepted here.
       resolve.php bounces a failed one back as the bare "t=<ticket>".
       Nothing else counts — a stray ?t= on some other site's QR must not
       open a repair job. */
    var TICKET_CODE_RE = /^CMNS:(.{1,50})$/;
    var TICKET_RE      = /^https?:\/\/[^\/\s]+\/(?:admin\/scan\/resolve\.php\?(?:[^#\s]*&)?t=|T\/)([^&#?\/\s]{1,150})(?:[&#]|$)/i;
    var TICKET_BACK_RE = /^t=(.{1,50})$/;   // decoded, may hold spaces ("V5508 (2)")

    /* A part label (inventory/print_labels.php) decodes to "CMNS:P-<id>".
       Checked before TICKET_CODE_RE, which would otherwise take it for a
       ticket — repair tickets are "V" + digits, never "P-". */
    var PART_RE = /^CMNS:P-(\d{1,9})$/i;

    /* Where a decode should go, or null for anything we did not print.
       A printed slip decodes to the full /warranty/?q=<no> URL; the same
       slip read by a generic barcode app decodes to the bare number. */
    function routeFrom(text) {
        text = (text || '').trim();

        var p = PART_RE.exec(text);
        if (p) {
            return { key: 'P' + p[1], part: p[1], icon: 'inventory_2', msg: 'เปิดอะไหล่…' };
        }

        var j = TICKET_CODE_RE.exec(text), ticket = null;
        if (j) {
            ticket = j[1];
        } else if ((j = TICKET_RE.exec(text))) {
            try { ticket = decodeURIComponent(j[1].replace(/\+/g, ' ')); } catch (e) { ticket = j[1]; }
        } else if ((j = TICKET_BACK_RE.exec(text))) {
            ticket = j[1];
        }
        if (ticket !== null) {
            return { key: 'T' + ticket.toUpperCase(), ticket: ticket,
                     href: 'resolve.php?src=app&t=' + encodeURIComponent(ticket),
                     icon: 'build', msg: 'เปิดงาน ' + ticket + '…' };
        }

        var m = /[?&]q=([^&\s]+)/.exec(text);
        var candidate = m ? decodeURIComponent(m[1]) : text;
        var w = WARRANTY_RE.exec(candidate.trim());
        if (w) {
            var no = w[0].toUpperCase();
            return { key: 'W' + no, href: 'resolve.php?q=' + encodeURIComponent(no),
                     icon: 'receipt_long', msg: 'เปิดใบประกัน ' + no + '…' };
        }
        return null;
    }

    /* The value resolve.php just rejected, if we came back from it. Comparing
       on the parsed key, not the raw text, so re-reading the same slip
       through a different encoding still counts as the same failure. */
    var lastFailRoute = routeFrom((stage && stage.dataset.lastFail) || '');
    var lastFail = lastFailRoute ? lastFailRoute.key : null;

    function found(text) {
        var route = routeFrom(text);
        if (route && route.key === sheetKey && Date.now() - sheetClosedAt < REOPEN_MS) {
            lastRead = '';   // same sticker still in frame after its sheet closed — keep scanning
            return;
        }

        pause();
        if (navigator.vibrate) navigator.vibrate(60);

        var suppressed = false;
        if (route && route.key === lastFail) {
            // Same slip that just failed — show it instead of looping.
            route = null;
            suppressed = true;
        }
        // Otherwise the panel would claim this is not ours, which the
        // value on screen plainly contradicts.
        if (noteEl) {
            noteEl.textContent = suppressed
                ? 'QR นี้เพิ่งเปิดไม่สำเร็จ เลยไม่เปิดซ้ำให้ — เอา QR อื่นมาสแกนได้เลย'
                : 'QR ใบประกัน สติ๊กเกอร์งานซ่อม และฉลากอะไหล่จะเปิดให้อัตโนมัติ — ที่เห็นค่านี้แปลว่าอ่านได้แต่ไม่ใช่ของร้าน';
        }
        if (route) {
            cover.hidden = false;
            retryBtn.hidden = true;
            coverIco.textContent = route.icon;
            coverMsg.textContent = route.msg;
            if (route.part) {
                openPartSheet(route, text);
            } else if (route.ticket && window.JobView && window.fetch) {
                openJobSheet(route);
            } else {
                leaveTo(route.href);
            }
            return;
        }

        showValue(text);
    }

    /* The "read it, but it is not ours" panel. `note` overrides the default
       explanation (e.g. a part label whose item was deleted). */
    function showValue(text, note) {
        if (note && noteEl) noteEl.textContent = note;
        valueEl.textContent = text;
        result.hidden = false;
        hint.hidden = true;
        cover.hidden = false;
        coverIco.textContent = 'qr_code_2';
        coverMsg.textContent = 'อ่านสำเร็จ';
        retryBtn.hidden = true;
        result.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    /* Leave the cover up on the way out — a live camera behind a page that
       is already navigating reads as a frozen scanner. */
    function leaveTo(href) {
        stop();
        location.href = href;
    }

    /* A job sticker opens its sheet right here, camera paused behind it.
       Navigating would end the stream, and a home-screen web app on iOS
       asks for camera permission again on every page load. Anything the
       sheet cannot show (unused number, not found, signed out) still goes
       through resolve.php, which knows what to do with it. */
    var sheetKey = '', sheetClosedAt = 0;
    var REOPEN_MS = 2500;   // the sticker is usually still in frame when the sheet closes

    function openJobSheet(route) {
        fetch('job.php?t=' + encodeURIComponent(route.ticket), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok) { leaveTo(route.href); return; }
                sheetKey = route.key;
                JobView.open(data.job);
            })
            .catch(function () { leaveTo(route.href); });
    }

    if (window.JobView) {
        JobView.onClose(function () {
            sheetClosedAt = Date.now();
            start();   // resumes the paused stream — no new permission prompt
        });
    }

    /* A part label opens the part sheet the same way. There is no page to
       fall back to, so a miss shows the value with the reason instead. */
    function openPartSheet(route, text) {
        if (!window.PartView || !window.fetch) { showValue(text); return; }
        fetch('part.php?id=' + encodeURIComponent(route.part), { credentials: 'same-origin' })
            .then(function (r) {
                if (r.status === 401) { leaveTo('../login.php'); return null; }
                return r.json();
            })
            .then(function (data) {
                if (!data) return;
                if (!data.ok) { showValue(text, 'ไม่พบอะไหล่นี้ในระบบ — อาจถูกลบไปแล้ว'); return; }
                sheetKey = route.key;
                PartView.open(data.part);
            })
            .catch(function () { showValue(text, 'โหลดข้อมูลอะไหล่ไม่สำเร็จ — เช็คอินเทอร์เน็ตแล้วสแกนใหม่'); });
    }

    if (window.PartView) {
        PartView.onClose(function () {
            sheetClosedAt = Date.now();
            start();
        });
    }

    /* Requisition done from the part sheet: inventory-requisition.js calls
       this instead of reloading (a reload re-asks camera permission). It
       already showed the success toast; close the sheet → camera resumes. */
    window.onRequisitionDone = function () {
        if (window.PartView && PartView.isOpen()) PartView.close();
    };

    againBtn.addEventListener('click', function () {
        result.hidden = true;
        hint.hidden = false;
        start();
    });

    copyBtn.addEventListener('click', function () {
        var text = valueEl.textContent;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function () {
                copyBtn.textContent = 'คัดลอกแล้ว';
                setTimeout(function () { copyBtn.textContent = 'คัดลอก'; }, 1500);
            });
        }
    });

    retryBtn.addEventListener('click', start);

    /* Free the camera whenever the page goes away — without this the
       indicator light stays on after navigating and iOS keeps the stream
       held until the tab is discarded. */
    window.addEventListener('pagehide', stop);
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            pause();   // app switch: keep the stream if iOS lets us, no new prompt on return
        } else if (result.hidden && !(window.JobView && JobView.isOpen())
                                  && !(window.PartView && PartView.isOpen())) {
            start();   // came back and no result on screen → resume scanning
        }
    });

    start();
})();
