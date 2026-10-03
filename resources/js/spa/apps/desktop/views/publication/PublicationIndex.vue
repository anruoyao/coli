<template>
    <SidedContentLayout>
        <template v-slot:content>
            <TimelineContainer>
                <PageHeader v-bind:hasBack="true" v-bind:titleText="$t('labels.publication_page_title')"></PageHeader>
                <Border></Border>
                <div v-if="state.isLoading" class="block">
                    <ProfileSidebarSkeleton></ProfileSidebarSkeleton>
                </div>
                <div v-else class="block">
                    <ProfileTimelineCard v-bind:profileData="postAuthor"></ProfileTimelineCard>
                </div>
                <Border height="h-3" opacity="opacity-70"></Border>
                <div v-if="state.isLoading">
                    <TimelinePublicationSkeleton v-for="i in 2"></TimelinePublicationSkeleton>
                </div>
                <div v-else class="block">
                    <div class="block">
                        <TimelinePublication v-bind:postData="postData" v-on:delete="handlePostDelete"></TimelinePublication>
                    </div>
                    <Border></Border>
                    <!-- 登录用户：评论编辑器 -->
                    <div v-if="! authStore.isGuest" class="sticky top-0 bg-bg-pr z-10">
                        <PublicationCommentEditor v-bind:postId="postData.id" v-on:add="handleCommentAdding"></PublicationCommentEditor>

                        <div class="px-4 bg-fill-fv py-2">
                            <span class="text-par-s text-lab-sc text-center block font-semibold">
                                {{ $t('labels.comment_number', postData.comments_count.raw )}}
                            </span>
                        </div>
                    </div>
                    <!-- 访客：登录引导 + 评论计数 -->
                    <div v-else class="bg-bg-pr z-10">
                        <button type="button" v-on:click="requestGate"
                            class="w-full px-4 py-3 text-left text-par-m text-lab-sc border-b border-bord-pr">
                            {{ $t('auth.gate_caption') }}
                        </button>

                        <div class="px-4 bg-fill-fv py-2">
                            <span class="text-par-s text-lab-sc text-center block font-semibold">
                                {{ $t('labels.comment_number', postData.comments_count.raw )}}
                            </span>
                        </div>
                    </div>
                    <div class="block" v-if="threads.length">

                        <template v-for="(threadItem, idx) in threads" v-bind:key="threadItem.root.id">
                            <Border v-if="idx > 0"></Border>
                            <CommentThread
                                v-bind:thread="threadItem"
                                v-on:toggle-expand="toggleExpand(threadItem)"
                                v-on:load-more="loadMore(threadItem)"
                                v-on:reply="handleCommentReply"
                            v-on:delete="handleCommentDelete"></CommentThread>
                        </template>
                        <div v-if="state.isLoadingComments">
                            <div class="flex justify-center my-4">
                                <div class="colibri-primary-animation"></div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="block py-40 border-t border-bord-pr">
                        <TimelineEmptyState v-bind:desc="$t('empty_state.comments.desc')"></TimelineEmptyState>
                    </div>
                </div>
            </TimelineContainer>
        </template>

        <!-- 登录用户侧栏；访客无侧栏 -->
        <template v-if="! authStore.isGuest" v-slot:sidebar>
            <FollowRecommendationList></FollowRecommendationList>

            <AdGridItem></AdGridItem>
        </template>
    </SidedContentLayout>
    <ScrollTopButton></ScrollTopButton>
</template>

<script>
    import { defineComponent, ref, reactive, computed, onMounted, onUnmounted } from 'vue';

    import { useRoute, useRouter } from 'vue-router';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';
    import { useAuthStore } from '@D/store/auth/auth.store.js';
    import { useAuthGate } from '@D/core/composables/useAuthGate.js';
    import { useInfiniteScroll } from '@/kernel/vue/composables/infinite-scroll/index.js';
    import { useDeletePost } from '@/kernel/vue/composables/delete-post/index.js';
    import { useCommentThreads } from '@/kernel/vue/composables/comment-threads/index.js';

    import TimelinePublication from '@D/components/timeline/feed/TimelinePublication.vue';
    import CommentThread from '@D/components/timeline/feed/parts/comment/CommentThread.vue';
    import PageHeader from '@D/components/layout/PageHeader.vue';

    import SidedContentLayout from '@D/components/layout/SidedContentLayout.vue';
    import TimelinePublicationSkeleton from '@D/components/timeline/feed/TimelinePublicationSkeleton.vue';
    import TimelineContainer from '@D/components/layout/TimelineContainer.vue';
    import TimelineEmptyState from '@D/components/timeline/state/TimelineEmptyState.vue';
    import PublicationCommentEditor from '@D/views/publication/editor/PublicationCommentEditor.vue';
    import ProfileTimelineCard from '@D/components/profile/ProfileTimelineCard.vue';
    import ProfileSidebarSkeleton from '@D/components/profile/parts/ProfileSidebarSkeleton.vue';
    import ScrollTopButton from '@D/components/inter-ui/buttons/ScrollTopButton.vue';
    import FollowRecommendationList from '@D/components/recommend/follow/list/FollowRecommendationList.vue';
    import AdGridItem from '@D/components/ads/AdGridItem.vue';

    export default defineComponent({
        setup: function() {
            const route = useRoute();
            const router = useRouter();
            const authStore = useAuthStore();
            const { guard } = useAuthGate();

            const requestGate = function() {
                colibriEventBus.emit('auth-gate:request', {});
            };

            const state = reactive({
                isLoading: true,
                isLoadingComments: false,
                noMoreComments: false
            });

            const { postDeleter } = useDeletePost();
            const postData = ref({});
            const postAuthor = ref({});

            // 树状评论线程（与 Flutter 端同一套 threaded API）
            const {
                threads,
                setRoots,
                appendRoots,
                toggleExpand,
                loadMore,
                applyCreated,
                applyDeleted,
                findComment
            } = useCommentThreads(computed(() => authStore.isGuest));

            const fetchComments = async () => {
                if (threads.value.length && ! state.noMoreComments && ! state.isLoadingComments) {
                    const cursorId = Math.min(...threads.value.map((thread) => thread.root.id));

                    if (cursorId) {
                        state.isLoadingComments = true;

                        // 访客走访客评论端点
                        const api = authStore.isGuest ? colibriAPI().guest() : colibriAPI().userTimeline();

                        await api.params({
                            cursor: cursorId,
                            threaded: 1
                        }).getFrom(`post/${route.params.hash_id}/comments`).then(function(response) {
                            let comments = response.data.data;

                            if (comments.length) {
                                appendRoots(comments);
                            }
                            else {
                                state.noMoreComments = true;
                            }
                        }).catch(function(error) {
                            state.noMoreComments = true;
                        });

                        state.isLoadingComments = false;
                    }
                }
            }

            useInfiniteScroll({
				callback: fetchComments
			});

            const fetchInitial = function() {
                // 访客走访客端点，登录用户走原端点；threaded=1 只回主评论 + 回复预览
                const api = authStore.isGuest ? colibriAPI().guest() : colibriAPI().userTimeline();

                api.params({
                    threaded: 1
                }).getFrom(`post/${route.params.hash_id}`).then(function(response) {
                    postData.value = response.data.data.post;
                    postAuthor.value = response.data.data.author;
                    setRoots(response.data.data.comments);
                    state.isLoading = false;
                }).catch(function(error) {
                    router.push({
                        name: 'error_404',
                        params: { pathMatch: route.path.substring(1).split('/') },
                        query: route.query,
                        hash: route.hash
                    });
                });
            };

            onMounted(fetchInitial);

            // 访客登录成功后：无刷新以登录态重新加载
            const onLoginSucceeded = function() {
                fetchInitial();
            };

            colibriEventBus.on('auth:login-succeeded', onLoginSucceeded);

            onUnmounted(function() {
                colibriEventBus.off('auth:login-succeeded', onLoginSucceeded);
            });

            return {
                authStore: authStore,
                requestGate: requestGate,
                state: state,
                postData: postData,
                postAuthor: postAuthor,
                threads: threads,
                toggleExpand: toggleExpand,
                loadMore: loadMore,
                handleCommentReply: (commentId) => {
                    if (! guard()) return;

                    const commentData = findComment(commentId);

                    if(commentData) {
                        colibriEventBus.emit('publication-comment:reply', {
                            commentData: commentData
                        });
                    }
                },
                handleCommentDelete: (commentId) => {
                    if (! guard()) return;

                    colibriEventBus.emit('confirmation-modal:open', {
                        title: __t('prompt.delete_comment.title'),
                        description: __t('prompt.delete_comment.description'),
                        onConfirm: () => {
                            colibriAPI().userTimeline().with({
                                id: commentId
                            }).delete('post/comment/delete').then((response) => {

                                postData.value.comments_count = response.data.data.post.comments_count;

                                // 主评论整棵线程移除；回复从线程内摘除
                                applyDeleted(commentId);

                                toastSuccess(__t('toast.media.comment_deleted'));
                            }).catch((error) => {
                                if (error.response) {
                                    toastError(error.response.data.message);
                                }
                            });
                        }
                    });
                },
                handleCommentAdding: (commentData) => {
                    const located = applyCreated(commentData.comment);

                    // 找不到所属线程（极端情况）：保守整页重载保证数据一致
                    if (! located) {
                        fetchInitial();
                    }

                    postData.value.comments_count = commentData.post.comments_count;
                },
                handlePostDelete: (postData) => {
                    colibriEventBus.emit('timeline:post-deleted', postData.id);

                    postDeleter(postData, () => {
                        router.push({
                            name: 'home_index'
                        });
                    });
                }
            }
        },
        components: {
            TimelinePublication: TimelinePublication,
            CommentThread: CommentThread,
            PageHeader: PageHeader,
            TimelinePublicationSkeleton: TimelinePublicationSkeleton,
            TimelineContainer: TimelineContainer,
            PublicationCommentEditor: PublicationCommentEditor,
            TimelineEmptyState: TimelineEmptyState,
            ProfileTimelineCard: ProfileTimelineCard,
            ProfileSidebarSkeleton: ProfileSidebarSkeleton,
            ScrollTopButton: ScrollTopButton,
            SidedContentLayout: SidedContentLayout,
            FollowRecommendationList: FollowRecommendationList,
            AdGridItem: AdGridItem
        }
    });
</script>
