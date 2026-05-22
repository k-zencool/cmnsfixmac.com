<?php
// admin/chat/index.php — Unified chat inbox (Facebook + LINE)

session_start();
date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pageTitle = 'Chat Inbox';
?>
<!DOCTYPE html>
<html lang="th" class="chat-page">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | FixMac Admin</title>

    <script>
        (function() {
            const t = localStorage.getItem('admin_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', t);
            document.documentElement.style.backgroundColor = t === 'dark' ? '#0a0a0a' : '#f1f5f9';
            if (localStorage.getItem('sidebarState') === 'collapsed' && window.innerWidth > 991) {
                document.documentElement.classList.add('sidebar-collapsed');
            }
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />
    <link rel="stylesheet" href="/admin/templates/assets/css/admin.css?v=10">
    <link rel="stylesheet" href="/assets/css/chat_admin.css?v=3">
</head>
<body>
<div class="wrapper">

<?php include __DIR__ . '/../../admin/templates/sidebar_admin.php'; ?>

<main class="main">

<?php include __DIR__ . '/../../admin/templates/navbar_admin.php'; ?>

<div class="content-padding">

<!-- ══ Chat Layout ══════════════════════════════════════════════════════════ -->
<div class="chat-wrap">

    <!-- LEFT: Conversation list -->
    <div class="chat-sidebar">
        <div class="chat-sidebar-head">
            <h2>
                <span class="material-symbols-rounded" style="font-size:20px">chat</span>
                Chat Inbox
            </h2>
            <div class="chat-tabs">
                <button class="chat-tab active" data-platform="all">ทั้งหมด</button>
                <button class="chat-tab" data-platform="facebook">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="#1877f2"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    Facebook
                </button>
                <button class="chat-tab" data-platform="line">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="#06c755"><path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.104.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63.346 0 .628.285.628.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.282.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/></svg>
                    LINE
                </button>
            </div>
        </div>

        <div class="conv-list" id="convList">
            <div class="conv-empty">
                <span class="material-symbols-rounded" style="font-size:32px;opacity:.3">inbox</span><br>
                ยังไม่มีข้อความ
            </div>
        </div>
    </div>

    <!-- RIGHT: Chat window -->
    <div class="chat-main" id="chatMain">
        <div class="chat-empty-state" id="chatEmptyState">
            <span class="material-symbols-rounded">forum</span>
            <div style="font-size:0.9rem">เลือกการสนทนาเพื่อเริ่มแชท</div>
        </div>

        <!-- Header (hidden until conv selected) -->
        <div class="chat-header" id="chatHeader" style="display:none">
            <div class="chat-header-avatar">
                <img id="headerAvatar" src="" alt="" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <div class="avatar-placeholder" id="headerAvatarFallback" style="display:none">
                    <span class="material-symbols-rounded">person</span>
                </div>
                <span class="platform-badge" id="headerPlatformBadge"></span>
            </div>
            <div class="chat-header-info">
                <div class="chat-header-name" id="headerName"></div>
                <div class="chat-header-platform">
                    <span class="platform-dot" id="headerPlatformDot"></span>
                    <span id="headerPlatformLabel"></span>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <div class="chat-messages" id="chatMessages" style="display:none"></div>

        <!-- Input -->
        <div class="chat-input-area" id="chatInputArea" style="display:none">
            <textarea
                class="chat-textarea"
                id="chatInput"
                placeholder="พิมพ์ข้อความ… (Enter ส่ง, Shift+Enter ขึ้นบรรทัด)"
                rows="1"
            ></textarea>
            <button class="chat-send-btn" id="sendBtn" title="ส่ง">
                <span class="material-symbols-rounded">send</span>
            </button>
        </div>
    </div>

</div>
<!-- ════════════════════════════════════════════════════════════════════════════ -->

</div><!-- .content-padding -->
</main>
</div><!-- .wrapper -->

<script src="/admin/templates/assets/js/admin.js?v=10"></script>
<script>
// ── State ────────────────────────────────────────────────────────────────────
const state = {
    platform:    'all',
    activeConvId: null,
    activeConv:   null,
    lastMsgId:    0,
    convTimer:    null,
    msgTimer:     null,
};

// ── DOM refs ─────────────────────────────────────────────────────────────────
const convList        = document.getElementById('convList');
const chatMain        = document.getElementById('chatMain');
const chatEmptyState  = document.getElementById('chatEmptyState');
const chatHeader      = document.getElementById('chatHeader');
const chatMessages    = document.getElementById('chatMessages');
const chatInputArea   = document.getElementById('chatInputArea');
const chatInput       = document.getElementById('chatInput');
const sendBtn         = document.getElementById('sendBtn');
const headerAvatar    = document.getElementById('headerAvatar');
const headerAvatarFB  = document.getElementById('headerAvatarFallback');
const headerName      = document.getElementById('headerName');
const headerBadge     = document.getElementById('headerPlatformBadge');
const headerDot       = document.getElementById('headerPlatformDot');
const headerLabel     = document.getElementById('headerPlatformLabel');

// ── Helpers ──────────────────────────────────────────────────────────────────
function timeAgo(dateStr) {
    const d    = new Date(dateStr);
    const now  = new Date();
    const diff = Math.floor((now - d) / 1000);
    if (diff < 60)   return 'เมื่อกี้';
    if (diff < 3600) return Math.floor(diff / 60) + ' นาทีที่แล้ว';
    if (diff < 86400) return Math.floor(diff / 3600) + ' ชม.ที่แล้ว';
    return d.toLocaleDateString('th-TH', { day: 'numeric', month: 'short' });
}

function formatTime(dateStr) {
    return new Date(dateStr).toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
}

function escHtml(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

const AVATAR_COLORS = [
    '#e53935','#d81b60','#8e24aa','#5e35b1',
    '#1e88e5','#039be5','#00acc1','#00897b',
    '#43a047','#f4511e','#fb8c00','#f6bf26',
];

function nameToColor(name) {
    let hash = 0;
    for (let i = 0; i < (name||'').length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash);
    return AVATAR_COLORS[Math.abs(hash) % AVATAR_COLORS.length];
}

function avatarHtml(url, name, size = 42) {
    const fs      = Math.round(size * 0.38);
    const initial = (name || '?')[0].toUpperCase();
    const color   = nameToColor(name);
    if (url) {
        return `<img src="${escHtml(url)}" alt="${escHtml(name)}"
            style="width:${size}px;height:${size}px;border-radius:50%;object-fit:cover;"
            onerror="this.outerHTML='<div class=\\'avatar-placeholder\\' style=\\'width:${size}px;height:${size}px;font-size:${fs}px;border-radius:50%;background:${color}\\'>${initial}</div>'">`;
    }
    return `<div class="avatar-placeholder" style="width:${size}px;height:${size}px;font-size:${fs}px;border-radius:50%;background:${color}">${initial}</div>`;
}

// ── Conversation List ─────────────────────────────────────────────────────────
async function loadConversations() {
    const res  = await fetch(`/admin/chat/api/conversations.php?platform=${state.platform}`);
    const json = await res.json();
    if (!json.ok) return;

    const items = json.data;
    if (!items.length) {
        convList.innerHTML = `<div class="conv-empty">
            <span class="material-symbols-rounded" style="font-size:32px;opacity:.3">inbox</span><br>
            ยังไม่มีข้อความ
        </div>`;
        return;
    }

    convList.innerHTML = items.map(c => {
        const isActive = c.id == state.activeConvId;
        const plat     = c.platform;
        const badge    = plat === 'facebook' ? 'fb' : 'ln';
        const label    = plat === 'facebook' ? 'FB' : 'LN';
        const unread   = c.unread_count > 0 ? `<span class="unread-badge">${c.unread_count}</span>` : '';
        const preview  = c.last_message_preview ? escHtml(c.last_message_preview) : '<em>ยังไม่มีข้อความ</em>';
        const time     = c.last_message_at ? timeAgo(c.last_message_at) : '';
        const av       = avatarHtml(c.picture_url, c.display_name, 42);

        return `<div class="conv-item ${isActive ? 'active' : ''}" data-id="${c.id}" data-name="${escHtml(c.display_name)}" data-platform="${plat}" data-pic="${escHtml(c.picture_url ?? '')}">
            <div class="conv-avatar">
                ${av}
                <span class="platform-badge ${badge}">${label}</span>
            </div>
            <div class="conv-body">
                <div class="conv-name">${escHtml(c.display_name || 'ไม่ระบุชื่อ')}</div>
                <div class="conv-preview">${preview}</div>
            </div>
            <div class="conv-meta">
                <span class="conv-time">${time}</span>
                ${unread}
            </div>
        </div>`;
    }).join('');

    // Re-attach click handlers
    convList.querySelectorAll('.conv-item').forEach(el => {
        el.addEventListener('click', () => openConversation({
            id:           parseInt(el.dataset.id),
            display_name: el.dataset.name,
            platform:     el.dataset.platform,
            picture_url:  el.dataset.pic,
        }));
    });
}

// ── Open Conversation ─────────────────────────────────────────────────────────
function openConversation(conv) {
    state.activeConvId = conv.id;
    state.activeConv   = conv;
    state.lastMsgId    = 0;

    // Update header
    const plat  = conv.platform;
    const badge = plat === 'facebook' ? 'fb' : 'ln';
    const label = plat === 'facebook' ? 'Facebook Messenger' : 'LINE';

    if (conv.picture_url) {
        headerAvatar.src             = conv.picture_url;
        headerAvatar.style.display   = 'block';
        headerAvatarFB.style.display = 'none';
    } else {
        headerAvatar.style.display   = 'none';
        headerAvatarFB.style.display = 'flex';
        const initial = (conv.display_name || '?')[0].toUpperCase();
        headerAvatarFB.style.background = nameToColor(conv.display_name);
        headerAvatarFB.style.color      = '#fff';
        headerAvatarFB.style.fontWeight = '700';
        headerAvatarFB.textContent      = initial;
    }
    headerBadge.className  = `platform-badge ${badge}`;
    headerBadge.textContent = plat === 'facebook' ? 'FB' : 'LN';
    headerName.textContent  = conv.display_name || 'ไม่ระบุชื่อ';
    headerDot.className    = `platform-dot ${badge}`;
    headerLabel.textContent = label;

    // Show panels
    chatEmptyState.style.display  = 'none';
    chatHeader.style.display      = 'flex';
    chatMessages.style.display    = 'flex';
    chatInputArea.style.display   = 'flex';
    chatMessages.innerHTML        = '';

    // Mark active in list
    document.querySelectorAll('.conv-item').forEach(el => {
        el.classList.toggle('active', parseInt(el.dataset.id) === conv.id);
    });

    // Load messages
    loadMessages(true);

    // Start polling messages
    clearInterval(state.msgTimer);
    state.msgTimer = setInterval(() => loadMessages(false), 2000);
}

// ── Messages ──────────────────────────────────────────────────────────────────
let lastDateLabel = '';

async function loadMessages(initial) {
    if (!state.activeConvId) return;
    const url = `/admin/chat/api/messages.php?conv_id=${state.activeConvId}&after_id=${state.lastMsgId}`;
    const res  = await fetch(url);
    const json = await res.json();
    if (!json.ok || !json.data.length) return;

    const msgs    = json.data;
    const wasAtBottom = isAtBottom();

    msgs.forEach(msg => {
        const dateLabel = new Date(msg.sent_at).toLocaleDateString('th-TH', { weekday:'long', day:'numeric', month:'long', year:'numeric' });
        if (dateLabel !== lastDateLabel) {
            lastDateLabel = dateLabel;
            chatMessages.insertAdjacentHTML('beforeend', `<div class="date-divider">${dateLabel}</div>`);
        }

        const dir     = msg.direction;
        const av      = dir === 'incoming' ? avatarHtml(state.activeConv.picture_url, state.activeConv.display_name, 28) : '';
        const bubble  = renderBubble(msg);
        const time    = formatTime(msg.sent_at);

        chatMessages.insertAdjacentHTML('beforeend', `
            <div class="msg-row ${dir}">
                ${dir === 'incoming' ? `<div class="msg-avatar">${av}</div>` : ''}
                <div>
                    ${bubble}
                    <div class="msg-time">${time}</div>
                </div>
            </div>
        `);

        state.lastMsgId = Math.max(state.lastMsgId, parseInt(msg.id));
    });

    if (initial || wasAtBottom) chatMessages.scrollTop = chatMessages.scrollHeight;

    // Refresh conv list to clear unread badge
    if (initial) loadConversations();
}

function renderBubble(msg) {
    if (msg.message_type === 'text') {
        return `<div class="msg-bubble">${escHtml(msg.content).replace(/\n/g,'<br>')}</div>`;
    }
    if (msg.message_type === 'image' && msg.media_url && !msg.media_url.startsWith('line_media:')) {
        return `<div class="msg-bubble" style="padding:4px"><img src="${escHtml(msg.media_url)}" alt="image"></div>`;
    }
    const icons = { sticker:'sentiment_satisfied', audio:'mic', video:'videocam', file:'attach_file', image:'image' };
    const icon  = icons[msg.message_type] || 'attachment';
    return `<div class="msg-bubble" style="display:flex;align-items:center;gap:6px;opacity:.7">
        <span class="material-symbols-rounded" style="font-size:18px">${icon}</span>
        [${msg.message_type}]
    </div>`;
}

function isAtBottom() {
    return chatMessages.scrollHeight - chatMessages.scrollTop - chatMessages.clientHeight < 60;
}

// ── Send Message ──────────────────────────────────────────────────────────────
async function sendMessage() {
    const text = chatInput.value.trim();
    if (!text || !state.activeConvId) return;

    sendBtn.disabled     = true;
    chatInput.disabled   = true;

    const res  = await fetch('/admin/chat/api/send.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ conv_id: state.activeConvId, text }),
    });
    const json = await res.json();

    sendBtn.disabled   = false;
    chatInput.disabled = false;
    chatInput.focus();

    if (json.ok) {
        chatInput.value = '';
        chatInput.style.height = '';
        loadMessages(false);
    } else {
        alert('ส่งไม่สำเร็จ: ' + (json.msg || 'unknown error'));
    }
}

// ── Platform Tabs ─────────────────────────────────────────────────────────────
document.querySelectorAll('.chat-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.chat-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        state.platform = tab.dataset.platform;
        loadConversations();
    });
});

// ── Input auto-resize + keyboard shortcut ─────────────────────────────────────
chatInput.addEventListener('input', () => {
    chatInput.style.height = '';
    chatInput.style.height = Math.min(chatInput.scrollHeight, 120) + 'px';
});

chatInput.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

sendBtn.addEventListener('click', sendMessage);

// ── Bootstrap ─────────────────────────────────────────────────────────────────
loadConversations();
state.convTimer = setInterval(loadConversations, 3000);
</script>
</body>
</html>
