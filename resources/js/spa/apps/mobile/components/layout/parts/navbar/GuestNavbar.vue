<template>
	<div class="fixed bottom-0 left-0 right-0 z-50 bg-bg-pr" v-bind:class="{ 'pb-5': $isStandalone() }">
		<div class="grid grid-cols-4 h-14 mb-safe-bottom">
			<div class="flex items-center justify-center">
				<RouterLink v-bind:to="{ name: 'home_index' }">
					<PrimaryIconButton buttonColor="text-lab-pr" iconName="home-smile" iconType="line"></PrimaryIconButton>
				</RouterLink>
			</div>
			<div class="flex items-center justify-center">
				<RouterLink v-bind:to="{ name: 'explore_index' }">
					<PrimaryIconButton buttonColor="text-lab-pr" iconName="search-lg" iconType="solid"></PrimaryIconButton>
				</RouterLink>
			</div>
			<div class="flex items-center justify-center">
				<a v-bind:href="$getRoute('user_auth_index')">
					<PrimaryIconButton buttonColor="text-lab-pr" iconName="log-in-01" iconType="line"></PrimaryIconButton>
				</a>
			</div>
			<div class="flex items-center justify-center">
				<a v-bind:href="$getRoute('user_auth_signup')">
					<PrimaryIconButton buttonColor="text-lab-pr" iconName="user-plus-01" iconType="line"></PrimaryIconButton>
				</a>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';

	export default defineComponent({
		setup: function() {
			return {};
		},
		components: {
			PrimaryIconButton: PrimaryIconButton
		}
	});
</script>
