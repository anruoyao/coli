<template>
    <div class="bg-fill-pr overflow-hidden relative">
        <img v-bind:src="mediaItem.thumbnail_url" class="w-full h-full object-cover" alt="Video thumbnail">
        <div class="from-black/60 to-transparent bg-gradient-to-t absolute bottom-0 left-0 right-0 px-4 pb-4 pt-6">
            <span class="text-white text-cap-s leading-none animate-pulse animate-ease-in-out animate-infinite">
                {{ $t('labels.video_processing') }}
            </span>
        </div>
    </div>
</template>

<script>
    import { defineComponent, computed, onMounted, onUnmounted } from 'vue';

    export default defineComponent({
        props: {
            mediaItem: {
                type: Object,
                default: {}
            }
        },
        setup: function(props) {
            const mediaItem = computed(() => {
                return props.mediaItem;
            });

            return {
                mediaItem: mediaItem
            }
        }
    });
</script>