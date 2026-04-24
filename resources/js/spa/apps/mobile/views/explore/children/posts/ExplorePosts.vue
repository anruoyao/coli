<template>
	<TimelineContainer>
		<div class="sticky top-0 popup-background-tr z-10">
			<ContentTabs v-bind:cols="2">
				<TabsLink v-bind:link="{ name: 'explore_posts' }">
					{{ $t('labels.explore') }}
				</TabsLink>
				<TabsLink v-bind:link="{ name: 'explore_people' }">
					{{ $t('labels.people') }}
				</TabsLink>
			</ContentTabs>
			<FeedUpdate v-if="newPosts.length" v-bind:posts="newPosts" v-on:click="applyNewPosts"></FeedUpdate>
			<Border></Border>
		</div>
		<template v-if="state.isLoading">
			<TimelinePublicationSkeleton v-for="i in 15" v-bind:key="i"></TimelinePublicationSkeleton>
		</template>
		<div v-else>
			<div v-if="posts.length">
				<template v-for="(postData, index) in posts" v-bind:key="postData.id">
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
			</div>
			<div v-else class="py-32">
				<p class="text-lab-sc text-par-s text-center">
					{{ $t('empty_state.empty') }}
				</p>
			</div>

			<div v-if="state.isLoadingContent">
				<Border></Border>
				<div class="flex justify-center my-4">
					<div class="colibri-primary-animation"></div>
				</div>
			</div>
		</div>
	</TimelineContainer>
</template>

<script>
    import { defineComponent, reactive, computed, onMounted, onUnmounted } from 'vue';
    import { useExplorePostsStore } from '@M/store/explore/posts.store.js';
    import { useInfiniteScroll } from '@/kernel/vue/composables/infinite-scroll/index.js';
	import { useDeletePost } from '@/kernel/vue/composables/delete-post/index.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
    
    import TimelineContainer from '@M/components/timeline/feed/TimelineContainer.vue';
    import TimelinePublication from '@M/components/timeline/feed/TimelinePublication.vue';
    import TimelinePublicationSkeleton from '@M/components/timeline/feed/TimelinePublicationSkeleton.vue';
    import ContentTabs from '@M/components/general/tabs/content/ContentTabs.vue';
    import TabsLink from '@M/components/general/tabs/content/parts/TabsLink.vue';
    import FeedUpdate from '@M/components/timeline/update/FeedUpdate.vue';
	import AdCard from '@M/components/ads/AdCard.vue';
    import FollowRecommendation from '@M/components/recommend/follow/FollowRecommendation.vue';

    export default defineComponent({
        setup: function() {
			const state = reactive({
				isLoading: true,
                isLoadingContent: false,
                noMoreContent: false,
                isUpdating: false
			});

            let updateIntervalId = null;
            let updateAttempts = 0;

			const { postDeleter } = useDeletePost();

            const explorePostsStore = useExplorePostsStore();
            const newPosts = computed(() => {
                return explorePostsStore.update;
            });

            const posts = computed(() => {
				return explorePostsStore.posts;
			});

            useInfiniteScroll({
                callback: async () => {
                    if(! state.isLoadingContent && ! state.noMoreContent) {
                        state.isLoadingContent = true;

                        explorePostsStore.filter.page += 1;

                        state.noMoreContent = (! await explorePostsStore.loadMorePosts());

                        state.isLoadingContent = false;
                    }
                }
            });

            onMounted(async() => {
                state.isLoading = true;

				// Reset filter on mount.
				// Because there can be a filter applied from the previous visits.

				explorePostsStore.resetFilter();

                await explorePostsStore.fetchPosts();

               	state.isLoading = false;

                // Update feed every 10 minutes.
				// 10 minutes are optimal for the feed update interval.

                if(posts.value.length) {
                    updateIntervalId = setInterval(async () => {
                        if(! state.isUpdating) {
                            // If 10 update requests are made and there are no new posts, clear the interval.
                            // Stop updating the feed after 10 attempts.

                            if(newPosts.value.length == 0 && updateAttempts > 10) {
                                clearInterval(updateIntervalId);
                            }
                            else {
                                state.isUpdating = true;
        
                                await explorePostsStore.updateFeed();
        
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

            return {
                state: state,
				posts: posts,
                newPosts: newPosts,
                applyNewPosts: () => {
                    explorePostsStore.applyUpdate();
                },
				handlePostDelete: (postData) => {
					postDeleter(postData, (postId) => {
						colibriEventBus.emit('timeline:post-deleted', postId);
                        
                        toastSuccess(__t('toast.media.post_deleted'));
					});
				}
            };
        },
        components: {
            TimelineContainer: TimelineContainer,
            TimelinePublication: TimelinePublication,
            TimelinePublicationSkeleton: TimelinePublicationSkeleton,
            ContentTabs: ContentTabs,
            TabsLink: TabsLink,
            FeedUpdate: FeedUpdate,
			AdCard: AdCard,
			FollowRecommendation: FollowRecommendation
        }
    });
</script>