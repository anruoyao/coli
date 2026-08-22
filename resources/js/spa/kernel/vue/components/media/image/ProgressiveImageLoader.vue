<template>
    <div v-if="isLoading" class="relative block min-h-24 bg-fill-fv">
        <img v-bind:src="base64Image" v-bind="$attrs" alt="Image">

        <div class="absolute inset-0 flex-center">
            <div class="size-10">
				<SpinnerIcon strokeColor="stroke-white" strokeWidth="1.5"></SpinnerIcon>
			</div>
        </div>
    </div>

    <div v-show="! isLoading" class="relative block size-full min-h-20 bg-bg-pr">
        <div v-show="isSensitive" class="absolute inset-0">
            <SensitiveContentAlert></SensitiveContentAlert>
        </div>
        <img ref="imageRef" v-bind:src="src" v-bind="$attrs" alt="Image">
    </div>
</template>

<script>
    import { defineComponent, ref, onMounted, onUnmounted } from 'vue';
    
    import SensitiveContentAlert from '@D/components/media/SensitiveContentAlert.vue';
    import SpinnerIcon from '@/kernel/vue/components/icons/SpinnerIcon.vue';

    export default defineComponent({
        props: {
            base64Image: {
                type: String,
                default: ''
            },
            src: {
                type: String,
                default: ''
            },
            isSensitive: {
                type: Boolean,
                default: false
            }
        },
        setup: function(props) {
            const isLoading = ref(true);
            const imageRef = ref(null);

            onMounted(() => {
                imageRef.value.onload = () => {
                    isLoading.value = false;
                }

                imageRef.value.onerror = () => {
                    isLoading.value = false;
                }
            });

            onUnmounted(() => {
                if (imageRef.value) {
                    imageRef.value.onload = null;
                    imageRef.value.onerror = null;
                }
            });

            return {
                isLoading: isLoading,
                imageRef: imageRef
            };
        },
        components: {
            SensitiveContentAlert: SensitiveContentAlert,
            SpinnerIcon: SpinnerIcon
        }
    });
</script>