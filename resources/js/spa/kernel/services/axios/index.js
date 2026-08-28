import Axios from 'axios';


const baseURL = import.meta.env.VITE_API_BASE_URL;
const appApiPrefix = import.meta.env.VITE_APP_API_PREFIX;

// 客户端请求密钥（X-App-Key）：随每个请求携带，服务端白名单校验（准入门槛，
// 非机密——bundle 公开；泄露后可轮换踢掉，见后端 config/security.php）。
// 生产可用 VITE_APP_API_KEY 覆盖，缺省用内置默认值（与服务端 SECURITY_APP_KEYS 默认一致）。
const appApiKey = import.meta.env.VITE_APP_API_KEY || 'clbPK-8f3k2m9xq4w7v1t6a5s0d2n8h4j6y1c';

const AxiosAuthHeaders = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-App-Key': appApiKey
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