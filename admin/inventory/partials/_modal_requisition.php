    <!-- ── เบิกอะไหล่ Modal ── -->
    <div id="modal-requisition" class="cmns-modal">
        <div class="modal-content" style="max-width:520px; padding:30px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; padding-bottom:16px; border-bottom:1px solid var(--border);">
                <h3 style="margin:0; display:flex; align-items:center; gap:10px; font-size:18px; color:var(--text-main);">
                    <span class="material-symbols-rounded" style="color:#10b981; font-size:26px;">output</span>
                    เบิกอะไหล่ใหม่
                </h3>
                <button class="modal-close-btn" onclick="closeRequisitionModal()"><span class="material-symbols-rounded">close</span></button>
            </div>

            <!-- Item info -->
            <div id="req-item-info" style="background:var(--bg-surface-alt); border:1px solid var(--border); border-radius:10px; padding:14px 16px; margin-bottom:16px;">
                <div id="req-item-name" style="font-weight:700; font-size:14px; color:var(--text-main);"></div>
                <div style="display:flex; gap:16px; margin-top:6px;">
                    <span style="font-size:12px; color:var(--text-muted);">SKU: <code id="req-item-sku"></code></span>
                    <span style="font-size:12px; color:var(--text-muted);">คงเหลือรวม: <b id="req-item-qty" style="color:var(--text-main);"></b> ชิ้น</span>
                </div>
            </div>

            <!-- Lot Selector -->
            <div style="margin-bottom:16px;">
                <label style="font-size:11px; font-weight:800; color:var(--text-muted); text-transform:uppercase; letter-spacing:.6px; display:block; margin-bottom:8px;">เลือก Lot</label>
                <div id="req-lots-wrap" style="display:flex; flex-direction:column; gap:6px;">
                    <div style="padding:24px; text-align:center; color:var(--text-muted); font-size:13px;">
                        <span class="material-symbols-rounded" style="animation:spin 1s linear infinite; font-size:20px; display:block; margin-bottom:6px;">sync</span>
                        กำลังโหลด lots...
                    </div>
                </div>
                <input type="hidden" id="req-lot-id" value="">
            </div>

            <div style="display:grid; gap:16px;">

                <!-- Qty -->
                <div>
                    <label style="font-size:11px; font-weight:800; color:var(--text-muted); text-transform:uppercase; letter-spacing:.6px; display:block; margin-bottom:6px;">จำนวนที่เบิก</label>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <button type="button" onclick="adjustQty(-1)" style="width:36px; height:36px; border-radius:8px; border:1px solid var(--border); background:var(--bg-surface-alt); font-size:20px; cursor:pointer; color:var(--text-main); display:flex; align-items:center; justify-content:center;">−</button>
                        <input type="number" id="req-qty" value="1" min="1" max="99"
                               style="width:72px; text-align:center; padding:8px; border:1.5px solid var(--border); border-radius:8px; background:var(--bg-surface-alt); color:var(--text-main); font-size:16px; font-weight:800; outline:none;">
                        <button type="button" onclick="adjustQty(1)" style="width:36px; height:36px; border-radius:8px; border:1px solid var(--border); background:var(--bg-surface-alt); font-size:20px; cursor:pointer; color:var(--text-main); display:flex; align-items:center; justify-content:center;">+</button>
                        <span id="req-qty-max-label" style="font-size:12px; color:var(--text-muted);"></span>
                    </div>
                </div>

                <!-- Link to job -->
                <div>
                    <label style="font-size:11px; font-weight:800; color:var(--text-muted); text-transform:uppercase; letter-spacing:.6px; display:block; margin-bottom:6px;">ผูกกับงานซ่อม (ไม่บังคับ)</label>
                    <div style="position:relative;">
                        <span class="material-symbols-rounded" style="position:absolute; left:11px; top:50%; transform:translateY(-50%); font-size:18px; color:var(--text-muted); pointer-events:none;">build_circle</span>
                        <input type="text" id="req-job-search" placeholder="พิมพ์ Job No. / ชื่อลูกค้า..."
                               autocomplete="off"
                               style="width:100%; padding:10px 12px 10px 38px; border:1.5px solid var(--border); border-radius:10px; background:var(--bg-surface-alt); color:var(--text-main); font-size:13px; font-family:inherit; outline:none;"
                               oninput="searchJobs(this.value)">
                        <div id="req-job-results" style="display:none; position:absolute; top:calc(100%+4px); left:0; right:0; background:var(--bg-surface); border:1.5px solid var(--border); border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,.12); z-index:200; max-height:220px; overflow-y:auto;"></div>
                    </div>
                    <div id="req-job-selected" style="display:none; margin-top:8px; background:rgba(16,185,129,.08); border:1px solid rgba(16,185,129,.25); border-radius:8px; padding:8px 12px; font-size:12px;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <span style="font-weight:700; color:#059669;" id="req-job-label"></span>
                            </div>
                            <button type="button" onclick="clearJobSelection()" style="background:none; border:none; cursor:pointer; color:var(--text-muted); font-size:16px; padding:0 4px;">✕</button>
                        </div>
                    </div>
                    <input type="hidden" id="req-tracking-id" value="">
                    <input type="hidden" id="req-ticket-number" value="">
                </div>

                <!-- Remarks -->
                <div>
                    <label style="font-size:11px; font-weight:800; color:var(--text-muted); text-transform:uppercase; letter-spacing:.6px; display:block; margin-bottom:6px;">หมายเหตุ</label>
                    <input type="text" id="req-remarks" placeholder="เช่น เปลี่ยนหน้าจอแตก..."
                           style="width:100%; padding:10px 12px; border:1.5px solid var(--border); border-radius:10px; background:var(--bg-surface-alt); color:var(--text-main); font-size:13px; font-family:inherit; outline:none;">
                </div>
            </div>

            <div id="req-error" style="display:none; margin-top:14px; padding:10px 14px; background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.3); border-radius:8px; color:#dc2626; font-size:13px; font-weight:600;"></div>

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:24px; padding-top:16px; border-top:1px solid var(--border);">
                <button type="button" class="cmns-btn cmns-btn-secondary" onclick="closeRequisitionModal()">ยกเลิก</button>
                <button type="button" id="req-submit-btn" class="cmns-btn cmns-btn-primary" style="background:#10b981; border-color:#10b981;" onclick="submitRequisition()">
                    <span class="material-symbols-rounded">output</span> ยืนยันเบิก
                </button>
            </div>
        </div>
    </div>
