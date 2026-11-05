/*
 * Admin Leonfrutta service worker.
 *
 * The admin panel is private and always live, so pages and Livewire requests
 * are never cached: they always go to the network. The worker only keeps a
 * static offline page, shown when a page cannot be loaded without connection,
 * and displays the push notifications sent when a new order arrives.
 */

const CACHE_NAME = 'leonfrutta-admin-v3';
const DEFAULT_NOTIFICATION_URL = '/admin';
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

// Payload built by App\Notifications\NewOrderReceived (title, body, icon, tag, data.url...).
self.addEventListener('push', (event) => {
    let payload = {};

    try {
        payload = event.data ? event.data.json() : {};
    } catch {
        payload = { body: event.data ? event.data.text() : '' };
    }

    const { title = 'Admin Leonfrutta', ...options } = payload;

    event.waitUntil(self.registration.showNotification(title, options));
});

// Tapping the notification opens the order, reusing the app window when it is already open.
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl = new URL(event.notification.data?.url ?? DEFAULT_NOTIFICATION_URL, self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            const appWindow = windows.find((client) => new URL(client.url).origin === self.location.origin);

            if (appWindow) {
                return appWindow.focus().then((client) => client.navigate(targetUrl));
            }

            return self.clients.openWindow(targetUrl);
        }),
    );
});
