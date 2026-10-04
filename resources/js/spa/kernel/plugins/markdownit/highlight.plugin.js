/**
 * Highlight plugin for markdown-it
 * Renders `:: text ::` syntax into `<mark>` HTML tag.
 *
 * @see underline.plugin.js (sibling `++ text ++` plugin)
 */
export default function (md) {
    md.inline.ruler.after('emphasis', 'highlight', (state, silent) => {
        const start = state.pos;

        // Check for "::" at the current position
        if (state.src[start] !== ':') return false;
        if (state.src[start + 1] !== ':') return false;

        // Find the closing "::"
        const match = state.src.slice(start + 2).match(/(.+?)::/);
        if (!match) return false;

        if (!silent) {
            const openToken = state.push('highlight_open', 'mark', 1);
            openToken.level = state.level;

            const textToken = state.push('text', '', 0);
            textToken.content = match[1];
            textToken.level = state.level;

            const closeToken = state.push('highlight_close', 'mark', -1);
            closeToken.level = state.level;
        }

        // Update position to after the closing "::"
        state.pos += match[0].length + 2;

        return true;
    });

    // Rendering rules for the highlight tokens
    md.renderer.rules.highlight_open = () => '<mark>';
    md.renderer.rules.highlight_close = () => '</mark>';
}
