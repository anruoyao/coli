<template>
	<div class="fixed bottom-0 left-0 right-0 z-50 bg-bg-pr" v-bind:class="{ 'pb-5': $isStandalone() }">
		<ToastNotification></ToastNotification>
		<StoryFileUploader></StoryFileUploader>

		<div class="grid grid-cols-5 h-14 mb-safe-bottom">
			<div class="flex items-center justify-center">
				<RouterLink v-bind:to="{ name: 'home_index' }">
					<PrimaryIconButton buttonColor="text-lab-pr" iconName="home-smile" iconType="line"></PrimaryIconButton>
				</RouterLink>
			</div>
			<div class="flex items-center justify-center">
				<RouterLink v-bind:to="{ name: 'explore_index' }">
					<PrimaryIconButton buttonColor="text-lab-pr" iconName="search-lg" iconType="solid"></PrimaryIconButton>
				</RouterLink>
			</div>
			<div class="flex items-center justify-center">
				<PrimaryIconButton v-on:click="state.mainMenu.open" buttonColor="text-lab-pr" iconName="plus-square-dashed" iconType="line"></PrimaryIconButton>
			</div>
			<div class="flex items-center justify-center">
				<div class="relative">
					<RouterLink v-bind:to="{ name: 'messenger_index' }">
						<PrimaryIconButton buttonColor="text-lab-pr" iconName="message-chat-circle" iconType="line"></PrimaryIconButton>
					</RouterLink>
					<span class="absolute -top-1.5 -right-1">
						<BadgeCounter v-if="inboxCount.raw" v-bind:count="inboxCount.formatted"></BadgeCounter>
					</span>
				</div>
			</div>
			<div class="flex items-center justify-center leading-zero">
				<RouterLink v-bind:to="{ name: 'profile_index', params: { id: userData.username } }">
					<div class="inline-flex border border-bord-card items-center justify-center size-6 rounded-full overflow-hidden">
						<img v-bind:src="userData.avatar_url" v-bind:alt="userData.username" class="size-full object-cover">
					</div>
				</RouterLink>
			</div>
		</div>
	</div>

	<ActionSheet v-if="state.mainMenu.status" v-on:close="state.mainMenu.close" v-bind:isMuted="true">
		<div v-on:click="state.mainMenu.close">
			<ActionSheetGroup>
				<RouterLink v-bind:to="{ name: 'post_editor' }">
					<ActionSheetItem v-bind:notLast="true" iconName="publication-01" v-bind:textLabel="$t('labels.create_labels.post')"></ActionSheetItem>
				</RouterLink>
				<ActionSheetItem v-on:click="createStory" iconName="create-story-01" v-bind:textLabel="$t('labels.create_labels.story')"></ActionSheetItem>
			</ActionSheetGroup>
		</div>
	</ActionSheet>
</template>

<script>
	import { defineComponent, computed, reactive, onMounted, onUnmounted } from 'vue';
	import { useAuthStore } from '@M/store/auth/auth.store.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { useMenu } from '@/kernel/vue/composables/menu/index.js';
	import { useInboxStore } from '@M/store/chats/inbox.store.js';
	import { colibriSounds } from '@/kernel/services/sounds/index.js';
	import BRD from '@/kernel/websockets/brd/index.js';

	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';
	import ToastNotification from '@M/components/notifications/toast/ToastNotification.vue';
	import ActionSheet from '@M/components/general/sheets/ActionSheet.vue';
	import ActionSheetItem from '@M/components/general/sheets/ActionSheetItem.vue';
	import ActionSheetGroup from '@M/components/general/sheets/ActionSheetGroup.vue';
	import StoryFileUploader from '@M/views/editors/stories/StoryFileUploader.vue';
	import BadgeCounter from '@M/components/general/counters/BadgeCounter.vue';

	export default defineComponent({
		setup: function() {
			const authStore = useAuthStore();
			const inboxStore = useInboxStore();
			const state = reactive({
				mainMenu: useMenu()
			});

			const inboxCount = computed(() => {
                return inboxStore.unreadCount;
            });

			onMounted(() => {
				inboxStore.fetchUnreadCount();
				
				if(window.ColibriBRD) {
                    ColibriBRD.private(BRD.getChannel('AUTH_USER', [authStore.userData.id])).notification(function (event) {
						if(event.type === 'chat.notification') {
							inboxStore.fetchUnreadCount();

							if(localStorage.getItem('notificationsSound')) {
								colibriSounds.backgroundChatMessageReceived();
							}
                        }
                    });
                }
			});

			return {
				userData: computed(() => {
					return authStore.userData;
				}),
				state: state,
				createStory: function() {
					colibriEventBus.emit('story:create');
				},
				inboxCount: inboxCount
			};
		},
		components: {
			PrimaryIconButton: PrimaryIconButton,
			ToastNotification: ToastNotification,
			ActionSheet: ActionSheet,
			ActionSheetItem: ActionSheetItem,
			ActionSheetGroup: ActionSheetGroup,
			StoryFileUploader: StoryFileUploader,
			BadgeCounter: BadgeCounter
		}
	});
</script>