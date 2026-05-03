<template>
	<div class="bg-black border border-bord-card flex justify-center rounded-2xl max-h-[620px] overflow-hidden relative">
		<template v-if="mediaItem.deleted">
			<MediaBlurOverlay></MediaBlurOverlay>
		</template>

		<template v-else>
			<div class="absolute top-4 right-4 inline-block">
				<MediaDeleteButton v-on:click="$emit('delete', mediaItem)"></MediaDeleteButton>
			</div>
		</template>

		<div class="w-full">
			<img v-bind:src="mediaItem.thumbnail_url" class="w-full h-full object-cover" alt="Image">
		</div>

		<div class="from-black/60 to-transparent bg-gradient-to-t absolute bottom-0 left-0 right-0 px-4 pb-4 pt-6">
			<VideoDurationTime v-bind:videoDuration="mediaItem.metadata.duration"></VideoDurationTime>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	import MediaBlurOverlay from '@D/components/timeline/editor/animations/MediaBlurOverlay.vue';
	import MediaDeleteButton from '@D/components/timeline/editor/buttons/MediaDeleteButton.vue';
	import VideoDurationTime from '@/kernel/vue/components/media/video/VideoDurationTime.vue';

	export default defineComponent({
		props: {
			mediaItem: {
				type: Object,
				required: true
			}
		},
		emits: ['delete'],
		setup: function(props, context) {
			return {
				
			};
		},
		components: {
			MediaBlurOverlay: MediaBlurOverlay,
			MediaDeleteButton: MediaDeleteButton,
			VideoDurationTime: VideoDurationTime,
		}
	});
</script>