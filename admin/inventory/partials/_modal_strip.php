    <!-- ── Strip Parts Modal ── -->
    <div id="modal-strip" class="cmns-modal">
        <div class="modal-content" style="max-width:860px; padding:30px;">

            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); padding-bottom:20px; margin-bottom:20px;">
                <h3 style="margin:0; display:flex; align-items:center; gap:10px; font-weight:800; font-size:20px; color:var(--text-main);">
                    <span class="material-symbols-rounded" style="color:#8b5cf6; font-size:28px;">content_cut</span>
                    แยกอะไหล่ → USED
                </h3>
                <button class="modal-close-btn" onclick="closeStripModal()"><span class="material-symbols-rounded">close</span></button>
            </div>

            <!-- machine source badge -->
            <div id="strip-machine-info" style="display:flex; align-items:center; gap:10px; background:rgba(139,92,246,.06); border:1px solid rgba(139,92,246,.2); border-radius:10px; padding:10px 16px; margin-bottom:22px;">
                <span class="material-symbols-rounded" style="color:#8b5cf6; font-size:20px;">computer</span>
                <div>
                    <div id="strip-machine-name" style="font-weight:700; font-size:13px; color:#8b5cf6;"></div>
                    <div id="strip-machine-tag" style="font-size:11px; color:var(--text-muted);"></div>
                </div>
            </div>

            <form id="form-strip" action="process_strip.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="source_machine_id" id="strip-machine-id">

                <div style="display:flex; gap:28px; flex-wrap:wrap; align-items:flex-start;">

                    <!-- รูป -->
                    <div style="flex-shrink:0; width:160px;">
                        <label class="cmns-label">PART IMAGE</label>
                        <div style="width:160px; height:160px; border:2px dashed var(--border); border-radius:16px; display:flex; flex-direction:column; align-items:center; justify-content:center; position:relative; overflow:hidden; cursor:pointer; transition:.2s; background:transparent;"
                             onclick="document.getElementById('strip-image').click()"
                             onmouseover="this.style.borderColor='#8b5cf6'" onmouseout="this.style.borderColor='var(--border)'">
                            <div id="strip-img-placeholder" style="display:flex; flex-direction:column; align-items:center; color:var(--text-muted); opacity:.5;">
                                <span class="material-symbols-rounded" style="font-size:40px; margin-bottom:8px;">add_photo_alternate</span>
                                <span style="font-size:11px; font-weight:700;">คลิกอัปโหลด</span>
                            </div>
                            <img id="strip-img-preview" src="" style="width:100%; height:100%; object-fit:contain; display:none; position:absolute; top:0; left:0; background:var(--bg-surface-alt);">
                        </div>
                        <input type="file" name="image" id="strip-image" accept="image/*" hidden onchange="previewStripImage(this)">
                    </div>

                    <!-- fields -->
                    <div style="flex:1; min-width:300px;">
                        <div style="display:grid; grid-template-columns:2fr 1fr; gap:14px; margin-bottom:14px;">
                            <div>
                                <label class="cmns-label">ชื่ออะไหล่ <span style="color:red">*</span></label>
                                <input type="text" name="name" id="strip-name" class="cmns-input" placeholder="เช่น LCD MacBook Pro 13 A2338" required>
                            </div>
                            <div>
                                <label class="cmns-label">รหัส SKU</label>
                                <input type="text" name="sku" class="cmns-input" placeholder="เว้นว่างออโต้">
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                            <div>
                                <label class="cmns-label">1. อุปกรณ์ <span style="color:red">*</span></label>
                                <select id="strip-main-cat" class="cmns-input" required onchange="updateStripSubCat()">
                                    <option value="">-- เลือกอุปกรณ์ --</option>
                                    <?php foreach(array_values($main_cats) as $mc): ?>
                                        <option value="<?= $mc['id'] ?>"><?= htmlspecialchars($mc['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="cmns-label">2. ประเภทอะไหล่ <span style="color:red">*</span></label>
                                <select name="category_id" id="strip-sub-cat" class="cmns-input" required disabled>
                                    <option value="">-- รอเลือกอุปกรณ์ --</option>
                                </select>
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                            <div>
                                <label class="cmns-label">Part No.</label>
                                <input type="text" name="part_number" class="cmns-input" placeholder="เช่น 661-18505">
                            </div>
                            <div>
                                <label class="cmns-label">Serial No.</label>
                                <input type="text" name="serial_number" class="cmns-input" placeholder="S/N ของชิ้นส่วน">
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px; margin-bottom:14px;">
                            <div>
                                <label class="cmns-label" style="color:#f59e0b;">สภาพ <span style="color:red">*</span></label>
                                <select name="status" class="cmns-input" style="border-color:#f59e0b; font-weight:700; color:#f59e0b;" required>
                                    <option value="GOOD">GOOD</option>
                                    <option value="TEST">TEST</option>
                                    <option value="DEAD">DEAD</option>
                                </select>
                            </div>
                            <div>
                                <label class="cmns-label">ราคาขาย (฿)</label>
                                <input type="number" name="sell_price" class="cmns-input" value="0" step="0.01">
                            </div>
                            <div>
                                <label class="cmns-label">ทุน (฿)</label>
                                <input type="number" name="cost_price" class="cmns-input" value="0" step="0.01">
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                            <div>
                                <label class="cmns-label">ตำแหน่งเก็บ</label>
                                <input type="text" name="location" class="cmns-input" placeholder="ตู้ A ชั้น 2">
                            </div>
                            <div>
                                <label class="cmns-label">หมายเหตุสภาพ</label>
                                <input type="text" name="condition_note" class="cmns-input" placeholder="มีรอยขีดข่วนเล็กน้อย...">
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:28px; padding-top:20px; border-top:1px solid var(--border);">
                    <button type="button" class="cmns-btn cmns-btn-secondary" onclick="closeStripModal()">ยกเลิก</button>
                    <button type="submit" class="cmns-btn cmns-btn-primary" style="background:#8b5cf6; border-color:#8b5cf6; padding:12px 28px;">
                        <span class="material-symbols-rounded">add_circle</span> บันทึกเข้า USED
                    </button>
                </div>
            </form>
        </div>
    </div>
