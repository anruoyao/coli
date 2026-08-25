<template>
    <SidedContentLayout>
        <template v-slot:content>
            <TimelineContainer>
                <HomeHeader></HomeHeader>

                <!-- 首页时间线切换：关注 / 推荐 -->
                <div class="grid grid-cols-2">
                    <div role="tab" v-bind:class="['cursor-pointer w-full flex-1 text-center overflow-hidden truncate leading-4 text-par-m px-3 py-5', (state.activeTab === 0 ? 'active-tab-link' : '')]" v-on:click="switchTab(0)">
                        <span v-bind:class="['font-semibold', (state.activeTab === 0 ? 'text-lab-pr2' : 'text-lab-sc')]">
                            {{ $t('labels.following') }}
                        </span>
                    </div>
                    <div role="tab" v-bind:class="['cursor-pointer w-full flex-1 text-center overflow-hidden truncate leading-4 text-par-m px-3 py-5', (state.activeTab === 1 ? 'active-tab-link' : '')]" v-on:click="switchTab(1)">
                        <span v-bind:class="['font-semibold', (state.activeTab === 1 ? 'text-lab-pr2' : 'text-lab-sc')]">
                            {{ $t('labels.for_you') }}
                        </span>
                    </div>
                </div>
                <Border></Border>

                <div class="block" v-if="state.isLoading">
                    <TimelinePublicationSkeleton v-for="i in 3" v-bind:key="i"></TimelinePublicationSkeleton>
                </div>
                <div class="block" v-else>
                    <div class="pb-4 px-4">
                        <StoriesFeed></StoriesFeed>
                    </div>
                    <Border></Border>
                    <div class="block">
                        <PublicationEditorTrigger></PublicationEditorTrigger>
                    </div>
                    <Border></Border>

                    <template v-if="state.activeTab === 0">
                        <template v-if="globalPinnedPosts.length">
                            <TimelinePublication
                                v-for="pinnedPostData in globalPinnedPosts"
                                v-bind:postData="pinnedPostData"
                                v-bind:isPinned="true"
                                v-on:delete="handlePostDelete(pinnedPostData)"
                            v-bind:key="pinnedPostData.hash_id"></TimelinePublication>
                        </template>
                        <FeedUpdate v-if="timelineNewPosts.length" v-bind:posts="timelineNewPosts" v-on:click="applyTimelineUpdate"></FeedUpdate>
                        <div v-if="timelinePosts.length">
                            <TimelinePublication
                                v-for="postData in timelinePosts"
                                v-bind:postData="postData"
                                v-on:delete="handlePostDelete(postData)"
                            v-bind:key="postData.hash_id"></TimelinePublication>

                            <div v-if="state.isLoadingContent">
                                <div class="flex justify-center my-4">
                                    <div class="colibri-primary-animation"></div>
                                </div>
                            </div>
                        </div>
                        <div v-else>
                            <div class="block py-72">
                                <p class="text-lab-sc text-par-s text-center">
                                    {{ $t('empty_state.home.posts') }}
                                </p>
                            </div>
                        </div>
                    </template>

                    <template v-else>
                        <div v-if="state.isRecommendLoading">
                            <TimelinePublicationSkeleton v-for="i in 3" v-bind:key="i"></TimelinePublicationSkeleton>
                        </div>
                        <template v-else>
                            <FeedUpdate v-if="recommendedNewPosts.length" v-bind:posts="recommendedNewPosts" v-on:click="applyRecommendedUpdate"></FeedUpdate>
                            <div v-if="recommendedPosts.length">
                                <TimelinePublication
                                    v-for="postData in recommendedPosts"
                                    v-bind:postData="postData"
                                    v-on:delete="handlePostDelete(postData)"
                                v-bind:key="postData.id"></TimelinePublication>

                                <div v-if="state.isLoadingContent">
                                    <div class="flex justify-center my-4">
                                        <div class="colibri-primary-animation"></div>
                                    </div>
                                </div>
                            </div>
                            <div v-else>
                                <div class="block py-72">
                                    <p class="text-lab-sc text-par-s text-center">
                                        {{ $t('empty_state.empty') }}
                                    </p>
                                </div>
                            </div>
                        </template>
                    </template>
                </div>
            </TimelineContainer>
        </template>

        <template v-slot:sidebar>
            <FollowRecommendationList></FollowRecommendationList>

            <AdGridItem></AdGridItem>
        </template>
    </SidedContentLayout>
    <ScrollTopButton></ScrollTopButton>
</template>

<script>
    import { defineComponent, ref, reactive, onMounted, computed, onUnmounted } from 'vue';
    import { useTimelineStore } from '@D/store/timeline/timeline.store.js';
    import { useExplorePostsStore } from '@D/store/explore/posts.store.js';
    import { usePinsStore } from '@D/store/timeline/pins.store.js';
    import { useDeletePost } from '@/kernel/vue/composables/delete-post/index.js';
    import { useInfiniteScroll } from '@/kernel/vue/composables/infinite-scroll/index.js';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';

    import StoriesFeed from '@D/components/stories/feed/StoriesFeed.vue';
    import TimelinePublication from '@D/components/timeline/feed/TimelinePublication.vue';
    import TimelinePublicationSkeleton from '@D/components/timeline/feed/TimelinePublicationSkeleton.vue';
    import PublicationEditorTrigger from '@D/features/home/parts/PublicationEditorTrigger.vue';

    import TimelineContainer from '@D/components/layout/TimelineContainer.vue';
    import ScrollTopButton from '@D/components/inter-ui/buttons/ScrollTopButton.vue';
    import FollowRecommendationList from '@D/components/recommend/follow/list/FollowRecommendationList.vue';
    import AdGridItem from '@D/components/ads/AdGridItem.vue';
    import SidedContentLayout from '@D/components/layout/SidedContentLayout.vue';
    import HomeHeader from '@D/views/home/parts/HomeHeader.vue';
    import FeedUpdate from '@D/components/timeline/update/FeedUpdate.vue';

    export default defineComponent({
        setup: function() {
            const state = reactive({
                isLoading: false,
                isLoadingContent: false,
                noMoreContent: false,
                isUpdating: false,
                activeTab: 0,
                isRecommendLoading: false,
                noMoreRecommend: false,
                filter: {
                    page: 1
                }
            });

            let updateIntervalId = null;
            let updateAttempts = 0;
            const { postDeleter } = useDeletePost();
            const timelineStore = useTimelineStore();
            const explorePostsStore = useExplorePostsStore();
            const pinsStore = usePinsStore();

            const timelineNewPosts = computed(() => {
                return timelineStore.update;
            });

            const timelinePosts = computed(() => {
                return timelineStore.posts;
            });

            const recommendedNewPosts = computed(() => {
                return explorePostsStore.update;
            });

            const recommendedPosts = computed(() => {
                return explorePostsStore.posts;
            });

            const globalPinnedPosts = computed(() => {
                return pinsStore.posts;
            });

            onMounted(async () => {
                state.isLoading = true;

                await timelineStore.initialLoad();

                pinsStore.fetchGlobalPins();

                state.isLoading = false;

                // Update feed every 10 minutes.
				// 10 minutes are optimal for the feed update interval.

                updateIntervalId = setInterval(async () => {
                    if(! state.isUpdating) {
                        const activeNewPosts = (state.activeTab === 0) ? timelineNewPosts.value : recommendedNewPosts.value;

                        if(activeNewPosts.length == 0 && updateAttempts > 10) {
                            clearInterval(updateIntervalId);
                        }
                        else {
                            state.isUpdating = true;

                            if(state.activeTab === 0) {
                                if(timelinePosts.value.length) {
                                    await timelineStore.updateFeed();
                                }
                            }
                            else {
                                if(recommendedPosts.value.length) {
                                    await explorePostsStore.updateFeed();
                                }
                            }

                            state.isUpdating = false;

                            updateAttempts++;
                        }
                    }
                }, ((60 * 1000) * 10));
            });

            onUnmounted(() => {
                if(updateIntervalId) {
                    clearInterval(updateIntervalId);
                }
			});

            const loadRecommended = async () => {
                state.isRecommendLoading = true;

                explorePostsStore.resetFilter();

                const hasPosts = recommendedPosts.value.length;

                if(! hasPosts) {
                    await explorePostsStore.fetchPosts();
                }

                state.isRecommendLoading = false;
            };

            const switchTab = (tabIndex) => {
                if(state.activeTab === tabIndex) {
                    return;
                }

                state.activeTab = tabIndex;

                if(tabIndex === 1 && ! recommendedPosts.value.length) {
                    loadRecommended();
                }
            };

            const loadMorePosts = async () => {
				try {
					if(state.activeTab === 0) {
						if(! state.isLoadingContent && ! state.noMoreContent && timelinePosts.value.length) {
							state.isLoadingContent = true;

							await timelineStore.loadNextPage().then(function(response) {
								let content = response.data.data;

								if(content.length) {
									timelineStore.appendPosts(content);
								}
								else{
									state.noMoreContent = true;
								}
							}).catch((error) => {
								if(error.response) {
									state.noMoreContent = true;
								}
							});

							state.isLoadingContent = false;
						}
					}
					else {
						if(! state.isLoadingContent && ! state.noMoreRecommend && recommendedPosts.value.length) {
							state.isLoadingContent = true;

							explorePostsStore.filter.page += 1;

							state.noMoreRecommend = (! await explorePostsStore.loadMorePosts());

							state.isLoadingContent = false;
						}
					}
				} catch (error) {
					console.log(error);
				}
			}

            useInfiniteScroll({
                callback: loadMorePosts
            });

            return {
                timelinePosts: timelinePosts,
                state: state,
                timelineNewPosts: timelineNewPosts,
                recommendedPosts: recommendedPosts,
                recommendedNewPosts: recommendedNewPosts,
                globalPinnedPosts: globalPinnedPosts,
                switchTab: switchTab,
                handlePostDelete: (postData) => {
                    postDeleter(postData, (postId) => {
                        colibriEventBus.emit('timeline:post-deleted', postId);
                    });
                },
                applyTimelineUpdate: () => {
                    timelineStore.applyUpdate();
                },
                applyRecommendedUpdate: () => {
                    explorePostsStore.applyUpdate();
                }
            };
        },
        components: {
            StoriesFeed: StoriesFeed,
            TimelinePublication: TimelinePublication,
            PublicationEditorTrigger: PublicationEditorTrigger,
            TimelinePublicationSkeleton: TimelinePublicationSkeleton,
            TimelineContainer: TimelineContainer,
            FollowRecommendationList: FollowRecommendationList,
            AdGridItem: AdGridItem,
            ScrollTopButton: ScrollTopButton,
            HomeHeader: HomeHeader,
            SidedContentLayout: SidedContentLayout,
            FeedUpdate: FeedUpdate
        }
    });
</script>