<template>
    <Backdrop>
        <div v-bind:class="[isCentered ? 'flex justify-center' : 'ml-page-offset']">
            <div class="py-top-offset">
                <PrimaryTransition>
                    <div class="w-content flex justify-center">
                        <div v-if="renderModal" v-bind:class="[contentWidth]" class="popup-background-tr rounded-2xl origin-top">
                            <div class="block">
                                <slot></slot>
                            </div>
                        </div>
                    </div>
                </PrimaryTransition>
                
                <template v-if="renderModal == false">
                    <template v-if="($slots['loadingSkeleton'] !== undefined)">
                        <div class="popup-background-tr w-content rounded-md">
                            <slot name="loadingSkeleton"></slot>
                        </div>
                    </template>
                </template>
            </div>
        </div>
        <Teleport to="body" v-if="!hideAuthorAttribution">
            <ColibriPlusAttribution></ColibriPlusAttribution>
        </Teleport>

        <div class="backdrop-close-button-container-top-right">
            <BackdropCloseButton v-on:click="$emit('close')"></BackdropCloseButton>
        </div>
    </Backdrop>
</template>

<script>
    import { defineComponent, ref, onMounted, onUnmounted, computed } from 'vue';

    import { useRoute } from 'vue-router';
    
    import ColibriPlusAttribution from '@D/components/attributions/ColibriPlusAttribution.vue';
    import Backdrop from '@D/components/general/modals/Backdrop.vue';
    import BackdropCloseButton from '@D/components/inter-ui/buttons/BackdropCloseButton.vue';
    import hotkeys from 'hotkeys-js';

    export default defineComponent({
        emits: ['close'],
        props: {
            contentWidth: {
                type: String,
                default: 'w-full'
            }
        },
        setup: function(props, context) {
            const renderModal = ref(false);
            const route = useRoute();

            onMounted(() => {
                setTimeout(() => {
                    renderModal.value = true;
                }, 200);

                document.body.classList.add('overflow-hidden');

                hotkeys('esc', () => {
                    context.emit('close');
                });
            });

            onUnmounted(() => {
                document.body.classList.remove('overflow-hidden');

                hotkeys.unbind('esc');
            });
            
            return {
                renderModal: renderModal,
                isCentered: computed(() => {
                    if(route.meta.fluidLayout) {
                        return true;
                    }

                    return false;
                }),
                hideAuthorAttribution: window.HIDE_AUTHOR_ATTRIBUTION
            }
        },
        components: {
            ColibriPlusAttribution: ColibriPlusAttribution,
            Backdrop: Backdrop,
            BackdropCloseButton: BackdropCloseButton
        }
    });
</script>