document.addEventListener('DOMContentLoaded', function() {

    // --- 1. Image Upload Logic ---
    (function(){
        var box = document.getElementById('imgPreviewWrap');
        var fileInput = document.getElementById('image');
        var removeBtn = document.getElementById('imageRemoveBtn');
        var txt = document.getElementById('imgPreviewText');
        var rmField = document.getElementById('remove_image');
        
        if (!box || !fileInput || !rmField) return;

        // Trigger Click
        box.addEventListener('click', function(e){ 
            if (e.target && e.target.closest('#imageRemoveBtn')) return; 
            fileInput.click(); 
        });

        // File Change
        fileInput.addEventListener('change', function(){
            if (!this.files || !this.files[0]) return;
            
            var f = this.files[0];
            // Validate Type & Size
            if (!/\.(jpe?g|png|webp)$/i.test(f.name)) { 
                alert('รองรับเฉพาะ JPG/PNG/WebP'); 
                this.value=''; 
                return; 
            }
            if (f.size > 5*1024*1024) { 
                alert('ไฟล์ใหญ่เกิน 5MB'); 
                this.value=''; 
                return; 
            }

            // Preview
            rmField.value = '0';
            var url = URL.createObjectURL(f);
            var imgEl = document.getElementById('imgPreview');
            
            if (!imgEl){
                imgEl = document.createElement('img'); 
                imgEl.id = 'imgPreview';
                // Reset styles handled by CSS class, but ensure object-fit here if needed
                box.appendChild(imgEl);
            }
            imgEl.src = url;
            
            if (txt) txt.style.display='none';
            if (removeBtn) removeBtn.style.display='flex'; // Flex to center X
        });

        // Remove Image
        if (removeBtn){
            removeBtn.addEventListener('click', function(e){
                e.stopPropagation(); // Don't trigger box click
                if(confirm('ต้องการลบรูปภาพนี้หรือไม่?')) {
                    rmField.value = '1';
                    var imgEl = document.getElementById('imgPreview');
                    if (imgEl) imgEl.remove();
                    if (txt) txt.style.display='';
                    fileInput.value=''; 
                    removeBtn.style.display='none';
                }
            });
        }
    })();

    // --- 2. Location & Quantity Logic ---
    (function(){
        var locInput = document.getElementById('location');
        var curQtyEl = document.getElementById('curQty');
        var desEl = document.getElementById('desired_qty');
        
        if(!locInput || !curQtyEl) return;

        // Parse location data from data-attribute (Pass from PHP)
        var locMap = JSON.parse(locInput.dataset.locations || '{}');

        function refreshQty(){
            var L = (locInput.value || 'main').trim();
            var q = (typeof locMap[L] !== 'undefined') ? locMap[L] : 0;
            curQtyEl.value = q;
            
            // Only update desired qty if it wasn't manually changed to something else
            // Or simple logic: default to current qty when location changes
            // if (desEl) desEl.value = q; 
        }

        locInput.addEventListener('input', refreshQty);
        // Initial run
        refreshQty();
    })();

    // --- 3. Form Validation (Name vs Number) ---
    (function(){
        var nameEl = document.getElementById('part_name');
        var numEl = document.getElementById('part_number');
        if(!nameEl || !numEl) return;

        function norm(v){ 
            v = (v||'').trim(); 
            return (v===''||v==='-') ? '' : v.toLowerCase(); 
        }

        function validate(){
            var same = (nameEl.value.trim().toLowerCase() === norm(numEl.value));
            var msg = 'ชื่ออะไหล่กับเลขอะไหล่ห้ามเหมือนกัน';
            
            if(same && nameEl.value.trim() !== '') { 
                nameEl.setCustomValidity(msg); 
                numEl.setCustomValidity(msg); 
            } else { 
                nameEl.setCustomValidity(''); 
                numEl.setCustomValidity(''); 
            }
        }

        nameEl.addEventListener('input', validate);
        numEl.addEventListener('input', validate);
        // Bind to form submit to trigger browser validation UI
        var form = document.getElementById('mainForm');
        if(form) form.addEventListener('submit', validate);
    })();

    // --- 4. Delete Confirmation ---
    window.confirmDelete = function(code) {
        if(!code) return false;
        if(!confirm('ยืนยันลบอะไหล่ ' + code + ' ?\n(ต้องมียอดรวมทุกที่เก็บ = 0 จึงจะลบได้)')) return false;
        
        var f = document.getElementById('mainForm');
        var actField = document.getElementById('actionField');
        var delField = document.getElementById('del_codeField');
        
        if(f && actField && delField) {
            actField.value = 'delete';
            delField.value = code;
            f.submit();
        }
        return false;
    };

});