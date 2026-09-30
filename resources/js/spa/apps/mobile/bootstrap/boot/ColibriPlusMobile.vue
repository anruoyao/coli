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

	<MaintenanceOverlay v-if="appStore.maintenance && appStore.maintenance.on" :message="appStore.maintenance.message" :until="appStore.maintenance.until"></MaintenanceOverlay>

	<GuestAuthSheet></GuestAuthSheet>
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
	import MaintenanceOverlay from '@M/components/layout/parts/maintenance/MaintenanceOverlay.vue';
	import UserBlockedOverlay from '@M/components/layout/parts/maintenance/UserBlockedOverlay.vue';
	import GuestAuthSheet from '@M/components/auth/GuestAuthSheet.vue';
	import { setAppBootPromise } from '@M/bootstrap/boot-state.js';
	import BRD from '@/kernel/websockets/brd/index.js';

	// 公共命令频道：维护模式等全局指令广播（与 App 端一致）
	const PUBLIC_COMMAND_CHANNEL = 'App.Commands';
	const MAINTENANCE_EVENT = '.main.command';

	export default defineComponent({
		setup: function() {
			const route = useRoute();
			const appLoading = ref(true);
			const appStore = useAppStore();
			const authStore = useAuthStore();

			// 订阅公共命令频道：维护模式实时开/关
			const maintenanceSubscription = () => {
				if(!window.ColibriBRD) return;

				ColibriBRD.channel(PUBLIC_COMMAND_CHANNEL).listen(MAINTENANCE_EVENT, function(event) {
					if(event && event.action === 'maintenance_on') {
						appStore.setMaintenance({
							on: true,
							message: event.message,
							until: event.until
						});
					}
					else if(event && event.action === 'maintenance_off') {
						appStore.setMaintenance({ on: false });
					}
				});
			};

			// 订阅用户私有频道：封禁/停用实时指令（banned/suspended → 封禁页，active → 恢复）
			const subscribeUserStatus = () => {
				if(!window.ColibriBRD || !authStore.userData) return;

				ColibriBRD.private(BRD.getChannel('AUTH_USER', [authStore.userData.id])).listen('.main.command', function(event) {
					if(!event) return;

					if(event.action === 'blocked' || event.action === 'suspended') {
						appStore.setUserStatus({
							status: event.action,
							reason: event.reason
						});
					}
					else if(event.action === 'active') {
						appStore.setUserStatus({ status: '' });
					}
				});
			};

			// 启动在 setup 同步发起并注册 promise：初始路由守卫 await 该 promise
			// 完成后再按访客/登录态放行，保证深链直达时权限判定准确。
			const bootPromise = appStore.bootstrapApplication();
			setAppBootPromise(bootPromise.then(() => {
				appLoading.value = false;
			}));

			const logoutUser = () => {
				colibriEventBus.emit('confirmation-modal:open', {
					title: __t('prompt.logout.title'),
					description: __t('prompt.logout.description'),
					confirmButtonText: __t('prompt.logout.confirm'),
					onConfirm: async () => {
						// 必须等待登出请求完成再跳转：否则导航会中断未完成的 POST，
						// session 未销毁，/login 的 guest 中间件会把用户弹回首页（表现为“退出没反应”）
						try {
							await authStore.logoutUser();
						} finally {
							window.location.href = embedder('routes.user_auth_index');
						}
					}
				});
			}

			const layoutType = computed(() => {
            return route.meta.layout;
        });

		colibriEventBus.on('auth:logout', logoutUser);

		maintenanceSubscription();
		subscribeUserStatus();

		onMounted(async () => {
			await bootPromise;
		});

		return {
				appLoading: appLoading,
				appStore: appStore,
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
			UserBlockedOverlay: UserBlockedOverlay,
			MaintenanceOverlay: MaintenanceOverlay,
			GuestAuthSheet: GuestAuthSheet,
			ApplicationMainLayout: ApplicationMainLayout,
			PostEditorLayout: PostEditorLayout,
			MessengerLayout: MessengerLayout,
			FlatLayout: FlatLayout
		}
	});
</script>