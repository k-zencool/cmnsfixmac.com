document.addEventListener('DOMContentLoaded', function() {

    // --- 1. Filter Toggles Specific to Index ---
    window.toggleFilterMenuUsed = function(){ 
        var m = document.getElementById('filterMenuUsed'); 
        if(m) m.classList.toggle('show'); 
    };
    window.clearFilterChecksUsed = function(){ 
        document.querySelectorAll('#filterMenuUsed input[type="checkbox"]').forEach(function(el){ el.checked=false; }); 
    };
    
    window.toggleFilterMenuDonor = function(){ 
        var m = document.getElementById('filterMenuDonor'); 
        if(m) m.classList.toggle('show'); 
    };
    window.clearFilterChecksDonor = function(){ 
        document.querySelectorAll('#filterMenuDonor input[type="checkbox"]').forEach(function(el){ el.checked=false; }); 
    };

    // --- 2. Image Preview Logic ---
    (function(){
        var overlay = document.getElementById('imgPreviewOverlay');
        var imgEl = document.getElementById('imgPreview');
        
        function openPreview(src){ 
            if(!overlay||!imgEl) return; 
            imgEl.src = src; 
            overlay.classList.add('show'); 
        }
        function closePreview(){ 
            if(!overlay) return; 
            overlay.classList.remove('show'); 
            if(imgEl) imgEl.src = ''; 
        }
        
        document.addEventListener('click', function(e){
            var btn = e.target.closest ? e.target.closest('.thumb-btn') : null;
            if(!btn) return; 
            var src = btn.getAttribute('data-src'); 
            if(src) openPreview(src);
        });
        
        if(overlay) {
            overlay.addEventListener('click', function(e){ 
                if(e.target===overlay || e.target.classList.contains('imgpv-close')) closePreview(); 
            });
        }
    })();

    // --- 3. Remark Modal Logic ---
    (function(){
        var modal = document.getElementById('remarkModal');
        var textContainer = document.getElementById('remarkFullText');
        if(!modal || !textContainer) return;
        
        document.addEventListener('click', function(e){
            if(e.target.classList.contains('remark-text')){
                var text = e.target.getAttribute('data-remark');
                if(text){ 
                    textContainer.textContent = text; 
                    modal.classList.add('show'); 
                    modal.setAttribute('aria-hidden','false'); 
                }
            }
        });
        
        function close(){ 
            modal.classList.remove('show'); 
            modal.setAttribute('aria-hidden','true'); 
        }
        
        var closeBtn = modal.querySelector('.close-btn');
        if(closeBtn) closeBtn.addEventListener('click', close);
        
        modal.addEventListener('click', function(e) { if(e.target === modal) close(); });
        document.addEventListener('keydown', function(e) { if(e.key==='Escape' && modal.classList.contains('show')) close(); });
    })();

    // --- 4. Pagination & Keyboard Nav ---
    (function(){
        // Per Page Select
        var sel = document.getElementById('ppSelect'); 
        if(sel) {
            sel.addEventListener('change', function(){
                if(window.showGlobalLoader) window.showGlobalLoader();
                var u = new URL(location.href);
                u.searchParams.set('per', this.value);
                u.searchParams.set('page', '1');
                location = u.toString();
            });
        }

        // Arrow Keys
        document.addEventListener('keydown', function(e){
            if(e.altKey || e.metaKey || e.ctrlKey) return;
            if(e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;

            if(e.key === 'ArrowRight') {
                var next = document.querySelector('.page-btn[rel="next"]');
                if(next && !next.classList.contains('is-disabled')) { 
                    if(window.showGlobalLoader) window.showGlobalLoader(); 
                    next.click(); 
                }
            }
            if(e.key === 'ArrowLeft') {
                var prev = document.querySelector('.page-btn[rel="prev"]');
                if(prev && !prev.classList.contains('is-disabled')) { 
                    if(window.showGlobalLoader) window.showGlobalLoader(); 
                    prev.click(); 
                }
            }
        });
    })();

});