<template>
	<div class="flex items-center leading-none gap-2">
		<span v-on:click="goBack" class="flex items-center -translate-x-1.5 cursor-pointer">
			<PrimaryIconButton iconName="chevron-left" buttonColor="text-lab-pr2"></PrimaryIconButton>
			<span class="text-lab-pr2 font-medium text-par-l">
				{{ profileData.username	 }}
			</span>
		</span>
		<div class="shrink-0 ml-auto">
			<NotificationsButton></NotificationsButton>
		</div>
		<div class="shrink-0">
			<PrimaryIconButton v-on:click.stop="state.mainMenu.open" iconName="circle-dots" iconType="line" buttonColor="text-lab-pr2"></PrimaryIconButton>
		</div>
	</div>

	<ActionSheet v-if="state.mainMenu.status" v-on:close="state.mainMenu.close" v-bind:isMuted="true">
		<div v-on:click.stop="state.mainMenu.close">
			<div class="mb-4">
				<ActionSheetGroup>
					<template v-if="permissions.can_mention">
						<ActionSheetItem v-on:click="mentionUser" iconName="at-sign" v-bind:textLabel="$t('dd.user.mention', { username: profileData.username})"></ActionSheetItem>
					</template>
					<ActionSheetItem v-on:click="copyProfileLink" iconName="link-01" iconType="solid" v-bind:textLabel="$t('dd.user.copy_link')"></ActionSheetItem>
				</ActionSheetGroup>
			</div>
			<ActionSheetGroup>
				<template v-if="permissions.can_report">
					<ActionSheetItem v-on:click="reportProfile" itemColor="text-red-900" iconName="annotation-alert" v-bind:textLabel="$t('dd.user.report', { username: profileData.username })"></ActionSheetItem>
				</template>
				<template v-if="permissions.can_block">
					<ActionSheetItem v-bind:disabled="true" v-on:click="$comingSoon" itemColor="text-red-900" iconName="slash-circle-01" v-bind:textLabel="$t('dd.user.block', { username: profileData.username })"></ActionSheetItem>
				</template>
				<template v-if="permissions.can_mute">
					<ActionSheetItem v-bind:disabled="true" v-on:click="$comingSoon" itemColor="text-red-900" iconName="volume-x" v-bind:textLabel="$t('dd.user.mute', { username: profileData.username })"></ActionSheetItem>
				</template>
				
				<RouterLink v-bind:to="{ name: 'profile_info', params: { id: profileData.username } }">
					<ActionSheetItem v-bind:notLast="true" iconName="info-circle" v-bind:textLabel="$t('dd.user.about')"></ActionSheetItem>
				</RouterLink>
				<template v-if="! permissions.can_follow">
					<ActionSheetItem v-on:click="state.editProfileMenu.open" iconName="pencil-02" v-bind:textLabel="$t('labels.edit_profile')"></ActionSheetItem>

					<RouterLink v-bind:to="{ name: 'settings_navigator' }">
						<ActionSheetItem iconName="settings-04" v-bind:textLabel="$t('labels.account_settings')"></ActionSheetItem>
					</RouterLink>
				</template>
			</ActionSheetGroup>
		</div>
	</ActionSheet>

	<ActionSheet v-if="state.editProfileMenu.status" v-on:close="state.editProfileMenu.close">
		<SheetInfo v-on:close="state.editProfileMenu.close" v-bind:sheetTitle="$t('info.only_desktop.title')" v-bind:sheetDesc="$t('info.only_desktop.profile_edit')"></SheetInfo>
	</ActionSheet>
</template>

<script>
	import { defineComponent, reactive, inject } from 'vue';
	import { useRouter } from 'vue-router';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { useMenu } from '@/kernel/vue/composables/menu/index.js';

	import DropdownButton from '@M/components/general/dropdowns/DropdownButton.vue';
	import ActionSheet from '@M/components/general/sheets/ActionSheet.vue';
	import ActionSheetItem from '@M/components/general/sheets/ActionSheetItem.vue';
	import ActionSheetGroup from '@M/components/general/sheets/ActionSheetGroup.vue';
	import NotificationsButton from '@M/components/layout/parts/NotificationsButton.vue';
	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';
	import SheetInfo from '@M/components/general/sheets/SheetInfo.vue';

	export default defineComponent({
		setup: function() {
			const state = reactive({
				mainMenu: useMenu(),
				editProfileMenu: useMenu()
			});

			const router = useRouter();

			const profileData = inject('profileData');

			return {
				permissions: profileData.value.meta.permissions,
				state: state,
				profileData: profileData,
				goBack: function() {
					router.go(-1);
				},
				mentionUser: () => {
					colibriEventBus.emit('post-editor:open', {
                        mentionName: profileData.value.username
                    });
				},
				copyProfileLink: () => {
					try {
						navigator.clipboard.writeText(profileData.value.profile_url).then(() => {
							toastSuccess(__t('toast.profile_link_copied'), 1000);
						});
					} catch (error) {
						toastError(error, 1000);
					}
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
			DropdownButton: DropdownButton,	
			ActionSheet: ActionSheet,
			ActionSheetItem: ActionSheetItem,
			NotificationsButton: NotificationsButton,
			ActionSheetGroup: ActionSheetGroup,
			PrimaryIconButton: PrimaryIconButton,
			SheetInfo: SheetInfo
		}
	});
</script>