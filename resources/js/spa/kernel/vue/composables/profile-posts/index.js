import { ref, reactive, onMounted, onUnmounted, inject } from 'vue';
import { useInfiniteScroll } from '@/kernel/vue/composables/infinite-scroll/index.js';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { colibriEventBus } from '@/kernel/events/bus/index.js';

export function useProfilePosts(contentType, authStore) {
    const profileData = inject('profileData');
    const profilePosts = ref([]);
    const pinnedPosts = ref([]);
    const state = reactive({ isLoading: true, isLoadingContent: false, noMoreContent: false });
    let cursor = 0;
    let generation = 0;
    let loadingGeneration = -1;

    const fetchPosts = async () => {
        if (loadingGeneration === generation || state.noMoreContent || !profileData.value?.id) return;
        const currentGeneration = generation;
        loadingGeneration = currentGeneration;
        state.isLoadingContent = true;
        try {
            const request = authStore.isGuest
                ? colibriAPI().guest().params({ cursor, filter: { type: contentType } })
                    .getFrom(`profile/${profileData.value.username}/posts`)
                : colibriAPI().userProfile().params({
                    id: profileData.value.id, filter: { type: contentType, cursor }
                }).getFrom('profile/posts');
            const { data, pinned, meta } = (await request).data;
            if (currentGeneration !== generation) return;
            if (cursor === 0 && contentType === 'posts') pinnedPosts.value = pinned || [];
            profilePosts.value.push(...data);
            cursor = meta?.next_cursor || 0;
            state.noMoreContent = !meta?.has_more;
        } catch (error) {
            if (currentGeneration === generation) {
                toastError(error.response?.data?.message || error.message);
            }
        } finally {
            if (currentGeneration === generation) {
                loadingGeneration = -1;
                state.isLoadingContent = false;
                state.isLoading = false;
            }
        }
    };

    const reload = () => {
        generation++;
        cursor = 0;
        loadingGeneration = -1;
        profilePosts.value = [];
        pinnedPosts.value = [];
        state.noMoreContent = false;
        state.isLoading = true;
        fetchPosts();
    };

    const removePost = (id) => {
        profilePosts.value = profilePosts.value.filter(post => post.id !== id);
        pinnedPosts.value = pinnedPosts.value.filter(post => post.id !== id);
    };

    const onPinChanged = ({ user_id }) => {
        if (contentType === 'posts' && profileData.value?.id === user_id) reload();
    };

    useInfiniteScroll({ callback: fetchPosts });
    onMounted(fetchPosts);
    colibriEventBus.on('profile:pin-changed', onPinChanged);
    colibriEventBus.on('auth:login-succeeded', reload);
    onUnmounted(() => {
        generation++;
        colibriEventBus.off('profile:pin-changed', onPinChanged);
        colibriEventBus.off('auth:login-succeeded', reload);
    });

    return { profilePosts, pinnedPosts, state, removePost };
}
