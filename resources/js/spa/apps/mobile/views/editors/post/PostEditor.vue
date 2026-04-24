<template>
	<div class="flex flex-col fixed inset-0 z-50 bg-bg-pr" v-bind:class="{ 'pb-5': $isStandalone() }">
		<div class="shrink-0">
			<Toolbar v-bind:title="$t('editor.new_post')" v-on:close="leaveEditor"></Toolbar>
		</div>
		<Border height="h-2" opacity="opacity-60"></Border>
		<div class="flex-1 overflow-y-auto">
			<div class="flex flex-col h-full">
				<div class="flex-1">
					<textarea ref="contentInput"
						v-model="postData.content"
						v-on:input="textInputHandler"
						class="resize-none py-4 px-6 h-full w-full leading-normal bg-transparent text-title-3 text-lab-pr2 outline-hidden placeholder:font-light placeholder:text-title-3" 
					v-bind:placeholder="$t('editor.post_text_input_placeholder')"></textarea>
				</div>
				<div class="shrink-0">
					<template v-if="postHasMedia">
						<template v-if="PostTypeUtils.isImage(postData.type)">
							<PostImagePreview v-bind:postMedia="postMedia" v-on:delete="deletePostMedia"></PostImagePreview>
						</template>
						<template v-if="PostTypeUtils.isVideo(postData.type)">
							<PostVideoPreview v-bind:postMedia="postMedia" v-on:delete="deletePostMedia"></PostVideoPreview>
						</template>
						<template v-else-if="PostTypeUtils.isDocument(postData.type) || PostTypeUtils.isAudio(postData.type)">
                            <PostDocumentPreview v-bind:postMedia="postMedia" v-on:delete="deletePostMedia"></PostDocumentPreview>
                        </template>

						<template v-else-if="PostTypeUtils.isGif(postData.type)">
                            <PostGifPreview v-bind:postMedia="postMedia" v-on:delete="deletePostMedia"></PostGifPreview>
                        </template>
					</template>
				</div>
			</div>
		</div>
		<div v-if="state.uploadProgress">
			<div class="flex">
				<div class="flex-1 bg-fill-tr h-1">
					<div class="bg-green-900 min-w-10 h-full" v-bind:style="{ width: state.uploadProgress + '%' }"></div>
				</div>
			</div>
		</div>
		<div class="shrink-0 px-6 pt-4 pb-4 mb-safe-bottom">
			<div class="flex gap-2 items-center mb-2 -translate-x-1.5">
				<PrimaryIconButton v-on:click="selectImage" v-bind:disabled="postMediaButtonStatus(PostType.IMAGE)" iconName="image-01" iconType="line" buttonColor="text-lab-pr3"></PrimaryIconButton>
				<PrimaryIconButton v-on:click="selectVideo" v-bind:disabled="postMediaButtonStatus(PostType.VIDEO)" iconName="video-recorder" iconType="line" buttonColor="text-lab-pr3"></PrimaryIconButton>
				<PrimaryIconButton v-on:click="selectAudio" v-bind:disabled="postMediaButtonStatus(PostType.AUDIO)" iconName="music-note-01" iconType="line" buttonColor="text-lab-pr3"></PrimaryIconButton>
				<PrimaryIconButton v-on:click="createPoll" v-bind:disabled="postMediaButtonStatus(PostType.POLL)" iconName="bar-chart-12" iconType="line" buttonColor="text-lab-pr3"></PrimaryIconButton>
				<PrimaryIconButton v-on:click="toggleGifPicker" v-bind:disabled="postMediaButtonStatus(PostType.GIF)" iconName="gif" iconType="line" buttonColor="text-lab-pr3"></PrimaryIconButton>

				<div class="ml-auto opacity-80">
					<PrimaryIconButton v-bind:disabled="submitButtonStatus" v-on:click="submitForm" iconName="send-03" buttonColor="text-lab-pr2"></PrimaryIconButton>
				</div>
			</div>

			<p class="text-par-s text-red-900 mb-2" v-if="validationError">
				{{ validationError }}
			</p>
			
			<p  v-if="userData.is_author" class="text-par-s text-lab-sc">
				{{ $t('editor.post_privacy') }}
			</p>
			<p v-else class="text-par-s text-lab-sc">
				{{ $t('editor.post_author_note') }} <a v-bind:href="$getRoute('become_author')" class="hover:underline text-brand-900">{{ $t('labels.learn_more') }}</a>
			</p>
		</div>

		<div class="hidden">
			<input v-on:change="onImageSelect" type="file" accept="image/*" ref="imageFileInput">
			<input v-on:change="onVideoSelect" type="file" accept="video/*" ref="videoFileInput">
			<input v-on:change="onAudioSelect" type="file" accept="audio/*" ref="audioFileInput">
		</div>
	</div>


	<GIFPicker v-on:select="selectGif" v-if="state.isGifPickerOpen" v-on:close="state.isGifPickerOpen = false"></GIFPicker>

	<PollEditor v-if="postHasPoll" v-on:leave="leaveEditor"></PollEditor>
</template>

<script>
	import { defineComponent, reactive, ref, defineAsyncComponent, computed, onMounted } from 'vue';
	import { useRouter } from 'vue-router';
	import { useInputHandlers } from '@/kernel/vue/composables/input/index.js';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { usePostEditorStore } from '@M/store/timeline/editor.store.js';
	import { useAuthStore } from '@M/store/auth/auth.store.js';
	import { PostTypeUtils, PostType } from '@/kernel/enums/post/post.type.js';
	import { colibriSounds } from '@/kernel/services/sounds/index.js';

	import Toolbar from '@M/components/layout/Toolbar.vue';
	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';

	export default defineComponent({
		setup: function() {
			const postEditorStore = usePostEditorStore();
			const imageFileInput = ref(null);
			const videoFileInput = ref(null);
			const audioFileInput = ref(null);
			const contentInput = ref(null);
			const authStore = useAuthStore();
			const router = useRouter();
			const validationError = ref(null);
			const { autoResize } = useInputHandlers();

			const postData = computed(() => {
                return postEditorStore.draftPost;
            });

			const userData = computed(() => {
				return authStore.userData;
			});

			const state = reactive({
				postSubmitting: false,
				uploadProgress: 0,
				isGifPickerOpen: false,
			});

			const validatePost = (message) => {
				// TODO: Add validation error to the editor store
				// Improve UX in future.

				validationError.value = message;

				debounce(() => {
					validationError.value = null;
				}, 3000);

				try {
					colibriSounds.uiFeedback();
					
					navigator.vibrate([150, 50, 150]);

				} catch (error) {
					//
				}
			}

			const getFormSubmitData = () => {
                let formData = {
                    content: postData.value.content
                };

                return formData;
            }

			onMounted(async function() {
                await postEditorStore.fetchDraftPost();
            });

			const uploadMedia = (mediaFile, type = 'image') => {
                const formData = new FormData();
                formData.append(type, mediaFile);

                colibriAPI().postEditor().with(formData).withHeaders({
                    'Content-Type': 'multipart/form-data'
                }).uploadProgress((progressEvent) => {                    
                    state.uploadProgress = Math.round((progressEvent.loaded / progressEvent.total) * 100);
                }).sendTo(`media/${type}/upload`).then((response) => {

                    postEditorStore.fetchDraftPost();

                    state.uploadProgress = 0;

                    resetFileInputTags();

                }).catch((error) => {
                    validatePost(error.response.data.message);

                    state.uploadProgress = 0;

                    resetFileInputTags();
                });
            }

			const submitForm = async () => {
                state.postSubmitting = true;
                
                await colibriAPI().postEditor().with(getFormSubmitData()).sendTo('create').then((response) => {
					postEditorStore.finishEditing();

                    autoResize(contentInput.value);

					leaveEditor();

					toastSuccess(__t('toast.post_published'));
                }).catch((error) => {
                    validatePost(error.response.data.message);
                });

                state.postSubmitting = false;
            }

			const resetFileInputTags = () => {
                imageFileInput.value.value = '';
            }

			const leaveEditor = () => {
				router.go(-1);
			}

			return {
				leaveEditor: leaveEditor,
				state: state,
				PostTypeUtils: PostTypeUtils,
				PostType: PostType,
				userData: userData,
				submitForm: submitForm,
				validationError: validationError,
				postData: postData,
				contentInput: contentInput,
				videoFileInput: videoFileInput,
				imageFileInput: imageFileInput,
				audioFileInput: audioFileInput,
				textInputHandler: function() {
					autoResize(contentInput.value);
				},
				onImageSelect: function(event) {
					uploadMedia(event.target.files[0], 'image');
				},
				onAudioSelect: function(event) {
					uploadMedia(event.target.files[0], 'audio');
				},
				onVideoSelect: function(event) {
					uploadMedia(event.target.files[0], 'video');
				},
				selectImage: function() {
					imageFileInput.value.click();
				},
				selectAudio: function() {
					audioFileInput.value.click();
				},
				selectVideo: function() {
					videoFileInput.value.click();
				},
				postHasMedia: computed(() => {
                    return postData.value.relations?.media?.length;
                }),
				postHasPoll: computed(() => {
                    return postData.value.relations?.poll;
                }),
				postMedia: computed(() => {
                    return postData.value.relations.media;
                }),
				selectGif: (gifItem) => {
					colibriAPI().postEditor().with({
						id: gifItem.id
					}).sendTo('gif/create').then((response) => {
						postEditorStore.fetchDraftPost();
					}).catch((error) => {
						validatePost(error.response.data.message);
					});

					state.isGifPickerOpen = false;
				},
				createPoll: () => {
					colibriAPI().postEditor().sendTo('poll/create').then((response) => {
						postEditorStore.fetchDraftPost();
					}).catch((error) => {
						validatePost(error.response.data.message);
					});
				},
				submitButtonStatus: computed(() => {
					return state.postSubmitting || state.uploadProgress;
				}),
				postMediaButtonStatus: (postType = null) => {
                    // Disable media button if post is being submitted
                    if (state.postSubmitting || state.uploadProgress) {
                        return true;
                    }
                    else {
                        // For text posts, media button is always enabled
                        if(PostTypeUtils.isText(postData.value.type)) {
                            return false;
                        }
                        else {
                            // For image posts, enable media button only if both
							// current and target types are images

                            if (PostTypeUtils.isImage(postData.value.type) && PostTypeUtils.isImage(postType)) {
                                return false;
                            }

                            // Otherwise disable if post type is set
                            return !!postData.value.type;
                        }
                    }
                },
				deletePostMedia: (mediaItem) => {
                    mediaItem.deleted = true;

                    colibriAPI().postEditor().with({
                        id: mediaItem.id
                    }).delete('media/delete').then((response) => {
                        postEditorStore.fetchDraftPost();
                    });
                },
				toggleGifPicker: () => {
					state.isGifPickerOpen = !state.isGifPickerOpen;
				}
			};
		},
		components: {
			Toolbar: Toolbar,
			PrimaryIconButton: PrimaryIconButton,
			PostImagePreview: defineAsyncComponent(() => {
				return import('@M/views/editors/post/parts/preview/PostImagePreview.vue');
			}),
			PostVideoPreview: defineAsyncComponent(() => {
				return import('@M/views/editors/post/parts/preview/PostVideoPreview.vue');
			}),
			PostDocumentPreview: defineAsyncComponent(() => {
				return import('@M/views/editors/post/parts/preview/PostDocumentPreview.vue');
			}),
			PostGifPreview: defineAsyncComponent(() => {
				return import('@M/views/editors/post/parts/preview/PostGifPreview.vue');
			}),
			GIFPicker: defineAsyncComponent(() => {
                return import('@M/components/gif/GIFPicker.vue');
            }),
			PollEditor: defineAsyncComponent(() => {
                return import('@M/views/editors/post/parts/poll/PollEditor.vue');
            }),
		}
	});
</script>