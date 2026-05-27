const fabWrap = document.getElementById('fabWrap');
const fabMain = document.getElementById('fabMain');

// Show after scroll > 100px, hide near footer
window.addEventListener('scroll', () => {
  if (!fabWrap) return;
  const footer = document.querySelector('footer');
  const footerTop = footer ? footer.getBoundingClientRect().top : Infinity;
  const show = window.scrollY > 100 && footerTop > window.innerHeight - 80;
  fabWrap.classList.toggle('show', show);
}, { passive: true });

// Toggle open/close
fabMain?.addEventListener('click', (e) => {
  e.stopPropagation();
  const isOpen = fabWrap.classList.toggle('open');
  fabMain.setAttribute('aria-expanded', isOpen);
});

// Close on outside click
document.addEventListener('click', (e) => {
  if (fabWrap && !fabWrap.contains(e.target)) {
    fabWrap.classList.remove('open');
    fabMain?.setAttribute('aria-expanded', 'false');
  }
});
