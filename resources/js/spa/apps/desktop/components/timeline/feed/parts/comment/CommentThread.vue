<template>
    <div>
        <PublicationComment
            variant="root"
            v-bind:commentData="thread.root"
            v-on:reply="(id) => $emit('reply', id)"
        v-on:delete="(id) => $emit('delete', id)"></PublicationComment>

        <RepliesContainer
            v-if="thread.total > 0"
            v-bind:thread="thread"
            v-on:toggle="$emit('toggle-expand')"
            v-on:load-more="$emit('load-more')"
            v-on:reply="(id) => $emit('reply', id)"
        v-on:delete="(id) => $emit('delete', id)"></RepliesContainer>
    </div>
</template>

<script>
    import { defineComponent } from 'vue';

    import PublicationComment from '@D/components/timeline/feed/parts/comment/PublicationComment.vue';
    import RepliesContainer from '@D/components/timeline/feed/parts/comment/RepliesContainer.vue';

    export default defineComponent({
        props: {
            thread: {
                type: Object,
                required: true
            }
        },
        emits: ['toggle-expand', 'load-more', 'reply', 'delete'],
        components: {
            PublicationComment: PublicationComment,
            RepliesContainer: RepliesContainer
        }
    });
</script>
