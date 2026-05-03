<template>
    <div class="h-screen overflow-hidden flex">
        <div class="flex flex-1 h-full flex-col">
            <div class="border-b border-bord-pr shrink-0">
                <ChatHeaderSkeleton v-if="state.isLoading"></ChatHeaderSkeleton>
                <ChatHeader v-else v-on:info="toggleMainMenu" v-bind:typingUser="state.typing"></ChatHeader>
            </div>
            <div ref="chatContainerBlock" class="flex-1 overflow-x-hidden overflow-y-auto py-4">
                <div class="border-b border-bord-pr py-8">
                    <ChatOverviewSkeleton v-if="state.isLoading"></ChatOverviewSkeleton>
                    <ChatOverview v-else></ChatOverview>
                </div>
                <div class="pb-24 pt-2">
                    <div v-if="state.isLoading" class="flex flex-col gap-4 opacity-70">
                        <ChatMessageSkeleton v-for="i in 3"></ChatMessageSkeleton>
                    </div>
                    <div v-else>
                        <template v-if="chatMessages.length">
                            <div v-for="(messageData, messageIndex) in chatMessages" class="block">
                                <div v-if="showDateSeparator(messageIndex)" class="py-4">
                                    <p class="text-par-n font-semibold text-lab-pr3 text-center">
                                        {{ messageData.date.date }}
                                    </p>
                                </div>

                                <ChatMessage
                                    v-on:delete="handleMessageDelete"
                                    v-on:reply="handleMessageReply"
                                    v-on:copy="handleMessageCopy"
                                v-bind:messageData="messageData"
                                v-bind:key="messageData.id"></ChatMessage>
                            </div>
                        </template>
                        <template v-else>
                            <div class="py-12 text-center">
                                <p class="text-par-s text-lab-sc">
                                    {{  $t('chat.no_messages_found') }}
                                </p>
                            </div>
                        </template>

                        <ChatMessageTyping v-if="isTyping" v-bind:typingUser="state.typing"></ChatMessageTyping>
                    </div>
                </div>
            </div>
            <div class="shrink-0">
                <ChatFormSkeleton v-if="state.isLoading"></ChatFormSkeleton>
                <ChatForm v-else v-on:typing="handleMessageTyping"></ChatForm>
            </div>
        </div>

        <template v-if="! isGroup">
            <div v-if="state.mainMenu.status" class="w-messenger-sidebar h-full overflow-y-auto border-l border-l-bord-pr">
                <ChatInfo v-on:close="state.mainMenu.close"></ChatInfo>
            </div>
        </template>
    </div>
</template>

<script>
    import { defineComponent, ref, nextTick, onMounted, reactive, computed, defineAsyncComponent, onUnmounted } from 'vue';
    import { colibriSounds } from '@/kernel/services/sounds/index.js';
    import { useRoute, useRouter } from 'vue-router';
    import { useMenu } from '@/kernel/vue/composables/menu/index.js';
    import { useChatStore } from '@D/store/chats/chat.store.js';
    import { useAuthStore } from '@D/store/auth/auth.store.js';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';
    
    import ChatHeader from '@D/views/messenger/children/chat/parts/ChatHeader.vue';
    import ChatHeaderSkeleton from '@D/views/messenger/children/chat/parts/skeletons/ChatHeaderSkeleton.vue';
    import ChatFormSkeleton from '@D/views/messenger/children/chat/parts/skeletons/ChatFormSkeleton.vue';
    import ChatOverviewSkeleton from '@D/views/messenger/children/chat/parts/skeletons/ChatOverviewSkeleton.vue';
    import ChatMessageSkeleton from '@D/views/messenger/children/chat/parts/skeletons/ChatMessageSkeleton.vue';
    import ChatOverview from '@D/views/messenger/children/chat/parts/ChatOverview.vue';
    import ChatMessage from '@D/views/messenger/children/chat/parts/ChatMessage.vue';
    import ChatMessageTyping from '@D/views/messenger/children/chat/parts/ChatMessageTyping.vue';
    import ChatForm from '@D/views/messenger/children/chat/parts/ChatForm.vue';
    import BRD from '@/kernel/websockets/brd/index.js';
    
    export default defineComponent({
        setup: function(props, context) {
            const state = reactive({
                isLoading: true,
                typing: {
                    is_typing: false,
                    user: null
                },
                mainMenu: useMenu()
            });

            const authStore = useAuthStore();
            const chatStore = useChatStore();
            const router = useRouter();
            const route = useRoute();
            const userData = ref(authStore.userData);
            const chatData = computed(() => {
                return chatStore.chatData;
            });

            const isGroup = computed(() => {
                return chatData.value.is_group;
            });

            const chatMessages = computed(() => {
                return chatStore.chatMessages;
            });

            const chatContainerBlock = ref(null);

            const scrollHistoryDown = function() {
                nextTick(() => {
                    chatContainerBlock.value.scrollTop = chatContainerBlock.value.scrollHeight;
                });
            }

            const teardownSubscriptions = () => {
                try {
                    stopListenEventInChat(BRD.getEvent('CHAT_MESSAGE_RECEIVED'));
                    stopListenEventInChat(BRD.getEvent('CHAT_MESSAGE_READ'));
                    stopListenEventInChat(BRD.getEvent('CHAT_MESSAGE_DELETED'));
                    stopListeningForWhisperInChat(BRD.getEvent('CHAT_MESSAGE_TYPING'));
                } catch (e) {}
            };

            onUnmounted(() => {
                teardownSubscriptions();
                window.removeEventListener('ws:connected', handleWsConnected);
                window.removeEventListener('ws:disconnected', handleWsDisconnected);
            });

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

                    const listenBoth = (eventName, callback) => {
                        // Support Reverb (with leading dot) and Pusher (some setups require no dot)
                        listenEventInChat(eventName, callback);
                        if (eventName.startsWith('.')) {
                            listenEventInChat(eventName.slice(1), callback);
                        } else {
                            listenEventInChat('.' + eventName, callback);
                        }
                    };

                    const initSubscriptions = () => {
                        listenBoth(BRD.getEvent('CHAT_MESSAGE_RECEIVED'), function (event) {
                            let messageData = event.data;
                            chatStore.appendMessage(messageData);
                            scrollHistoryDown();
                            let isSender = (userData.value.id == messageData.user_id);
                            if(! isSender) {
                                colibriSounds.activeChatMessageReceived();
                                chatStore.markMessagesAsRead();
                            }
                        });
                        listenBoth(BRD.getEvent('CHAT_MESSAGE_DELETED'), function (event) {
                            chatStore.markMessageAsDeleted(event.data.message_id);
                        });
                        listenWhisperInChat(BRD.getEvent('CHAT_MESSAGE_TYPING'), function (event) {
                            state.typing = event.data;
                        });
                        listenBoth(BRD.getEvent('CHAT_MESSAGE_READ'), function (event) {
                            chatStore.updateLastReadMessageForParticipant(event.data);
                        });
                    };

                    const handleWsConnected = () => {
                        teardownSubscriptions();
                        initSubscriptions();
                    };
                    const handleWsDisconnected = () => {
                        teardownSubscriptions();
                    };

                    if(window.ColibriBRConnected) {
                        initSubscriptions();
                    } else {
                        window.addEventListener('ws:connected', handleWsConnected);
                        window.addEventListener('ws:disconnected', handleWsDisconnected);
                    }
                } catch (error) {
                    router.push({
                        name: 'error_404',
                        params: { pathMatch: route.path.substring(1).split('/') },
                        query: route.query,
                        hash: route.hash
                    });
                }
            });

            const stopListenEventInChat = (eventName) => {
                ColibriBRD.private(BRD.getChannel('CHAT', [chatData.value.chat_id])).stopListening(eventName);
            }

            const listenEventInChat = (eventName, callback) => {
                ColibriBRD.private(BRD.getChannel('CHAT', [chatData.value.chat_id])).listen(eventName, callback);
            }

            const stopListeningForWhisperInChat = (whisperEvent) => {
                ColibriBRD.private(BRD.getChannel('CHAT', [chatData.value.chat_id])).stopListeningForWhisper(whisperEvent);
            }

            const listenWhisperInChat = (whisperEvent, callback) => {
                ColibriBRD.private(BRD.getChannel('CHAT', [chatData.value.chat_id])).listenForWhisper(whisperEvent, callback);
            }

            const whisperToChat = (whisperEvent, eventData) => {
                ColibriBRD.private(BRD.getChannel('CHAT', [chatData.value.chat_id])).whisper(whisperEvent, eventData);
            }

            const broadcastTyping = (isTyping) => {
                whisperToChat(BRD.getEvent('CHAT_MESSAGE_TYPING'), {
                    data: {
                        user: {
                            name: userData.value.name,
                            avatar_url: userData.value.avatar_url
                        },
                        is_typing: isTyping
                    }
                });
            }

            return {
                state: state,
                chatData: chatData,
                chatMessages: chatMessages,
                isGroup: isGroup,
                chatContainerBlock: chatContainerBlock,
                isTyping: computed(() => {
                    return state.typing.is_typing;
                }),
                toggleMainMenu: () => {
                    state.mainMenu.toggle();
                },
                handleMessageDelete: (messageData) => {
                    const modalData = {
                        title: __t('prompt.delete_message.title'),
                        description: (messageData.isSender ? __t('prompt.delete_message.description') : __t('prompt.delete_message_for_me.description')),
                        confirmButtonText: (messageData.isSender ? null : __t('prompt.delete_message_for_me.confirm')),
                        onConfirm: async () => {
                            await chatStore.deleteMessage(messageData.messageId);

                            toastSuccess(__t('toast.chat.message_deleted'), 1000);
                        },
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
                handleMessageReply: (messageData) => {
                    colibriEventBus.emit('messenger-message:reply', {
                        messageData: messageData
                    });
                },
                handleMessageCopy: (messageData) => {
                    navigator.clipboard.writeText(messageData.content).then(() => {
                        toastSuccess(__t('toast.chat.message_text_copied'), 1000);
                    });
                },
                handleMessageTyping: () => {
                    if(window.ColibriBRConnected) {
                        broadcastTyping(true);

                        debounce(() => {
                            broadcastTyping(false);
                        }, 1000);
                    }
                },
                showDateSeparator: (messageIndex) => {
                    if(messageIndex > 0 && chatMessages.value[messageIndex - 1].date.generic !== chatMessages.value[messageIndex].date.generic) {
                        return true;
                    }

                    return false;
                }
            }
        },
        components: {
            ChatHeader: ChatHeader,
            ChatOverview: ChatOverview,
            ChatMessage: ChatMessage,
            ChatMessageTyping: ChatMessageTyping,
            ChatForm: ChatForm,
            ChatHeaderSkeleton: ChatHeaderSkeleton,
            ChatFormSkeleton: ChatFormSkeleton,
            ChatOverviewSkeleton: ChatOverviewSkeleton,
            ChatMessageSkeleton: ChatMessageSkeleton,
            ChatInfo: defineAsyncComponent(() => {
                return import('@D/views/messenger/children/chat/parts/ChatInfo.vue');
            }),
        }
    });
</script>