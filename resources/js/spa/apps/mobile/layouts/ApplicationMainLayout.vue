<template>
	<ApplicationHeader v-if="! hideHeader"></ApplicationHeader>

	<div class="pb-14">
		<RouterView></RouterView>
	</div>

	<LightboxPlayer></LightboxPlayer>
	
	<ConfirmationModal></ConfirmationModal>

	<ApplicationNavbar v-if="! hideNavbar"></ApplicationNavbar>

	<ReportModal></ReportModal>

	<NotificationsModal v-if="isNotificationsOpen"></NotificationsModal>

	
</template>

<script>
	import { defineComponent, computed, onMounted, onUnmounted } from 'vue';
	import { useRouter, useRoute } from 'vue-router';
	import { useNotificationsStore } from '@M/store/notifications/notifications.store.js';
	import BRD from '@/kernel/websockets/brd/index.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { useAuthStore } from '@M/store/auth/auth.store.js';
	import { colibriSounds } from '@/kernel/services/sounds/index.js';
	import { usePostEditorStore } from '@M/store/timeline/editor.store.js';

	import ApplicationHeader from '@M/components/layout/ApplicationHeader.vue';
	import ApplicationNavbar from '@M/components/layout/ApplicationNavbar.vue';
	import LightboxPlayer from '@M/components/lightbox/LightboxPlayer.vue';
	import ConfirmationModal from '@M/components/general/modals/prompt/ConfirmationModal.vue';
	import ReportModal from '@M/components/reports/ReportModal.vue';
	import NotificationsModal from '@M/components/notifications/native/NotificationsModal.vue';
	
	
	export default defineComponent({
		setup: function() {
			const notificationsStore = useNotificationsStore();
			const authStore = useAuthStore();
			const postEditorStore = usePostEditorStore();
			const router = useRouter();
			const route = useRoute();

			const openEditor = (data) => {
				if(data.mentionName) {
					postEditorStore.mentionName = data.mentionName;
				}

				router.push({
					name: 'post_editor'
				});
			};

			onMounted(() => {
				if(window.ColibriBRD) {
                    ColibriBRD.private(BRD.getChannel('AUTH_USER', [authStore.userData.id])).notification(function (event) {
                        if(event.type === 'chat.notification') {
                            // TODO: Handle chat notifications
                        }
                        else {
                            notificationsStore.setUnreadNotificationsCount(event.data);
                            colibriEventBus.emit('notifications:received');
                        }

                        colibriSounds.notificationReceived();
                    });
                }

				colibriEventBus.on('post-editor:open', openEditor);
			});

			onUnmounted(() => {
                if(window.ColibriBRD) {
                    ColibriBRD.leave(BRD.getChannel('AUTH_USER', [authStore.userData.id]));
                }

				colibriEventBus.off('post-editor:open', openEditor);
            });

			return {
				isNotificationsOpen: computed(() => {
					return notificationsStore.isOpen;
				}),
				hideNavbar: computed(() => {
					return route.meta.hideNavbar || false;
				}),
				hideHeader: computed(() => {
					return route.meta.hideHeader || false;
				})
			};
		},
		components: {
			ApplicationHeader: ApplicationHeader,
			ApplicationNavbar: ApplicationNavbar,
			LightboxPlayer: LightboxPlayer,
			ConfirmationModal: ConfirmationModal,
			ReportModal: ReportModal,
			NotificationsModal: NotificationsModal
		}
	});
</script>