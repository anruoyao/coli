self.addEventListener('install', (event) => {
	console.log('Service worker installed');
	self.skipWaiting();
});

self.addEventListener('activate', (event) => {
	event.waitUntil(self.clients.claim());
	console.log('Service worker activated');
});

const CACHE_NAME = 'colibri-static-v1';

self.addEventListener('fetch', (event) => {
	const request = event.request;

	// 1) 页面导航（HTML）：network-first。
	//    维护模式下服务端返回 503 维护页，必须透传给浏览器；
	//    只有离线时才回退到缓存（保证断网仍可见上次页面）。
	if (request.mode === 'navigate' && request.method === 'GET') {
		event.respondWith(
			fetch(request).catch(() => caches.match(request))
		);
		return;
	}

	// 2) 同源静态资源（JS/CSS/图片等）：cache-first + 后台填充。
	if (request.method === 'GET' && request.url.startsWith(self.location.origin)) {
		event.respondWith(
			caches.match(request).then((cached) => {
				if (cached) return cached;
				return fetch(request).then((response) => {
					if (response && response.status === 200 && response.type === 'basic') {
						const clone = response.clone();
						caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
					}
					return response;
				});
			}).catch(() => fetch(request))
		);
		return;
	}

	// 3) 其它（POST 等）：直连网络，不缓存。
	event.respondWith(fetch(request));
});