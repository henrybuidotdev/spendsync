// Caches static build assets only. Pages and data always come from the network.
const CACHE = 'thuchi-static-v1';

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);
    const isStatic = event.request.method === 'GET'
        && url.origin === self.location.origin
        && (url.pathname.startsWith('/build/') || url.pathname === '/icon.svg');

    if (!isStatic) {
        return;
    }

    event.respondWith(
        caches.open(CACHE).then(async (cache) => {
            const cached = await cache.match(event.request);

            if (cached) {
                return cached;
            }

            const response = await fetch(event.request);

            if (response.ok) {
                cache.put(event.request, response.clone());
            }

            return response;
        }),
    );
});
