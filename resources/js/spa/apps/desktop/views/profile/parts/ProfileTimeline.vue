<template>
	<template v-if="state.isLoading">
		<div class="block">
			<TimelinePublicationSkeleton v-for="i in 3"></TimelinePublicationSkeleton>
		</div>
	</template>
	<template v-else>
		<template v-if="profilePosts.length"> 
			<TimelinePublication 
				v-for="postData in profilePosts"
				v-bind:postData="postData"
				v-on:delete="handleDeletePost(postData)"
			v-bind:key="postData.hash_id"></TimelinePublication>

			<div v-if="state.isLoadingContent">
				<div class="flex justify-center my-4">
					<div class="colibri-primary-animation"></div>
				</div>
			</div>
		</template>
		<template v-else>
			<div class="block py-40">
				<TimelineEmptyState v-if="contentType == 'posts'" v-bind:desc="$t('empty_state.profile.posts.desc')"></TimelineEmptyState>
				<TimelineEmptyState v-else v-bind:desc="$t('empty_state.profile.media.desc')"></TimelineEmptyState>
			</div>
		</template>
	</template>
</template>

<script>
	import { defineComponent, ref, reactive, onMounted, inject } from 'vue';
	import { useInfiniteScroll } from '@/kernel/vue/composables/infinite-scroll/index.js';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { useDeletePost } from '@/kernel/vue/composables/delete-post/index.js';

	import TimelinePublicationSkeleton from '@D/components/timeline/feed/TimelinePublicationSkeleton.vue';
	import TimelinePublication from '@D/components/timeline/feed/TimelinePublication.vue';
    import TimelineEmptyState from '@D/components/timeline/state/TimelineEmptyState.vue';

	export default defineComponent({
		props: {
			contentType: {
				type: String,
				default: 'posts'
			}
		},
		setup(props) {
			const profileData = inject('profileData');
			const profilePosts = ref([]);
			const { postDeleter } = useDeletePost();
			
			const state = reactive({
                noMoreContent: false,
                isLoading: true,
                isLoadingContent: false
            });

			const fetchPosts = async () => {
				try {
					if(! state.isLoadingContent && ! state.noMoreContent) {
						state.isLoadingContent = true;

						let cursorId = 0;

						if(profilePosts.value.length) {
							cursorId = profilePosts.value.at(-1).id;
						}

						await colibriAPI().userProfile().params({
							id: profileData.value.id,
							filter: {
								type: props.contentType,
								cursor: cursorId
							}
						}).getFrom('profile/posts').then(function(response) {
							let content = response.data.data;

							if(content.length) {
								profilePosts.value = profilePosts.value.concat(content);
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
				callback: fetchPosts
			});

			onMounted(async () => {
				await fetchPosts();

				state.isLoading = false;
			});

			const handleDeletePost = (postData) => {
				postDeleter(postData, (postId) => {
					let postIndex = profilePosts.value.findIndex((item) => {
						return item.id == postId;
					});

					if(postIndex !== -1) {
						profilePosts.value.splice(postIndex, 1);
					}
				});
			}
			
			return {
				state: state,
				profilePosts: profilePosts,
				handleDeletePost: handleDeletePost
			};
		},
		components: {
            TimelinePublication: TimelinePublication,
			TimelinePublicationSkeleton: TimelinePublicationSkeleton,
            TimelineEmptyState: TimelineEmptyState,
		}
	});
</script>