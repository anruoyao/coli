import MarkdownParser from 'markdown-it';
import MarkdownMentionPlugin from '@/kernel/plugins/markdownit/mention.plugin.js';
import MarkdownUnderlinePlugin from '@/kernel/plugins/markdownit/underline.plugin.js';
import MarkdownHighlightPlugin from '@/kernel/plugins/markdownit/highlight.plugin.js';

const parserInstances = {};

const getParserInstance = (options = {}) => {
	const cacheKey = JSON.stringify(options);

	if (! parserInstances[cacheKey]) {
		const parser = new MarkdownParser({
			breaks: true,
			linkify: true,
			...options,
			// User-authored Markdown (including profile biographies) must never
			// become executable HTML in v-html consumers.
			html: false,
		});

		// Keep inline rendering consistent with publication text
		// (mentions, underline `++ x ++` and highlight `:: x ::` syntax)
		parser.use(MarkdownMentionPlugin);
		parser.use(MarkdownUnderlinePlugin);
		parser.use(MarkdownHighlightPlugin);

		parserInstances[cacheKey] = parser;
	}

	return parserInstances[cacheKey];
}

const mdInlineRenderer = (text = '', options = {}) => {
	return getParserInstance(options).renderInline(text);
}

export { mdInlineRenderer };
