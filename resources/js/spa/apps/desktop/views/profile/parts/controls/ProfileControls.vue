<template>
	<div class="flex items-center gap-2 leading-none">
		<div class="w-8 shrink-0 relative">
			<div class="opacity-30 hover:opacity-100">
				<DropdownButton v-on:click.stop="toggleMainDropdown"></DropdownButton>
			</div>
			<div class="absolute top-full right-0 z-50" v-if="state.isDropdownOpen">
                <DropdownMenu v-outside-click="toggleMainDropdown" v-on:click="toggleMainDropdown">
					<template v-if="permissions.can_sanction">
						<DropdownMenuItem v-on:click="applySanctions" itemColor="text-red-900" iconName="shield-01" v-bind:textLabel="$t('dd.user.apply_sanctions')"></DropdownMenuItem>
						<Border/>
					</template>
					<template v-if="permissions.can_message">
						<DropdownMenuItem v-on:click="sendMessage" iconName="message-chat-circle" v-bind:loading="state.sendingMessage" v-bind:textLabel="$t('dd.user.send_message')"></DropdownMenuItem>
					</template>
					<template v-if="permissions.can_mention">
						<DropdownMenuItem v-on:click="mentionUser" iconName="at-sign" v-bind:textLabel="$t('dd.user.mention', { username: profileData.username})"></DropdownMenuItem>
					</template>
					<Border/>
					<DropdownMenuItem v-on:click="copyProfileLink" iconName="link-01" iconType="solid" v-bind:textLabel="$t('dd.user.copy_link')"></DropdownMenuItem>
					<RouterLink v-bind:to="{ name: 'profile_info', params: { id: profileData.username } }">
						<DropdownMenuItem iconName="info-circle" v-bind:textLabel="$t('dd.user.about')"></DropdownMenuItem>
					</RouterLink>
					
					<template v-if="permissions.can_block">
						<Border/>
						<DropdownMenuItem v-bind:disabled="true" v-on:click="$comingSoon" itemColor="text-red-900" iconName="slash-circle-01" v-bind:textLabel="$t('dd.user.block', { username: profileData.username })"></DropdownMenuItem>
					</template>
					<template v-if="permissions.can_report">
						<DropdownMenuItem v-on:click="reportProfile" itemColor="text-red-900" iconName="annotation-alert" v-bind:textLabel="$t('dd.user.report', { username: profileData.username })"></DropdownMenuItem>
					</template>
					<template v-if="permissions.can_mute">
						<DropdownMenuItem v-on:click="$comingSoon" itemColor="text-red-900" iconName="volume-x" v-bind:textLabel="$t('dd.user.mute', { username: profileData.username })"></DropdownMenuItem>
					</template>
				</DropdownMenu>
			</div>
		</div>
		<template v-if="permissions.can_follow">
			<FollowPillButton v-bind:relationship="profileData.meta.relationship.follow" v-bind:followableId="profileData.id"></FollowPillButton>
		</template>
		<template v-else>
			<PrimaryPillButton v-on:click="state.isModalOpen = true" v-bind:buttonText="$t('labels.edit_profile')" buttonSize="md"></PrimaryPillButton>
		</template>
	</div>
	<template v-if="state.isModalOpen">
		<ProfileEditModal v-on:close="state.isModalOpen = false"></ProfileEditModal>
	</template>
</template>

<script>
	import { defineComponent, reactive, inject } from 'vue';
	import { useRouter } from 'vue-router';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

	import FollowPillButton from '@D/components/inter-ui/buttons/follows/FollowPillButton.vue';
	import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';
	import DropdownButton from '@D/components/general/dropdowns/parts/DropdownButton.vue';
    import DropdownMenu from '@D/components/general/dropdowns/parts/DropdownMenu.vue';
    import DropdownMenuItem from '@D/components/general/dropdowns/parts/DropdownMenuItem.vue';

	import ProfileEditModal from '@D/views/profile/parts/modals/ProfileEditModal.vue';

	export default defineComponent({
		setup: function() {
			const router = useRouter();
			const profileData = inject('profileData');
			const state = reactive({
                isDropdownOpen: false,
				sendingMessage: false,
				isModalOpen: false
            });

			return {
				state: state,
				profileData: profileData,
				toggleMainDropdown: () => {
                    state.isDropdownOpen = !state.isDropdownOpen;
                },
				permissions: profileData.value.meta.permissions,
				sendMessage: async () => {
					state.sendingMessage = true;

					await colibriAPI().messenger().with({
						user_id: profileData.value.id
					}).sendTo('chats/create').then((response) => {
						let chatData = response.data.data;

						router.push({
							name: 'messenger_chat',
							params: {
								chat_id: chatData.chat_id
							}
						});
					}).catch((error) => {
						if(error.response) {
							alert(error.response.data.message);
						}
					});
				},
				applySanctions: async () => {
					// TODO
					// Remove this API call and Remove Controller.
					
					// Redirect admin to admin panel, to the page,
					// where they can apply needed sanctions on user
					// on centralized and functional scalable page.

					const promptVal = confirm('Are you sure you want to delete this user?');

					if(promptVal) {
						await colibriAPI().admin().with({
							user_id: profileData.value.id
						}).delete('profile/delete').then((response) => {
							router.push({
								name: 'home_index'
							});
						}).catch((error) => {
							if(error.response) {
								alert(error.response.data.message);
							}
						});
					}
				},
				mentionUser: () => {
					colibriEventBus.emit('post-editor:open', {
						mentionName: profileData.value.username
					});
				},
				copyProfileLink: () => {
					navigator.clipboard.writeText(profileData.value.profile_url).then(() => {
                        toastSuccess(__t('toast.profile_link_copied'), 1000);
                    });
				},
				reportProfile: () => {
                    colibriEventBus.emit('report:open', {
                        type: 'user',
                        reportableId: profileData.value.id
                    });
                },
			}
		},
		components: {
			FollowPillButton: FollowPillButton,
			PrimaryPillButton: PrimaryPillButton,
			DropdownMenu: DropdownMenu,
			DropdownButton: DropdownButton,
			DropdownMenuItem: DropdownMenuItem,
			ProfileEditModal: ProfileEditModal
		}
	});
</script>