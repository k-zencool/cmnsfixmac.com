/* CMNS Admin service worker — makes the admin panel installable as an app.
   Strategy: cache static assets (stale-while-revalidate); ALWAYS hit the
   network for dynamic PHP so admin data is never served stale. */
const CACHE = 'cmns-admin-v3';
const ASSET_RE = /\.(?:css|js|png|jpe?g|webp|svg|woff2?|ico|gif)$/i;

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

// ── Web Push: โชว์ noti จาก server (payload JSON: {title, body, url, tag}) ──
self.addEventListener('push', (e) => {
  let d = {};
  try { d = e.data ? e.data.json() : {}; } catch (err) { d = { body: e.data && e.data.text() }; }
  const title = d.title || 'CMNS FixMac';
  e.waitUntil(self.registration.showNotification(title, {
    body:  d.body || '',
    icon:  '/assets/img/favicon1.png',
    badge: '/assets/img/favicon1.png',
    tag:   d.tag || 'cmns',           // noti ประเภทเดียวกันทับกัน ไม่กองเป็นตั้ง
    data:  { url: d.url || '/admin/dashboard/' },
  }));
});

// คลิก noti → โฟกัสแท็บแอปที่เปิดอยู่ ไม่มีค่อยเปิดใหม่
self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const url = (e.notification.data && e.notification.data.url) || '/admin/dashboard/';
  e.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
      for (const c of list) {
        if (c.url.includes('/admin/') && 'focus' in c) { c.navigate(url); return c.focus(); }
      }
      return clients.openWindow(url);
    })
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;                 // never touch POST/PUT/DELETE
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;  // let CDN fonts etc. pass through
  if (!ASSET_RE.test(url.pathname)) return;         // dynamic PHP → default network, always fresh

  // stale-while-revalidate for static assets only
  e.respondWith(
    caches.open(CACHE).then((cache) =>
      cache.match(req).then((cached) => {
        const net = fetch(req)
          .then((res) => { if (res && res.ok) cache.put(req, res.clone()); return res; })
          .catch(() => cached);
        return cached || net;
      })
    )
  );
});
