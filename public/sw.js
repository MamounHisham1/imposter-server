const CACHE_NAME = 'traitor-v3';
const PRECACHE_URLS = [
  '/',
  '/manifest.json',
  '/logo.png',
  '/logo-192.png',
  '/logo-512.png',
  '/favicon.ico'
];

self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE_URLS))
  );
});

self.addEventListener('activate', (event) => {
  // Delete old caches so stale hashed assets are removed
  event.waitUntil(
    caches.keys().then((names) =>
      Promise.all(
        names
          .filter((name) => name !== CACHE_NAME)
          .map((name) => caches.delete(name))
      )
    ).then(() => clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // Only handle GET requests — POST/PUT/DELETE are not cacheable
  if (event.request.method !== 'GET') {
    return;
  }

  if (
    url.origin !== location.origin ||
    url.pathname.startsWith('/api') ||
    url.pathname.startsWith('/broadcasting') ||
    url.pathname.includes('hot-update') ||
    url.pathname.includes('__vite') ||
    url.pathname.startsWith('/@') ||
    event.request.mode === 'websocket'
  ) {
    return;
  }

  // Navigation: network-first so HTML always fresh (brings new Vite hashes)
  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request)
        .then((response) => {
          const clone = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
          return response;
        })
        .catch(() => caches.match('/'))
    );
    return;
  }

  // Build assets (/build/assets/*): network-only — Vite hashes handle cache busting
  if (url.pathname.startsWith('/build/assets/')) {
    return;
  }

  // Other static assets (images, manifest, etc.): cache-first
  event.respondWith(
    caches.match(event.request).then((cached) => {
      if (cached) return cached;
      return fetch(event.request).then((response) => {
        const clone = response.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
        return response;
      });
    })
  );
});
