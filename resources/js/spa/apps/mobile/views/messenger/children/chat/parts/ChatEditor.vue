<template>
	<ToastNotification></ToastNotification>

	<template v-if="repliedMessage">
		<MessageReplyPreview v-bind:messageData="repliedMessage" v-on:cancel="cancelReply" v-bind:key="repliedMessage.id"></MessageReplyPreview>
	</template>
	
	<div class="pb-3 px-4 pt-3">
		<div class="relative leading-none">
			<textarea ref="messageContentField" class="resize-none pl-5 pt-3 pr-16 pb-2 leading-normal text-lab-pr text-par-l bg-fill-qt w-full h-12 min-h-12 max-h-40 overflow-x-hidden overflow-y-auto rounded-3xl outline-hidden placeholder:text-par-l placeholder:text-lab-sc placeholder:font-normal"
				v-model.trim="messageContent"
				v-on:input="messageInputHandler"
			v-bind:placeholder="inputPlaceholder"></textarea>
	
			<div class="absolute right-2 top-2">
				<div class="flex gap-4">
					<div class="shrink-0">
						<PrimaryIconButton v-bind:disabled="submitButtonStatus" v-on:click="submitForm" iconName="send-03" iconSize="icon-normal" buttonColor="text-brand-900"></PrimaryIconButton>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, ref, reactive, computed, nextTick, onMounted } from 'vue';
	import { useChatStore } from '@M/store/chats/chat.store.js';
	import { useInputHandlers } from '@/kernel/vue/composables/input/index.js';
	import { colibriSounds } from '@/kernel/services/sounds/index.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';

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
				isSubmitting: false
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
				submitButtonStatus: computed(() => {
					return state.isSubmitting || ! messageContent.value.length;
				}),
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
			MessageReplyPreview: MessageReplyPreview
		}
	});
</script>