// --- dashboard-script.js (Corrected Version) ---
// This script should ONLY contain general admin functions like the sidebar toggle.
// The chart logic is now handled directly in dashboard.php

const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');
const toggle = document.getElementById('menu-toggle');
const closeBtn = document.getElementById('close-sidebar');
const main = document.getElementById('main-content'); // Keep this if you use it for collapsing content

// IMPORTANT: Check if elements exist before adding listeners
if (toggle) {
  toggle.addEventListener('click', function () {
    // This logic might need to match your sidebar's specific implementation
    // For example, toggling a class on the main layout container
    const adminLayout = document.querySelector('.admin-layout');
    if (adminLayout) {
      adminLayout.classList.toggle('sidebar-collapsed');
    }
  });
}

// Example for overlay click if you have one
if (overlay) {
  overlay.addEventListener('click', function () {
    const adminLayout = document.querySelector('.admin-layout');
    if (adminLayout) {
      adminLayout.classList.remove('sidebar-collapsed');
    }
  });
}

// Note: Your actual sidebar toggle logic might be different.
// The key is to REMOVE the Chart.js code from this file.
// If you don't have a menu toggle button with id 'menu-toggle' and just use the hamburger from header.php,
// then the JS for that might be in a different file or should be checked.
// Based on our previous fixes, the code below is what we made robust.
if (document.getElementById('menu-toggle')) {
  document.getElementById('menu-toggle').addEventListener('click', function() {
    // Your menu toggle logic here
  });
}