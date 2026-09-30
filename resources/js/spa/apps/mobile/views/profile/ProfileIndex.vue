<template>
	<template v-if="state.isLoading">
		<HeaderSkeleton></HeaderSkeleton>
	</template>
	<template v-else>
		<div class="pt-4 mb-2">
			<div class="mb-4 px-4">
				<ProfileControls></ProfileControls>
			</div>
			<ProfileAvatar></ProfileAvatar>
		</div>
		<div class="mb-2 px-4">
			<ProfileBio></ProfileBio>
		</div>
		<div class="mb-3 px-4 empty:hidden">
			<ProfileOverview></ProfileOverview>
		</div>
		<div class="px-4">
			<ProfileMetrics></ProfileMetrics>
		</div>

		<div v-if="profileData.meta.permissions.can_follow" class="px-4 py-3">
			<ProfileActions></ProfileActions>
		</div>

		<div v-if="! state.isLoading" class="block sticky top-0 bg-bg-pr z-20">
			<ContentTabs>
				<TabsLink v-bind:link="{ name: 'profile_posts' }">
					{{ $t('labels.posts') }}
				</TabsLink>
				<TabsLink v-bind:link="{ name: 'profile_media' }">
					{{ $t('labels.media') }}
				</TabsLink>
				<TabsLink v-if="! authStore.isGuest" v-bind:link="{ name: 'profile_info' }">
					{{ $t('labels.info') }}
				</TabsLink>
			</ContentTabs>
		</div>
		<div class="block border-t border-t-bord-pr">
			<RouterView></RouterView>
		</div>
	</template>
</template>

<script>
	import { defineComponent, computed, ref, watch, provide, onMounted, onUnmounted, reactive } from 'vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { useAuthStore } from '@M/store/auth/auth.store.js';
	import { useRoute, useRouter } from 'vue-router';

	import HeaderSkeleton from '@M/views/profile/parts/skeletons/HeaderSkeleton.vue';
	import ProfileAvatar from '@M/views/profile/parts/ProfileAvatar.vue';
	import ProfileBio from '@M/views/profile/parts/ProfileBio.vue';
	import ProfileControls from '@M/views/profile/parts/controls/ProfileControls.vue';
	import ProfileOverview from '@M/views/profile/parts/ProfileOverview.vue';
	import ProfileMetrics from '@M/views/profile/parts/ProfileMetrics.vue';
	import ContentTabs from '@M/components/general/tabs/content/ContentTabs.vue';
    import TabsLink from '@M/components/general/tabs/content/parts/TabsLink.vue';
	import DropdownButton from '@M/components/general/dropdowns/DropdownButton.vue';
	import ProfileActions from '@M/views/profile/parts/controls/ProfileActions.vue';

	export default defineComponent({
		props: ['id'],
		setup: function(props) {
			const route = useRoute();
			const router = useRouter();
			const authStore = useAuthStore();

			const state = reactive({
				isLoading: true
			});

			const profileId = ref(props.id);

			watch(() => { return route.params.id; }, () => {
				if(route.params.id !== profileId.value) {
					profileId.value = route.params.id;
					fetchProfile();
				}
			});

			const profileData = ref({});

			provide('profileData', profileData);

			onMounted(async () => {
				profileId.value = props.id;

                await fetchProfile();
            });

			const fetchProfile = async () => {
				state.isLoading = true;

				// 访客走访客 profile 端点（路径参数 username），登录用户走原端点
				const request = authStore.isGuest
					? colibriAPI().guest().getFrom(`profile/${props.id}`)
					: colibriAPI().userProfile().params({ id: props.id }).getFrom('profile');

				await request.then(function(response) {
                    profileData.value = response.data.data;
                }).catch(function(error) {
                    router.push({
                        name: 'error_404',
                        params: {
                            id: route.path.substring(1).split('/')
                        },
                        query: route.query,
                        hash: route.hash
                    });
                });

				state.isLoading = false;
			};

			// 访客登录成功后：无刷新以登录态重新加载主页
			const onLoginSucceeded = function() {
				fetchProfile();
			};

			colibriEventBus.on('auth:login-succeeded', onLoginSucceeded);

			onUnmounted(function() {
				colibriEventBus.off('auth:login-succeeded', onLoginSucceeded);
			});

			return {
				authStore: authStore,
				state: state,
				profileData: profileData
			};
		},
		components: {
			HeaderSkeleton: HeaderSkeleton,
			ProfileAvatar: ProfileAvatar,
			ProfileBio: ProfileBio,
			ProfileOverview: ProfileOverview,
			ProfileMetrics: ProfileMetrics,
			ContentTabs: ContentTabs,
			TabsLink: TabsLink,
			ProfileActions: ProfileActions,
			DropdownButton: DropdownButton,
			ProfileControls: ProfileControls
		}
	});
</script>
