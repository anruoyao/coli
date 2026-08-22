<template>
	<div class="bg-bg-pr px-6" v-bind:class="[(isReplaying || state.videoRecorder.open || state.audioRecorder.open) ? 'border-t border-t-bord-card' : '']">
        <template v-if="state.videoRecorder.open">
            <VideoRecordPreview></VideoRecordPreview>
        </template>
        <template v-else-if="state.audioRecorder.open">
            <AudioRecorder v-on:cancel="state.audioRecorder.open = false" v-on:sendAudio="sendAudio"></AudioRecorder>
        </template>
        <template v-else>
            <div class="py-4">
                <div v-if="isReplaying" class="mb-3">
                    <MessageReplyPreview v-on:cancel="cancelMessageReply" v-bind:key="repliedMessage.id" v-bind:messageData="repliedMessage"></MessageReplyPreview>
                </div>
                <div class="relative leading-none">
                    <div class="absolute left-3 top-3">
                        <div class="relative">
                            <IconButton v-on:click.stop="state.isEmojisPickerOpen = true" v-bind:disabled="state.isSubmitting" iconName="face-smile" iconType="line"></IconButton>
                            <template v-if="state.isEmojisPickerOpen">
                                <div class="block absolute bottom-6 left-0 w-80 z-50">
                                    <EmojisPicker
                                        v-on:pick="insertMessageEmoji"
                                    v-on:close="state.isEmojisPickerOpen = false"></EmojisPicker>
                                </div>
                            </template>
                        </div>
                    </div>

                    <textarea ref="messageInputField" class="resize-none pl-11 pr-36 pt-2.5 pb-2 leading-normal text-lab-pr font-normal text-par-l border border-bord-card w-full h-12 min-h-12 max-h-40 overflow-x-hidden overflow-y-auto rounded-3xl outline-hidden placeholder:text-par-l placeholder:text-lab-sc placeholder:font-normal"
                        v-on:input.trim="messageInputHandler"
                        v-on:keydown.enter="submitForm"
                        v-model.trim="inputMessageText"
                    v-bind:placeholder="isReplaying ? $t('chat.write_reply') : $t('chat.write_message')"></textarea>

                    <div class="absolute right-4 top-3">
                        <div class="flex gap-4">
                            <IconButton v-if="false" v-on:click="$comingSoon" v-bind:disabled="true" iconName="paperclip" iconType="line"></IconButton>
                            <IconButton v-if="hasTyped" v-bind:disabled="state.isSubmitting" v-on:click="submitForm" iconName="send-03" iconType="solid"></IconButton>
                            <template v-else>
                                <IconButton v-on:click="$refs.messageImageFileInput.click()" iconName="image-01" iconType="line"></IconButton>
                                <IconButton v-on:click.stop="state.videoRecorder.open = true" iconName="camera-03" iconType="line"></IconButton>
                                <IconButton v-on:click.stop="state.audioRecorder.open = true" iconName="microphone-01" iconType="line"></IconButton>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </template>
	</div>

    <template v-if="state.videoRecorder.open">
        <VideoRecorder v-on:sendVideo="sendVideo" v-on:cancel="state.videoRecorder.open = false"></VideoRecorder>
    </template>

    <div class="hidden">
        <input v-on:change="sendImage" type="file" accept="image/jpeg, image/png, image/webp, image/heic, image/heif, image/heif-sequence, image/heic-sequence" ref="messageImageFileInput">
    </div>
</template>

<script>
	import { defineComponent, ref, computed, reactive, defineAsyncComponent, onMounted } from 'vue';
	import { useInputHandlers } from '@/kernel/vue/composables/input/index.js';
	import { useChatStore } from '@D/store/chats/chat.store.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { colibriSounds } from '@/kernel/services/sounds/index.js';

	import MessageReplyPreview from '@D/views/messenger/children/chat/parts/form/MessageReplyPreview.vue';
    import IconButton from '@D/views/messenger/children/chat/parts/ui/IconButton.vue';
    import VideoRecorder from '@D/views/messenger/children/chat/parts/form/VideoRecorder.vue';
    import AudioRecorder from '@D/views/messenger/children/chat/parts/form/AudioRecorder.vue';
    import VideoRecordPreview from '@D/views/messenger/children/chat/parts/form/VideoRecordPreview.vue';

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
				isEmojisPickerOpen: false,
                videoRecorder: {
                    open: false,
                },
                audioRecorder: {
                    open: false,
                },
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

            const sendMessage = async function(payload = null) {
                if(payload !== null) {
                    try {
                        state.isSubmitting = true;

                        if(repliedMessage.value) {
                            payload['parent_id'] = repliedMessage.value.id;
                        }

                        await chatStore.sendMessage(payload);

                        state.isSubmitting = false;
                        colibriSounds.chatMessageSent();


                        repliedMessage.value = null;
                    } catch (error) {
                        alert(error);
                    }
                }
            }

			const submitForm = async function(event) {
				if(! state.isSubmitting) {
					if (event.shiftKey) {
						messageInputHandler();
					}
					else{
						event.preventDefault();
						state.isEmojisPickerOpen = false;

						await sendMessage({
                            content: inputMessageText.value,
                        });

                        inputMessageText.value = '';

                        messageInputHandler();
					}
				}
            }

            const sendVideo = async (videoData) => {
                state.videoRecorder.open = false;

                await chatStore.sendMediaMessage({
                    type: 'video',
                    file: videoData.blob,
                    extension: videoData.blob.type.includes('mp4') ? 'mp4' : 'webm',
                    duration: videoData.duration,
                });
            }

            const sendAudio = async (audioData) => {
                state.audioRecorder.open = false;

                await chatStore.sendMediaMessage({
                    type: 'audio',
                    extension: audioData.blob.type.includes('mp4') ? 'mp4' : 'webm',
                    file: audioData.blob,
                    duration: audioData.duration,
                });
            }

            const sendImage = async (event) => {
                const file = event.target.files[0];
                const extension = file.name.split('.').pop();

                await chatStore.sendMediaMessage({
                    type: 'image',
                    extension: extension,
                    file: file,
                });
            }

			return {
				state: state,
				repliedMessage: repliedMessage,
				messageInputHandler: messageInputHandler,
				submitForm: submitForm,
                sendVideo: sendVideo,
                sendAudio: sendAudio,
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
				},
                sendImage: sendImage,
			};
		},
		components: {
            IconButton: IconButton,
			EmojisPicker: defineAsyncComponent(() => {
                return import('@D/components/emojis/EmojisPicker.vue');
            }),
			MessageReplyPreview: MessageReplyPreview,
            VideoRecorder: VideoRecorder,
            VideoRecordPreview: VideoRecordPreview,
            AudioRecorder: AudioRecorder,
		}
	});
</script>
