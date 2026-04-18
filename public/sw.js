const CACHE_NAME = 'videotool-cache-v2';
const PRECACHE_URLS = [
  '/',
  '/manifest.json'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(PRECACHE_URLS))
  );
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => Promise.all(
      keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))
    ))
  );
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  const { request } = event;
  if (request.method !== 'GET') {
    return;
  }

  const url = new URL(request.url);
  // Avoid stale admin UI scripts/styles in SW cache.
  if (url.pathname.startsWith('/admin.php') || url.pathname.startsWith('/static/admin/')) {
    event.respondWith(fetch(request));
    return;
  }

  // HTML: network-first, fallback to cached shell.
  if (request.headers.get('accept')?.includes('text/html')) {
    event.respondWith(
      fetch(request).catch(() => caches.match('/'))
    );
    return;
  }

  // Static assets: cache-first.
  event.respondWith(
    caches.match(request).then(cached => cached || fetch(request))
  );
});
