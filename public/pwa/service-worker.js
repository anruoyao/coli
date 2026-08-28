// ColibriPlus PWA Service Worker
//
// 版本化缓存桶：每次发布新前端时递增 BUILD_VERSION，activate 阶段自动删除
// 所有旧 colibri-static-* 桶。避免旧 JS bundle 被持续命中——例如未携带
// X-App-Key 的历史版本导致 /api 一律 404、页面进入 bootstrap-error。
//
// 注意：浏览器需拉取到本文件（nginx 已对 /pwa/service-worker.js 设 no-cache）
// 才会安装新 SW；存量用户首次仍由旧 SW 控制，属正常，下一次导航即切换。

const BUILD_VERSION = '2026-08-28';
const CACHE_NAME = 'colibri-static-' + BUILD_VERSION;

self.addEventListener('install', (event) => {
	console.log('Service worker installed', CACHE_NAME);
	self.skipWaiting();
});

self.addEventListener('activate', (event) => {
	event.waitUntil(
		caches.keys().then((keys) =>
			Promise.all(
				keys
					.filter((key) => key.startsWith('colibri-static-') && key !== CACHE_NAME)
					.map((key) => caches.delete(key))
			)
		)
	);
	self.clients.claim();
	console.log('Service worker activated', CACHE_NAME);
});

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
});