<template>
	<div class="fixed inset-0 bg-bg-pr flex flex-col mb-safe-bottom">
		<div v-if="! state.isLoading" class="shrink-0">
			<ChatHeader v-on:close="$router.push({ name: 'messenger_inbox' })" v-bind:chatData="chatData" v-bind:typingUser="state.typing"></ChatHeader>
		</div>
		<div class="flex-1 overflow-y-auto py-4" ref="messagesHistoryContainer">
			<template v-if="chatMessages.length">
				<div v-for="messageData in chatMessages" class="block">
					<ChatMessage v-on:delete="handleMessageDelete"
						v-on:copy="handleMessageCopy"
					v-bind:messageData="messageData"
					v-bind:key="messageData.id"></ChatMessage>
				</div>
			</template>
			<template v-else>
				<div class="h-full flex items-center justify-center">
					<p class="text-par-s text-lab-sc">
						{{  $t('chat.no_messages_found') }}
					</p>
				</div>
			</template>
			<div class="h-8 py-2">
				<ChatMessageTyping v-bind:typingUser="state.typing"></ChatMessageTyping>
			</div>
		</div>
		<div class="shrink-0" v-if="! state.isLoading" v-bind:class="{ 'pb-5': $isStandalone() }">
			<ChatEditor v-on:typing="handleMessageTyping"></ChatEditor>
		</div>
	</div>
</template>

<script>
	import { defineComponent, reactive, ref, computed, onMounted, nextTick, onUnmounted } from 'vue';

	import { useRoute, useRouter } from 'vue-router';
	import { colibriSounds } from '@/kernel/services/sounds/index.js';
	import { useChatStore } from '@M/store/chats/chat.store.js';
	import { useAuthStore } from '@M/store/auth/auth.store.js';
	import BRD from '@/kernel/websockets/brd/index.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';

	import ChatMessage from '@M/views/messenger/children/chat/parts/ChatMessage.vue';
	import ChatHeader from '@M/views/messenger/children/chat/parts/ChatHeader.vue';
	import ChatEditor from '@M/views/messenger/children/chat/parts/ChatEditor.vue';
	import ChatMessageTyping from '@M/views/messenger/children/chat/parts/ChatMessageTyping.vue';

	export default defineComponent({
		setup: function() {
			const route = useRoute();
			const router = useRouter();
			const messagesHistoryContainer = ref(null);
			const authStore = useAuthStore();

			const chatStore = useChatStore();

			const chatChannel = ref(null);

			const userData = computed(() => {
                return authStore.userData;
            });

			const chatMessages = computed(() => {
                return chatStore.chatMessages;
            });

			const chatData = computed(() => {
                return chatStore.chatData;
            });

			const state = reactive({
                isLoading: true,
                typing: {
                    is_typing: false,
                    user: null
                }
            });

			const scrollHistoryDown = function() {
                nextTick(() => {
                    messagesHistoryContainer.value.scrollTop = messagesHistoryContainer.value.scrollHeight;
                });
            }

			onUnmounted(() => {
                if(window.ColibriBRConnected) {
                    stopListenEventInChat(BRD.getEvent('CHAT_MESSAGE_RECEIVED'));
                    stopListenEventInChat(BRD.getEvent('CHAT_MESSAGE_READ'));
                    stopListenEventInChat(BRD.getEvent('CHAT_MESSAGE_DELETED'));

					ColibriBRD.private(chatChannel.value).stopListeningForWhisper(BRD.getEvent('CHAT_MESSAGE_TYPING'));
                }
            });

			const stopListenEventInChat = (eventName) => {
                ColibriBRD.private(chatChannel.value).stopListening(eventName);
            }

            const listenEventInChat = (eventName, callback) => {
                ColibriBRD.private(chatChannel.value).listen(eventName, callback);
            }

			const whisperToChat = (isTyping) => {
                ColibriBRD.private(chatChannel.value).whisper(BRD.getEvent('CHAT_MESSAGE_TYPING'), {
                    data: {
                        user: {
                            name: userData.value.name,
							avatar_url: userData.value.avatar_url
                        },
                        is_typing: isTyping
                    }
                });
            }

			onMounted(async function() {
                try {
					await chatStore.fetchChatData(route.params.chat_id);
					await chatStore.fetchChatMessages();

					if (chatMessages.value.length > 0) {
                        chatStore.markMessagesAsRead();

                        debounce(() => {
                            scrollHistoryDown();
                        }, 500);
                    }

					state.isLoading = false;

					chatChannel.value = BRD.getChannel('CHAT', [chatData.value.chat_id]);

					if(window.ColibriBRConnected) {
                        listenEventInChat(BRD.getEvent('CHAT_MESSAGE_RECEIVED'), function (event) {
                            let messageData = event.data;

                            chatStore.appendMessage(messageData);

                            scrollHistoryDown();
							
                            if(userData.value.id != messageData.user_id) {
                                colibriSounds.activeChatMessageReceived();
                                chatStore.markMessagesAsRead();
                            }
                        });

						listenEventInChat(BRD.getEvent('CHAT_MESSAGE_DELETED'), function (event) {
                            chatStore.markMessageAsDeleted(event.data.message_id);
                        });

						ColibriBRD.private(chatChannel.value).listenForWhisper(BRD.getEvent('CHAT_MESSAGE_TYPING'), function (event) {
                            state.typing = event.data;
                        });

						listenEventInChat(BRD.getEvent('CHAT_MESSAGE_READ'), function (event) {
                            chatStore.updateLastReadMessageForParticipant(event.data);
                        });
					}

					scrollHistoryDown();
				}
				catch(e) {
					router.push({ name: 'messenger_inbox' });
				}
			});

			return {
				chatMessages: chatMessages,
				state: state,
				chatData: chatData,
				messagesHistoryContainer: messagesHistoryContainer,
				isTyping: computed(() => {
                    return state.typing.is_typing;
                }),
				handleMessageDelete: (messageData) => {
					const modalData = {
						title: __t('prompt.delete_message.title'),
                        description: (messageData.isSender ? __t('prompt.delete_message.description') : __t('prompt.delete_message_for_me.description')),
                        confirmButtonText: (messageData.isSender ? null : __t('prompt.delete_message_for_me.confirm')),
                        onConfirm: async () => {
                            await chatStore.deleteMessage(messageData.messageId);
                        }
					};

					if(messageData.isSender) {
						modalData.confirmSecondary = true;
						modalData.confirmSecondaryText = __t('chat.delete_message_for_all');
						modalData.onConfirmSecondary = async () => {
							await chatStore.deleteMessage(messageData.messageId, true);
						}
					}

                    colibriEventBus.emit('confirmation-modal:open', modalData);
                },
				handleMessageCopy: (messageData) => {
					try {
						navigator.clipboard.writeText(messageData.content).then(() => {
							toastSuccess(__t('toast.chat.message_text_copied'), 1000);
						});
					} catch (error) {
						toastError(error);
					}
				},
				handleMessageTyping: () => {
                    if(window.ColibriBRConnected) {
                        whisperToChat(true);

                        debounce(() => {
                            whisperToChat(false);
                        }, 1000);
                    }
                }
			};
		},
		components: {
			ChatMessage: ChatMessage,
			ChatHeader: ChatHeader,
			ChatMessageTyping: ChatMessageTyping,
			ChatEditor: ChatEditor
		}
	});
</script>