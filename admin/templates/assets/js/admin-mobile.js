/* =========================================================
   CMNS Admin — MOBILE CHROME AUTO-HIDE
   Path: admin/templates/assets/js/admin-mobile.js

   Scroll down → the topbar slides away. Scroll up → it comes straight
   back. The tab bar deliberately stays put (it is the navigation), so this
   only ever moves the top bar. Mobile only (<992px); never binds on desktop.

   Follows the navbar convention from CLAUDE.md: the visible/hidden state
   is cached in a boolean and classList is only touched when the state
   actually flips, so a fast scroll does not thrash the DOM.
   ========================================================= */
(function () {
    'use strict';

    var mq = window.matchMedia('(max-width: 991px)');

    var THRESHOLD = 90;   // never hide while still near the top of the page
    var DELTA     = 6;    // ignore sub-pixel / jitter scrolls (iOS rubber-band)

    var hidden   = false; // ← the cached state; the only source of truth
    var lastY    = 0;
    var ticking  = false;
    var bound    = false;

    function apply(next) {
        if (next === hidden) return;          // no change → don't touch the DOM
        hidden = next;
        document.body.classList.toggle('nav-hidden', next);
    }

    function onFrame() {
        ticking = false;

        var y   = window.scrollY || document.documentElement.scrollTop || 0;
        var max = document.documentElement.scrollHeight - window.innerHeight;

        // Page too short to scroll — chrome must never get stuck off-screen.
        if (max <= THRESHOLD) { apply(false); lastY = y; return; }

        var diff = y - lastY;
        if (Math.abs(diff) < DELTA) return;   // keep lastY so small moves accumulate

        if (y <= THRESHOLD) {
            apply(false);                     // at the top: always visible
        } else if (diff > 0) {
            apply(true);                      // scrolling down: get out of the way
        } else {
            apply(false);                     // scrolling up: come back
        }
        lastY = y;
    }

    function onScroll() {
        if (ticking) return;
        ticking = true;
        window.requestAnimationFrame(onFrame);
    }

    function bind() {
        if (bound) return;
        bound = true;
        lastY = window.scrollY || 0;
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    function unbind() {
        if (!bound) return;
        bound = false;
        window.removeEventListener('scroll', onScroll);
        apply(false);                         // leave desktop with the topbar visible
    }

    function sync() { mq.matches ? bind() : unbind(); }

    document.addEventListener('DOMContentLoaded', sync);
    // Rotating a phone or resizing a desktop window crosses the breakpoint.
    mq.addEventListener ? mq.addEventListener('change', sync) : mq.addListener(sync);

    /* A focused input scrolls the page under the iOS keyboard; the chrome
       hiding on top of that reads as a glitch. Force it back. */
    document.addEventListener('focusin', function (e) {
        if (!mq.matches) return;
        var t = e.target;
        if (t && /^(INPUT|SELECT|TEXTAREA)$/.test(t.tagName)) apply(false);
    });
})();

/* =========================================================
   BOTTOM SHEET — DRAG TO DISMISS
   Any element carrying .sheet-on-mobile can be pulled down and thrown
   away, the way a native sheet behaves. Generic on purpose: the sheet
   does not know how its page closes it. On dismissal it fires a
   bubbling `sheetdismiss` event and the page runs its own close
   function; on release below the threshold the sheet springs back and
   nothing is fired.

   Mobile only (<992px) — .sheet-on-mobile is itself a mobile-only
   treatment, so there is nothing to drag on desktop.
   ========================================================= */
(function () {
    'use strict';

    var mq = window.matchMedia('(max-width: 991px)');

    var DISMISS_RATIO = 0.28;   // pulled past ~a quarter of its height → close
    var DISMISS_VEL   = 0.55;   // …or flicked down faster than this (px/ms)
    var RESIST        = 0.28;   // upward drag is rubber-banded, not free

    var sheet = null, startY = 0, lastY = 0, lastT = 0, dy = 0, h = 0,
        vel = 0, dragging = false, scrim = null, scrimRGB = '0,0,0', scrimA = 0.4;

    function overlayOf(el) {
        /* The scrim is whatever fixed parent the sheet sits in; fading it
           with the drag is most of what sells the gesture. */
        var p = el.parentElement;
        while (p && p !== document.body) {
            if (getComputedStyle(p).position === 'fixed') return p;
            p = p.parentElement;
        }
        return null;
    }

    /* A drag may only start where it cannot steal a scroll: nothing between
       the touch and the sheet may be scrolled away from its top. Walking the
       ancestors keeps this generic — the handler knows no page's class names. */
    function canStart(sheetEl, target) {
        if (target.closest('input, select, textarea, button, a')) return false;
        var el = target;
        while (el && el !== sheetEl.parentElement) {
            if (el.scrollTop > 0) return false;
            el = el.parentElement;
        }
        return true;
    }

    /* Dim by repainting the scrim's background, NOT by setting opacity on the
       overlay — the sheet is inside that overlay, so opacity fades the sheet
       itself and the whole panel goes see-through mid-drag. */
    function readScrim(ov) {
        scrim = ov;
        if (!ov) return;
        var m = /rgba?\(([^)]+)\)/.exec(getComputedStyle(ov).backgroundColor || '');
        if (!m) { scrimRGB = '0,0,0'; scrimA = 0.4; return; }
        var p = m[1].split(',').map(function (n) { return parseFloat(n); });
        scrimRGB = p[0] + ',' + p[1] + ',' + p[2];
        scrimA   = p.length > 3 ? p[3] : 1;
    }
    function dimScrim(factor) {
        if (!scrim) return;
        scrim.style.backgroundColor =
            'rgba(' + scrimRGB + ',' + (scrimA * Math.max(0, Math.min(1, factor))).toFixed(3) + ')';
    }
    function clearScrim() {
        if (scrim) { scrim.style.backgroundColor = ''; scrim.style.transition = ''; }
        scrim = null;
    }
    /* Settling is animated; the scrim has to ease with it or the background
       snaps a frame ahead of the sheet. */
    function easeScrim() {
        if (scrim) scrim.style.transition = 'background-color .26s cubic-bezier(.22,1,.36,1)';
    }

    function onStart(e) {
        if (!mq.matches || dragging) return;
        var t = e.target;
        if (!t || !t.closest) return;
        var el = t.closest('.sheet-on-mobile');
        if (!el || !canStart(el, t)) return;

        sheet    = el;
        h        = el.getBoundingClientRect().height || 1;
        startY   = lastY = e.touches[0].clientY;
        lastT    = e.timeStamp;
        dy       = 0;
        vel      = 0;
        dragging = true;
        readScrim(overlayOf(el));
        sheet.classList.remove('is-settling');
        sheet.classList.add('is-dragging');
    }

    function onMove(e) {
        if (!dragging || !sheet) return;
        var y = e.touches[0].clientY;
        dy = y - startY;
        if (dy < 0) dy *= RESIST;               // pulling up goes nowhere much

        /* Only claim the gesture once it is clearly a downward drag —
           otherwise a tap or a horizontal swipe would be swallowed. */
        if (Math.abs(dy) > 4 && e.cancelable) e.preventDefault();

        sheet.style.translate = '0 ' + dy + 'px';
        dimScrim(1 - (dy / h) * 1.1);

        /* Velocity of the last segment only — that is what a flick is. */
        vel   = (y - lastY) / Math.max(1, e.timeStamp - lastT);
        lastY = y;
        lastT = e.timeStamp;
    }

    function onEnd(e) {
        if (!dragging || !sheet) return;
        dragging = false;

        var el = sheet;
        /* A stale flick should not close a sheet the finger then held still. */
        if (e.timeStamp - lastT > 120) vel = 0;
        var go = dy > h * DISMISS_RATIO || (dy > 24 && vel > DISMISS_VEL);

        el.classList.remove('is-dragging');
        el.classList.add('is-settling');
        easeScrim();

        if (go) {
            el.style.translate = '0 100%';
            dimScrim(0);
            /* Let the throw finish before the page tears the sheet down,
               then hand the styles back to CSS. */
            setTimeout(function () {
                el.classList.remove('is-settling');
                el.style.translate = '';
                clearScrim();
                el.dispatchEvent(new CustomEvent('sheetdismiss', { bubbles: true }));
            }, 200);
        } else {
            el.style.translate = '0 0';
            dimScrim(1);
            setTimeout(function () {
                el.classList.remove('is-settling');
                el.style.translate = '';
                clearScrim();
            }, 260);
        }

        sheet = null;
        dy = 0;
    }

    document.addEventListener('touchstart', onStart, { passive: true });
    /* Not passive: a downward drag has to be able to cancel the scroll. */
    document.addEventListener('touchmove',  onMove,  { passive: false });
    document.addEventListener('touchend',   onEnd,   { passive: true });
    document.addEventListener('touchcancel', onEnd,  { passive: true });
})();
