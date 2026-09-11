const CACHE_NAME = 'artsci-attendance-v1';

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(clients.claim());
});

self.addEventListener('fetch', (event) => {
  // Let network handle dynamic API requests
  event.respondWith(fetch(event.request).catch(() => caches.match(event.request)));
});
