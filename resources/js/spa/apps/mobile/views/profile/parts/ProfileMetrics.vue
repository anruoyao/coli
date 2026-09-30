<template>
	<div class="flex gap-4">
		<span v-if="profileData.followers_count" v-on:click="openFollowers" class="cursor-pointer text-lab-pr2 text-par-m">
			<span class="font-semibold">
				{{ profileData.followers_count.formatted }}
			</span>
			{{ $t('labels.followers_count', profileData.followers_count.raw) }}
		</span>
		<span v-if="profileData.following_count" v-on:click="openFollowings" class="cursor-pointer text-lab-pr2 text-par-m">
			<span class="font-semibold">
				{{ profileData.following_count.formatted }}
			</span>
			{{ $t('labels.following_count', profileData.following_count.raw) }}
		</span>
		<span v-if="profileData.publications_count" class="text-lab-pr2 text-par-m">
			<span class="font-semibold">
				{{ profileData.publications_count.formatted }}
			</span>
			{{ $t('labels.posts_count', profileData.publications_count.raw) }}
		</span>
	</div>
	<!-- 仅登录用户渲染关系弹层（访客已被闸门拦截） -->
	<template v-if="! authStore.isGuest && profileData.followers_count && state.isFollowersModalOpen">
		<ProfileFollowers v-on:close="state.isFollowersModalOpen = false"></ProfileFollowers>
	</template>
	<template v-if="! authStore.isGuest && profileData.following_count && state.isFollowingsModalOpen">
		<ProfileFollowings v-on:close="state.isFollowingsModalOpen = false"></ProfileFollowings>
	</template>
</template>

<script>
	import { defineComponent, reactive, inject } from 'vue';

	import ProfileFollowers from '@M/views/profile/parts/relationship/ProfileFollowers.vue';
	import ProfileFollowings from '@M/views/profile/parts/relationship/ProfileFollowings.vue';
	import { useAuthGate } from '@M/core/composables/useAuthGate.js';

	export default defineComponent({
		setup: function() {
			const profileData = inject('profileData');
			const state = reactive({
				isFollowersModalOpen: false,
				isFollowingsModalOpen: false
			});

			const { guard, authStore } = useAuthGate();

			return {
				authStore: authStore,
				state: state,
				profileData: profileData,
				// 访客点击：弹登录引导，不打开列表
				openFollowers: function() {
					if (guard()) state.isFollowersModalOpen = true;
				},
				openFollowings: function() {
					if (guard()) state.isFollowingsModalOpen = true;
				},
			}
		},
		components: {
			ProfileFollowers: ProfileFollowers,
			ProfileFollowings: ProfileFollowings
		}
	});
</script>
