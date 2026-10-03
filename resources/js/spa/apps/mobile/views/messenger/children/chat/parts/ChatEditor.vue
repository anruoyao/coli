<template>
	<ToastNotification></ToastNotification>

    <template v-if="repliedMessage">
        <MessageReplyPreview v-bind:messageData="repliedMessage" v-on:cancel="cancelReply" v-bind:key="repliedMessage.id"></MessageReplyPreview>
    </template>

    <div class="pb-3 px-4 pt-3">
        <div class="flex overflow-hidden gap-2">
            <div class="flex-1">
                <textarea ref="messageContentField" class="resize-none border border-bord-pr pl-4 pt-2.5 pr-22 pb-2 leading-normal text-lab-pr text-par-l bg-fill-qt w-full h-12 min-h-12 max-h-40 overflow-x-hidden overflow-y-auto rounded-3xl outline-hidden placeholder:whitespace-nowrap placeholder:text-par-l placeholder:text-lab-sc placeholder:font-normal"
                    v-model.trim="messageContent"
                    v-on:input="messageInputHandler"
                v-bind:placeholder="inputPlaceholder"></textarea>
            </div>

            <div class="shrink-0 pt-2">
                <div class="inline-flex gap-2">
                    <PrimaryIconButton v-if="hasTyped" v-bind:disabled="state.isSubmitting" v-on:click="submitForm" iconName="send-03" iconSize="icon-normal" buttonColor="text-brand-900"></PrimaryIconButton>
                    <template v-else>
                        <PrimaryIconButton v-on:click="$refs.messageImageFileInput.click()" v-bind:disabled="state.isSubmitting" iconName="image-01" iconType="line"></PrimaryIconButton>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <div class="hidden">
        <input v-on:change="sendImage" type="file" accept="image/jpeg, image/png, image/webp, image/heic, image/heif, image/heif-sequence, image/heic-sequence" ref="messageImageFileInput">
    </div>
</template>

<script>
	import { defineComponent, ref, reactive, computed, nextTick, onMounted } from 'vue';
	import { useChatStore } from '@M/store/chats/chat.store.js';
	import { useInputHandlers } from '@/kernel/vue/composables/input/index.js';
	import { colibriSounds } from '@/kernel/services/sounds/index.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { checkFileSize } from '@/kernel/services/upload-limits/index.js';

	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';
	import ToastNotification from '@M/components/notifications/toast/ToastNotification.vue';
	import MessageReplyPreview from '@M/views/messenger/children/chat/parts/editor/MessageReplyPreview.vue';

	export default defineComponent({
		emits: ['typing'],
		setup: function (props, context) {
			const chatStore = useChatStore();
			const messageContentField = ref(null);
			const messageContent = ref('');
			const repliedMessage = ref(null);
			const { autoResize } = useInputHandlers();

			const state = reactive({
				isSubmitting: false,
			});

			onMounted(() => {
				colibriEventBus.on('messenger-message:reply', (event) => {
					repliedMessage.value = event.messageData;

					if(messageContentField.value) {
						messageContentField.value.focus();
					}
				});
			});

			const messageInputHandler = function() {
				autoResize(messageContentField.value);

				context.emit('typing');
			}

			const submitForm = async function(event) {
				try {
					state.isSubmitting = true;

					if(messageContent.value.length) {
						const content = messageContent.value;

						const payload = {
							content: content
						};

						messageContent.value = '';

						if(repliedMessage.value) {
							payload['parent_id'] = repliedMessage.value.id;
						}

						repliedMessage.value = null;

						await chatStore.sendMessage(payload);

						nextTick(() => {
							messageInputHandler();
						});

						colibriSounds.chatMessageSent();
					}

					state.isSubmitting = false;
				} catch (error) {
					alert(error);
				}
            }

            const sendImage = async (event) => {
                const file = event.target.files[0];

                // 本地预检测：超出后台配置的图片大小限制时立即提示
                const sizeCheck = await checkFileSize(file, 'image');
                if (! sizeCheck.ok) {
                    toastError(sizeCheck.message);
                    return;
                }

                const extension = file.name.split('.').pop();

                await chatStore.sendMediaMessage({
                    type: 'image',
                    extension: extension,
                    file: file,
                });
            }

			return {
				state: state,
				messageContent: messageContent,
				submitForm: submitForm,
				repliedMessage: repliedMessage,
                messageContentField: messageContentField,
				messageInputHandler: messageInputHandler,
				isReplaying: computed(() => {
					return repliedMessage.value !== null;
				}),
                hasTyped: computed(() => {
					return messageContent.value.length > 0;
				}),
                sendImage: sendImage,
				cancelReply: () => {
					repliedMessage.value = null;
				},
				inputPlaceholder: computed(() => {
					if(state.isSubmitting) {
						return __t('chat.sending_message');
					}

					else if(repliedMessage.value) {
						return __t('chat.write_reply');
					}

					return __t('chat.write_message');
				})
			};
		},
		components: {
			PrimaryIconButton: PrimaryIconButton,
			ToastNotification: ToastNotification,
			MessageReplyPreview: MessageReplyPreview,
		}
	});
</script>
