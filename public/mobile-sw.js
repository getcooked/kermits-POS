const CACHE = 'kermits-mobile-v1';
const SHELL = ['/app', '/mobile.webmanifest', '/kermits-logo.jpg'];
self.addEventListener('install', event => event.waitUntil(caches.open(CACHE).then(cache => cache.addAll(SHELL)).then(() => self.skipWaiting())));
self.addEventListener('activate', event => event.waitUntil(self.clients.claim()));
self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET') return;
    if (new URL(event.request.url).pathname.startsWith('/api/')) return;
    event.respondWith(fetch(event.request).then(response => {
        const copy = response.clone();
        caches.open(CACHE).then(cache => cache.put(event.request, copy));
        return response;
    }).catch(() => caches.match(event.request).then(cached => cached || caches.match('/app'))));
});
