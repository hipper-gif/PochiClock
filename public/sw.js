const CACHE_NAME = 'pochiclock-v1';
const STATIC_ASSETS = [
    '/dashboard',
    '/manifest.json',
];

// Install - cache static assets
self.addEventListener('install', (event) => {
    self.skipWaiting();
});

// Activate - clean old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(clients.claim());
});

// Fetch - network first, no offline fallback (by design)
self.addEventListener('fetch', (event) => {
    event.respondWith(
        fetch(event.request).catch(() => {
            // No offline support for now
            return new Response('オフラインです。インターネット接続を確認してください。', {
                headers: { 'Content-Type': 'text/html; charset=utf-8' }
            });
        })
    );
});
