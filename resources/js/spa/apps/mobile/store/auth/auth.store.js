import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

const useAuthStore = defineStore('mobile_auth_store', {
    state: function() {
		return {
            user: null,
            // 访客能力矩阵（由 guest bootstrap 下发；null 表示尚未初始化）
            guestCapabilities: null,
            guestVisibleNav: [],
            guestEnabled: false,
		}
	},
    getters: {
        authCheck: function() {
            return this.user !== null;
        },
        userData: function(state) {
            return this.user;
        },
        // 访客态：未登录且访客功能已开启
        isGuest: function() {
            return this.user === null && this.guestEnabled === true;
        },
    },
    actions: {
        setUser: function(userData) {
           this.user = userData;
        },
        setGuestBootstrap: function(guestData) {
            this.guestEnabled = guestData?.enabled === true;
            this.guestCapabilities = guestData?.capabilities ?? null;
            this.guestVisibleNav = guestData?.visible_nav ?? [];
        },
        setProperty: function(key, value) {
            this.user[key] = value;
        },
        // 登录/注册成功后调用：注入用户，状态从访客无刷新切换为完整模式
        setLoginSucceeded: function(userData) {
            this.user = userData;
        },
        logoutUser: async function() {
            const response = await colibriAPI().userAuth().sendTo('logout');

            this.user = null;

            return response;
        }
    }
});

export { useAuthStore };
