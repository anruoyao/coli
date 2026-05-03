<template>
	<div class="relative flex justify-center cursor-pointer">
		<video v-on:click="togglePlay" class="w-full" ref="videoPlayerRef" v-bind:poster="thumbnailUrl" loop="loop">
			<source v-bind:src="videoUrl" type="video/mp4">
		</video>
		<div v-if="! state.isLoaded" class="absolute backdrop-blur-xs inset-0 flex-center w-full h-full bg-black/50">
			<div class="size-12">
				<SpinnerIcon strokeColor="stroke-white" strokeWidth="1"></SpinnerIcon>
			</div>
		</div>
		
		<div class="from-black/60 to-transparent bg-gradient-to-t absolute bottom-0 left-0 right-0 px-2 pb-2 pt-6">
			<div class="flex items-center justify-between">
				<div class="ml-2 inline-flex items-center gap-2">
					<VideoDurationTime v-if="state.isPlaying" v-bind:videoDuration="$filters.secondsToDuration(state.playbackTime)"></VideoDurationTime>
					<VideoDurationTime v-else v-bind:videoDuration="duration"></VideoDurationTime>
				</div>
				
				<PrimaryIconButton
					v-on:click="toggleMute"
					v-bind:iconName="state.isMuted ? 'volume-x' : 'volume-max'" 
					buttonColor="text-white"
					iconSize="icon-small"
				hoverText="hover:text-white/80"></PrimaryIconButton>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, watch, reactive, onMounted, onUnmounted } from 'vue';
	import { useIntersectionObserver } from '@/kernel/vue/composables/inter-obs/index.js';
	
	import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';
	import VideoDurationTime from '@/kernel/vue/components/media/video/VideoDurationTime.vue';
	import SpinnerIcon from '@/kernel/vue/components/icons/SpinnerIcon.vue';

	export default defineComponent({
		props: {
			videoUrl: {
				type: String,
				required: true
			},
			duration: {
				type: Object,
				required: true
			},
			thumbnailUrl: {
				type: String,
				required: true
			}
		},
		setup: function(props, context) {
			const { isIntersecting, videoPlayerRef } = useIntersectionObserver({
				threshold: 0.5
			});
			
			const state = reactive({
				isMuted: false,
				isLoaded: false,
				isPlaying: false,
				progressBar: 0,
				playbackTime: 0
			});

			function startProgressUpdater() {
                function updateProgress() {
                    const currentTime = videoPlayerRef.value.currentTime;
                    const duration = videoPlayerRef.value.duration;
                    
                    state.progressBar = Math.round((currentTime / duration) * 100);
                    state.playbackTime = Math.round(currentTime);
                    
                    if (state.isPlaying) {
                        window.colibriVideoTimer = requestAnimationFrame(updateProgress);
                    }
                }

                window.colibriVideoTimer = requestAnimationFrame(updateProgress);
            }

            function stopProgressUpdater() {
                cancelAnimationFrame(window.colibriVideoTimer);
            }

			watch(isIntersecting, (newVal) => {
				if(newVal && state.isLoaded) {
					playVideo();
				}
				else {
					pauseVideo();
				}
			});

			onMounted(() => {
				videoPlayerRef.value.addEventListener('loadedmetadata', () => {
					state.isLoaded = true;
				});
			});

			onUnmounted(() => {
				if(videoPlayerRef.value) {
					videoPlayerRef.value.removeEventListener('loadedmetadata', playVideo);
					pauseVideo();
				}
			});

			const setMuted = () => {
				if(localStorage.getItem('videoPlayerMuted')) {
					state.isMuted = true;
					videoPlayerRef.value.muted = true;
				}
				else {
					videoPlayerRef.value.muted = false;
					state.isMuted = false;
				}
			}

			const playVideo = () => {
				if(state.isLoaded) {
					videoPlayerRef.value.play().then(() => {
						setMuted();
						state.isPlaying = true;
						startProgressUpdater();
					}).catch((error) => {
						if(error.name === 'NotAllowedError') {
							console.info('Cannot play video because user has not interacted with the page yet');
						}
					});
				}
			};

			const pauseVideo = () => {
				videoPlayerRef.value.pause();
				state.isPlaying = false;
				stopProgressUpdater();
			};

			const toggleMute = () => {
				state.isMuted = !state.isMuted;
				videoPlayerRef.value.muted = state.isMuted;

				if(state.isMuted) {
					localStorage.setItem('videoPlayerMuted', 1);
				}
				else {
					localStorage.removeItem('videoPlayerMuted');
				}
			};

			return {
				videoPlayerRef: videoPlayerRef,
				state: state,
				toggleMute: toggleMute,
				togglePlay: () => {
					if(state.isLoaded) {
						if(state.isPlaying) {
							pauseVideo();
						}
						else {
							playVideo();
						}
					}
				}
			};
		},
		components: {
			SpinnerIcon: SpinnerIcon,
			PrimaryIconButton: PrimaryIconButton,
			VideoDurationTime: VideoDurationTime
		}
	});
</script>