/* =========================================================
   CMNS Admin — QR scanner
   Path: admin/templates/assets/js/scan.js
   Used by: admin/scan/index.php only.

   Decodes with jsQR because iOS Safari ships no BarcodeDetector, and the
   admin runs as an iOS PWA. Reading the value is all this does — routing
   on the result is deliberately not built yet.
   ========================================================= */
(function () {
    'use strict';

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

    if (!video) return;

    /* Decode off a downscaled copy of the frame. Full-resolution decoding
       burns battery and drops the frame rate on a phone for no accuracy
       gain — 480px on the long edge reads a QR from arm's length fine. */
    var MAX_EDGE = 480;
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
        if (typeof window.jsQR !== 'function') {
            fail('cloud_off', 'โหลดตัวถอดรหัส QR ไม่สำเร็จ — เช็คอินเทอร์เน็ตแล้วลองใหม่');
            return;
        }

        navigator.mediaDevices.getUserMedia({
            audio: false,
            // ideal, not exact: a laptop with only a front camera still works
            video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } }
        }).then(function (s) {
            stream = s;
            video.srcObject = s;
            return video.play();
        }).then(function () {
            cover.hidden = true;
            running = true;
            frame = 0;
            rafId = requestAnimationFrame(tick);
        }).catch(function (err) {
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

    function stop() {
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
        if ((frame++ & 1) === 0) return;
        if (video.readyState !== video.HAVE_ENOUGH_DATA) return;

        var vw = video.videoWidth, vh = video.videoHeight;
        if (!vw || !vh) return;

        var scale = Math.min(1, MAX_EDGE / Math.max(vw, vh));
        var w = Math.round(vw * scale), h = Math.round(vh * scale);
        if (canvas.width !== w || canvas.height !== h) { canvas.width = w; canvas.height = h; }

        ctx.drawImage(video, 0, 0, w, h);
        var img = ctx.getImageData(0, 0, w, h);
        var code = window.jsQR(img.data, w, h, { inversionAttempts: 'dontInvert' });

        if (code && code.data) found(code.data);
    }

    function found(text) {
        stop();
        if (navigator.vibrate) navigator.vibrate(60);
        valueEl.textContent = text;
        result.hidden = false;
        hint.hidden = true;
        cover.hidden = false;
        coverIco.textContent = 'qr_code_2';
        coverMsg.textContent = 'อ่านสำเร็จ';
        retryBtn.hidden = true;
        result.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

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
            stop();
        } else if (result.hidden) {
            start();   // came back and no result on screen → resume scanning
        }
    });

    start();
})();
