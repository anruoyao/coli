import { useAuthStore } from '@M/store/auth/auth.store.js';
import { colibriEventBus } from '@/kernel/events/bus/index.js';

/**
 * 访客访问闸门（mobile）。
 *
 * 用法：
 *   const { guard } = useAuthGate();
 *   if (guard()) { 执行已登录才允许的动作 }
 *
 * - 已登录：guard() 返回 true，正常执行；
 * - 访客：不执行后续逻辑，通过事件总线打开登录引导面板，返回 false。
 */
export function useAuthGate()
{
    const authStore = useAuthStore();

    const guard = function(payload = {}) {
        if (authStore.authCheck) {
            return true;
        }

        colibriEventBus.emit('auth-gate:request', payload);

        return false;
    };

    return {
        guard,
        authStore,
    };
}
