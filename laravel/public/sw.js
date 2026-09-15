/**
 * BuildXact Saudi — minimal, safe service worker.
 *
 * Scope: PWA installability + a static-asset cache + a genuine offline-fallback page.
 * This is NOT an offline-first data layer — it never touches /app/* page content,
 * /uploads/* files, or any form/API response. Those stay authenticated and always fresh.
 *
 * - Static assets (CSS/JS/icons/manifest): cache-first, refreshed in the background cache
 *   as they're fetched.
 * - Navigation requests (loading a page): always try the network first; only on failure
 *   (no connection) do we hand back the offline fallback page instead of a broken tab.
 * - Everything else, and every non-GET request (form submits, uploads): passed straight
 *   through to the network untouched — never intercepted, never queued, never swallowed.
 */

const CACHE_NAME = 'buildxact-static-v1';
const OFFLINE_URL = '/offline.html';

const PRECACHE_URLS = [
  '/manifest.json',
  OFFLINE_URL,
  '/assets/css/app.css',
  '/assets/js/password-toggle.js',
  '/assets/js/site.js',
  '/assets/icons/icon-192.png',
  '/assets/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(PRECACHE_URLS))
      .then(() => self.skipWaiting())
      .catch(() => { /* a missing precache asset must never block install */ })
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
      .then(() => self.clients.claim())
  );
});

function isCacheableStaticAsset(url) {
  if (url.origin !== self.location.origin) {
    return false;
  }
  return url.pathname.startsWith('/assets/')
    || url.pathname === '/manifest.json'
    || url.pathname === OFFLINE_URL;
}

self.addEventListener('fetch', (event) => {
  const request = event.request;

  // Never intercept non-GET requests — a POST (login, form submit, photo/document upload)
  // must reach the network or fail naturally, never be silently queued or swallowed here.
  if (request.method !== 'GET') {
    return;
  }

  const url = new URL(request.url);

  if (isCacheableStaticAsset(url)) {
    event.respondWith(
      caches.match(request).then((cached) => {
        if (cached) {
          return cached;
        }
        return fetch(request).then((response) => {
          if (response && response.ok) {
            const copy = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
          }
          return response;
        });
      })
    );
    return;
  }

  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() => caches.match(OFFLINE_URL))
    );
    return;
  }

  // Authenticated /app/* page data, /uploads/*, and everything else: always network, never cached.
});
