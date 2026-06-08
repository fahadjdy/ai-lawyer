import MarkdownIt from 'markdown-it';

/**
 * Markdown renderer for assistant replies. `html: false` means raw HTML in the
 * model output is escaped, not passed through — so rendering is XSS-safe without
 * a separate sanitiser.
 */
const md = new MarkdownIt({
    html: false,
    linkify: true,
    breaks: true,
    typographer: true,
});

// Make links open safely in a new tab.
const defaultLinkOpen =
    md.renderer.rules.link_open ??
    ((tokens, idx, options, _env, self) => self.renderToken(tokens, idx, options));

md.renderer.rules.link_open = (tokens, idx, options, env, self) => {
    tokens[idx].attrSet('target', '_blank');
    tokens[idx].attrSet('rel', 'noopener noreferrer');
    return defaultLinkOpen(tokens, idx, options, env, self);
};

export function renderMarkdown(text: string): string {
    return md.render(text ?? '');
}
