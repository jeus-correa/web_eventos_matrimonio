/* Service Worker mínimo para PWA (caché de shell básico). */
const CACHE = 'recuerdos-v1';
const SHELL = ['./index.php', './assets/css/style.css', './assets/js/script.js', './manifest.webmanifest'];

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(CACHE).then((cache) => cache.addAll(SHELL).catch(() => cache.add('./index.php')))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
  );
  self.clients.claim();
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  e.respondWith(
    fetch(req).catch(() => caches.match(req).then((r) => r || caches.match('./index.php')))
  );
});
