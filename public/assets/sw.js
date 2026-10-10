const CACHE_NAME = 'lotacoes-bh-v2';

// Apenas os recursos estáticos essenciais da interface (App Shell)
const STATIC_ASSETS = [
    '/',
    '/assets/style.css',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'
];

// Instalação: Guarda a estrutura visual em cache
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                return cache.addAll(STATIC_ASSETS);
            })
            .then(() => self.skipWaiting())
    );
});

// Ativação: Limpa caches antigos se houver atualização de versão
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.map((key) => {
                    if (key !== CACHE_NAME) {
                        return caches.delete(key);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Interceção de requisições
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Se for requisição de dados dos ônibus (/onibus/...), NÃO usa cache. Vai direto à rede para garantir tempo real.
    if (url.pathname.includes('/onibus/')) {
        return; 
    }

    // Para os arquivos estáticos: tenta o cache primeiro, se falhar busca na rede
    event.respondWith(
        caches.match(event.request)
            .then((response) => {
                return response || fetch(event.request);
            })
    );
});