<template>
    <div class="block px-4">
        <RouterLink v-bind:to="link" class="cursor-pointer gap-3 items-center flex justify-between py-2">
            <div class="shrink-0 size-6 text-lab-pr">
                <SvgIcon v-bind:name="iconName" v-bind:type="iconType"/>
            </div>
            <div class="flex-1">
                <h5 class="text-par-m font-bold text-lab-pr2">
                    {{ titleText }}
                </h5>
                <p class="text-par-m text-lab-sc" v-html="captionText"></p>
            </div>
            <div class="shrink-0 size-6 text-lab-tr">
                <SvgIcon name="chevron-right"/>
            </div>
        </RouterLink>
    </div>
</template>

<script>
    import { defineComponent } from 'vue';

    export default defineComponent({
        props: {
            iconName: {
                type: String,
                default: ''
            },
            iconType: {
                type: String,
                default: 'line'
            },
            link: {
                type: Object,
                default: {}
            },
            titleText: {
                type: String,
                default: ''
            },
            captionText: {
                type: String,
                default: ''
            }
        }
    });
</script>