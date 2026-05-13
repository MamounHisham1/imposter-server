const CACHE_NAME = 'traitor-v1';
const ASSETS_TO_CACHE = [
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
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS_TO_CACHE);
    })
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(clients.claim());
});

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // Only handle same-origin navigation and static asset requests.
  // Skip HMR, WebSocket, hot-update, API calls, and cross-origin requests.
  if (
    url.origin !== location.origin ||
    url.pathname.startsWith('/api') ||
    url.pathname.startsWith('/broadcasting') ||
    url.pathname.includes('hot-update') ||
    url.pathname.includes('__vite') ||
    url.pathname.startsWith('/@') ||
    event.request.mode === 'websocket'
  ) {
    return; // Let the browser handle these normally
  }

  event.respondWith(
    caches.match(event.request).then((cachedResponse) => {
      if (cachedResponse) {
        return cachedResponse;
      }

      return fetch(event.request).catch(() => {
        // If it's a navigation request and fetch fails, serve the cached root page
        if (event.request.mode === 'navigate') {
          return caches.match('/');
        }
        // For other requests, return a simple offline response
        return new Response('Offline', {
          status: 503,
          statusText: 'Service Unavailable'
        });
      });
    })
  );
});
