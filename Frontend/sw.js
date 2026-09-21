/**
 * Service Worker: Barbearia VIP Borcelle
 * Estratégia Network First: sempre busca as novidades mais recentes do servidor.
 * Se estiver sem internet ou offline, usa a versão salva no cache.
 */
const CACHE_NAME = 'barbearia-vip-v3';
const ASSETS_TO_CACHE = [
  './index.html',
  './admin.html',
  './style.css',
  './script.js',
  './manifest.json',
  './icons/apple-touch-icon.png',
  './icons/icon-192.png',
  './icons/icon-512.png',
  './icons/favicon-32.png',
  './icons/favicon-64.png'
];

self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS_TO_CACHE).catch((err) => console.log('SW cache err:', err));
    })
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
      );
    }).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  // Ignora chamadas dinâmicas da API para manter dados em tempo real
  if (event.request.url.includes('/api/')) {
    return;
  }

  // Estratégia Network First: sempre busca do servidor primeiro, atualizando o cache
  event.respondWith(
    fetch(event.request)
      .then((networkResponse) => {
        if (networkResponse && networkResponse.status === 200 && event.request.method === 'GET') {
          const responseClone = networkResponse.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(event.request, responseClone));
        }
        return networkResponse;
      })
      .catch(() => caches.match(event.request))
  );
});
