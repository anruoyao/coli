<template>
    <SidedContentLayout>
        <template v-slot:content>
            <TimelineContainer>
                <HomeHeader></HomeHeader>
                
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
                filter: {
                    page: 1
                }
            });

            let updateIntervalId = null;
            let updateAttempts = 0;
            const { postDeleter } = useDeletePost();
            const timelineStore = useTimelineStore();

            const timelineNewPosts = computed(() => {
                return timelineStore.update;
            });

            const timelinePosts = computed(() => {
                return timelineStore.posts;
            });

            onMounted(async () => {
                state.isLoading = true;

                await timelineStore.initialLoad();

                state.isLoading = false;

                // Update feed every 10 minutes.
				// 10 minutes are optimal for the feed update interval.

                if(timelinePosts.value.length) {
                    updateIntervalId = setInterval(async () => {
                        if(! state.isUpdating) {
                            if(timelineNewPosts.value.length == 0 && updateAttempts > 10) {
                                clearInterval(updateIntervalId);
                            }
                            else {
                                state.isUpdating = true;
        
                                await timelineStore.updateFeed();
        
                                state.isUpdating = false;

                                updateAttempts++;
                            }
                        }
                    }, ((60 * 1000) * 10));
                }
            });

            onUnmounted(() => {
                if(updateIntervalId) {
                    clearInterval(updateIntervalId);
                }
			});

            const loadMorePosts = async () => {
				try {
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
                handlePostDelete: (postData) => {
                    postDeleter(postData, (postId) => {
                        colibriEventBus.emit('timeline:post-deleted', postId);
                    });
                },
                applyTimelineUpdate: () => {
                    timelineStore.applyUpdate();
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