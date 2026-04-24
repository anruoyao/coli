@if (config('pwa.enabled'))
	<link rel="manifest" href="{{ asset('pwa/manifest.json') }}">
	<script src="{{ asset('pwa/service-worker.js') }}"></script>

	<script>
		if ('serviceWorker' in navigator) {
			window.addEventListener('load', () => {
				navigator.serviceWorker.register('/pwa/service-worker.js').then((reg) => {
					console.log('Service worker registered');
				}).catch((err) => {
					console.error('Service worker error');
				});
			});
		}
	</script>
@endif