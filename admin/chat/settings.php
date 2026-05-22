<?php
session_start();
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/chat_config.php';
require_login();

$fb   = chat_get_platform_config($pdo, 'facebook');
$line = chat_get_platform_config($pdo, 'line');

$select_page   = isset($_GET['select_page']) && !empty($_SESSION['fb_pending_pages']);
$pending_pages = $_SESSION['fb_pending_pages'] ?? [];

$messenger_settings_url = 'https://developers.facebook.com/apps/' . FB_APP_ID . '/messenger-platform/';
$base_url = rtrim($_ENV['APP_URL'] ?? ('http://' . $_SERVER['HTTP_HOST']), '/');

$pageTitle = 'Chat Settings';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | FixMac Admin</title>
    <script>
        (function() {
            const t = localStorage.getItem('admin_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', t);
            document.documentElement.style.backgroundColor = t === 'dark' ? '#0a0a0a' : '#f1f5f9';
            if (localStorage.getItem('sidebarState') === 'collapsed' && window.innerWidth > 991)
                document.documentElement.classList.add('sidebar-collapsed');
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>
    <link rel="stylesheet" href="/admin/templates/assets/css/admin.css?v=10">
    <style>
        .connect-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; max-width: 860px; }
        @media(max-width:640px){ .connect-grid { grid-template-columns: 1fr; } }

        .platform-card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 28px 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .platform-card-head {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .platform-logo {
            width: 48px; height: 48px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; flex-shrink: 0;
        }
        .platform-logo.fb  { background: #1877f2; }
        .platform-logo.ln  { background: #06c755; }
        .platform-title    { font-size: 1.1rem; font-weight: 700; color: var(--text-main); }
        .platform-subtitle { font-size: 0.8rem; color: var(--text-muted); }

        .status-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 12px; border-radius: 20px;
            font-size: 0.82rem; font-weight: 600;
        }
        .status-badge.connected    { background: rgba(22,163,74,.15); color: #16a34a; }
        .status-badge.disconnected { background: rgba(239,68,68,.12); color: #dc2626; }
        .status-dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }

        .platform-info {
            background: var(--bg-main);
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 0.85rem;
        }
        .platform-info .info-label { color: var(--text-muted); font-size: 0.75rem; margin-bottom: 2px; }
        .platform-info .info-value { color: var(--text-main); font-weight: 600; }

        .btn-connect {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 0.9rem; font-weight: 600;
            cursor: pointer; border: none; text-decoration: none;
            transition: opacity .15s;
            width: 100%;
        }
        .btn-connect:hover { opacity: .85; }
        .btn-connect.fb { background: #1877f2; color: #fff; }
        .btn-connect.ln { background: #06c755; color: #fff; }
        .btn-disconnect {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-muted);
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 0.82rem;
            cursor: pointer;
            transition: all .15s;
            width: 100%;
        }
        .btn-disconnect:hover { border-color: #dc2626; color: #dc2626; }

        /* Page selector modal */
        .modal-overlay {
            position: fixed; inset: 0;
            background: rgba(0,0,0,.6);
            display: flex; align-items: center; justify-content: center;
            z-index: 9999;
        }
        .modal-box {
            background: var(--bg-surface);
            border-radius: 16px;
            padding: 28px;
            width: 400px;
            max-width: 95vw;
        }
        .modal-title { font-size: 1rem; font-weight: 700; margin-bottom: 16px; }
        .page-option {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 10px;
            cursor: pointer;
            transition: all .12s;
            margin-bottom: 8px;
        }
        .page-option:hover { border-color: #1877f2; background: rgba(24,119,242,.06); }
        .page-option .page-name { font-size: 0.9rem; font-weight: 600; }
        .page-option .page-id  { font-size: 0.75rem; color: var(--text-muted); }

        /* LINE modal */
        .line-modal { display: none; }
        .line-modal.show { display: flex; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 0.82rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; }
        .form-group input, .form-group textarea {
            width: 100%; padding: 10px 12px;
            border: 1px solid var(--border); border-radius: 8px;
            background: var(--bg-main); color: var(--text-main);
            font-size: 0.85rem; font-family: monospace;
            box-sizing: border-box;
        }
        .form-group textarea { min-height: 80px; resize: vertical; }
        .webhook-url-box {
            background: var(--bg-main);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 12px;
            font-family: monospace;
            font-size: 0.8rem;
            color: var(--text-muted);
            word-break: break-all;
            margin-bottom: 16px;
        }
        .alert-info {
            background: rgba(59,130,246,.1);
            border: 1px solid rgba(59,130,246,.3);
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.82rem;
            color: var(--text-main);
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
<div class="wrapper">
<?php include __DIR__ . '/../../admin/templates/sidebar_admin.php'; ?>
<main class="main">
<?php include __DIR__ . '/../../admin/templates/navbar_admin.php'; ?>
<div class="content-padding">

<div style="max-width:860px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:28px">
        <div>
            <h1 style="font-size:1.4rem;font-weight:700;margin:0">Chat Connections</h1>
            <p style="color:var(--text-muted);font-size:0.85rem;margin:4px 0 0">เชื่อมต่อ Facebook และ LINE เพื่อรับข้อความใน Chat Inbox</p>
        </div>
        <a href="/admin/chat/" style="display:flex;align-items:center;gap:6px;font-size:0.85rem;color:var(--primary);text-decoration:none">
            <span class="material-symbols-rounded" style="font-size:18px">arrow_back</span> กลับ Inbox
        </a>
    </div>

    <div class="connect-grid">

        <!-- ── Facebook Card ── -->
        <div class="platform-card">
            <div class="platform-card-head">
                <div class="platform-logo fb">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="#fff"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                </div>
                <div>
                    <div class="platform-title">Facebook Messenger</div>
                    <div class="platform-subtitle">รับข้อความจาก Facebook Page</div>
                </div>
            </div>

            <?php if (!empty($fb['access_token'])): ?>
                <span class="status-badge connected"><span class="status-dot"></span> เชื่อมต่อแล้ว</span>
                <div class="platform-info">
                    <div class="info-label">Facebook Page</div>
                    <div class="info-value"><?= htmlspecialchars($fb['page_name'] ?? '-') ?></div>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <button class="btn-disconnect" onclick="disconnect('facebook')">ยกเลิกการเชื่อมต่อ</button>
                    <button class="btn-disconnect" id="refreshFbBtn" onclick="refreshFbContacts()" style="border-color:var(--primary);color:var(--primary)">
                        <span class="material-symbols-rounded" style="font-size:16px;vertical-align:-3px">person_search</span>
                        ดึงชื่อลูกค้า FB
                    </button>
                </div>
            <?php else: ?>
                <span class="status-badge disconnected"><span class="status-dot"></span> ยังไม่เชื่อมต่อ</span>
                <button class="btn-connect fb" onclick="document.getElementById('fbModal').classList.add('show')">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="#fff"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    เชื่อมต่อ Facebook
                </button>
            <?php endif; ?>
        </div>

        <!-- ── LINE Card ── -->
        <div class="platform-card">
            <div class="platform-card-head">
                <div class="platform-logo ln">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="#fff"><path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.104.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63.346 0 .628.285.628.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.282.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/></svg>
                </div>
                <div>
                    <div class="platform-title">LINE Official Account</div>
                    <div class="platform-subtitle">รับข้อความจาก LINE OA</div>
                </div>
            </div>

            <?php if (!empty($line['access_token'])): ?>
                <span class="status-badge connected"><span class="status-dot"></span> เชื่อมต่อแล้ว</span>
                <div class="platform-info">
                    <div class="info-label">LINE OA</div>
                    <div class="info-value"><?= htmlspecialchars($line['page_name'] ?? '-') ?></div>
                </div>
                <button class="btn-disconnect" onclick="disconnect('line')">ยกเลิกการเชื่อมต่อ</button>
            <?php else: ?>
                <span class="status-badge disconnected"><span class="status-dot"></span> ยังไม่เชื่อมต่อ</span>
                <button class="btn-connect ln" onclick="document.getElementById('lineModal').classList.add('show')">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="#fff"><path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.104.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63.346 0 .628.285.628.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.282.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/></svg>
                    เชื่อมต่อ LINE
                </button>
            <?php endif; ?>

            <div style="font-size:0.75rem;color:var(--text-muted)">
                Webhook URL:
                <div class="webhook-url-box"><?= htmlspecialchars($base_url) ?>/webhook/line.php</div>
            </div>
        </div>

    </div><!-- .connect-grid -->
</div>

</div><!-- .content-padding -->
</main>
</div>

<!-- ── Facebook Page Selector Modal ── -->
<?php if ($select_page && $pending_pages): ?>
<div class="modal-overlay" id="pageSelectModal">
    <div class="modal-box">
        <div class="modal-title">เลือก Facebook Page</div>
        <?php foreach ($pending_pages as $p): ?>
        <div class="page-option" onclick="selectPage('<?= htmlspecialchars($p['id']) ?>', this)">
            <div style="width:36px;height:36px;border-radius:50%;background:#1877f2;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;flex-shrink:0">
                <?= mb_substr($p['name'], 0, 1) ?>
            </div>
            <div>
                <div class="page-name"><?= htmlspecialchars($p['name']) ?></div>
                <div class="page-id">ID: <?= $p['id'] ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ── Facebook Config Modal ── -->
<div class="modal-overlay line-modal" id="fbModal">
    <div class="modal-box">
        <div class="modal-title">เชื่อมต่อ Facebook Page</div>
        <div class="alert-info">
            ไปที่ <a href="<?= htmlspecialchars($messenger_settings_url) ?>" target="_blank" style="color:var(--primary)">
                Messenger API Settings ↗
            </a> → section "สร้างโทเค็นสำเร็จรูป" → กด <strong>สร้าง</strong> ตรง Page ที่ต้องการ → copy token มาวาง
        </div>
        <div class="form-group">
            <label>Page Access Token</label>
            <textarea id="fbToken" placeholder="EAAxxxxxxxxxxxxxxx..." style="min-height:90px"></textarea>
        </div>
        <div style="display:flex;gap:10px">
            <button onclick="saveFbConfig()" style="flex:1;padding:11px;background:#1877f2;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer">
                บันทึก & ทดสอบ
            </button>
            <button onclick="document.getElementById('fbModal').classList.remove('show')" style="padding:11px 18px;background:transparent;border:1px solid var(--border);border-radius:8px;cursor:pointer;color:var(--text-muted)">
                ยกเลิก
            </button>
        </div>
        <div id="fbMsg" style="margin-top:12px;font-size:0.82rem;display:none"></div>
    </div>
</div>

<!-- ── LINE Config Modal ── -->
<div class="modal-overlay line-modal" id="lineModal">
    <div class="modal-box">
        <div class="modal-title">เชื่อมต่อ LINE OA</div>
        <div class="alert-info">
            ไปที่ <strong>developers.line.biz</strong> → เลือก Channel → แท็บ <strong>Messaging API</strong>
            แล้ว copy ค่ามาใส่
        </div>
        <div class="form-group">
            <label>Channel Access Token</label>
            <textarea id="lineToken" placeholder="Channel access token (ยาวมาก)"></textarea>
        </div>
        <div class="form-group">
            <label>Channel Secret</label>
            <input type="text" id="lineSecret" placeholder="Channel secret (32 ตัวอักษร)">
        </div>
        <div style="display:flex;gap:10px">
            <button onclick="saveLineConfig()" style="flex:1;padding:11px;background:var(--primary);color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer">
                บันทึก & ทดสอบ
            </button>
            <button onclick="document.getElementById('lineModal').classList.remove('show')" style="padding:11px 18px;background:transparent;border:1px solid var(--border);border-radius:8px;cursor:pointer;color:var(--text-muted)">
                ยกเลิก
            </button>
        </div>
        <div id="lineMsg" style="margin-top:12px;font-size:0.82rem;display:none"></div>
    </div>
</div>

<script src="/admin/templates/assets/js/admin.js?v=10"></script>
<script>
async function disconnect(platform) {
    if (!confirm(`ยืนยันยกเลิกการเชื่อมต่อ ${platform === 'facebook' ? 'Facebook' : 'LINE'}?`)) return;
    const res = await fetch('/admin/chat/api/disconnect_platform.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ platform }),
    });
    const json = await res.json();
    if (json.ok) location.reload();
    else alert('เกิดข้อผิดพลาด');
}

async function selectPage(pageId, el) {
    el.style.opacity = '0.5';
    const res = await fetch('/admin/chat/api/select_fb_page.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ page_id: pageId }),
    });
    const json = await res.json();
    if (json.ok) location.href = '/admin/chat/settings.php?connected=1';
    else alert('เกิดข้อผิดพลาด: ' + json.msg);
}

async function saveFbConfig() {
    const token = document.getElementById('fbToken').value.trim();
    const msg   = document.getElementById('fbMsg');
    if (!token) { showFbMsg('กรุณาใส่ token', 'error'); return; }

    const res  = await fetch('/admin/chat/api/save_fb_config.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ access_token: token }),
    });
    const json = await res.json();

    if (json.ok) { showFbMsg('เชื่อมต่อสำเร็จ: ' + json.name, 'success'); setTimeout(() => location.reload(), 1000); }
    else showFbMsg(json.msg || 'เกิดข้อผิดพลาด', 'error');

    function showFbMsg(text, type) {
        msg.style.display = 'block';
        msg.style.color   = type === 'success' ? '#16a34a' : '#dc2626';
        msg.textContent   = text;
    }
}

async function saveLineConfig() {
    const token  = document.getElementById('lineToken').value.trim();
    const secret = document.getElementById('lineSecret').value.trim();
    const msg    = document.getElementById('lineMsg');

    if (!token || !secret) { showMsg('กรุณากรอกทั้งสองช่อง', 'error'); return; }

    const res  = await fetch('/admin/chat/api/save_line_config.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ access_token: token, channel_secret: secret }),
    });
    const json = await res.json();

    if (json.ok) {
        showMsg('เชื่อมต่อสำเร็จ: ' + json.name, 'success');
        setTimeout(() => location.reload(), 1000);
    } else {
        showMsg(json.msg || 'เกิดข้อผิดพลาด', 'error');
    }

    function showMsg(text, type) {
        msg.style.display = 'block';
        msg.style.color   = type === 'success' ? '#16a34a' : '#dc2626';
        msg.textContent   = text;
    }
}

// Flash messages from PHP session
<?php if (!empty($_SESSION['success'])): ?>
alert('<?= addslashes($_SESSION['success']) ?>');
<?php unset($_SESSION['success']); endif; ?>
<?php if (!empty($_SESSION['error'])): ?>
alert('❌ <?= addslashes($_SESSION['error']) ?>');
<?php unset($_SESSION['error']); endif; ?>

async function refreshFbContacts() {
    const btn = document.getElementById('refreshFbBtn');
    btn.disabled = true;
    btn.textContent = 'กำลังดึงข้อมูล...';
    const res  = await fetch('/admin/chat/api/refresh_fb_contacts.php', { method: 'POST' });
    const json = await res.json();
    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:16px;vertical-align:-3px">person_search</span> ดึงชื่อลูกค้า FB';
    alert(json.ok ? '✅ ' + json.msg : '❌ ' + json.msg);
}
</script>
</body>
</html>
