<template>
    <div class="flex flex-col">
        <div class="px-4 pb-4 border-b border-b-bord-pr text-center">
            <SheetTitle v-bind:title="$t('labels.edit_profile')"></SheetTitle>
        </div>

        <div class="mb-4">
            <div v-if="state.uploadProgress" class="h-1 bg-fill-qt overflow-hidden">
                <div class="h-full bg-green-900" v-bind:style="{ width: state.uploadProgress + '%' }"></div>
            </div>
            <div class="bg-black overflow-hidden relative min-h-26">
                <img class="w-full" v-bind:src="profileData.cover_url" alt="Cover">
                <button v-on:click="$refs.coverInput.click()" class="cursor-pointer absolute top-2 right-2 bg-black/50 hover:bg-black/30 smoothing size-10 inline-flex-center rounded-full">
                    <SvgIcon name="pencil-02" type="line" classes="size-icon-small text-white/90"></SvgIcon>
                </button>
            </div>

            <div class="px-4 -mt-[35px] z-20 relative">
                <div class="relative rounded-full overflow-hidden size-large-avatar border-4 border-fill-pr bg-bg-pr">
                    <img class="w-full z-20 relative" v-bind:src="profileData.avatar_url" alt="Image">

                    <div class="absolute cursor-pointer inset-0 z-20 flex items-center justify-center">
                        <button v-on:click="$refs.avatarInput.click()" class="bg-black/50 hover:bg-black/30 smoothing size-10 inline-flex-center rounded-full">
                            <SvgIcon name="camera-01" type="line" classes="size-icon-normal text-white/90"></SvgIcon>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="px-4 pb-8 text-par-s text-lab-sc flex-1">
            <p class="mb-2" v-html="$t('settings.forms.avatar_settings.desc')"></p>
            <p class="mb-2">
                {{ $t('settings.forms.avatar_settings.formats') }}
            </p>
            <Border height="h-px" bg="bg-bord-pr"></Border>
            <p class="mt-2">
                {{ $t('settings.forms.avatar_settings.cover_resolution') }}
            </p>
        </div>

        <ActionSheetGroup>
            <RouterLink v-bind:to="{ name: 'settings_navigator' }">
                <ActionSheetItem iconName="settings-04" v-bind:textLabel="$t('labels.account_settings')"></ActionSheetItem>
            </RouterLink>
            <Border></Border>
            <ActionSheetItem
                v-on:click="$emit('close')"
                iconName="x"
                iconType="solid"
            v-bind:textLabel="$t('labels.close')"></ActionSheetItem>
        </ActionSheetGroup>

        <div class="hidden">
            <input v-on:change="uploadAvatar" type="file" ref="avatarInput" accept="image/*">
            <input v-on:change="uploadCover" type="file" ref="coverInput" accept="image/*">
        </div>
    </div>
</template>

<script>
    import { defineComponent, inject, reactive } from 'vue';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
    import { useAuthStore } from '@M/store/auth/auth.store.js';

    import SheetTitle from '@M/components/general/sheets/SheetTitle.vue';
    import ActionSheetItem from '@M/components/general/sheets/ActionSheetItem.vue';
    import ActionSheetGroup from '@M/components/general/sheets/ActionSheetGroup.vue';

    export default defineComponent({
        emits: ['close'],
        setup: function() {
            const authStore = useAuthStore();
            const profileData = inject('profileData');
            const state = reactive({
				uploadProgress: 0
			});

            return {
                profileData: profileData,
                state: state,
                uploadAvatar: async(event) => {
					event.preventDefault();

					const formData = new FormData();
					formData.append('avatar', event.target.files[0]);

					await colibriAPI().userSettings().with(formData).uploadProgress((progressEvent) => {
						state.uploadProgress = Math.round((progressEvent.loaded / progressEvent.total) * 100);
					}).sendTo('account/avatar/update').then((response) => {
						authStore.setProperty('avatar_url', response.data.data.avatar_url);
                        profileData.value.avatar_url = response.data.data.avatar_url;
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
                        profileData.value.cover_url = response.data.data.cover_url;
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
            SheetTitle: SheetTitle,
            ActionSheetItem: ActionSheetItem,
            ActionSheetGroup: ActionSheetGroup
        }
    });
</script>
