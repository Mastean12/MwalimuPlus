/* MwalimuPlus service worker — network-first, cache fallback.
 * Registered only over HTTPS (see includes/footer.php), so this file is safe
 * to serve from a Laragon localhost dev environment too.
 */

'use strict';

var CACHE_NAME = 'mwalimuplus-v1';

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function (cache) {
            return cache.addAll([
                '../assets/css/app.css',
                '../assets/js/app.js',
                '../assets/js/lesson.js',
                'offline.html'
            ]);
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
        })
    );
});

self.addEventListener('fetch', function (event) {
    var request = event.request;

    // Only handle GET page/navigation + same-origin static assets.
    if (request.method !== 'GET') {
        return;
    }

    // Offline fallback for navigations.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then(function (response) {
                    var copy = response.clone();
                    caches.open(CACHE_NAME).then(function (cache) {
                        cache.put(request, copy);
                    });
                    return response;
                })
                .catch(function () {
                    return caches.match(request).then(function (cached) {
                        return cached || caches.match('offline.html');
                    });
                })
        );
        return;
    }

    // Static assets: stale-while-revalidate.
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
