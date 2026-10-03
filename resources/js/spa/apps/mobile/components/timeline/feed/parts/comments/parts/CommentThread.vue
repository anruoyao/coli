<template>
    <div>
        <Comment
            variant="root"
            v-bind:commentData="thread.root"
            v-on:reply="(id) => $emit('reply', id)"
        v-on:delete="(id) => $emit('delete', id)"></Comment>

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

    import Comment from '@M/components/timeline/feed/parts/comments/parts/Comment.vue';
    import RepliesContainer from '@M/components/timeline/feed/parts/comments/parts/RepliesContainer.vue';

    export default defineComponent({
        props: {
            thread: {
                type: Object,
                required: true
            }
        },
        emits: ['toggle-expand', 'load-more', 'reply', 'delete'],
        components: {
            Comment: Comment,
            RepliesContainer: RepliesContainer
        }
    });
</script>
