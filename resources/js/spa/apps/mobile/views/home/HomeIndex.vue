<template>
	<TimelineContainer>
        <!-- 首页时间线切换：关注 / 推荐（吸顶） -->
        <div class="sticky top-0 popup-background-tr z-10">
            <ContentTabs v-bind:cols="2">
                <TabsItem v-bind:isActive="state.activeTab === 0" v-on:click="switchTab(0)">
                    {{ $t('labels.following') }}
                </TabsItem>
                <TabsItem v-bind:isActive="state.activeTab === 1" v-on:click="switchTab(1)">
                    {{ $t('labels.for_you') }}
                </TabsItem>
            </ContentTabs>
            <Border></Border>
        </div>

        <div class="px-4 pb-3 pt-1">
            <StoriesFeed></StoriesFeed>
        </div>
        <Border height="h-2" opacity="opacity-30"></Border>
		<div v-if="state.isLoading">
			<TimelinePublicationSkeleton v-for="i in 2" v-bind:key="i"></TimelinePublicationSkeleton>
		</div>
		<div class="pb-6" v-else>
            <template v-if="state.activeTab === 0">
                <FeedUpdate v-if="timelineNewPosts.length" v-bind:posts="timelineNewPosts" v-on:click="applyTimelineUpdate"></FeedUpdate>
                <div v-if="timelinePosts.length">
                    <template v-for="(postData, index) in timelinePosts" v-bind:key="postData.hash_id">
                        <TimelinePublication v-bind:postData="postData" v-on:delete="handlePostDelete(postData)"></TimelinePublication>
                        
                        <!-- Show follow recommendation every 35 posts -->
                        <template v-if="(index + 1) % 35 === 0">
                            <FollowRecommendation v-bind:key="index"></FollowRecommendation>
                        </template>

                        <!-- Show ad card every 10 posts -->
                        <template v-if="(index + 1) % 10 === 0">
                            <AdCard v-bind:key="index"></AdCard>
                            <Border height="h-2" opacity="opacity-30"></Border>
                        </template>
                    </template>

                    <div v-if="state.isLoadingContent">
                        <div class="flex justify-center my-4">
                            <div class="colibri-primary-animation"></div>
                        </div>
                    </div>
                </div>
                <div v-else>
                    <div class="py-32">
                        <p class="text-lab-sc text-par-s text-center">
                            {{ $t('empty_state.home.posts') }}
                        </p>
                    </div>
                </div>
            </template>

            <template v-else>
                <div v-if="state.isRecommendLoading">
                    <TimelinePublicationSkeleton v-for="i in 5" v-bind:key="i"></TimelinePublicationSkeleton>
                </div>
                <template v-else>
                    <FeedUpdate v-if="recommendedNewPosts.length" v-bind:posts="recommendedNewPosts" v-on:click="applyRecommendedUpdate"></FeedUpdate>
                    <div v-if="recommendedPosts.length">
                        <template v-for="(postData, index) in recommendedPosts" v-bind:key="postData.id">
                            <TimelinePublication v-bind:postData="postData" v-on:delete="handlePostDelete(postData)"></TimelinePublication>

                            <!-- Show follow recommendation every 35 posts -->
                            <template v-if="(index + 1) % 35 === 0">
                                <FollowRecommendation v-bind:key="index"></FollowRecommendation>
                            </template>

                            <!-- Show ad card every 10 posts -->
                            <template v-if="(index + 1) % 10 === 0">
                                <AdCard v-bind:key="index"></AdCard>
                                <Border height="h-2" opacity="opacity-30"></Border>
                            </template>
                        </template>

                        <div v-if="state.isLoadingContent">
                            <div class="flex justify-center my-4">
                                <div class="colibri-primary-animation"></div>
                            </div>
                        </div>
                    </div>
                    <div v-else>
                        <div class="py-32">
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

<script>
    import { defineComponent, ref, reactive, onMounted, computed, onUnmounted } from 'vue';
    import { useTimelineStore } from '@M/store/timeline/timeline.store.js';
    import { useExplorePostsStore } from '@M/store/explore/posts.store.js';
    import { useDeletePost } from '@/kernel/vue/composables/delete-post/index.js';
    import { useInfiniteScroll } from '@/kernel/vue/composables/infinite-scroll/index.js';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';

    import TimelinePublication from '@M/components/timeline/feed/TimelinePublication.vue';
    import TimelinePublicationSkeleton from '@M/components/timeline/feed/TimelinePublicationSkeleton.vue';
    import TimelineContainer from '@M/components/timeline/feed/TimelineContainer.vue';
    import StoriesFeed from '@M/components/stories/feed/StoriesFeed.vue';
    import AdCard from '@M/components/ads/AdCard.vue';
    import FollowRecommendation from '@M/components/recommend/follow/FollowRecommendation.vue';
    import FeedUpdate from '@M/components/timeline/update/FeedUpdate.vue';
    import ContentTabs from '@M/components/general/tabs/content/ContentTabs.vue';
    import TabsItem from '@M/components/general/tabs/content/parts/TabsItem.vue';

    export default defineComponent({
        setup: function() {
            const state = reactive({
                isLoading: false,
                isLoadingContent: false,
                noMoreContent: false,
                isUpdating: false,
                activeTab: 0,
                isRecommendLoading: false,
                noMoreRecommend: false
            });

            let updateIntervalId = null;
            let updateAttempts = 0;

            const { postDeleter } = useDeletePost();

            const timelineStore = useTimelineStore();
            const explorePostsStore = useExplorePostsStore();

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

            onMounted(async () => {
                state.isLoading = true;

                // If they are loaded, we don't need to load them again.
                // Timeline will be update by feed update component.
                
                if(! timelinePosts.value.length) {
                    await timelineStore.initialLoad();
                }

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

                if(! recommendedPosts.value.length) {
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

            const loadMorePost = async () => {
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
                callback: loadMorePost
            });

            return {
                timelinePosts: timelinePosts,
                state: state,
                timelineNewPosts: timelineNewPosts,
                recommendedPosts: recommendedPosts,
                recommendedNewPosts: recommendedNewPosts,
                switchTab: switchTab,
                handlePostDelete: (postData) => {
                    postDeleter(postData, (postId) => {
                        colibriEventBus.emit('timeline:post-deleted', postId);
                        
                        toastSuccess(__t('toast.media.post_deleted'));
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
            TimelinePublication: TimelinePublication,
            TimelinePublicationSkeleton: TimelinePublicationSkeleton,
            TimelineContainer: TimelineContainer,
            StoriesFeed: StoriesFeed,
            AdCard: AdCard,
            FollowRecommendation: FollowRecommendation,
            FeedUpdate: FeedUpdate,
            ContentTabs: ContentTabs,
            TabsItem: TabsItem
        }
    });
</script>