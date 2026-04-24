<template>
	<div class="flex overflow-hidden">
		<div class="flex-1 flex relative items-stretch rounded-tl-md rounded-bl-md rounded-tr-lg rounded-br-lg overflow-hidden">
			<div class="absolute inset-0 opacity-15" v-bind:style="{ backgroundColor: messageColor }"></div>
			<div class="w-1 shrink-0 relative z-10" v-bind:style="{ backgroundColor: messageColor }"></div>
			<div class="cursor-pointer pl-2 pb-1 pt-2 pr-3 mb-1 relative z-10">
				<h3 class="text-cap-l font-semibold leading-none mb-0.5" v-bind:style="{ color: messageColor }">
					{{ replyData.user.name }}
				</h3>
				<p class="text-par-s text-lab-pr2 break-words" v-html="replyData.content"></p>
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