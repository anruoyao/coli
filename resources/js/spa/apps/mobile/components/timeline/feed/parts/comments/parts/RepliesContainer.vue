<template>
    <div class="flex pl-11 pr-3 pb-1">
        <!-- 层级关系竖线 -->
        <div aria-hidden="true" class="w-[2.5px] shrink-0 rounded-full bg-brand-900/30 self-stretch my-1 mr-2"></div>

        <!-- 回复卡片：折叠预览 / 展开全部 -->
        <div class="flex-1 min-w-0 rounded-xl bg-fill-fv">
            <div ref="bodyRef">
                <!-- 折叠态：最新 2 条回复预览 + 入口 -->
                <div v-if="! thread.expanded" class="px-2 py-1 ct-fade-in">
                    <div v-for="(replyItem, idx) in thread.previews" v-bind:key="replyItem.id" class="ct-reply-in">
                        <Border v-if="idx > 0"></Border>
                        <Comment
                            variant="reply"
                            v-bind:preview="true"
                            v-bind:commentData="replyItem"
                            v-on:reply="(id) => $emit('reply', id)"
                        v-on:delete="(id) => $emit('delete', id)"></Comment>
                    </div>
                    <button type="button" class="flex items-center text-par-s font-semibold text-brand-900 leading-none pt-1 pb-0.5 px-0.5 active:opacity-70"
                        v-on:click="$emit('toggle')">
                        {{ thread.total > thread.previews.length
                            ? $t('labels.total_replies', {n: thread.total})
                            : $t('labels.expand_replies') }}
                        <SvgIcon name="chevron-down" classes="size-icon-x-small ml-0.5"></SvgIcon>
                    </button>
                </div>

                <!-- 展开态：常驻收起头部 + 有界独立滚动列表 -->
                <div v-else class="ct-fade-in">
                    <button type="button" class="w-full flex items-center gap-2 px-3 pt-2 pb-1 text-par-s font-semibold text-brand-900 leading-none active:opacity-70"
                        v-on:click="$emit('toggle')">
                        <span>{{ $t('labels.all_replies', {n: thread.total}) }}</span>
                        <span class="ml-auto inline-flex items-center">
                            {{ $t('labels.collapse_replies') }}
                            <SvgIcon name="chevron-up" classes="size-icon-x-small ml-0.5"></SvgIcon>
                        </span>
                    </button>
                    <div class="max-h-[55vh] overflow-y-auto overscroll-contain px-2 pb-1">
                        <div v-for="replyItem in thread.loaded" v-bind:key="replyItem.id" class="ct-reply-in">
                            <Comment
                                variant="reply"
                                v-bind:commentData="replyItem"
                                v-on:reply="(id) => $emit('reply', id)"
                            v-on:delete="(id) => $emit('delete', id)"></Comment>
                        </div>

                        <!-- 加载失败：点击重试 -->
                        <button v-if="thread.error" type="button" class="w-full text-center text-par-s text-red-900 py-2 active:opacity-70"
                            v-on:click="$emit('load-more')">
                            {{ $t('labels.replies_load_failed') }}
                        </button>

                        <template v-else>
                            <div v-if="thread.loadingMore || thread.loadingFirst" class="flex justify-center py-3">
                                <div class="colibri-primary-animation"></div>
                            </div>
                            <template v-else-if="thread.hasMore">
                                <button type="button" class="w-full flex justify-center items-center text-par-s font-semibold text-brand-900 py-1.5 active:opacity-70"
                                    v-on:click="$emit('load-more')">
                                    {{ $t('labels.view_more_replies_count', {n: remainingCount}) }}
                                    <SvgIcon name="chevron-down" classes="size-icon-x-small ml-0.5"></SvgIcon>
                                </button>
                            </template>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import { defineComponent, ref, computed, watch } from 'vue';
    import { useHeightSwap } from '@/kernel/vue/composables/comment-threads/index.js';

    import Border from '@/kernel/vue/components/general/Border.vue';
    import Comment from '@M/components/timeline/feed/parts/comments/parts/Comment.vue';

    export default defineComponent({
        props: {
            thread: {
                type: Object,
                required: true
            }
        },
        emits: ['toggle', 'load-more', 'reply', 'delete'],
        components: {
            Border: Border,
            Comment: Comment
        },
        setup: function(props) {
            const bodyRef = ref(null);
            const heightSwap = useHeightSwap(bodyRef);

            // 展开/折叠状态切换时执行高度动画（watcher 默认 flush:'pre'）
            watch(() => props.thread.expanded, heightSwap);

            const remainingCount = computed(() => {
                return Math.max(0, props.thread.total - props.thread.loaded.length);
            });

            return {
                bodyRef: bodyRef,
                remainingCount: remainingCount
            };
        }
    });
</script>
