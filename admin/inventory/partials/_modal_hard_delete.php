<!-- Hard Delete Confirm Dialog (super_admin id=1 only) — house style ตาม shop/user -->
<div id="hd-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:var(--bg-surface);width:90%;max-width:380px;border-radius:16px;overflow:hidden;border:1px solid var(--border);box-shadow:0 8px 32px rgba(0,0,0,.2);">
    <div style="padding:20px 20px 12px;text-align:center;background:rgba(239,68,68,.06);border-bottom:1px solid var(--border);">
      <div style="width:44px;height:44px;border-radius:50%;background:rgba(239,68,68,.12);color:#ef4444;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;">
        <span class="material-symbols-rounded" style="font-size:22px;">delete_forever</span>
      </div>
      <h3 style="margin:0;font-size:15px;font-weight:700;color:#dc2626;">ลบทั้งก้อนถาวร?</h3>
    </div>
    <div style="padding:16px 20px;text-align:center;font-size:14px;line-height:1.6;">
      ลบ <strong id="hd-title" style="color:var(--primary)"></strong> พร้อมล็อตสต็อกทั้งหมด<br>
      <span style="font-size:12px;color:#ef4444;font-weight:600;">‼️ กู้คืนไม่ได้ และไม่เก็บประวัติ</span>
      <input id="hd-input" type="text" oninput="_hdCheck()" placeholder="พิมพ์ DELETE เพื่อยืนยัน" autocomplete="off"
             style="width:100%;margin-top:14px;padding:10px 12px;border:1px solid var(--border);border-radius:10px;background:var(--bg-surface-alt);color:var(--text-main);text-align:center;font-size:14px;letter-spacing:1px;">
    </div>
    <div style="padding:12px 20px;border-top:1px solid var(--border);background:var(--bg-surface-alt);display:flex;gap:8px;justify-content:center;">
      <button onclick="closeHardDelete()" class="cmns-btn cmns-btn-secondary">ยกเลิก</button>
      <button id="hd-btn" onclick="doHardDelete()" class="cmns-btn cmns-btn-primary" disabled style="background:#ef4444;border-color:#ef4444;">ลบถาวร</button>
    </div>
  </div>
</div>
<script>
document.getElementById('hd-overlay').addEventListener('click', e => { if (e.target === document.getElementById('hd-overlay')) closeHardDelete(); });
document.getElementById('hd-input').addEventListener('keydown', e => { if (e.key === 'Enter' && !document.getElementById('hd-btn').disabled) doHardDelete(); });
</script>
