/* CMNS Admin service worker — makes the admin panel installable as an app.
   Strategy: cache static assets (stale-while-revalidate); ALWAYS hit the
   network for dynamic PHP so admin data is never served stale. */
const CACHE = 'cmns-admin-v1';
const ASSET_RE = /\.(?:css|js|png|jpe?g|webp|svg|woff2?|ico|gif)$/i;

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
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
