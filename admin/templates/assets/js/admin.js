/* =========================================================
   CMNS Admin — Main Javascript (Pro Edition)
   Path: admin/templates/assets/js/admin.js
   Includes: Loader, Sidebar, Dark Mode, Profile Menu, Toasts, Mobile Fix
   ========================================================= */

document.addEventListener("DOMContentLoaded", function() {
    const loader = document.getElementById('global-loader');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');

    // ------------------------------------------------
    // 1. Global Loader Control
    // ------------------------------------------------
    window.showGlobalLoader = function() { 
        if(loader) {
            loader.style.display = 'flex';
            void loader.offsetWidth; // Force Reflow
            loader.style.opacity = '1'; 
        }
    };
    window.hideGlobalLoader = function() { 
        if(loader) {
            loader.style.opacity = '0';
            setTimeout(() => { loader.style.display = 'none'; }, 200);
        }
    };
    // แก้บั๊ก Safari กด Back แล้ว Loader ค้าง
    window.addEventListener('pageshow', function() { hideGlobalLoader(); });

    // ------------------------------------------------
    // 2. Smart Link Handling (คลิกแล้วหมุน Loader)
    // ------------------------------------------------
    document.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            const target = this.getAttribute('target');
            // เงื่อนไข: ลิงก์ภายใน, ไม่ใช่ JS, ไม่เปิดแท็บใหม่
            if (href && href !== '#' && !href.startsWith('javascript') && target !== '_blank' && !this.dataset.noLoader) {
                // ปิด Sidebar มือถือทันที
                if (sidebar && sidebar.classList.contains('show')) sidebar.classList.remove('show');
                if (overlay && overlay.classList.contains('show')) overlay.classList.remove('show');
                showGlobalLoader();
            }
        });
    });

    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            if (this.checkValidity()) showGlobalLoader();
        });
    });

    // ------------------------------------------------
    // 3. Theme Initialization
    // ------------------------------------------------
    const savedTheme = localStorage.getItem('admin_theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    const themeIcon = document.getElementById('themeIcon');
    if(themeIcon) themeIcon.textContent = savedTheme === 'dark' ? 'light_mode' : 'dark_mode';

    // ------------------------------------------------
    // 4. Auto Active Menu (ไฮไลท์เมนูปัจจุบัน)
    // ------------------------------------------------
    const currentPath = window.location.pathname;
    const curSearch   = new URLSearchParams(window.location.search);
    const navLinks    = document.querySelectorAll('.sidebar-nav a');

    /* Pick the single best-matching link. Siblings can share a pathname and
       differ only by query (?type=, ?group=), so score by path + query match
       and disqualify any link that demands a query the current URL doesn't have. */
    let bestLink = null, bestScore = -1;
    navLinks.forEach(link => {
        const linkUrl = new URL(link.href, window.location.origin);
        const exact   = currentPath === linkUrl.pathname;
        const prefix  = linkUrl.pathname !== '/admin/' && currentPath.startsWith(linkUrl.pathname);
        if (!exact && !prefix) return;

        let score = exact ? 2 : 1;
        let disqualified = false;
        linkUrl.searchParams.forEach((v, k) => {
            if (curSearch.get(k) === v) score += 2;   // matched the right ?type / ?group
            else disqualified = true;                  // link wants a query we're not on
        });
        if (disqualified) return;

        if (score > bestScore) { bestScore = score; bestLink = link; }
    });

    if (bestLink) {
        bestLink.classList.add(bestLink.closest('.submenu-inner') ? 'sub-active' : 'active');

        // กางเมนูแม่ (Parent) ออกมา
        const parentGroup = bestLink.closest('.has-sub');
        if (parentGroup) {
            parentGroup.classList.add('open');
            const toggle = parentGroup.querySelector('.sub-toggle');
            if (toggle) toggle.style.transform = 'rotate(180deg)';
            const parentLink = parentGroup.querySelector('.sub-link');
            if (parentLink) parentLink.classList.add('active-parent');
        }
    }

    // ------------------------------------------------
    // 5. Restore Sidebar State (PC Only)
    // ------------------------------------------------
    const savedState = localStorage.getItem('sidebarState');
    if (savedState === 'collapsed' && window.innerWidth > 991) {
        document.body.classList.add('sidebar-collapsed');
    }
    // sync: โอน class จาก html → body แล้วเอาออกจาก html
    document.documentElement.classList.remove('sidebar-collapsed');

    // ------------------------------------------------
    // 6. ✅ Resize Fix (แก้บั๊กย่อจอเป็นมือถือแล้ว Margin ค้าง)
    // ------------------------------------------------
    window.addEventListener('resize', function() {
        if (window.innerWidth <= 991) {
            if (document.body.classList.contains('sidebar-collapsed')) {
                document.body.classList.remove('sidebar-collapsed');
            }
        }
    });

    // ------------------------------------------------
    // 7. ✅ User Dropdown Logic (คลิกข้างนอกเพื่อปิด)
    // ------------------------------------------------
    document.addEventListener('click', function(e) {
        const wrapper = document.querySelector('.user-dropdown-wrapper');
        if (wrapper && wrapper.classList.contains('active')) {
            if (!wrapper.contains(e.target)) {
                wrapper.classList.remove('active');
            }
        }
    });

    // ------------------------------------------------
    // 8. ✅ Flash Message Handler (รับค่าจาก PHP มาเด้ง Toast)
    // ------------------------------------------------
    if (typeof flashData !== 'undefined') {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';

        const t = isDark
            ? { bg: '#1e1e1e', color: '#e5e5e5', border: '#3a3a3a', shadow: '0 8px 32px rgba(0,0,0,.55)', bar: 'rgba(255,255,255,.12)' }
            : { bg: '#ffffff', color: '#0f172a', border: '#e2e8f0', shadow: '0 8px 28px rgba(0,0,0,.10)', bar: 'rgba(0,0,0,.07)' };

        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true,
            background: t.bg,
            color: t.color,
            didOpen: (toast) => {
                toast.style.border        = `1px solid ${t.border}`;
                toast.style.borderRadius  = '14px';
                toast.style.boxShadow     = t.shadow;
                toast.style.fontFamily    = "'Sarabun', sans-serif";
                toast.style.fontSize      = '14px';
                toast.style.fontWeight    = '600';
                toast.style.padding       = '10px 16px';

                // push below navbar (--header-h = 64px)
                const container = toast.closest('.swal2-container');
                if (container) container.style.paddingTop = '74px';

                const bar = toast.querySelector('.swal2-timer-progress-bar');
                if (bar) bar.style.background = t.bar;

                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });

        if (flashData.success) {
            Toast.fire({ icon: 'success', title: flashData.success, iconColor: '#10b981' });
        }
        if (flashData.error) {
            Toast.fire({ icon: 'error', title: flashData.error, iconColor: '#ef4444' });
        }
    }
});


/* =========================================================
   GLOBAL FUNCTIONS (Window Scope) - เรียกใช้ผ่าน onclick=""
   ========================================================= */

/**
 * 🌗 Toggle Theme
 */
window.toggleTheme = function() {
    const html = document.documentElement;
    const newTheme = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    
    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('admin_theme', newTheme);
    
    const icon = document.getElementById('themeIcon');
    if(icon) icon.textContent = newTheme === 'dark' ? 'light_mode' : 'dark_mode';
};

/**
 * 📂 Toggle Submenu
 */
window.toggleSubmenu = function(el) {
    const parent = el.closest('.has-sub');
    const toggle = parent.querySelector('.sub-toggle');

    if (document.body.classList.contains('sidebar-collapsed')) {
        // ถ้าพับอยู่ ให้กางออกก่อนแล้วค่อยเปิดเมนูย่อย
        window.toggleSidebarCollapse();
        setTimeout(() => {
            parent.classList.toggle('open');
            rotateIcon(parent, toggle);
        }, 300);
    } else {
        parent.classList.toggle('open');
        rotateIcon(parent, toggle);
    }
};

function rotateIcon(parent, toggle) {
    if(!toggle) return;
    toggle.style.transform = parent.classList.contains('open') ? 'rotate(180deg)' : 'rotate(0deg)';
}

/**
 * 📱 Toggle Sidebar Mobile (Hamburger)
 */
window.toggleSidebarMobile = function() {
    document.getElementById('sidebar')?.classList.toggle('show');
    document.getElementById('overlay')?.classList.toggle('show');
};

/**
 * 💻 Toggle Sidebar Desktop (Collapse)
 */
window.toggleSidebarCollapse = function() {
    document.body.classList.toggle('sidebar-collapsed');
    
    const isCollapsed = document.body.classList.contains('sidebar-collapsed');
    localStorage.setItem('sidebarState', isCollapsed ? 'collapsed' : 'expanded');
    
    // พับเมนูย่อยเก็บให้หมดเมือย่อ
    if (isCollapsed) {
        document.querySelectorAll('.has-sub.open').forEach(el => {
            el.classList.remove('open');
            const t = el.querySelector('.sub-toggle');
            if(t) t.style.transform = 'rotate(0deg)';
        });
    }
};

/**
 * 👤 ✅ Toggle User Menu (Dropdown Profile)
 */
window.toggleUserMenu = function(e) {
    if(e) e.stopPropagation(); // กันไม่ให้ Event ไปชนกับ click outside
    const wrapper = document.querySelector('.user-dropdown-wrapper');
    if(wrapper) wrapper.classList.toggle('active');
};