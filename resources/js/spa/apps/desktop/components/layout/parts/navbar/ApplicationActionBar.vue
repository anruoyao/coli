<template>
	<div class="pl-6 shrink-0">
		<div class="flex w-medium-avatar flex-col h-full justify-end items-center pb-6">
			<div v-if="! isWSEstablished">
				<ActionBarButton v-on:click="showWSConnectionError" iconName="signal-03" buttonColor="text-red-900" v-bind:hasBg="false"></ActionBarButton>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed } from 'vue';
	
	import ActionBarButton from '@D/components/layout/parts/navbar/ActionBarButton.vue';

	export default defineComponent({
		setup: function () {
			return {
				isWSEstablished: computed(() => {
                    return (window.ColibriBRConnected !== false);
                }),
				showWSConnectionError: () => {
					alert('If you see this message, it means that the WS connection is not established. Some features will not work properly. Please, try to refresh the page or contact support.');
				}
			};
		},
		components: {
			ActionBarButton: ActionBarButton
		}
	});
</script>