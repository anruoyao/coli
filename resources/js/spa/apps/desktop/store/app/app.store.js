import { defineStore } from 'pinia';
import { useRouter } from 'vue-router';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { useAuthStore } from '@D/store/auth/auth.store.js';

const useAppStore = defineStore('app', {
    state: () => {
        return {
            appData: null,
            // 全局维护模式（SPA 实时遮罩）：由公共频道 main.command 指令驱动
            maintenance: {
                on: false,
                message: '',
                until: ''
            },
            // 账号封禁/停用（SPA 实时封禁页）：由用户私有频道 main.command 指令驱动
            userStatus: {
                status: '', // blocked / suspended / ''（正常）
                reason: ''
            }
        };
    },
    actions: {
        bootstrapApplication: async function() {
            let state = this;

            const authStore = useAuthStore();

            const router = useRouter();

            await fetch('sanctum/csrf-cookie', {
                method: 'GET',
                credentials: 'include'
            });

            await colibriAPI().bootstrap().getFrom('bootstrap').then(function(response) {
                state.appData = response.data.data;
                authStore.setUser(state.appData.auth.user);
            }).catch(function(error) {
                if(error.response) {
                    router.push({ name: 'bootstrap_error' });
                }
            });
        },
        setMaintenance: function(payload) {
            this.maintenance.on = payload.on === true;
            this.maintenance.message = payload.message ?? '';
            this.maintenance.until = payload.until ?? '';
        },
        setUserStatus: function(payload) {
            this.userStatus.status = payload.status ?? '';
            this.userStatus.reason = payload.reason ?? '';
        }
    }
});

export { useAppStore };