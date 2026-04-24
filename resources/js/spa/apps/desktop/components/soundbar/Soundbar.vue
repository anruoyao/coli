<template>
    <SoundbarPlayer v-if="showSoundbar"></SoundbarPlayer>
</template>

<script>
    import { defineComponent, computed } from 'vue';
    import { useAudioStore } from '@D/store/audio/audio.store.js';
    import { useUIStore } from '@D/store/global/ui.store.js';
    
    import SoundbarPlayer from '@D/components/soundbar/SoundbarPlayer.vue';

    export default defineComponent({
        setup: function(props) {
            const audioStore = useAudioStore();
            const uiStore = useUIStore();

            return {
                showSoundbar: computed(() => {
                    if (uiStore.cheatSheet.isOpen) {
                        return false;
                    }

                    return audioStore.postAudioData !== null;
                })
            };
        },
        components: {
            SoundbarPlayer: SoundbarPlayer
        }
    });
</script>