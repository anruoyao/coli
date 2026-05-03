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
	import { defineComponent, ref, onMounted, onBeforeUnmount } from 'vue';
	
	import ActionBarButton from '@D/components/layout/parts/navbar/ActionBarButton.vue';

	export default defineComponent({
		setup: function () {
			const isWSEstablished = ref(window.ColibriBRConnected === true);

			const onConnected = () => { isWSEstablished.value = true; };
			const onDisconnected = () => { isWSEstablished.value = false; };

			onMounted(() => {
				window.addEventListener('ws:connected', onConnected);
				window.addEventListener('ws:disconnected', onDisconnected);
			});

			onBeforeUnmount(() => {
				window.removeEventListener('ws:connected', onConnected);
				window.removeEventListener('ws:disconnected', onDisconnected);
			});

			return {
				isWSEstablished: isWSEstablished,
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