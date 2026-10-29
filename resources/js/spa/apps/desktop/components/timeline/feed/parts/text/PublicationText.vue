<template>
    <div class="block">
        <div ref="publicationTextHolder" v-bind:class="[!state.textExpanded ? 'line-clamp-[12]' : '']" class="leading-6 text-lab-pr2 text-par-l font-normal markdown-text break-words" v-html="markdownRenderer()"></div>
        <div v-if="state.textOverflow" class="block opacity-90">
            <button v-on:click="state.textExpanded = !state.textExpanded" class="text-par-s text-brand-900 underline outline-hidden font-light">
                {{ state.textExpanded ? $t('labels.show_less') : $t('labels.show_more') }}
            </button>
        </div>
    </div>
</template>

<script>
    import { defineComponent, computed, reactive, ref, onMounted } from 'vue';
    import { checkTextOverflow } from '@/kernel/helpers/html/index.js';
    
    import MarkdownMentionPlugin from '@/kernel/plugins/markdownit/mention.plugin.js';
    import MarkdownUnderlinePlugin from '@/kernel/plugins/markdownit/underline.plugin.js';
    import MarkdownHighlightPlugin from '@/kernel/plugins/markdownit/highlight.plugin.js';
    import MarkdownParser from 'markdown-it';

    export default defineComponent({
        props: {
            postContent: {
                type: String,
                default: ''
            },
            allowHtml: {
                type: Boolean,
                default: true
            }
        },
        setup: function(props) {
            const MarkdownIT = computed(() => {
                const parser = new MarkdownParser({
                    html: props.allowHtml,
                    breaks: true,
                    linkify: true,
                });

                parser.use(MarkdownMentionPlugin);
                parser.use(MarkdownUnderlinePlugin);
                parser.use(MarkdownHighlightPlugin);

                return parser;
            });

            const state = reactive({
                textExpanded: false,
                textOverflow: false
            });

            const publicationTextHolder = ref(null);
            const postContent = computed(() => {
                return props.postContent;
            });

            onMounted(() => {
                state.textOverflow = checkTextOverflow(publicationTextHolder.value, 12);
            });

            return {
                state: state,
                markdownRenderer: () => {
                    return MarkdownIT.value.renderInline(postContent.value);
                },
                publicationTextHolder: publicationTextHolder
            };
        }
    });
</script>