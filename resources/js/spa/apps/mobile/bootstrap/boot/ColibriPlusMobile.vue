<template>
	<template v-if="appLoading">
		<div class="fixed inset-0 z-100 bg-bg-pr flex-center">
            <div class="inline-block w-10">
                <img v-bind:src="$embedder('assets.logos.url')" alt="Logo" class="w-full">
            </div>
        </div>
	</template>
	<template v-else>
		<ApplicationMainLayout v-if="isMainLayout"></ApplicationMainLayout>

		<FlatLayout v-if="isFlatLayout"></FlatLayout>

		<PostEditorLayout v-if="isPostEditorLayout"></PostEditorLayout>

		<MessengerLayout v-if="isMessengerLayout"></MessengerLayout>
	</template>
</template>

<script>
	import { defineComponent, computed, ref, onMounted } from 'vue';
	import { useAppStore } from '@M/store/app/app.store.js';
	import { useAuthStore } from '@M/store/auth/auth.store.js';
	import { useRoute } from 'vue-router';
	import { Layouts } from '@M/core/constants/layouts.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';

	import ApplicationMainLayout from '@M/layouts/ApplicationMainLayout.vue';
	import PostEditorLayout from '@M/layouts/PostEditorLayout.vue';
	import MessengerLayout from '@M/layouts/MessengerLayout.vue';
	import FlatLayout from '@M/layouts/FlatLayout.vue';

	export default defineComponent({
		setup: function() {
			const route = useRoute();
			const appLoading = ref(true);
			const appStore = useAppStore();
			const authStore = useAuthStore();

			onMounted(async () => {
                await appStore.bootstrapApplication();

				appLoading.value = false;

				colibriEventBus.on('auth:logout', logoutUser);
			});

			const logoutUser = () => {
				colibriEventBus.emit('confirmation-modal:open', {
					title: __t('prompt.logout.title'),
					description: __t('prompt.logout.description'),
					confirmButtonText: __t('prompt.logout.confirm'),
					onConfirm: () => {
						authStore.logoutUser();
						window.location.href = embedder('routes.user_auth_index');
					}
				});
			}

			const layoutType = computed(() => {
                return route.meta.layout;
            });

			return {
				appLoading: appLoading,
				isMainLayout: computed(() => {
					return layoutType.value == Layouts.MAIN;
				}),
				isPostEditorLayout: computed(() => {
					return layoutType.value == Layouts.POST_EDITOR;
				}),
				isMessengerLayout: computed(() => {
					return layoutType.value == Layouts.MESSENGER;
				}),
				isFlatLayout: computed(() => {
					return layoutType.value == Layouts.FLAT;
				})
			};
		},
		components: {
			ApplicationMainLayout: ApplicationMainLayout,
			PostEditorLayout: PostEditorLayout,
			MessengerLayout: MessengerLayout,
			FlatLayout: FlatLayout
		}
	});
</script>