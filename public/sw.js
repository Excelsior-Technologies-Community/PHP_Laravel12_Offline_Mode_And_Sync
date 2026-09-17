const CACHE_NAME = "offline-notes-app-v2";

const urlsToCache = [
    "/",
    "/offline",
    "/offline.js"
];

/*
|--------------------------------------------------------------------------
| Install
|--------------------------------------------------------------------------
*/

self.addEventListener("install", event => {

    event.waitUntil(

        caches.open(CACHE_NAME)
            .then(cache => {

                return cache.addAll(
                    urlsToCache
                );

            })

    );

    self.skipWaiting();
});


/*
|--------------------------------------------------------------------------
| Activate
|--------------------------------------------------------------------------
*/

self.addEventListener("activate", event => {

    event.waitUntil(

        caches.keys()
            .then(cacheNames => {

                return Promise.all(

                    cacheNames
                        .filter(
                            cacheName =>
                                cacheName !== CACHE_NAME
                        )
                        .map(
                            cacheName =>
                                caches.delete(cacheName)
                        )

                );

            })

    );

    self.clients.claim();
});


/*
|--------------------------------------------------------------------------
| Fetch
|--------------------------------------------------------------------------
*/

self.addEventListener("fetch", event => {

    /*
     * API requests should always go
     * to the Laravel server.
     */
    if (
        event.request.url.includes("/api/")
    ) {
        return;
    }

    event.respondWith(

        fetch(event.request)
            .then(response => {

                return response;

            })
            .catch(() => {

                return caches.match(
                    event.request
                ).then(cachedResponse => {

                    return cachedResponse ||
                        caches.match("/offline");

                });

            })

    );

});