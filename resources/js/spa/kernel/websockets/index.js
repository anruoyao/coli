import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.ColibriBRConnected = false;
window.Pusher = Pusher;
window.Echo = Echo;
Pusher.logToConsole = import.meta.env.PUSHER_DEBUG_CONSOLE;
const REVERB_CONNECTION_STATUS = import.meta.env.VITE_REVERB_CONNECTION_STATUS;
const PUSHER_ENABLED = import.meta.env.VITE_PUSHER_ENABLED;

try {
    if (PUSHER_ENABLED == 'on') {
        window.ColibriBRD = new Echo({
            namespace: 'null',
            broadcaster: 'pusher',
            key: import.meta.env.VITE_PUSHER_KEY,
            cluster: import.meta.env.VITE_PUSHER_CLUSTER,
            forceTLS: (import.meta.env.VITE_PUSHER_FORCE_TLS ?? 'true') !== 'false',
            enabledTransports: ['ws', 'wss']
        });

        window.ColibriBRD.connector.pusher.connection.bind('connected', function() {
            console.log('📶 Websockets connection is established.');
            window.ColibriBRConnected = true;
            window.dispatchEvent(new CustomEvent('ws:connected'));
        });
        window.ColibriBRD.connector.pusher.connection.bind('disconnected', function() {
            window.ColibriBRConnected = false;
            window.dispatchEvent(new CustomEvent('ws:disconnected'));
        });
        window.ColibriBRD.connector.pusher.connection.bind('connected', function() {
            console.log('📶 Websockets connection is established.');
            window.ColibriBRConnected = true;
            window.dispatchEvent(new CustomEvent('ws:connected'));
        });
        window.ColibriBRD.connector.pusher.connection.bind('disconnected', function() {
            window.ColibriBRConnected = false;
            window.dispatchEvent(new CustomEvent('ws:disconnected'));
        });
    }

    else if (REVERB_CONNECTION_STATUS == 'on') {
        window.ColibriBRD = new Echo({
            namespace: 'null',
            broadcaster: 'reverb',
            key: import.meta.env.VITE_REVERB_APP_KEY,
            wsHost: import.meta.env.VITE_REVERB_HOST,
            wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
            wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
            cluster: false
        });

        window.ColibriBRD.connector.pusher.connection.bind('connected', function() {
            console.log('📶 Websockets connection is established.');

            window.ColibriBRConnected = true;
        });
    }

    else {
        console.info("📶 Websockets connection is disabled. Please configure your broadcaster server and enable Reverb connection in your app settings. (ColibriPlus)");
    }
}

catch (error) {
    console.log(error);
}