/**
 * Atelier — service worker
 *
 * Scope is deliberately narrow. This is a server-rendered, database-driven app,
 * so it cannot meaningfully run offline; the worker exists to make the app
 * installable, to serve static assets instantly, and to show a branded offline
 * screen instead of the browser's error page when the server is unreachable.
 *
 * Authenticated HTML is never cached, so no user's data can leak to the next
 * person to open the app on a shared machine.
 */

const VERSION = 'atelier-v1';
const STATIC_CACHE = `${VERSION}-static`;
const OFFLINE_URL = '/offline.html';

const PRECACHE = [OFFLINE_URL];

/* ---------------------------------------------------------------- install */
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then((cache) => cache.addAll(PRECACHE))
      .then(() => self.skipWaiting())
  );
});

/* --------------------------------------------------------------- activate */
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys.filter((key) => !key.startsWith(VERSION)).map((key) => caches.delete(key))
      ))
      .then(() => self.clients.claim())
  );
});

/* ------------------------------------------------------------------ fetch */
function isStaticAsset(url) {
  return url.pathname.startsWith('/build/assets/')
      || url.pathname.startsWith('/icons/')
      || /\.(?:css|js|woff2?|ttf|otf|eot|png|jpe?g|gif|svg|webp|ico)$/i.test(url.pathname);
}

self.addEventListener('fetch', (event) => {
  const { request } = event;

  // Only ever interfere with same-origin GETs. Anything that mutates state, and
  // anything cross-origin, goes straight to the network untouched.
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  // Live polling endpoints must always hit the server.
  if (url.pathname.startsWith('/live/')) return;

  /* Static assets: cache-first. They are content-hashed by Vite, so a cached
     copy is always safe to reuse. */
  if (isStaticAsset(url)) {
    event.respondWith(
      caches.match(request).then((cached) => {
        if (cached) return cached;

        return fetch(request).then((response) => {
          if (response.ok && response.type === 'basic') {
            const copy = response.clone();
            caches.open(STATIC_CACHE).then((cache) => cache.put(request, copy));
          }
          return response;
        });
      })
    );
    return;
  }

  /* Page navigations: network-only, with the offline screen as a fallback.
     Never cached — these responses are authenticated. */
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() => caches.match(OFFLINE_URL))
    );
  }
});

/* ---------------------------------------------------------------- updates */
self.addEventListener('message', (event) => {
  if (event.data === 'SKIP_WAITING') self.skipWaiting();
});
