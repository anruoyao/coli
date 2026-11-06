import { reactive, onUnmounted } from 'vue';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { colibriEventBus } from '@/kernel/events/bus/index.js';

const pending = reactive({});

export function useProfilePin(postData) {
    const onPinChanged = ({ id, profile_pinned, profile_pinned_at }) => {
        if (postData.value.id !== id) return;
        postData.value.meta.profile_pinned = profile_pinned;
        postData.value.meta.profile_pinned_at = profile_pinned_at;
    };

    colibriEventBus.on('profile:pin-changed', onPinChanged);
    onUnmounted(() => colibriEventBus.off('profile:pin-changed', onPinChanged));

    const toggleProfilePin = async () => {
        const post = postData.value;
        if (pending[post.id]) return;

        pending[post.id] = true;
        try {
            const response = await colibriAPI().userProfile()
                .with({ pinned: !post.meta.profile_pinned })
                .putTo(`profile/posts/${post.id}/pin`);
            colibriEventBus.emit('profile:pin-changed', response.data.data);
            toastSuccess(__t(response.data.data.profile_pinned ? 'toast.post.profile_pinned' : 'toast.post.profile_unpinned'));
        } catch (error) {
            toastError(error.response?.data?.message || error.message);
        } finally {
            delete pending[post.id];
        }
    };

    return { pending, toggleProfilePin };
}
