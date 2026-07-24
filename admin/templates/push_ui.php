<?php
/**
 * admin/templates/push_ui.php — ปุ่มเปิด/ปิดแจ้งเตือนผ่านแอป "ของเครื่องนี้" (ใช้ได้ทุก role)
 * ใช้: require_once includes/push_helper.php แล้ว include ไฟล์นี้ตรงจุดที่อยากให้ปุ่มโผล่
 * ต้องมี $pdo ใน scope · ปุ่ม/สถานะจัดสไตล์เองขั้นต่ำ ให้หน้าแม่คุมภาพรวม
 */
$push_vapid_pub_b64u = push_vapid_public($pdo);
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$push_csrf = $_SESSION['csrf_token'];
?>
<div id="push-ui" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
    <button type="button" id="push-btn" class="pushui-btn on" style="display:none;">
        <span class="material-symbols-rounded" style="font-size:17px;">notifications_active</span>
        <span id="push-btn-text">เปิดแจ้งเตือนบนเครื่องนี้</span>
    </button>
    <span id="push-status" style="font-size:.78rem;color:var(--text-muted,#6b7280);line-height:1.5;">กำลังตรวจสอบ…</span>
</div>
<style>
.pushui-btn { display:inline-flex; align-items:center; justify-content:center; gap:7px;
    padding:9px 16px; border-radius:10px; font-size:.88rem; font-weight:600;
    font-family:inherit; line-height:1; cursor:pointer; border:1px solid transparent; white-space:nowrap;
    transition:background .18s ease, border-color .18s ease; }
.pushui-btn.on  { background:var(--primary,#2563eb); color:#fff; }
.pushui-btn.on:hover { filter:brightness(.92); }
.pushui-btn.off { background:var(--bg-surface,transparent); color:var(--text-main,#111);
    border-color:var(--border,#d1d5db); }
.pushui-btn.off:hover { border-color:var(--text-muted,#6b7280); }
.pushui-btn:disabled { opacity:.6; cursor:default; }
</style>
<script>
(function () {
    const KEY  = '<?= $push_vapid_pub_b64u ?>';
    const CSRF = <?= json_encode($push_csrf) ?>;
    const btn = document.getElementById('push-btn');
    const txt = document.getElementById('push-btn-text');
    const st  = document.getElementById('push-status');

    function b64ToU8(s) {
        const pad = '='.repeat((4 - s.length % 4) % 4);
        const raw = atob((s + pad).replace(/-/g, '+').replace(/_/g, '/'));
        return Uint8Array.from(raw, c => c.charCodeAt(0));
    }
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
    const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;

    async function refresh() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
            // iOS Safari ธรรมดาไม่มี PushManager — ต้องติดตั้งเป็นแอปก่อน (iOS 16.4+)
            st.textContent = (isIOS && !standalone)
                ? 'iPhone/iPad: กด แชร์ → เพิ่มลงหน้าจอโฮม แล้วเปิดจากไอคอนแอปก่อน ถึงจะเปิดแจ้งเตือนได้'
                : 'เบราว์เซอร์นี้ไม่รองรับแจ้งเตือน';
            return;
        }
        if (Notification.permission === 'denied') {
            st.textContent = 'เคยกดบล็อกแจ้งเตือนไว้ — ไปปลดที่ตั้งค่าเบราว์เซอร์ก่อน';
            return;
        }
        const reg = await navigator.serviceWorker.ready;
        const sub = await reg.pushManager.getSubscription();
        btn.style.display = 'inline-flex';
        if (sub) {
            txt.textContent = 'ปิดแจ้งเตือนเครื่องนี้';
            btn.classList.remove('on'); btn.classList.add('off');
            st.textContent = 'เครื่องนี้รับแจ้งเตือนอยู่ ✓';
        } else {
            txt.textContent = 'เปิดแจ้งเตือนบนเครื่องนี้';
            btn.classList.remove('off'); btn.classList.add('on');
            st.textContent = 'ยังไม่เปิดบนเครื่องนี้';
        }
    }

    async function post(action, subscription) {
        const r = await fetch('/admin/push_subscribe.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, subscription, csrf_token: CSRF }),
        });
        const j = await r.json();
        if (!j.ok) throw new Error(j.err || 'บันทึกไม่สำเร็จ');
    }

    btn && btn.addEventListener('click', async () => {
        btn.disabled = true;
        try {
            const reg = await navigator.serviceWorker.ready;
            let sub = await reg.pushManager.getSubscription();
            if (sub) {
                await post('unsubscribe', sub.toJSON());
                await sub.unsubscribe();
            } else {
                const perm = await Notification.requestPermission();
                if (perm !== 'granted') throw new Error('ไม่ได้รับอนุญาตแจ้งเตือน');
                sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToU8(KEY) });
                await post('subscribe', sub.toJSON());
            }
        } catch (e) {
            st.textContent = e.message || 'ผิดพลาด';
        }
        btn.disabled = false;
        refresh();
    });

    refresh();
})();
</script>
