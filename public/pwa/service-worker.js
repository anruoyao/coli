self.addEventListener('install', (event) => {
	console.log('Service worker installed');
	self.skipWaiting();
});

self.addEventListener('activate', (event) => {
	console.log('Service worker activated');
});

self.addEventListener('fetch', (event) => {
	// Simple cache logic
	event.respondWith(
		caches.match(event.request).then((response) => {
			return response || fetch(event.request);
		})
	);
});