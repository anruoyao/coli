import { useAuthStore } from '@D/store/auth/auth.store.js';
import { colibriEventBus } from '@/kernel/events/bus/index.js';

/**
 * 访客访问闸门（desktop）。
 *
 *   const { guard } = useAuthGate();
 *   if (guard()) { 已登录动作 }
 *
 * 已登录返回 true；访客打开登录引导模态框并返回 false。
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
