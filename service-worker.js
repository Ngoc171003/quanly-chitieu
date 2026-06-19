const CACHE_NAME = 'chi-tieu-pwa-v3';
const urlsToCache = [
  './',
  './index.php',
  './dashboard.php',
  './transactions.php',
  './add-transaction.php',
  './categories.php',
  './budget.php',
  './statistics.php',
  './profile.php',
  './login.php',
  './manifest.json',
  './public/css/style.css',
  './public/js/main.js',
  './public/images/icon-192.svg',
  './public/images/icon-512.svg'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(urlsToCache))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames =>
      Promise.all(
        cacheNames.filter(cacheName => cacheName !== CACHE_NAME)
          .map(cacheName => caches.delete(cacheName))
      )
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') return;

  event.respondWith(
    fetch(event.request).then(networkResponse => {
      // If we get a successful response from network, update cache
      return caches.open(CACHE_NAME).then(cache => {
        if (event.request.url.startsWith(self.location.origin) && networkResponse.status === 200) {
          cache.put(event.request, networkResponse.clone());
        }
        return networkResponse;
      });
    }).catch(() => {
      // If network fails, try to get it from cache (ignoring search query parameters)
      return caches.match(event.request, { ignoreSearch: true }).then(cachedResponse => {
        if (cachedResponse) {
          return cachedResponse;
        }
        // Fallback for missing images/assets
        return caches.match('./public/images/icon-192.svg');
      });
    })
  );
});
