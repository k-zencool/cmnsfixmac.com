<?php
/* Shared PWA <head> block for the admin app — installable "Add to Home Screen"
   launch (fullscreen, no URL bar). Included by header_admin.php and login.php
   so the manifest/meta/icons live in ONE place. */
?>
    <!-- PWA: installable admin app (Add to Home Screen → fullscreen, no URL bar) -->
    <link rel="manifest" href="/admin/manifest.webmanifest?v=6">
    <meta name="theme-color" content="#0a0a0a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="CMNS Admin">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/img/pwa/icon-180-v2.png">
    <script>
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
          navigator.serviceWorker.register('/admin/sw.js', { scope: '/admin/' }).catch(function () {});
        });
      }
    </script>
