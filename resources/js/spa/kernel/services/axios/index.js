import Axios from 'axios';


const baseURL = import.meta.env.VITE_API_BASE_URL;
const appApiPrefix = import.meta.env.VITE_APP_API_PREFIX;

const AxiosAuthHeaders = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
};

if(window.ColibriBRD) {
    AxiosAuthHeaders['X-Socket-ID'] = window.ColibriBRD.connector.pusher.connection.socket_id;
}

// Create an Axios instance
const AxiosAuth = Axios.create({
    baseURL: `${baseURL}/${appApiPrefix}/`,
    headers: AxiosAuthHeaders
});

AxiosAuth.defaults.withCredentials = true;
AxiosAuth.defaults.withXSRFToken = true;
AxiosAuth.defaults.xsrfCookieName = 'XSRF-TOKEN';
AxiosAuth.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';

// Ensure XSRF header is set even if Axios version lacks withXSRFToken support
AxiosAuth.interceptors.request.use(function (config) {
    try {
        if (typeof document !== 'undefined') {
            const match = document.cookie.match(new RegExp('(?:^|; )' + 'XSRF-TOKEN'.replace(/([.$?*|{}()\[\]\\\/\+^])/g, '\\$1') + '=([^;]*)'));
            const xsrfToken = match ? decodeURIComponent(match[1]) : null;
            if (xsrfToken && (!config.headers || !config.headers['X-XSRF-TOKEN'])) {
                config.headers = config.headers || {};
                config.headers['X-XSRF-TOKEN'] = xsrfToken;
            }
        }
    } catch (e) {}
    return config;
});

export { AxiosAuth, Axios };