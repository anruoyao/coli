<template>
	<div class="py-4 px-6 bg-bg-pr" v-bind:class="[isReplaying ? 'border-t border-t-bord-pr' : '']">
		<div v-if="isReplaying" class="mb-3">
			<MessageReplyPreview v-on:cancel="cancelMessageReply" v-bind:key="repliedMessage.id" v-bind:messageData="repliedMessage"></MessageReplyPreview>
		</div>
		<div class="block relative leading-none">
			<div class="absolute left-4 top-3">
				<div class="relative">
					<button v-on:click.stop="state.isEmojisPickerOpen = true" v-bind:disabled="state.isSubmitting" class="outline-hidden size-icon-normal text-brand-900 disabled:opacity-80 disabled:cursor-wait">
						<SvgIcon type="line" name="face-smile"></SvgIcon>
					</button>
					<template v-if="state.isEmojisPickerOpen">
						<div class="block absolute bottom-6 left-0 w-80 z-50">
							<EmojisPicker 
								v-on:pick="insertMessageEmoji"
							v-on:close="state.isEmojisPickerOpen = false"></EmojisPicker>
						</div>
					</template>
				</div>
			</div>
	
			<textarea ref="messageInputField" class="resize-none pl-12 pr-36 pt-2.5 pb-2 leading-normal text-lab-pr font-normal text-par-l bg-fill-qt w-full h-12 min-h-12 max-h-40 overflow-x-hidden overflow-y-auto rounded-3xl outline-hidden placeholder:text-par-l placeholder:text-lab-sc placeholder:font-normal"
				v-on:input.trim="messageInputHandler"
				v-on:keydown.enter="submitForm"
				v-model.trim="inputMessageText"
			v-bind:placeholder="isReplaying ? $t('chat.write_reply') : $t('chat.write_message')"></textarea>
	
			<div class="absolute right-4 top-3">
				<div class="flex gap-4">
					<button v-on:click="$comingSoon" v-bind:disabled="true" class="outline-hidden size-icon-normal text-brand-900 disabled:opacity-60 disabled:cursor-default">
						<SvgIcon type="line" name="image-plus"></SvgIcon>
					</button>
					<button v-on:click="$comingSoon" v-bind:disabled="true" class="outline-hidden size-icon-normal text-brand-900 disabled:opacity-60 disabled:cursor-default">
						<SvgIcon type="line" name="paperclip"></SvgIcon>
					</button>
					<button v-if="hasTyped" v-bind:disabled="state.isSubmitting" v-on:click="submitForm" class="outline-hidden size-icon-normal text-brand-900 disabled:opacity-60 disabled:cursor-wait">
						<SvgIcon type="solid" name="send-03"></SvgIcon>
					</button>
					<button v-else v-bind:disabled="true" class="outline-hidden size-icon-normal text-brand-900 disabled:opacity-60 disabled:cursor-wait">
						<SvgIcon type="line" name="microphone-01"></SvgIcon>
					</button>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, ref, computed, reactive, defineAsyncComponent, onMounted } from 'vue';
	import { useInputHandlers } from '@/kernel/vue/composables/input/index.js';
	import { useChatStore } from '@D/store/chats/chat.store.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { colibriSounds } from '@/kernel/services/sounds/index.js';
	
	import MessageReplyPreview from '@D/views/messenger/children/chat/parts/form/MessageReplyPreview.vue';

	export default defineComponent({
		emits: ['typing'],
		setup: function (props, context) {
			const repliedMessage = ref(null);
			const chatStore = useChatStore();
			const messageInputField = ref(null);
			const inputMessageText = ref('');
			const { autoResize, insertSymbolAtCaret } = useInputHandlers();
			const state = reactive({
				isSubmitting: false,
				isEmojisPickerOpen: false
			});

            const messageInputHandler = function() {
                autoResize(messageInputField.value);

				context.emit('typing');
            }

			onMounted(() => {
				colibriEventBus.on('messenger-message:reply', (event) => {
					repliedMessage.value = event.messageData;

					if(messageInputField.value) {
						messageInputField.value.focus();
					}
				});
			});

			const submitForm = async function(event) {
				if(! state.isSubmitting) {
					if (event.shiftKey) {
						messageInputHandler();
					}
					else{
						event.preventDefault();
						state.isEmojisPickerOpen = false;
	
						if(inputMessageText.value.length) {
							try {
								state.isSubmitting = true;

								let payload = {
									content: inputMessageText.value
								};

								if(repliedMessage.value) {
									payload['parent_id'] = repliedMessage.value.id;
								}

								await chatStore.sendMessage(payload);

								state.isSubmitting = false;
								colibriSounds.chatMessageSent();
	
								inputMessageText.value = '';
								repliedMessage.value = null;
	
								messageInputHandler();
							} catch (error) {
								alert(error);
							}
						}
					}
				}
            }

			return {
				state: state,
				repliedMessage: repliedMessage,
				messageInputHandler: messageInputHandler,
				submitForm: submitForm,
				autoResize: autoResize,
                messageInputField: messageInputField,
                inputMessageText: inputMessageText,
				hasTyped: computed(() => {
					return inputMessageText.value.length > 0;
				}),
				insertMessageEmoji: (emojiSymbol) => {
                    inputMessageText.value = insertSymbolAtCaret(messageInputField.value, emojiSymbol);
                    messageInputField.value.focus();
                },
				isReplaying: computed(() => {
					return (repliedMessage.value !== null);
				}),
				cancelMessageReply: () => {
					repliedMessage.value = null;
				}
			};
		},
		components: {
			EmojisPicker: defineAsyncComponent(() => {
                return import('@D/components/emojis/EmojisPicker.vue');
            }),
			MessageReplyPreview: MessageReplyPreview
		}
	});
</script>