<template>
	<div class="flex">
		<div class="shrink-0 overflow-hidden">
			<div class="flex relative items-stretch overflow-hidden">
				<div class="w-[3px] shrink-0 relative z-10 rounded-xs" v-bind:style="{ backgroundColor: messageColor }"></div>
				<div class="cursor-pointer pl-2 py-0.5 relative z-10 overflow-hidden">
					<h3 class="text-cap-l font-semibold leading-none mb-0.5" v-bind:style="{ color: messageColor }">
						{{ replyData.user.name }}
					</h3>
					<p class="text-par-m text-lab-sc break-words" v-html="replyData.content"></p>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed } from 'vue';

	export default defineComponent({
		props: {
			replyData: {
				type: Object,
				required: true
			}
		},
		setup: function(props) {
			return {
				messageColor: computed(() => {
					return props.replyData.participant.color;
				})
			}
		}
	});
</script>