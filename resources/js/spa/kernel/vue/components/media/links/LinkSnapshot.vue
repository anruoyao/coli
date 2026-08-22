<template>
	<div class="group smoothing">
		<div v-if="linkSnapshot.metadata.preview_image_base64" class="max-h-96 overflow-hidden">
			<img v-bind:src="linkSnapshot.metadata.preview_image_base64" class="w-full object-cover">
		</div>

		<div class="p-3 md:p-4 md:pr-12 leading-snug">
			<h4 class="text-lab-pr2 text-par-m font-semibold mb-1 line-clamp-2">
				{{ linkSnapshot.title }}
			</h4>

			<p v-if="linkSnapshot.description" class="text-lab-sc text-par-s line-clamp-2 mb-2.5">
				{{ linkSnapshot.description }}
			</p>

			<a v-bind:href="linkSnapshot.url" target="_blank" class="text-lab-sc text-par-s block truncate group-hover:underline group-hover:text-brand-900">
				{{ linkSnapshot.url }}
			</a>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			linkSnapshot: {
				type: Object,
				required: true
			}
		}
	});
</script>