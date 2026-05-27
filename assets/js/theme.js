/* Dark-mode toggle — runs before first paint to prevent flash */
(function () {
  var stored = localStorage.getItem('cmns_theme');
  var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  var theme = stored || (prefersDark ? 'dark' : 'light');
  document.documentElement.setAttribute('data-theme', theme);

  function applyTheme(t) {
    document.documentElement.setAttribute('data-theme', t);
    localStorage.setItem('cmns_theme', t);
    document.querySelectorAll('.theme-toggle-icon').forEach(function (el) {
      el.textContent = t === 'dark' ? 'light_mode' : 'dark_mode';
    });
  }

  window.toggleTheme = function () {
    var current = document.documentElement.getAttribute('data-theme');
    applyTheme(current === 'dark' ? 'light' : 'dark');
  };

  /* Sync icon after DOM ready */
  document.addEventListener('DOMContentLoaded', function () {
    var t = document.documentElement.getAttribute('data-theme');
    document.querySelectorAll('.theme-toggle-icon').forEach(function (el) {
      el.textContent = t === 'dark' ? 'light_mode' : 'dark_mode';
    });
  });
})();
