document.addEventListener('DOMContentLoaded', function() {
    
    // --- 1. Global Loader ---
    window.showGlobalLoader = function() { 
        var loader = document.getElementById('global-loader');
        if(loader) loader.style.display = 'flex'; 
    };

    // Auto-attach loader to standard forms and pagination
    document.querySelectorAll('.search-form-bind').forEach(function(form) { 
        form.addEventListener('submit', function() { showGlobalLoader(); }); 
    });
    
    document.querySelectorAll('.page-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) { 
            if(!this.classList.contains('is-disabled') && this.getAttribute('href') !== '#') {
                showGlobalLoader(); 
            }
        });
    });

    // --- 2. Clipboard Copy Helper ---
    window.copyTag = function(text, btn) {
        if(!text) return;
        navigator.clipboard.writeText(text).then(function() {
            var originalHtml = btn.innerHTML;
            btn.innerHTML = '<svg class="icon-svg" style="color:#10b981;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>';
            setTimeout(function() { btn.innerHTML = originalHtml; }, 2000);
        }).catch(function(err) { console.error('Failed to copy', err); });
    };

    // --- 3. Toggle Menu Helper ---
    window.toggleMenu = function(id){ 
        var m = document.getElementById(id); 
        if(m) m.classList.toggle('show'); 
    };
    
    // Clear common inputs
    window.clearMenu = function(id){ 
        var root = document.getElementById(id); 
        if(!root) return; 
        root.querySelectorAll('input[type="checkbox"]').forEach(function(el){ el.checked=false; }); 
        root.querySelectorAll('select').forEach(function(el){ el.selectedIndex=0; });
        root.querySelectorAll('input[type="date"]').forEach(function(el){ el.value=''; });
    };

});