<?php
$stmt_cats = $pdo->query("SELECT id, name, parent_id FROM parts_categories ORDER BY name ASC");
$all_categories_raw = $stmt_cats->fetchAll(PDO::FETCH_ASSOC);
$main_cats = []; $sub_cats = [];
foreach($all_categories_raw as $c) {
    if(empty($c['parent_id'])) $main_cats[] = $c;
    else $sub_cats[] = $c;
}

$stmt_items = $pdo->query("SELECT id, name, sku, type, category_id FROM inventory ORDER BY name ASC");
$all_inventory_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

$stmt_mach = $pdo->query("SELECT id, name, asset_tag FROM inventory WHERE type = 'machine' ORDER BY id DESC");
$machine_list = $stmt_mach->fetchAll(PDO::FETCH_ASSOC);

$auto_type = isset($current_type) && $current_type !== 'all' ? $current_type : 'new';

// ── Category context (มาจาก view.php) ──
$modal_cat_id      = isset($category_id) ? (int)$category_id : 0;
$modal_cat_name    = isset($category)    ? ($category['name'] ?? '') : '';
$modal_cat_icon    = isset($category)    ? ($category['icon'] ?? 'folder') : 'folder';

// หา parent ของ category ปัจจุบัน (สำหรับ pre-select main_cat / sub_cat)
$modal_main_cat_id = 0;
$modal_sub_cat_id  = 0;
if ($modal_cat_id) {
    foreach ($all_categories_raw as $c) {
        if ($c['id'] == $modal_cat_id) {
            if (!$c['parent_id']) {
                $modal_main_cat_id = $modal_cat_id; // is a root cat
            } else {
                $modal_main_cat_id = (int)$c['parent_id'];
                $modal_sub_cat_id  = $modal_cat_id;
            }
            break;
        }
    }
}

// Items ในหมวดปัจจุบัน (สำหรับ quick-pick)
$cat_items = [];
if ($modal_cat_id) {
    // รวม id ของ cat นี้และลูก
    $cat_ids = [$modal_cat_id];
    foreach ($all_categories_raw as $c) {
        if ($c['parent_id'] == $modal_cat_id) $cat_ids[] = $c['id'];
    }
    $cat_items = array_values(array_filter($all_inventory_items, fn($i) => in_array($i['category_id'], $cat_ids)));
}
?>

<div id="modal-add" class="cmns-modal">
    <div class="modal-content" style="max-width: 850px; padding: 30px;">
        
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 20px; margin-bottom: 25px;">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 10px; color: var(--text-main); font-weight: 800; font-size: 20px;">
                <span class="material-symbols-rounded" style="color: var(--primary); font-size: 28px;">add_circle</span>
                นำเข้าสินค้าเข้าคลัง
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeAddModal()"><span class="material-symbols-rounded">close</span></button>
        </div>

        <form id="form-add-item" action="process_add.php" method="POST" enctype="multipart/form-data">
            
            <div style="display: flex; gap: 10px; margin-bottom: 30px;">
                <label class="mode-btn active" id="btn-mode-new" style="flex: 1; text-align: center; padding: 12px; border-radius: 12px; cursor: pointer; font-weight: 700; transition: 0.2s; border: 1px solid transparent;">
                    <input type="radio" name="add_mode" value="new" checked hidden onchange="toggleAddMode('new')">
                    <span class="material-symbols-rounded" style="vertical-align: middle; font-size: 20px; margin-right: 6px;">fiber_new</span> สร้างโปรไฟล์สินค้าใหม่
                </label>
                <label class="mode-btn" id="btn-mode-exist" style="flex: 1; text-align: center; padding: 12px; border-radius: 12px; cursor: pointer; font-weight: 700; transition: 0.2s; border: 1px solid transparent;">
                    <input type="radio" name="add_mode" value="existing" hidden onchange="toggleAddMode('existing')">
                    <span class="material-symbols-rounded" style="vertical-align: middle; font-size: 20px; margin-right: 6px;">move_to_inbox</span> เติมสต็อกสินค้าเดิม
                </label>
            </div>

            <div id="section-existing-item" style="display:none; min-height:420px; margin-bottom:10px; background:var(--bg-surface-alt); border:1px dashed var(--border); border-radius:16px; padding:40px; flex-direction:column; justify-content:center; align-items:center;">
                <?php if ($modal_cat_id && !empty($cat_items)): ?>
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                        <span class="material-symbols-rounded" style="font-size:28px; color:var(--primary);"><?= htmlspecialchars($modal_cat_icon) ?></span>
                        <h3 style="margin:0; color:var(--text-main);">เติมสต็อก — <?= htmlspecialchars($modal_cat_name) ?></h3>
                    </div>
                    <p style="color:var(--text-muted); font-size:13px; margin:0 0 20px;">เลือกสินค้าด้านล่าง หรือค้นหาจากทั้งระบบ</p>
                <?php else: ?>
                    <span class="material-symbols-rounded" style="font-size:64px; color:var(--primary); margin-bottom:12px; opacity:.25;">inventory_2</span>
                    <h3 style="margin:0 0 6px; color:var(--text-main);">เติมสต็อกสินค้าที่มีอยู่</h3>
                    <p style="color:var(--text-muted); font-size:13px; margin:0 0 24px;">ค้นหาด้วยชื่อ, SKU หรือ Part No.</p>
                <?php endif; ?>

                <div style="width:100%; max-width:560px;">

                    <?php if ($modal_cat_id && !empty($cat_items)): ?>
                    <!-- Quick-pick dropdown -->
                    <div style="position:relative; margin-bottom:12px;" id="qp-wrap">
                        <button type="button" id="qp-trigger"
                                onclick="toggleQP()"
                                style="width:100%; display:flex; align-items:center; justify-content:space-between; gap:10px; padding:12px 16px; border:1.5px solid var(--primary); border-radius:12px; background:var(--bg-surface); cursor:pointer; font-family:inherit; font-size:14px; color:var(--text-main); transition:background .15s;">
                            <span style="display:flex; align-items:center; gap:8px;">
                                <span class="material-symbols-rounded" style="font-size:18px; color:var(--primary);"><?= htmlspecialchars($modal_cat_icon) ?></span>
                                <span style="font-weight:600;">เลือกจาก <?= htmlspecialchars($modal_cat_name) ?></span>
                                <span style="font-size:11px; background:var(--bg-surface-alt); border:1px solid var(--border); border-radius:20px; padding:1px 8px; color:var(--text-muted);"><?= count($cat_items) ?> รายการ</span>
                            </span>
                            <span id="qp-chevron" class="material-symbols-rounded" style="font-size:20px; color:var(--text-muted); transition:transform .2s;">expand_more</span>
                        </button>

                        <div id="qp-dropdown"
                             style="display:none; position:absolute; top:calc(100% + 6px); left:0; right:0; background:var(--bg-surface); border:1.5px solid var(--border); border-radius:12px; box-shadow:0 8px 24px rgba(0,0,0,.15); z-index:300; overflow:hidden;">
                            <div id="qp-list" style="max-height:240px; overflow-y:auto;"></div>
                        </div>
                    </div>

                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px;">
                        <div style="flex:1; height:1px; background:var(--border);"></div>
                        <span style="font-size:11px; font-weight:600; color:var(--text-muted);">หรือค้นหาจากทั้งระบบ</span>
                        <div style="flex:1; height:1px; background:var(--border);"></div>
                    </div>

                    <script>
                    (function(){
                        const _catItems = <?= json_encode(array_values($cat_items), JSON_UNESCAPED_UNICODE) ?>;
                        const typeColor = {new:'#10b981',used:'#f59e0b',machine:'#8b5cf6',sale:'#ef4444'};
                        const list = document.getElementById('qp-list');
                        if (!list) return;

                        _catItems.forEach(function(item){
                            const tc = typeColor[item.type] || '#888';
                            const row = document.createElement('div');
                            row.className = 'qp-row';
                            row.dataset.id   = item.id;
                            row.dataset.name = item.name;
                            row.dataset.sku  = item.sku || '';
                            row.dataset.type = item.type || '';
                            row.innerHTML =
                                `<span class="material-symbols-rounded" style="font-size:16px;color:${tc};flex-shrink:0;">inventory_2</span>`+
                                `<span style="flex:1;min-width:0;">`+
                                    `<span style="font-weight:600;font-size:13px;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${_e(item.name)}</span>`+
                                    (item.sku?`<span style="font-size:11px;color:var(--text-muted);">${_e(item.sku)}</span>`:'')+
                                `</span>`+
                                `<span style="font-size:10px;font-weight:800;padding:2px 7px;border-radius:5px;background:${tc}22;color:${tc};border:1px solid ${tc}44;flex-shrink:0;">${(item.type||'').toUpperCase()}</span>`;
                            list.appendChild(row);
                        });

                        list.addEventListener('click', function(e){
                            const row = e.target.closest('.qp-row');
                            if (!row) return;
                            selectExistItem(+row.dataset.id, row.dataset.name, row.dataset.sku, row.dataset.type);
                            closeQP();
                        });

                        function _e(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
                    })();

                    function toggleQP(){
                        const dd = document.getElementById('qp-dropdown');
                        const ch = document.getElementById('qp-chevron');
                        const open = dd.style.display === 'block';
                        dd.style.display = open ? 'none' : 'block';
                        ch.style.transform = open ? '' : 'rotate(180deg)';
                    }
                    function closeQP(){
                        const dd = document.getElementById('qp-dropdown');
                        const ch = document.getElementById('qp-chevron');
                        if (dd) dd.style.display = 'none';
                        if (ch) ch.style.transform = '';
                    }
                    document.addEventListener('click', function(e){
                        if (!e.target.closest('#qp-wrap')) closeQP();
                    });
                    </script>
                    <style>
                    .qp-row { display:flex; align-items:center; gap:10px; padding:10px 16px; cursor:pointer; border-bottom:1px solid var(--border); transition:background .1s; }
                    .qp-row:last-child { border-bottom:none; }
                    .qp-row:hover { background:var(--bg-surface-alt); }
                    </style>
                    <?php endif; ?>

                    <!-- Search box -->
                    <div style="position:relative; margin-bottom:12px;">
                        <span class="material-symbols-rounded" style="position:absolute; left:14px; top:50%; transform:translateY(-50%); font-size:20px; color:var(--text-muted); pointer-events:none;">search</span>
                        <input type="text" id="exist-search-input"
                               placeholder="ค้นหาชื่อสินค้า, SKU, Part No..."
                               autocomplete="off"
                               style="width:100%; padding:13px 14px 13px 44px; border:1.5px solid var(--primary); border-radius:12px; background:var(--bg-surface); color:var(--text-main); font-size:14px; font-family:inherit; outline:none; box-shadow:0 0 0 3px rgba(37,99,235,.1);"
                               oninput="filterExistItems(this.value)">
                        <span id="exist-search-clear" onclick="clearExistSearch()"
                              style="display:none; position:absolute; right:12px; top:50%; transform:translateY(-50%); cursor:pointer; color:var(--text-muted); font-size:18px; line-height:1;">✕</span>
                    </div>

                    <!-- Results dropdown -->
                    <div id="exist-results"
                         style="background:var(--bg-surface); border:1.5px solid var(--border); border-radius:12px; max-height:260px; overflow-y:auto; display:none; box-shadow:0 8px 24px rgba(0,0,0,.1);">
                    </div>

                    <!-- Selected item card -->
                    <div id="exist-selected-card" style="display:none; margin-top:12px; background:var(--bg-surface); border:1.5px solid #10b981; border-radius:12px; padding:14px 16px;">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                            <div>
                                <div style="font-weight:700; font-size:14px; color:var(--text-main);" id="exist-sel-name"></div>
                                <div style="font-size:12px; color:var(--text-muted); margin-top:4px; display:flex; gap:12px;">
                                    <span>SKU: <code id="exist-sel-sku" style="background:var(--bg-surface-alt); padding:1px 5px; border-radius:4px;"></code></span>
                                    <span id="exist-sel-type-wrap">ประเภท: <b id="exist-sel-type"></b></span>
                                </div>
                            </div>
                            <button type="button" onclick="clearExistSelection()"
                                    style="background:none; border:none; cursor:pointer; color:var(--text-muted); font-size:18px; padding:0 4px; line-height:1;">✕</button>
                        </div>
                    </div>

                    <input type="hidden" name="existing_item_id" id="exist-item-id-hidden" value="">
                </div>
            </div>

            <?php
            $exist_items_json = json_encode(array_map(fn($i) => [
                'id'   => $i['id'],
                'name' => $i['name'],
                'sku'  => $i['sku'] ?: '',
                'type' => $i['type'] ?? '',
            ], $all_inventory_items), JSON_UNESCAPED_UNICODE);
            ?>
            <script>
            const _existItems = <?= $exist_items_json ?>;
            let _existTimer = null;

            function filterExistItems(q) {
                const clear = document.getElementById('exist-search-clear');
                const results = document.getElementById('exist-results');
                clear.style.display = q ? 'block' : 'none';

                if (!q.trim()) { results.style.display = 'none'; return; }

                const kw = q.toLowerCase();
                const matched = _existItems.filter(i =>
                    i.name.toLowerCase().includes(kw) || i.sku.toLowerCase().includes(kw)
                ).slice(0, 50);

                if (!matched.length) {
                    results.innerHTML = `<div style="padding:16px; font-size:13px; color:var(--text-muted); text-align:center;">ไม่พบสินค้าที่ตรงกัน</div>`;
                } else {
                    const typeColor = { new:'#10b981', used:'#f59e0b', machine:'#8b5cf6', sale:'#ef4444' };
                    results.innerHTML = matched.map(i => `
                        <div class="exist-result-row"
                             onclick="selectExistItem(${i.id}, ${JSON.stringify(i.name)}, ${JSON.stringify(i.sku)}, ${JSON.stringify(i.type)})"
                             style="padding:11px 16px; cursor:pointer; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:12px; transition:background .12s;">
                            <div style="flex:1; min-width:0;">
                                <div style="font-weight:600; font-size:13px; color:var(--text-main); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escH(i.name)}</div>
                                <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">
                                    ${i.sku ? `<code style="background:var(--bg-surface-alt); padding:1px 5px; border-radius:4px;">${escH(i.sku)}</code>` : '<span style="opacity:.5;">ไม่มี SKU</span>'}
                                </div>
                            </div>
                            <span style="font-size:10px; font-weight:800; padding:2px 8px; border-radius:6px; background:${typeColor[i.type] || 'var(--text-muted)'}22; color:${typeColor[i.type] || 'var(--text-muted)'}; border:1px solid ${typeColor[i.type] || 'var(--border)'}44; white-space:nowrap;">${(i.type||'').toUpperCase()}</span>
                        </div>
                    `).join('');
                    // hover effect
                    results.querySelectorAll('.exist-result-row').forEach(r => {
                        r.addEventListener('mouseenter', () => r.style.background = 'var(--bg-surface-alt)');
                        r.addEventListener('mouseleave', () => r.style.background = '');
                    });
                }
                results.style.display = 'block';
            }

            function selectExistItem(id, name, sku, type) {
                document.getElementById('exist-item-id-hidden').value = id;
                document.getElementById('exist-sel-name').textContent = name;
                document.getElementById('exist-sel-sku').textContent  = sku || '—';
                document.getElementById('exist-sel-type').textContent = (type||'').toUpperCase();
                document.getElementById('exist-selected-card').style.display = 'block';
                document.getElementById('exist-results').style.display = 'none';
                document.getElementById('exist-search-input').value   = '';
                document.getElementById('exist-search-clear').style.display = 'none';
            }

            function clearExistSelection() {
                document.getElementById('exist-item-id-hidden').value = '';
                document.getElementById('exist-selected-card').style.display = 'none';
            }

            function clearExistSearch() {
                document.getElementById('exist-search-input').value = '';
                document.getElementById('exist-search-clear').style.display = 'none';
                document.getElementById('exist-results').style.display = 'none';
            }

            function escH(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('#section-existing-item')) {
                    const r = document.getElementById('exist-results');
                    if (r) r.style.display = 'none';
                }
            });
            </script>

            <div id="section-new-item" style="display: flex; gap: 30px; flex-wrap: wrap; margin-bottom: 10px; align-items: stretch; min-height: 420px;">
                <div style="flex: 1; min-width: 200px; max-width: 220px;">
                    <label class="cmns-label">PRODUCT IMAGE</label>
                    <div style="width: 100%; aspect-ratio: 1; border: 2px dashed var(--border); border-radius: 16px; display: flex; flex-direction: column; align-items: center; justify-content: center; background: transparent; position: relative; overflow: hidden; cursor: pointer; transition: 0.2s;" onclick="document.getElementById('add-image').click()">
                        <div id="add-img-placeholder" style="display: flex; flex-direction: column; align-items: center; color: var(--text-muted); opacity: 0.5;">
                            <span class="material-symbols-rounded" style="font-size: 40px; margin-bottom: 8px;">add_photo_alternate</span>
                            <span style="font-size: 11px; font-weight: 700;">คลิกอัปโหลด</span>
                        </div>
                        <img id="add-img-preview" src="" style="width: 100%; height: 100%; object-fit: contain; display: none; position: absolute; top:0; left:0; background: var(--bg-surface-alt);">
                    </div>
                    <input type="file" name="image" id="add-image" accept="image/*" hidden onchange="previewAddImage(this)">
                </div>

                <div style="flex: 2; min-width: 300px;">
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label class="cmns-label">ชื่อสินค้า <span style="color:red">*</span></label>
                            <input type="text" name="name" id="input-name" class="cmns-input" placeholder="กรอกชื่อสินค้า..." required>
                        </div>
                        <div>
                            <label class="cmns-label">รหัส SKU</label>
                            <input type="text" name="sku" class="cmns-input" placeholder="เว้นว่างเพื่อออโต้">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label class="cmns-label">1. อุปกรณ์ (Device) <span style="color:red">*</span></label>
                            <select id="main_cat_select" class="cmns-input" required onchange="updateSubCategory()">
                                <option value="">-- เลือกอุปกรณ์ --</option>
                                <?php foreach($main_cats as $mc): ?>
                                    <option value="<?= $mc['id'] ?>"><?= htmlspecialchars($mc['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="cmns-label">2. ประเภทอะไหล่ (Part) <span style="color:red">*</span></label>
                            <select name="category_id" id="sub_cat_select" class="cmns-input" required disabled>
                                <option value="">-- รอเลือกอุปกรณ์ --</option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label class="cmns-label" style="color: var(--primary);">ประเภทสินค้า (Item Type) <span style="color:red">*</span></label>
                        <select name="type" id="add-type-select" class="cmns-input" onchange="toggleTypeFields()" style="border-color: var(--primary); font-weight: 700; color: var(--primary);" required>
                            <option value="new" <?= ($auto_type == 'new') ? 'selected' : '' ?>>NEW (อะไหล่มือ 1)</option>
                            <option value="used" <?= ($auto_type == 'used') ? 'selected' : '' ?>>USED (อะไหล่ถอด / มือ 2)</option>
                            <option value="machine" <?= ($auto_type == 'machine') ? 'selected' : '' ?>>MACHINE (ซาก / เครื่องรอแกะ)</option>
                            <option value="sale" <?= ($auto_type == 'sale') ? 'selected' : '' ?>>SALE (เครื่องพร้อมขาย)</option>
                        </select>
                    </div>

                    <div id="dynamic-type-fields" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        </div>
                </div>
            </div>

            <hr style="border: none; border-top: 1px dashed var(--border); margin: 30px 0;">

            <div>
                <h4 style="margin: 0 0 20px 0; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
                    <span class="material-symbols-rounded" style="color: #10b981; font-size: 22px;">inventory</span> ข้อมูลสต็อกล็อตนี้
                </h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div><label class="cmns-label">จำนวนรับ (Qty) <span style="color:red">*</span></label><input type="number" name="qty_received" class="cmns-input" value="1" min="1" required style="font-weight: 800; color: #10b981; border-color: #10b981;"></div>
                    <div><label class="cmns-label">ทุน/ชิ้น</label><input type="number" name="cost_price" class="cmns-input" placeholder="0.00" step="0.01"></div>
                    <div><label class="cmns-label">ขาย/ชิ้น</label><input type="number" name="sell_price" class="cmns-input" placeholder="0.00" step="0.01"></div>
                </div>
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                    <div><label class="cmns-label">ซัพพลายเออร์</label><input type="text" name="supplier_name" class="cmns-input" placeholder="ร้านค้า..."></div>
                    <div><label class="cmns-label" style="color: #ef4444;">วันหมดประกัน</label><input type="date" name="warranty_end" class="cmns-input"></div>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 35px;">
                <button type="button" class="cmns-btn cmns-btn-secondary" onclick="closeAddModal()">ยกเลิก</button>
                <button type="submit" class="cmns-btn cmns-btn-primary" id="btn-submit-modal" style="padding: 12px 30px;">
                    <span class="material-symbols-rounded">save</span> บันทึกเข้าสต็อก
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.cmns-label { font-size: 11px; font-weight: 800; color: var(--text-muted); margin-bottom: 8px; display: block; text-transform: uppercase; letter-spacing: 0.5px; }
.cmns-input { width: 100%; background: var(--bg-surface-alt); border: 1px solid var(--border); color: var(--text-main); padding: 12px 14px; border-radius: 12px; font-size: 13px; outline: none; transition: all 0.2s ease; }
.cmns-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); background: var(--bg-surface); }
.cmns-input:disabled { opacity: 0.5; cursor: not-allowed; }
.mode-btn { background: transparent; color: var(--text-muted); }
.mode-btn:hover { background: var(--bg-surface-alt); }
.mode-btn.active { background: rgba(37, 99, 235, 0.1); color: var(--primary); border-color: var(--primary) !important; }
</style>

<script>
const allSubCats = <?= json_encode($sub_cats) ?>;
const machinesList = <?= json_encode($machine_list) ?>;

function toggleTypeFields() {
    const type = document.getElementById('add-type-select').value;
    const container = document.getElementById('dynamic-type-fields');
    let html = '';

    if (type === 'new') {
        html = `<div><label class="cmns-label">เลขพาร์ท (Part No.)</label><input type="text" name="part_number" class="cmns-input" placeholder="เช่น 661-123"></div><div><label class="cmns-label">รุ่นรองรับ (Model)</label><input type="text" name="compatible_models" class="cmns-input" placeholder="เช่น A2337"></div><div><label class="cmns-label">ตำแหน่งเก็บ (Location)</label><input type="text" name="location" class="cmns-input" placeholder="ตู้ A ชั้น 2"></div><div><label class="cmns-label" style="color:#f59e0b;">เตือนของหมด (Min)</label><input type="number" name="min_qty" class="cmns-input" value="1" min="0"></div>`;
    } 
    else if (type === 'used') {
        let machineOpts = '<option value="">-- ไม่ทราบที่มา / ไม่ผูกเครื่องซาก --</option>';
        machinesList.forEach(m => { machineOpts += `<option value="${m.id}">[${m.asset_tag || 'NO-TAG'}] ${m.name}</option>`; });
        html = `<div style="grid-column: span 2;"><label class="cmns-label" style="color:#ef4444;"><span class="material-symbols-rounded" style="font-size:16px; vertical-align:middle;">link</span> แกะมาจากเครื่องซากตัวไหน?</label><select name="source_machine_id" class="cmns-input" style="border-color:#ef4444;">${machineOpts}</select></div><div><label class="cmns-label">เลขพาร์ท (Part No.)</label><input type="text" name="part_number" class="cmns-input" placeholder="เช่น 661-123"></div><div><label class="cmns-label">ตำแหน่งเก็บ (Location)</label><input type="text" name="location" class="cmns-input" placeholder="ตู้ A ชั้น 2"></div>`;
    }
    else if (type === 'machine') {
        html = `<div><label class="cmns-label">รหัสเครื่อง (Asset Tag)</label><input type="text" name="asset_tag" class="cmns-input" placeholder="เช่น MC-001"></div><div><label class="cmns-label">Serial Number</label><input type="text" name="serial_number" class="cmns-input" placeholder="S/N..."></div><div style="grid-column: span 2;"><label class="cmns-label" style="color:#10b981;">สถานะการแยกอะไหล่</label><select name="disassembly_status" class="cmns-input" style="border-color:#10b981;"><option value="intact">ยังไม่แกะ</option><option value="partially_stripped">แกะไปบางส่วน</option><option value="stripped">แกะหมดแล้ว</option></select></div>`;
    }
    else if (type === 'sale') {
        html = `<div><label class="cmns-label">รหัสเครื่อง (Asset Tag)</label><input type="text" name="asset_tag" class="cmns-input"></div><div><label class="cmns-label">Serial Number</label><input type="text" name="serial_number" class="cmns-input"></div><div style="grid-column: span 2;"><label class="cmns-label">สภาพเครื่อง (Condition)</label><input type="text" name="condition_note" class="cmns-input" placeholder="ตำหนิต่างๆ..."></div>`;
    }
    container.innerHTML = html;
}

// 🛑 ฟังก์ชันเปิด Modal (เพิ่มคำสั่งให้สั่งเปลี่ยนฟอร์มออโต้)
function openAddModal(mode) {
    const modal = document.getElementById('modal-add');
    if (!modal) return;
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    toggleTypeFields();

    // ── Pre-select category ถ้ามี context จากหน้า view ──
    <?php if ($modal_main_cat_id): ?>
    const mainSel = document.getElementById('main_cat_select');
    if (mainSel && mainSel.value != '<?= $modal_main_cat_id ?>') {
        mainSel.value = '<?= $modal_main_cat_id ?>';
        updateSubCategory();
        <?php if ($modal_sub_cat_id): ?>
        // รอ updateSubCategory populate แล้วค่อย set sub
        setTimeout(() => {
            const subSel = document.getElementById('sub_cat_select');
            if (subSel) subSel.value = '<?= $modal_sub_cat_id ?>';
        }, 50);
        <?php endif; ?>
    }
    <?php endif; ?>

    // ── ถ้าส่ง mode='existing' มาให้ switch ทันที ──
    if (mode === 'existing') toggleAddMode('existing');
}

function previewAddImage(input) {
    const placeholder = document.getElementById('add-img-placeholder');
    const preview = document.getElementById('add-img-preview');
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result; preview.style.display = 'block'; placeholder.style.display = 'none';
        }
        reader.readAsDataURL(input.files[0]);
    } else { preview.style.display = 'none'; placeholder.style.display = 'flex'; }
}

function updateSubCategory() {
    const mainCatId = document.getElementById('main_cat_select').value;
    const subCatSelect = document.getElementById('sub_cat_select');
    subCatSelect.innerHTML = '<option value="">-- เลือกประเภทอะไหล่ --</option>';
    if (!mainCatId) { subCatSelect.disabled = true; subCatSelect.innerHTML = '<option value="">-- รอเลือกอุปกรณ์ --</option>'; return; }
    subCatSelect.disabled = false;
    const filtered = allSubCats.filter(c => c.parent_id == mainCatId);
    if (filtered.length > 0) {
        filtered.forEach(c => { subCatSelect.innerHTML += `<option value="${c.id}">${c.name}</option>`; });
        subCatSelect.innerHTML += `<option value="${mainCatId}" style="color:var(--primary); font-weight:bold;">📍 วางไว้ในตู้หลัก</option>`;
    } else { subCatSelect.innerHTML = `<option value="${mainCatId}" selected>📍 วางไว้ในตู้หลัก</option>`; }
}

function toggleAddMode(mode) {
    const secNew    = document.getElementById('section-new-item');
    const secExist  = document.getElementById('section-existing-item');
    const btnNew    = document.getElementById('btn-mode-new');
    const btnExist  = document.getElementById('btn-mode-exist');
    const inputName = document.getElementById('input-name');
    const hiddenId  = document.getElementById('exist-item-id-hidden');

    if (mode === 'new') {
        secNew.style.display   = 'flex'; secExist.style.display = 'none';
        btnNew.classList.add('active'); btnExist.classList.remove('active');
        if (inputName) inputName.setAttribute('required', 'true');
        if (hiddenId)  hiddenId.removeAttribute('required');
    } else {
        secNew.style.display   = 'none'; secExist.style.display = 'flex';
        btnExist.classList.add('active'); btnNew.classList.remove('active');
        if (inputName) inputName.removeAttribute('required');
        if (hiddenId)  hiddenId.setAttribute('required', 'true');
    }
}

function closeAddModal() {
    const modal = document.getElementById('modal-add');
    if (!modal) return;
    modal.classList.remove('show');
    document.body.style.overflow = 'auto';
    document.getElementById('form-add-item').reset();
    document.getElementById('add-img-preview').style.display = 'none';
    document.getElementById('add-img-placeholder').style.display = 'flex';
    document.getElementById('sub_cat_select').disabled = true;
    // reset existing search
    clearExistSelection();
    clearExistSearch();
    toggleAddMode('new');
    document.getElementById('add-type-select').value = "<?= $auto_type ?>";
    toggleTypeFields();
}
</script>