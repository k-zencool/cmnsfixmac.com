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
