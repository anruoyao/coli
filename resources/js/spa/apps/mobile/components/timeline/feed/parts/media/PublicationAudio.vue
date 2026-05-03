<template>
    <div class="overflow-hidden" v-bind:key="mediaItem.id">
		<div class="px-4 py-2">
			<div class="flex justify-between items-center gap-2">
				<div class="shrink-0 size-small-avatar inline-flex items-center justify-center bg-fill-tr rounded-full">
					<template v-if="MediaStatusUtils.isProcessing(mediaItem.status)">
						<div class="cursor-not-allowed translate-x-px size-icon-small rounded-full text-lab-tr smoothing opacity-50">
							<span class="size-4">
								<SvgIcon name="play" classes="size-full"></SvgIcon>
							</span>
						</div>
					</template>
					<template v-else>
						<div class="cursor-pointer translate-x-px size-icon-small rounded-full text-lab-tr hover:text-brand-900 smoothing">
							<template v-if="playerState">
								<span v-on:click="togglePlay" class="size-4" v-if="playerState.playing">
									<SvgIcon name="pause" classes="size-full"></SvgIcon>
								</span>
								<span  v-on:click="togglePlay" class="size-4" v-else>
									<SvgIcon name="play" classes="size-full"></SvgIcon>
								</span>
							</template>
							<template v-else>
								<span v-on:click="addAudio" class="size-4">
									<SvgIcon name="play" classes="size-full"></SvgIcon>
								</span>
							</template>
						</div>
					</template>
				</div>
				<div class="flex-1 leading-none overflow-hidden">
					<h4 class="text-lab-pr2 text-par-n font-medium mb-1 truncate">
						{{ mediaItem.metadata.file_name }}
					</h4>
					<p class="text-par-s text-lab-sc">
						{{ $filters.fileSize(mediaItem.size) }} - {{ $filters.mediaDuration(mediaItem.metadata.duration) }}
					</p>
				</div>
			</div>
		</div>
    </div>
</template>

<script>
    import { defineComponent, computed, ref, defineAsyncComponent } from 'vue';

    import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';

    import { useAudioStore } from '@M/store/audio/audio.store.js';
    import { MediaStatusUtils } from '@/kernel/enums/post/media.status.js';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';

    export default defineComponent({
        props: {
            postMedia: {
                type: Object,
                default: {}
            }
        },
        setup: function(props) {
            const audioStore = useAudioStore();

            const mediaItem = computed(() => {
                return props.postMedia[0];
            });

            const playerState = computed(() => {
                if (audioStore.audioData) {
                    if(audioStore.audioData.id === mediaItem.value.id) {
                        return audioStore.playerState;
                    }
                }

                return null;
            });

            return {
                mediaItem: mediaItem,
                playerState: playerState,
                addAudio: () => {
                    audioStore.add(mediaItem.value);

                    colibriEventBus.emit('soundbar:reinitialize');
                },
                togglePlay: () => {
                    colibriEventBus.emit('soundbar:play');
                },
                MediaStatusUtils: MediaStatusUtils
            }
        },
        components: {
            PrimaryIconButton: PrimaryIconButton
        }
    });
</script>