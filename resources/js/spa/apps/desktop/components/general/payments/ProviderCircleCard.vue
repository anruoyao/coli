<template>
	<div class="cursor-pointer">
		<div class="flex flex-col items-center">
			<div class="size-full aspect-square rounded-full overflow-hidden bg-fill-qt mb-1 ring-2"
			v-bind:class="[isActive ? 'ring-brand-900' : 'ring-fill-pr']">
				<img class="size-full" v-bind:src="logoUrl" alt="Img">
			</div>
			<span class="block text-center text-par-s text-lab-pr2 truncate max-w-full">
				{{ providerName }}
			</span>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			logoUrl: {
				type: String,
				required: true
			},
			providerName: {
				type: String,
				required: true
			},
			isActive: {
				type: Boolean,
				default: false
			}
		}
	});
</script>