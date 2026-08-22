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

export { AxiosAuth, Axios };