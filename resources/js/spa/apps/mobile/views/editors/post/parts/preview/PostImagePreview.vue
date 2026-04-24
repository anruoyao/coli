<template>
	<div class="w-full overflow-x-auto hidden-scroll flex gap-px items-center">
		<div v-for="mediaItem in postMedia"
			v-bind:class="[width, (mediaItem.deleted ? 'opacity-20' : '')]"
		class="shrink-0 relative aspect-square bg-fill-tr overflow-hidden">
			<img v-bind:src="mediaItem.source_url" class="size-full object-cover">

			<div v-if="! mediaItem.deleted" class="absolute top-3 right-3 inline-block z-10">
				<MediaDeleteButton v-on:click="$emit('delete', mediaItem)"></MediaDeleteButton>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed } from 'vue';
	import MediaDeleteButton from '@M/views/editors/post/parts/buttons/MediaDeleteButton.vue';

	export default defineComponent({
		emits: ['delete'],
		props: {
			postMedia: {
				type: Array,
				required: true
			}
		},
		setup: function(props) {
			return {
				width: computed(() => {
					if(props.postMedia.length === 1) {
						return 'w-full';
					}
					else if(props.postMedia.length === 2) {
						return 'w-1/2';
					}
					else {
						return 'w-40';
					}
				})
			}
		},
		components: {
			MediaDeleteButton: MediaDeleteButton
		}
	})
</script>