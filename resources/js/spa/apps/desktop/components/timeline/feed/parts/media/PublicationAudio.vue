<template>
    <div class="overflow-hidden" v-bind:key="mediaItem.id">
        <div class="py-3.5">
            <div class="flex justify-between items-center gap-2.5">
                <div class="shrink-0">
                    <template v-if="MediaStatusUtils.isProcessing(mediaItem.status)">
                        <AudioPlayButton v-bind:disabled="true"></AudioPlayButton>
                    </template>
                    <template v-else>
                        <template v-if="playerState">
                            <AudioPlayButton v-if="playerState.playing" iconName="pause" v-on:click="togglePlay"></AudioPlayButton>
                            <AudioPlayButton v-else v-on:click="togglePlay"></AudioPlayButton>
                        </template>
                        <template v-else>
                            <AudioPlayButton v-on:click="addAudio"></AudioPlayButton>
                        </template>
                    </template>
                </div>
                <div class="flex-1 overflow-hidden">
                    <h4 class="text-lab-pr2 text-par-s font-medium mb-0.5 truncate">
                        {{ mediaItem.metadata.file_name }}
                    </h4>
                    <p class="text-cap-l text-lab-sc">
                        {{ $filters.fileSize(mediaItem.size) }} - {{ $filters.mediaDuration(mediaItem.metadata.duration) }}
                    </p>
                </div>
            </div>
        </div>
        <template v-if="MediaStatusUtils.isProcessing(mediaItem.status)">
            <PublicationAudioProcessing v-bind:mediaItem="mediaItem" v-bind:key="mediaItem.id"></PublicationAudioProcessing>
        </template>
    </div>
</template>

<script>
    import { defineComponent, computed, ref, defineAsyncComponent } from 'vue';
    import { useAudioStore } from '@D/store/audio/audio.store.js';
    import { MediaStatusUtils } from '@/kernel/enums/post/media.status.js';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';

    import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';
    import AudioPlayButton from '@D/components/timeline/feed/parts/media/parts/AudioPlayButton.vue';

    export default defineComponent({
        props: {
            postMedia: {
                type: Object,
                default: {}
            }
        },
        setup: function(props) {
            const audioWaveCanvas = ref(null);
            const audioStore = useAudioStore();

            const mediaItem = computed(() => {
                return props.postMedia[0];
            });

            const playerState = computed(() => {
                if (audioStore.postAudioData) {
                    if(audioStore.postAudioData.id === mediaItem.value.id) {
                        return audioStore.playerState;
                    }
                }

                return null;
            });

            return {
                mediaItem: mediaItem,
                playerState: playerState,
                audioWaveCanvas: audioWaveCanvas,
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
            PrimaryIconButton: PrimaryIconButton,
            AudioPlayButton: AudioPlayButton,
            PublicationAudioProcessing: defineAsyncComponent(() => {
                return import('@D/components/timeline/feed/parts/media/state/PublicationAudioProcessing.vue');
            })
        }
    });
</script>