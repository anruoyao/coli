<template>
	<TimelineContainer>
        <div class="px-4 pb-3 pt-1">
            <StoriesFeed></StoriesFeed>
        </div>
        <Border height="h-2" opacity="opacity-30"></Border>
		<div v-if="state.isLoading">
			<TimelinePublicationSkeleton v-for="i in 2" v-bind:key="i"></TimelinePublicationSkeleton>
		</div>
		<div class="pb-6" v-else>
            <FeedUpdate v-if="timelineNewPosts.length" v-bind:posts="timelineNewPosts" v-on:click="applyTimelineUpdate"></FeedUpdate>
			<div v-if="timelinePosts.length">
                <template v-for="(postData, index) in timelinePosts" v-bind:key="postData.hash_id">
                    <TimelinePublication v-bind:postData="postData" v-on:delete="handlePostDelete(postData)"></TimelinePublication>
                    
                    <!-- Show follow recommendation every 10 posts -->
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
		</div>
	</TimelineContainer>
</template>

<script>
    import { defineComponent, ref, reactive, onMounted, computed, onUnmounted } from 'vue';
    import { useTimelineStore } from '@M/store/timeline/timeline.store.js';
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

                // If they are loaded, we don't need to load them again.
                // Timeline will be update by feed update component.
                
                if(! timelinePosts.value.length) {
                    await timelineStore.initialLoad();
    
                    state.isLoading = false;

                    // Update feed every 10 minutes.
                    // 10 minutes are optimal for the feed update interval.
                    
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

                else{
                    state.isLoading = false;
                }
            });

            onUnmounted(() => {
                if(updateIntervalId) {
                    clearInterval(updateIntervalId);
                }
			});

            const loadMorePost = async () => {
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
                callback: loadMorePost
            });

            return {
                timelinePosts: timelinePosts,
                state: state,
                timelineNewPosts: timelineNewPosts,
                handlePostDelete: (postData) => {
                    postDeleter(postData, (postId) => {
                        colibriEventBus.emit('timeline:post-deleted', postId);
                        
                        toastSuccess(__t('toast.media.post_deleted'));
                    });
                },
                applyTimelineUpdate: () => {
                    timelineStore.applyUpdate();
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
            FeedUpdate: FeedUpdate
        }
    });
</script>