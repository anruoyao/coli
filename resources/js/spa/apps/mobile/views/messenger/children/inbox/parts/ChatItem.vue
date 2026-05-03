<template>
	<RouterLink v-on:click="markAsRead" v-bind:to="{ name: 'messenger_chat', params: {chat_id: chatData.chat_id } }" class="flex gap-2.5 py-3 items-center px-4">
		<div class="shrink-0">
			<AvatarNormal v-bind:avatarSrc="chatData.chat_info.avatar_url"></AvatarNormal>
		</div>

		<div class="flex-1 overflow-hidden">
			<div class="flex justify-between items-center gap-4">
				<Name v-bind:name="chatData.chat_info.name" v-bind:isVerified="chatData.chat_info.verified"></Name>
			</div>
			<div class="flex items-center justify-between gap-4">
				<div class="flex-1 overflow-hidden">
					<p class="text-lab-pr2 text-par-m">
						<template v-if="isTyping">
							<span class="text-green-900 font-medium">
								{{ $t('chat.typing') }}
							</span>
						</template>
						<template v-else-if="chatData.is_deleted">
							{{ $t('chat.message_is_deleted') }}
						</template>
						<template v-else-if="chatData.last_message">
							<span class="flex items-end gap-2.5 overflow-hidden max-w-full">
								<span class="line-clamp-2" v-html="chatData.last_message"></span>
								<span class="text-par-s text-lab-sc whitespace-nowrap">
									{{ chatData.last_activity.time_ago }}
								</span>
							</span>
						</template>
						<template v-else>
							<time class="text-par-s text-lab-sc whitespace-nowrap">
								{{ $t('labels.was_online_at', { time: chatData.last_activity.formatted }) }}
							</time>
						</template>
					</p>
				</div>
				<BadgeCounter color="bg-green-600"
					v-if="chatData.unread_messages_count.raw"
				v-bind:count="chatData.unread_messages_count.formatted"></BadgeCounter>
			</div>
		</div>
	</RouterLink>
</template>

<script>
	import { defineComponent, computed, onMounted, reactive, ref, onUnmounted } from 'vue';
	import { useChatStore } from '@D/store/chats/chat.store.js';

	import AvatarNormal from '@M/components/general/avatars/AvatarNormal.vue';
	import BRD from '@/kernel/websockets/brd/index.js';
	import BadgeCounter from '@M/components/general/counters/BadgeCounter.vue';

	export default defineComponent({
		props: {
			chatData: {
				type: Object,
				required: true
			}
		},
		setup: function(props) {
			const chatData = ref(props.chatData);
			const chatStore = useChatStore();

			const state = reactive({
				typing: {
                    is_typing: false,
                    user: null
                }
			});

			onUnmounted(() => {
                if(window.ColibriBRConnected) {
					ColibriBRD.private(BRD.getChannel('CHAT', [chatData.value.chat_id])).stopListeningForWhisper(BRD.getEvent('CHAT_MESSAGE_TYPING'));
				}
			});

			onMounted(() => {
				if(window.ColibriBRConnected) {
					ColibriBRD.private(BRD.getChannel('CHAT', [chatData.value.chat_id])).listenForWhisper(BRD.getEvent('CHAT_MESSAGE_TYPING'), function (event) {
						state.typing = event.data;
					});
				}
			});

			return {
				state: state,
				isTyping: computed(() => {
                    return state.typing.is_typing;
                }),
				markAsRead: () => {
					chatData.value.unread_messages_count.raw = 0;
					chatData.value.unread_messages_count.formatted = 0;
				}
			}
		},
		components: {
			AvatarNormal: AvatarNormal,
			BadgeCounter: BadgeCounter
		}
	});
</script>