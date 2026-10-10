self.addEventListener('install', (e) => {
    self.skipWaiting();
});

self.addEventListener('activate', (e) => {
    return self.clients.claim();
});

self.addEventListener('fetch', (e) => {
    // Permite que as chamadas de API do autocarro passem normalmente
    e.respondWith(fetch(e.request));
});