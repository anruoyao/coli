<template>
	<TimelineContainer>
		<Toolbar v-on:close="$router.back" v-bind:title="$t('labels.publication_page_title')"></Toolbar>
		<TimelinePublicationSkeleton v-if="state.isLoading"></TimelinePublicationSkeleton>
		<template v-else>
			<TimelinePublication v-bind:postData="postData" v-on:delete="handlePostDelete"></TimelinePublication>
		</template>
	</TimelineContainer>
</template>

<script>
	import { defineComponent, onMounted, onUnmounted, reactive, ref } from 'vue';
	import { useRouter } from 'vue-router';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { useAuthStore } from '@M/store/auth/auth.store.js';

	import TimelinePublication from '@M/components/timeline/feed/TimelinePublication.vue';
    import TimelinePublicationSkeleton from '@M/components/timeline/feed/TimelinePublicationSkeleton.vue';
    import TimelineContainer from '@M/components/timeline/feed/TimelineContainer.vue';
	import Toolbar from '@M/components/layout/Toolbar.vue';

	export default defineComponent({
		props: {
			hash_id: {
				type: String,
				required: true
			}
		},
		setup: function(props) {
			const router = useRouter();
			const authStore = useAuthStore();
			const state = reactive({
				isLoading: true
			});

			const postData = ref(null);
			const postAuthor = ref(null);

			const fetchPost = async function() {
				state.isLoading = true;

				// 访客走访客端点，登录用户走原 timeline 端点
				const api = authStore.isGuest ? colibriAPI().guest() : colibriAPI().userTimeline();

				await api.getFrom(`post/${props.hash_id}`).then(function(response) {
					postData.value = response.data.data.post;
					postAuthor.value = response.data.data.author;
					state.isLoading = false;
				}).catch(function(error) {
					router.push({
                        name: 'error_404'
                    });
				});
			};

			onMounted(fetchPost);

			// 访客登录成功后：无刷新以登录态重新加载本页
			const onLoginSucceeded = function() {
				fetchPost();
			};

			colibriEventBus.on('auth:login-succeeded', onLoginSucceeded);

			onUnmounted(function() {
				colibriEventBus.off('auth:login-succeeded', onLoginSucceeded);
			});

			return {
				state: state,
				postData: postData,
				postAuthor: postAuthor,
				handlePostDelete: () => {
					alert('post deleted');
				}
			};
		},
		components: {
			TimelinePublication: TimelinePublication,
			TimelinePublicationSkeleton: TimelinePublicationSkeleton,
			TimelineContainer: TimelineContainer,
			Toolbar: Toolbar
		}
	});
</script>