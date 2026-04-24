<template>
	<div class="block">
		<div class="flex overflow-hidden">
			<div class="size-small-avatar shrink-0">
				<div v-bind:style="{ backgroundColor: messageColor }" class="transform -scale-x-100 size-small-avatar inline-flex-center overflow-hidden rounded-full">
					<SvgIcon name="share-06" type="line" classes="size-icon-small text-white"></SvgIcon>
				</div>
			</div>
			<div class="flex-1 overflow-hidden ml-2">
				<div class="overflow-hidden">
					<h3 class="text-par-s font-semibold leading-none" v-bind:style="{ color: messageColor }">
						{{ messageData.relations.user.name }}

						<span class="text-par-s text-lab-sc leading-none ml-1 font-normal">
							{{ messageData.date.time_ago }}
						</span>
					</h3>
					<p class="text-par-m truncate text-lab-pr2 max-w-content" v-html="messageData.content"></p>
				</div>
			</div>
			<div class="size-small-avatar shrink-0">
				<PrimaryIconButton v-on:click="cancelMessageReply" name="x-close"></PrimaryIconButton>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, ref, computed } from 'vue';
	import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';

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