const CACHE_NAME = 'staff-attendance-v2';

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
