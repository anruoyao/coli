import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

const useAuthStore = defineStore('auth_store', {
    state: function() {
		return {
            user: null,
            // 访客 bootstrap 段
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
        // 访客面板登录成功：无刷新切换登录态
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
