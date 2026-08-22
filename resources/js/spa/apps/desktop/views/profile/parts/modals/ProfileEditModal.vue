<template>
	<Teleport to="body">
		<ContentModal v-on:close="$emit('close')">
			<ModalHeader v-bind:modalTitle="$t('labels.edit_profile')"></ModalHeader>
			<div class="overflow-hidden rounded-b-md">
				<div class="block mb-4">
					<div class="bg-black overflow-hidden relative min-h-[180px]">
						<img class="w-full" v-bind:src="userData.cover_url" alt="Cover">
						<button v-on:click="$refs.coverInput.click()" class="cursor-pointer absolute top-2 right-2 bg-black/50 hover:bg-black/30 smoothing size-10 inline-flex-center rounded-full">
							<SvgIcon name="pencil-02" type="line" classes="size-icon-small text-white/90"></SvgIcon>
						</button>
					</div>

					<div class="px-4 -mt-[56px] z-20 relative">
						<div class="relative rounded-full overflow-hidden size-large-avatar border-4 border-fill-pr bg-bg-pr">
							<img class="w-full z-20 relative" v-bind:src="userData.avatar_url" alt="Image">

							<div class="absolute cursor-pointer inset-0 z-20 flex items-center justify-center">
								<button v-on:click="$refs.avatarInput.click()" class="bg-black/50 hover:bg-black/30 smoothing size-10 inline-flex-center rounded-full">
									<SvgIcon name="camera-01" type="line" classes="size-icon-normal text-white/90"></SvgIcon>
								</button>
							</div>
						</div>
					</div>
				</div>
				<div class="px-4 pb-8 text-par-s text-lab-sc">
					<p class="mb-2" v-html="$t('settings.forms.avatar_settings.desc')"></p>
					<p class="mb-2">
						{{ $t('settings.forms.avatar_settings.formats') }}
					</p>
					<Border height="h-px" bg="bg-bord-pr"></Border>
					<p class="mt-2">
						{{ $t('settings.forms.avatar_settings.cover_resolution') }}
					</p>
				</div>
				
				<div v-if="state.uploadProgress" class="h-3 bg-fill-qt overflow-hidden">
					<div class="h-full bg-green-900" v-bind:style="{ width: state.uploadProgress + '%' }"></div>
				</div>
				<Border v-else height="h-3" bg="bg-fill-qt" opacity="opacity-70"></Border>
				<RouterLink v-bind:to="{ name: 'settings_account' }">
					<ModalRowButton 
						v-bind:hasArrow="true"
						v-bind:buttonText="$t('labels.account_settings')"
					iconName="settings-01"></ModalRowButton>
				</RouterLink>
			</div>

			<template v-slot:loadingSkeleton>
				<div class="flex justify-center py-20">
					<div class="colibri-primary-animation"></div>
				</div>
			</template>

			<div class="hidden">
				<input v-on:change="uploadAvatar" type="file" ref="avatarInput" accept="image/*">
				<input v-on:change="uploadCover" type="file" ref="coverInput" accept="image/*">
			</div>
		</ContentModal>
	</Teleport>
</template>

<script>
	import { defineComponent, reactive, computed } from 'vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { useAuthStore } from '@D/store/auth/auth.store.js';

	import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';
	import ModalRowButton from '@D/components/inter-ui/buttons/ModalRowButton.vue';
	import ContentModal from '@D/components/general/modals/ContentModal.vue';
	import ModalHeader from '@D/components/general/modals/parts/ModalHeader.vue'; 

	export default defineComponent({
		emits: ['close'],
		setup: function() {
			const state = reactive({
				isLoading: true,
				uploadProgress: 0
			});

			const authStore = useAuthStore();

			return {
				state: state,
				userData: computed(() => {
					return authStore.userData;
				}),
				uploadAvatar: async(event) => {
					event.preventDefault();
					
					const formData = new FormData();
					formData.append('avatar', event.target.files[0]);

					await colibriAPI().userSettings().with(formData).uploadProgress((progressEvent) => {                    
						state.uploadProgress = Math.round((progressEvent.loaded / progressEvent.total) * 100);
					}).sendTo('account/avatar/update').then((response) => {
						authStore.setProperty('avatar_url', response.data.data.avatar_url);
					}).catch((error) => {
						if(error.response) {
							alert(error.response.data.message);
						}
					});

					state.uploadProgress = 0;
				},
				uploadCover: async(event) => {
					event.preventDefault();
					
					const formData = new FormData();
					formData.append('cover', event.target.files[0]);

					await colibriAPI().userSettings().with(formData).uploadProgress((progressEvent) => {                    
						state.uploadProgress = Math.round((progressEvent.loaded / progressEvent.total) * 100);
					}).sendTo('account/cover/update').then((response) => {
						authStore.setProperty('cover_url', response.data.data.cover_url);
					}).catch((error) => {
						if(error.response) {
							alert(error.response.data.message);
						}
					});

					state.uploadProgress = 0;
				}
			};
		},
		components: {
			ContentModal: ContentModal,
			ModalHeader: ModalHeader,
			ModalRowButton: ModalRowButton,
			PrimaryIconButton: PrimaryIconButton
		}
	});
</script>