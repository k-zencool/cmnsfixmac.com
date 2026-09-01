/* =========================================================
   CMNS Admin — Pull to Refresh
   Path: admin/templates/assets/js/pull-refresh.js

   ใช้ร่วมกันระหว่าง footer_admin.php (ทุกหน้า admin) กับ login.php
   ทั้งสองหน้าต้องมี <div id="pull-refresh"><span class="ptr-spinner"></span></div>
   ส่วนหน้าตาแยกกันอยู่ใน admin.css กับ <style> ของ login.php

   ทำเฉพาะตอนรันเป็น PWA (แอดหน้าจอโฮม) — browser ปกติมี pull-to-refresh
   ของตัวเองอยู่แล้ว ถ้าใส่ทับจะเด้งสองชั้น
   ========================================================= */
(function () {
    'use strict';

    var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches)
                  || navigator.standalone === true;
    if (!standalone) return;

    document.addEventListener('DOMContentLoaded', function () {
        var ptr = document.getElementById('pull-refresh');
        if (!ptr) return;

        var TRIGGER = 72;    // ดึง (หลังหน่วงแล้ว) เกินนี้ = ติด
        var MAX     = 120;   // ยืดได้ไกลสุดหลังหน่วง
        var REST    = -52;   // ตำแหน่งซ่อนเหนือขอบจอ
        var SPRING  = 'cubic-bezier(.22,1,.36,1)';

        var startY = 0, pulled = 0, frame = 0;
        var tracking = false, refreshing = false, armed = false;

        /* หน่วงแบบยางยืด — ยิ่งดึงยิ่งฝืด แล้วค่อยๆ ตันที่ MAX
           ดีกว่าคูณค่าคงที่ ซึ่งลากแล้วรู้สึกแข็งและยืดได้ไม่จบ */
        function resist(d) {
            return MAX * (1 - Math.exp(-d / (MAX * 1.15)));
        }

        /* เขียน style ครั้งเดียวต่อเฟรม ไม่ใช่ทุก touchmove (กันกระตุก) */
        function schedule() {
            if (frame) return;
            frame = requestAnimationFrame(paint);
        }

        function paint() {
            frame = 0;
            var p = Math.min(pulled / TRIGGER, 1);
            ptr.style.transform = 'translate3d(-50%,' + (REST + pulled) + 'px,0)'
                                + ' rotate(' + (pulled * 2.6) + 'deg)'
                                + ' scale(' + (0.55 + 0.45 * p) + ')';
            ptr.style.opacity = p;

            var ready = pulled >= TRIGGER;
            if (ready !== armed) {
                armed = ready;
                ptr.classList.toggle('ptr-ready', ready);
            }
        }

        function park(animate) {
            if (frame) { cancelAnimationFrame(frame); frame = 0; }
            ptr.style.transition = animate ? 'transform .34s ' + SPRING + ', opacity .24s ease' : '';
            ptr.style.transform  = 'translate3d(-50%,' + REST + 'px,0) scale(.55)';
            ptr.style.opacity    = '0';
            ptr.classList.remove('ptr-ready');
            armed = false;
            if (animate) {
                setTimeout(function () { ptr.style.willChange = ''; }, 360);
            } else {
                ptr.style.willChange = '';
            }
        }

        document.addEventListener('touchstart', function (e) {
            if (refreshing || e.touches.length !== 1) return;
            if (window.scrollY > 0) return;                            // ต้องอยู่บนสุดของหน้า
            var sidebar = document.getElementById('sidebar');
            if (sidebar && sidebar.classList.contains('show')) return;  // เมนูมือถือเปิดอยู่ ไม่ยุ่ง

            startY = e.touches[0].clientY;
            pulled = 0;
            tracking = true;
            ptr.style.transition = '';        // ตอนลากต้องตามนิ้วทันที ห้ามมี transition
            ptr.style.willChange = 'transform, opacity';
        }, { passive: true });

        document.addEventListener('touchmove', function (e) {
            if (!tracking || refreshing) return;

            var delta = e.touches[0].clientY - startY;
            // ปัดขึ้น หรือหน้าเลื่อนไปแล้ว = เลิกจับ ปล่อยให้ scroll ตามปกติ
            if (delta <= 0 || window.scrollY > 0) {
                tracking = false;
                park(true);
                return;
            }
            if (e.cancelable) e.preventDefault();   // กัน rubber-band ของ iOS มาสู้กับ transform
            pulled = resist(delta);
            schedule();
        }, { passive: false });

        function release() {
            if (!tracking || refreshing) return;
            tracking = false;

            if (pulled < TRIGGER) { park(true); return; }

            refreshing = true;
            if (frame) { cancelAnimationFrame(frame); frame = 0; }
            ptr.classList.remove('ptr-ready');
            ptr.classList.add('ptr-loading');
            ptr.style.transition = 'transform .3s ' + SPRING;
            ptr.style.transform  = 'translate3d(-50%,22px,0) scale(1)';
            ptr.style.opacity    = '1';
            // หน่วงให้สปินเนอร์เข้าที่ก่อน จะได้ไม่เหมือนจอกระพริบเฉยๆ
            setTimeout(function () { location.reload(); }, 220);
        }

        document.addEventListener('touchend', release, { passive: true });
        document.addEventListener('touchcancel', function () {
            if (!tracking || refreshing) return;
            tracking = false;
            park(true);
        }, { passive: true });
    });
})();
