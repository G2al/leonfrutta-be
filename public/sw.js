/*
 * LeonFrutta Admin service worker.
 *
 * The admin panel is private and always live, so pages and Livewire requests
 * are never cached: they always go to the network. The worker only keeps a
 * static offline page, shown when a page cannot be loaded without connection.
 */

const CACHE_NAME = 'leonfrutta-admin-v1';
const OFFLINE_URL = '/pwa/offline.html';
const PRECACHED_URLS = [OFFLINE_URL, '/pwa/icons/icon-192.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(PRECACHED_URLS))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((cacheNames) => Promise.all(
                cacheNames
                    .filter((cacheName) => cacheName !== CACHE_NAME)
                    .map((cacheName) => caches.delete(cacheName)),
            ))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.mode !== 'navigate') {
        return;
    }

    event.respondWith(
        fetch(event.request).catch(() => caches.match(OFFLINE_URL)),
    );
});
