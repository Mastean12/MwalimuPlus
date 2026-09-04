/* MwalimuPlus service worker — network-first for pages, stale-while-revalidate
 * for static assets, with an offline fallback page.
 *
 * Lives at the app root so its scope covers the whole site (public browse and
 * signed-in pages alike). Registered from includes/footer.php on HTTPS and on
 * http://localhost / 127.0.0.1 for Laragon development.
 */

'use strict';

var CACHE_NAME = 'mwalimuplus-v2';

var PRECACHE = [
    'assets/css/app.css',
    'assets/js/app.js',
    'assets/icons/icon-192.png',
    'offline/offline.html',
    'browse.php'
];

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function (cache) {
            return cache.addAll(PRECACHE);
        }).then(function () {
            return self.skipWaiting();
        })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(
                keys.filter(function (key) { return key !== CACHE_NAME; })
                    .map(function (key) { return caches.delete(key); })
            );
        }).then(function () {
            return self.clients.claim();
        })
    );
});

function shouldSkip(request) {
    var url = new URL(request.url);

    // Only same-origin GET traffic is cached.
    if (request.method !== 'GET' || url.origin !== location.origin) {
        return true;
    }

    // Never cache API responses, logout, or anything not under the app.
    if (url.pathname.indexOf('/api/') !== -1) {
        return true;
    }
    var last = url.pathname.split('/').pop();
    if (last === 'logout.php') {
        return true;
    }

    return false;
}

self.addEventListener('fetch', function (event) {
    var request = event.request;

    if (shouldSkip(request)) {
        return;
    }

    // Pages: try the network first, fall back to the cache, then offline.html.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then(function (response) {
                    if (response && response.ok) {
                        var copy = response.clone();
                        caches.open(CACHE_NAME).then(function (cache) {
                            cache.put(request, copy);
                        });
                    }
                    return response;
                })
                .catch(function () {
                    return caches.match(request).then(function (cached) {
                        return cached || caches.match('offline/offline.html');
                    });
                })
        );
        return;
    }

    // Static assets: serve from cache fast, refresh in the background.
    event.respondWith(
        caches.match(request).then(function (cached) {
            var network = fetch(request).then(function (response) {
                if (response && response.status === 200 && response.type === 'basic') {
                    var copy = response.clone();
                    caches.open(CACHE_NAME).then(function (cache) {
                        cache.put(request, copy);
                    });
                }
                return response;
            }).catch(function () {
                return cached;
            });
            return cached || network;
        })
    );
});
