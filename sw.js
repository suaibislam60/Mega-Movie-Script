// Service Worker for PWA
const CACHE_NAME = 'streamflix-v1';
const urlsToCache = [
    '/',
    '/index.html',
    '/manifest.json',
    // Add other static assets
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(urlsToCache))
    );
});

self.addEventListener('fetch', event => {
    event.respondWith(
        caches.match(event.request)
            .then(response => response || fetch(event.request))
    );
});