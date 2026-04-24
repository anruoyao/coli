<template>
    <SidedContentLayout>
        <template v-slot:content>
            <TimelineContainer>
                <ContentTabs v-bind:cols="2">
                    <TabsLink v-bind:link="{ name: 'explore_posts' }">
                        {{ $t('labels.explore') }}
                    </TabsLink>
                    <TabsLink v-bind:link="{ name: 'explore_people' }">
                        {{ $t('labels.people') }}
                    </TabsLink>
                </ContentTabs>
                <Border></Border>

                <TimelineCallout
					iconName="star-04"
					iconType="solid"
					v-bind:title="$t('info.explore_posts.title')"
				v-bind:desc="$t('info.explore_posts.desc')"></TimelineCallout>
                <Border></Border>

                <div class="block" v-if="state.isLoading">
                    <TimelinePublicationSkeleton v-for="i in 3" v-bind:key="i"></TimelinePublicationSkeleton>
                </div>
                <div class="block" v-else>
                    <FeedUpdate v-if="newPosts.length" v-bind:posts="newPosts" v-on:click="applyNewPosts"></FeedUpdate>
                    <div v-if="posts.length">
                        <TimelinePublication v-for="postData in posts" v-bind:key="postData.id" v-bind:postData="postData"></TimelinePublication>
                    </div>
                    <div v-else>
                        <FluidEmptyState v-bind:text="$t('empty_state.empty')"></FluidEmptyState>
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
        <template v-slot:sidebar>
            <FollowRecommendationList></FollowRecommendationList>

            <AdGridItem></AdGridItem>
        </template>
    </SidedContentLayout>

    <Teleport to="body">
        <ScrollTopButton></ScrollTopButton>
    </Teleport>
</template>

<script>
    import { defineComponent, reactive, computed, onMounted, onUnmounted } from 'vue';
    import { useExplorePostsStore } from '@D/store/explore/posts.store.js';
    import { useInfiniteScroll } from '@/kernel/vue/composables/infinite-scroll/index.js';
    
    import SidedContentLayout from '@D/components/layout/SidedContentLayout.vue';
    import TimelineContainer from '@D/components/layout/TimelineContainer.vue';
    import FollowRecommendationList from '@D/components/recommend/follow/list/FollowRecommendationList.vue';
    import AdGridItem from '@D/components/ads/AdGridItem.vue';
    import TimelinePublication from '@D/components/timeline/feed/TimelinePublication.vue';
    import TimelinePublicationSkeleton from '@D/components/timeline/feed/TimelinePublicationSkeleton.vue';
    import FluidEmptyState from '@D/components/page-states/empty/FluidEmptyState.vue';
    import ContentTabs from '@D/components/general/tabs/content/ContentTabs.vue';
    import TabsLink from '@D/components/general/tabs/content/parts/TabsLink.vue';
    import TimelineCallout from '@D/components/timeline/info/TimelineCallout.vue';
    import ScrollTopButton from '@D/components/inter-ui/buttons/ScrollTopButton.vue';
    import FeedUpdate from '@D/components/timeline/update/FeedUpdate.vue';

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
                }
            };
        },
        components: {
            SidedContentLayout: SidedContentLayout,
            TimelineContainer: TimelineContainer,
            FollowRecommendationList: FollowRecommendationList,
            AdGridItem: AdGridItem,
            TimelinePublication: TimelinePublication,
            FluidEmptyState: FluidEmptyState,
            TimelinePublicationSkeleton: TimelinePublicationSkeleton,
            ContentTabs: ContentTabs,
            TabsLink: TabsLink,
            TimelineCallout: TimelineCallout,
            ScrollTopButton: ScrollTopButton,
            FeedUpdate: FeedUpdate
        }
    });
</script>