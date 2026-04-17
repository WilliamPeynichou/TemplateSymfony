/* Andfield — Service Worker minimal pour PWA.
 *
 * Stratégie :
 * - Cache shell (HTML de base) au install.
 * - Network-first pour les requêtes GET de pages, fallback cache.
 * - Cache-first pour les assets statiques (/assets/, /icons/).
 * - On ne met JAMAIS en cache les requêtes POST/PATCH/DELETE ni les routes /api.
 */

const CACHE_VERSION = 'andfield-v1';
const SHELL_URLS = ['/', '/pricing', '/offline'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_VERSION).then((c) => c.addAll(SHELL_URLS).catch(() => {}))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE_VERSION).map((k) => caches.delete(k)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);
    if (url.pathname.startsWith('/api/')) return; // pass-through API

    if (url.pathname.startsWith('/assets/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(
            caches.match(req).then((hit) => hit || fetch(req).then((resp) => {
                const copy = resp.clone();
                caches.open(CACHE_VERSION).then((c) => c.put(req, copy)).catch(() => {});
                return resp;
            }))
        );
        return;
    }

    event.respondWith(
        fetch(req)
            .then((resp) => {
                const copy = resp.clone();
                caches.open(CACHE_VERSION).then((c) => c.put(req, copy)).catch(() => {});
                return resp;
            })
            .catch(() => caches.match(req).then((hit) => hit || caches.match('/offline')))
    );
});
