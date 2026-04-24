<template>
	<ActionSheet v-on:close="$emit('close')" v-bind:isMuted="true">
		<div v-on:click.stop="$emit('close')">
			<div class="mb-4">
				<ActionSheetGroup>
					<RouterLink v-bind:to="{ name: 'bookmarks_index' }">
						<ActionSheetItem v-bind:notLast="true" iconName="bookmark" v-bind:textLabel="$t('labels.bookmarks')"></ActionSheetItem>
					</RouterLink>
					<RouterLink v-bind:to="{ name: 'wallet_index' }">
						<ActionSheetItem iconName="wallet-02" v-bind:textLabel="$t('labels.wallet')"></ActionSheetItem>
					</RouterLink>
				</ActionSheetGroup>
			</div>

			<ActionSheetGroup>
				<RouterLink v-bind:to="{ name: 'settings_index' }">
					<ActionSheetItem v-bind:notLast="true" iconName="settings-01" v-bind:textLabel="$t('labels.account_settings')"></ActionSheetItem>
				</RouterLink>
				<ActionSheetItem v-on:click="logoutUser" iconName="log-out-01" itemColor="text-red-900" iconType="solid" v-bind:textLabel="$t('labels.logout')"></ActionSheetItem>
			</ActionSheetGroup>

			<div class="px-4 mt-4 text-center">
				<span class="text-par-s text-lab-tr">
					{{ $config('app.name') }} &copy; {{ currentYear }} - <a href="https://www.terla.me" target="_blank" class="underline">Created by Mansur Terla</a>
				</span>
			</div>
		</div>
	</ActionSheet>
</template>

<script>
	import { defineComponent } from 'vue';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';

	import ActionSheet from '@M/components/general/sheets/ActionSheet.vue';
	import ActionSheetGroup from '@M/components/general/sheets/ActionSheetGroup.vue';
	import ActionSheetItem from '@M/components/general/sheets/ActionSheetItem.vue';

	export default defineComponent({
		emits: ['close'],
		setup: function() {
			return {
				currentYear: new Date().getFullYear(),
				logoutUser: () => {
					colibriEventBus.emit('auth:logout');
				}
			};
		},
		components: {
			ActionSheet: ActionSheet,
			ActionSheetGroup: ActionSheetGroup,
			ActionSheetItem: ActionSheetItem
		}
	});
</script>