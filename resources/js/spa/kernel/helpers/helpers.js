window.debounceTimeout = null;

window.debounce = (callback, delay = 500) => {
	if (window.debounceTimeout) {
		clearTimeout(window.debounceTimeout);
	}
	
	window.debounceTimeout = setTimeout(() => {
		callback();
	}, delay);
};

window.isStandalone = () => {
	return window.navigator.standalone || window.matchMedia('(display-mode: standalone)').matches;
};