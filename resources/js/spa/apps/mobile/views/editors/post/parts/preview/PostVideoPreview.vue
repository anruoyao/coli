<template>
	<div v-for="mediaItem in postMedia" v-bind:class="[(mediaItem.deleted ? 'opacity-20' : '')]" class="bg-black aspect-video flex justify-center overflow-hidden relative">
		<div class="absolute top-3 right-3 inline-block">
			<MediaDeleteButton v-on:click="$emit('delete', mediaItem)"></MediaDeleteButton>
		</div>
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

	import MediaDeleteButton from '@M/views/editors/post/parts/buttons/MediaDeleteButton.vue';
	import VideoDurationTime from '@/kernel/vue/components/media/video/VideoDurationTime.vue';

	export default defineComponent({
		props: {
			postMedia: {
				type: Object,
				required: true
			}
		},
		emits: ['delete'],
		components: {
			MediaDeleteButton: MediaDeleteButton,
			VideoDurationTime: VideoDurationTime,
		}
	});
</script>