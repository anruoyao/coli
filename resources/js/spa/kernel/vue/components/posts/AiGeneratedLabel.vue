<template>
    <div class="inline-flex items-center gap-1.5 mt-1 select-none">
        <span class="size-icon-x-small inline-flex text-amber-500">
            <SvgIcon name="ai-icon"></SvgIcon>
        </span>
        <span class="text-cap-s font-medium text-amber-500">
            {{ $t('labels.ai_generated') }}
        </span>
    </div>
</template>

<script>
    import { defineComponent } from 'vue';

    export default defineComponent({
        name: 'AiGeneratedLabel'
    });
</script>
