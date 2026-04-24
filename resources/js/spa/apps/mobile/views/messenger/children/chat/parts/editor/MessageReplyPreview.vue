<template>
	<div class="px-4 pt-3 border-t border-t-bord-tr">
		<div class="flex overflow-hidden items-center gap-2.5">
			<div class="size-2x-small-avatar shrink-0">
				<div v-bind:style="{ backgroundColor: messageColor }" class="size-2x-small-avatar inline-flex-center overflow-hidden rounded-full">
					<SvgIcon name="share-06" type="line" classes="size-icon-x-small text-white"></SvgIcon>
				</div>
			</div>
			<div class="flex-1 overflow-hidden">
				<h5 class="text-par-s font-medium leading-none">
					{{ messageData.relations.user.name }} <span class="text-par-xs text-lab-sc leading-none ml-1 font-normal">{{ messageData.date.time_ago }}</span>
				</h5>
				<p class="text-par-n truncate text-lab-pr3 mt-0.5" v-html="messageData.content"></p>
			</div>
			<div class="shrink-0">
				<PrimaryIconButton v-on:click="cancelMessageReply"></PrimaryIconButton>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, ref, computed } from 'vue';

	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';

	export default defineComponent({
		props: {
			messageData: {
				type: Object,
				required: true
			}
		},
		emits: ['cancel'],
		setup: function(props, context) {
			const messageData = ref(props.messageData);

			return {
				messageData: messageData,
				cancelMessageReply: () => {
					context.emit('cancel');
				},
				messageColor: computed(() => {
					return messageData.value.relations.participant.color;
				})
			};
		},
		components: {
			PrimaryIconButton: PrimaryIconButton
		}
	});
</script>