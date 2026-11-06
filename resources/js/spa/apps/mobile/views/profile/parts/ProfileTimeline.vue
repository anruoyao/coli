<template>
    <template v-if="state.isLoading">
        <TimelinePublicationSkeleton v-for="i in 3" v-bind:key="i"></TimelinePublicationSkeleton>
    </template>
    <template v-else>
        <template v-if="pinnedPosts.length || profilePosts.length">
            <template v-if="contentType === 'posts'">
                <TimelinePublication v-for="postData in pinnedPosts" v-bind:key="postData.hash_id"
                    v-bind:postData="postData" v-bind:isPinned="true"
                    v-on:delete="handleDeletePost(postData)"></TimelinePublication>
            </template>
            <TimelinePublication v-for="postData in profilePosts" v-bind:key="postData.hash_id"
                v-bind:postData="postData" v-bind:isPinned="contentType === 'media' && postData.meta.profile_pinned"
                v-on:delete="handleDeletePost(postData)"></TimelinePublication>
            <div v-if="state.isLoadingContent" class="flex justify-center my-4">
                <div class="colibri-primary-animation"></div>
            </div>
        </template>
        <div v-else class="block py-40">
            <TimelineEmptyState v-if="contentType === 'posts'" v-bind:desc="$t('empty_state.profile.posts.desc')"></TimelineEmptyState>
            <TimelineEmptyState v-else v-bind:desc="$t('empty_state.profile.media.desc')"></TimelineEmptyState>
        </div>
    </template>
</template>

<script>
    import { defineComponent } from 'vue';
    import { useProfilePosts } from '@/kernel/vue/composables/profile-posts/index.js';
    import { useDeletePost } from '@/kernel/vue/composables/delete-post/index.js';
    import { useAuthStore } from '@M/store/auth/auth.store.js';
    import TimelinePublicationSkeleton from '@M/components/timeline/feed/TimelinePublicationSkeleton.vue';
    import TimelinePublication from '@M/components/timeline/feed/TimelinePublication.vue';
    import TimelineEmptyState from '@M/components/timeline/state/TimelineEmptyState.vue';

    export default defineComponent({
        props: {
            contentType: { type: String, default: 'posts' }
        },
        setup(props) {
            const { profilePosts, pinnedPosts, state, removePost } = useProfilePosts(props.contentType, useAuthStore());
            const { postDeleter } = useDeletePost();
            const handleDeletePost = (postData) => {
                postDeleter(postData, removePost);
            };
            return { profilePosts, pinnedPosts, state, handleDeletePost };
        },
        components: { TimelinePublication, TimelinePublicationSkeleton, TimelineEmptyState }
    });
</script>
