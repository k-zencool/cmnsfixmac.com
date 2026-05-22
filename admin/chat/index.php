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
    <link rel="stylesheet" href="/assets/css/chat_admin.css?v=13">
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
                <button class="chat-tab active" data-platform="all" data-archived="0"><span class="tab-label">ทั้งหมด</span></button>
                <button class="chat-tab" data-platform="facebook" data-archived="0">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="#1877f2" style="flex-shrink:0"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    <span class="tab-label">Facebook</span>
                </button>
                <button class="chat-tab" data-platform="line" data-archived="0">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="#06c755" style="flex-shrink:0"><path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.104.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63.346 0 .628.285.628.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.282.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/></svg>
                    <span class="tab-label">LINE</span>
                </button>
                <button class="chat-tab" data-platform="all" data-archived="1">
                    <span class="material-symbols-rounded" style="font-size:13px;flex-shrink:0">inventory_2</span>
                    <span class="tab-label">จัดเก็บ</span>
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

    <!-- Resize handle -->
    <div class="chat-resize-handle" id="chatResizeHandle"></div>

    <!-- RIGHT: Chat window -->
    <div class="chat-main" id="chatMain">
        <div class="chat-empty-state" id="chatEmptyState">
            <span class="material-symbols-rounded">forum</span>
            <div style="font-size:0.9rem">เลือกการสนทนาเพื่อเริ่มแชท</div>
        </div>

        <!-- Header (hidden until conv selected) -->
        <div class="chat-header" id="chatHeader" style="display:none">
            <button class="chat-back-btn" id="backBtn" title="กลับ">
                <span class="material-symbols-rounded">arrow_back_ios</span>
            </button>
            <div class="chat-header-avatar">
                <img id="headerAvatar" src="" alt="" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <div class="avatar-placeholder" id="headerAvatarFallback" style="display:none">
                    <span class="material-symbols-rounded">person</span>
                </div>
                <span class="platform-badge" id="headerPlatformBadge"></span>
            </div>
            <div class="chat-header-info">
                <div class="chat-header-name-wrap">
                    <span class="chat-header-name" id="headerName"></span>
                    <button class="chat-rename-btn" id="renameBtn" title="แก้ไขชื่อ">
                        <span class="material-symbols-rounded">edit</span>
                    </button>
                </div>
                <div class="chat-header-platform">
                    <span class="platform-dot" id="headerPlatformDot"></span>
                    <span id="headerPlatformLabel"></span>
                </div>
            </div>
            <div class="chat-header-actions">
                <button class="chat-action-btn" id="archiveBtn" title="จัดเก็บแชท">
                    <span class="material-symbols-rounded">archive</span>
                </button>
                <button class="chat-action-btn danger" id="deleteBtn" title="ลบแชท">
                    <span class="material-symbols-rounded">delete</span>
                </button>
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
    platform:      'all',
    archived:      0,
    activeConvId:  null,
    activeConv:    null,
    lastMsgId:     0,
    firstMsgId:    Infinity,
    hasMoreHistory: false,
    loadingHistory: false,
    lastMsgDir:    null,
    convTimer:     null,
    msgTimer:      null,
};

// ── DOM refs ─────────────────────────────────────────────────────────────────
const chatWrap        = document.querySelector('.chat-wrap');
const convList        = document.getElementById('convList');
const chatMain        = document.getElementById('chatMain');
const chatEmptyState  = document.getElementById('chatEmptyState');
const chatHeader      = document.getElementById('chatHeader');
const chatMessages    = document.getElementById('chatMessages');
const chatInputArea   = document.getElementById('chatInputArea');
const chatInput       = document.getElementById('chatInput');
const sendBtn         = document.getElementById('sendBtn');
const backBtn         = document.getElementById('backBtn');
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
    const res  = await fetch(`/admin/chat/api/conversations.php?platform=${state.platform}&archived=${state.archived}`);
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
    state.lastMsgDir   = null;

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
    headerBadge.className   = `platform-badge ${badge}`;
    headerBadge.textContent = plat === 'facebook' ? 'FB' : 'LN';
    headerName.textContent  = conv.display_name || 'ไม่ระบุชื่อ';
    headerDot.className     = `platform-dot ${badge}`;
    headerLabel.textContent = label;

    const isPlaceholder = !conv.display_name || ['Facebook User', 'LINE User', ''].includes(conv.display_name);
    document.getElementById('renameBtn').classList.toggle('needs-name', isPlaceholder);
    document.getElementById('renameBtn').title = isPlaceholder ? 'ยังไม่มีชื่อ — คลิกแก้ไข' : 'แก้ไขชื่อ';

    // Show panels
    chatEmptyState.style.display = 'none';
    chatHeader.style.display     = 'flex';
    chatMessages.style.display   = 'flex';
    chatInputArea.style.display  = 'flex';
    chatMessages.innerHTML       = '<div class="chat-spacer"></div>';
    lastDateLabel                = '';
    state.firstMsgId             = Infinity;
    state.hasMoreHistory         = false;
    state.loadingHistory         = false;

    // Mobile: switch to chat view
    if (window.innerWidth <= 768) {
        chatWrap.classList.add('mobile-chat-open');
    }

    // Mark active in list
    document.querySelectorAll('.conv-item').forEach(el => {
        el.classList.toggle('active', parseInt(el.dataset.id) === conv.id);
    });

    loadMessages(true);
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

    const msgs        = json.data;
    const wasAtBottom = isAtBottom();

    msgs.forEach((msg, idx) => {
        const dateLabel = new Date(msg.sent_at).toLocaleDateString('th-TH', { weekday:'long', day:'numeric', month:'long', year:'numeric' });
        if (dateLabel !== lastDateLabel) {
            lastDateLabel     = dateLabel;
            state.lastMsgDir  = null; // reset grouping across date dividers
            chatMessages.insertAdjacentHTML('beforeend', `<div class="date-divider">${dateLabel}</div>`);
        }

        const dir     = msg.direction;
        const nextMsg = msgs[idx + 1] ?? null;
        const nextDir = nextMsg?.direction ?? null;

        const isGrouped  = (state.lastMsgDir === dir);
        const showAvatar = (dir === 'incoming') && (nextDir !== dir);

        // Show time only when: different sender next, or gap >= 5 min, or last in batch
        const TIME_GAP = 5 * 60 * 1000;
        const showTime = !nextMsg
            || nextDir !== dir
            || (new Date(nextMsg.sent_at) - new Date(msg.sent_at)) >= TIME_GAP;

        const avatarSlot = dir === 'incoming'
            ? `<div class="msg-avatar-slot ${showAvatar ? '' : 'hidden'}">${showAvatar ? avatarHtml(state.activeConv.picture_url, state.activeConv.display_name, 28) : ''}</div>`
            : '';

        const bubble = renderBubble(msg);
        const time   = formatTime(msg.sent_at);

        chatMessages.insertAdjacentHTML('beforeend', `
            <div class="msg-row ${dir}${isGrouped ? ' grouped' : ''}">
                ${avatarSlot}
                <div class="msg-content">${bubble}</div>
            </div>
            ${showTime ? `<div class="msg-time ${dir}">${time}</div>` : ''}
        `);

        state.lastMsgDir = dir;
    });

    // Track first/last msg ids
    if (msgs.length) {
        state.firstMsgId = Math.min(state.firstMsgId, parseInt(msgs[0].id));
        state.lastMsgId  = Math.max(state.lastMsgId,  parseInt(msgs[msgs.length - 1].id));
    }

    if (initial || wasAtBottom) chatMessages.scrollTop = chatMessages.scrollHeight;

    if (initial) {
        loadConversations();
        state.hasMoreHistory = !!json.has_more;
        if (state.hasMoreHistory) showLoadMoreBtn();
    }
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

// ── Load history (scroll up) ──────────────────────────────────────────────────
function showLoadMoreBtn() {
    const existing = chatMessages.querySelector('.chat-load-more');
    if (existing) return;
    const spacer = chatMessages.querySelector('.chat-spacer');
    const div = document.createElement('div');
    div.className = 'chat-load-more';
    div.innerHTML = '<button onclick="loadHistory()">โหลดข้อความเก่า</button>';
    chatMessages.insertBefore(div, spacer ? spacer.nextSibling : chatMessages.firstChild);
}

async function loadHistory() {
    if (!state.activeConvId || state.loadingHistory || state.firstMsgId === Infinity) return;
    state.loadingHistory = true;

    const btn = chatMessages.querySelector('.chat-load-more button');
    if (btn) btn.textContent = 'กำลังโหลด...';

    const url  = `/admin/chat/api/messages.php?conv_id=${state.activeConvId}&before_id=${state.firstMsgId}`;
    const res  = await fetch(url);
    const json = await res.json();
    state.loadingHistory = false;

    if (!json.ok || !json.data.length) {
        chatMessages.querySelector('.chat-load-more')?.remove();
        return;
    }

    // Remember scroll position before prepending
    const prevHeight = chatMessages.scrollHeight;

    const frag        = document.createDocumentFragment();
    const insertAfter = chatMessages.querySelector('.chat-load-more') || chatMessages.querySelector('.chat-spacer');
    let   localDateLabel = '';
    let   localLastDir   = null;

    json.data.forEach((msg, idx) => {
        const dateLabel = new Date(msg.sent_at).toLocaleDateString('th-TH', { weekday:'long', day:'numeric', month:'long', year:'numeric' });
        if (dateLabel !== localDateLabel) {
            localDateLabel = dateLabel;
            localLastDir   = null;
            const d = document.createElement('div');
            d.className = 'date-divider';
            d.textContent = dateLabel;
            frag.appendChild(d);
        }

        const dir       = msg.direction;
        const nextMsg   = json.data[idx + 1] ?? null;
        const nextDir   = nextMsg?.direction ?? null;
        const isGrouped = (localLastDir === dir);
        const showAvatar = (dir === 'incoming') && (nextDir !== dir);
        const TIME_GAP   = 5 * 60 * 1000;
        const showTime   = !nextMsg || nextDir !== dir || (new Date(nextMsg.sent_at) - new Date(msg.sent_at)) >= TIME_GAP;

        const avatarSlot = dir === 'incoming'
            ? `<div class="msg-avatar-slot ${showAvatar ? '' : 'hidden'}">${showAvatar ? avatarHtml(state.activeConv.picture_url, state.activeConv.display_name, 28) : ''}</div>`
            : '';

        const row = document.createElement('div');
        row.innerHTML = `
            <div class="msg-row ${dir}${isGrouped ? ' grouped' : ''}">
                ${avatarSlot}
                <div class="msg-content">${renderBubble(msg)}</div>
            </div>
            ${showTime ? `<div class="msg-time ${dir}">${formatTime(msg.sent_at)}</div>` : ''}
        `;
        [...row.childNodes].forEach(n => { if (n.nodeType === 1 || (n.nodeType === 3 && n.textContent.trim())) frag.appendChild(n); });

        localLastDir = dir;
    });

    // Insert history block after load-more button
    insertAfter.after(frag);

    // Update firstMsgId
    state.firstMsgId = parseInt(json.data[0].id);

    // Remove button if no more
    if (!json.has_more) {
        chatMessages.querySelector('.chat-load-more')?.remove();
        state.hasMoreHistory = false;
    } else if (btn) {
        btn.textContent = 'โหลดข้อความเก่า';
    }

    // Maintain scroll position (don't jump to top)
    chatMessages.scrollTop += chatMessages.scrollHeight - prevHeight;
}

// Auto-trigger loadHistory when scrolled near top
chatMessages.addEventListener('scroll', () => {
    if (chatMessages.scrollTop < 80 && state.hasMoreHistory && !state.loadingHistory) {
        loadHistory();
    }
});

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

// ── Rename contact ────────────────────────────────────────────────────────────
document.getElementById('renameBtn').addEventListener('click', async () => {
    if (!state.activeConvId) return;
    const current = state.activeConv?.display_name || '';
    const newName = prompt('แก้ไขชื่อลูกค้า:', current);
    if (!newName || newName.trim() === current) return;

    const res  = await fetch('/admin/chat/api/rename_contact.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ conv_id: state.activeConvId, name: newName.trim() }),
    });
    const json = await res.json();
    if (json.ok) {
        state.activeConv.display_name = newName.trim();
        headerName.textContent = newName.trim();
        loadConversations();
    }
});

// ── Archive conversation ──────────────────────────────────────────────────────
document.getElementById('archiveBtn').addEventListener('click', async () => {
    if (!state.activeConvId) return;
    const btn = document.getElementById('archiveBtn');
    btn.disabled = true;
    const res  = await fetch('/admin/chat/api/archive_conversation.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ conv_id: state.activeConvId }),
    });
    const json = await res.json();
    btn.disabled = false;
    if (json.ok) {
        chatWrap.classList.remove('mobile-chat-open');
        state.activeConvId = null;
        state.activeConv   = null;
        chatHeader.style.display    = 'none';
        chatMessages.style.display  = 'none';
        chatInputArea.style.display = 'none';
        chatEmptyState.style.display = 'flex';
        clearInterval(state.msgTimer);
        loadConversations();
    }
});

// ── Delete conversation ───────────────────────────────────────────────────────
document.getElementById('deleteBtn').addEventListener('click', async () => {
    if (!state.activeConvId) return;
    if (!confirm(`ลบการสนทนากับ "${state.activeConv?.display_name}" ออกถาวรเลยใช่ไหม?\nข้อความทั้งหมดจะหายไป`)) return;
    const btn = document.getElementById('deleteBtn');
    btn.disabled = true;
    const res  = await fetch('/admin/chat/api/delete_conversation.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ conv_id: state.activeConvId }),
    });
    const json = await res.json();
    btn.disabled = false;
    if (json.ok) {
        chatWrap.classList.remove('mobile-chat-open');
        state.activeConvId = null;
        state.activeConv   = null;
        chatHeader.style.display    = 'none';
        chatMessages.style.display  = 'none';
        chatInputArea.style.display = 'none';
        chatEmptyState.style.display = 'flex';
        clearInterval(state.msgTimer);
        loadConversations();
    } else {
        alert('ลบไม่สำเร็จ: ' + (json.msg || ''));
    }
});

// ── Back button (mobile) ──────────────────────────────────────────────────────
backBtn.addEventListener('click', () => {
    chatWrap.classList.remove('mobile-chat-open');
    clearInterval(state.msgTimer);
    state.activeConvId = null;
    state.activeConv   = null;
});

// ── Platform Tabs ─────────────────────────────────────────────────────────────
document.querySelectorAll('.chat-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.chat-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        state.platform = tab.dataset.platform;
        state.archived = parseInt(tab.dataset.archived ?? '0');
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

// ── Resizable sidebar ─────────────────────────────────────────────────────────
const SIDEBAR_MIN = 200;
const SIDEBAR_MAX = 520;
const SIDEBAR_KEY = 'chat_sidebar_w';
const resizeHandle = document.getElementById('chatResizeHandle');

(function initSidebarWidth() {
    const saved = parseInt(localStorage.getItem(SIDEBAR_KEY));
    if (saved && saved >= SIDEBAR_MIN && saved <= SIDEBAR_MAX) {
        chatWrap.style.setProperty('--chat-sidebar-w', saved + 'px');
    }
})();

resizeHandle.addEventListener('mousedown', e => {
    e.preventDefault();
    resizeHandle.classList.add('dragging');
    document.body.style.cursor = 'col-resize';
    document.body.style.userSelect = 'none';

    function onMove(e) {
        const rect = chatWrap.getBoundingClientRect();
        let w = e.clientX - rect.left;
        w = Math.max(SIDEBAR_MIN, Math.min(SIDEBAR_MAX, w));
        chatWrap.style.setProperty('--chat-sidebar-w', w + 'px');
    }

    function onUp() {
        resizeHandle.classList.remove('dragging');
        document.body.style.cursor = '';
        document.body.style.userSelect = '';
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
        const w = getComputedStyle(chatWrap).getPropertyValue('--chat-sidebar-w').trim();
        localStorage.setItem(SIDEBAR_KEY, parseInt(w));
    }

    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onUp);
});

// ── Bootstrap ─────────────────────────────────────────────────────────────────
loadConversations();
state.convTimer = setInterval(loadConversations, 3000);
</script>
</body>
</html>
