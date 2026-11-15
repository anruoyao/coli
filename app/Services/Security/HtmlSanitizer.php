<?php

namespace App\Services\Security;

use DOMDocument;
use DOMElement;
use DOMNode;

final class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'a', 'blockquote', 'br', 'code', 'div', 'em', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'hr', 'img', 'li', 'mark', 'ol', 'p', 'pre', 's', 'span', 'strong', 'u', 'ul',
    ];

    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height'],
    ];

    private const BLOCKED_TAGS = [
        'audio', 'base', 'button', 'embed', 'form', 'iframe', 'input', 'link', 'math', 'meta',
        'object', 'option', 'script', 'select', 'source', 'style', 'svg', 'template', 'textarea',
        'video',
    ];

    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        try {
            $dom->loadHTML(
                '<!doctype html><html><head><meta charset="utf-8"></head><body>'.$html.'</body></html>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET,
            );

            $body = $dom->getElementsByTagName('body')->item(0);
            $result = '';

            if ($body) {
                $this->sanitizeChildren($body);

                foreach ($body->childNodes as $child) {
                    $result .= $dom->saveHTML($child);
                }
            }

            return strtr($result, [
                '@' => '&#64;',
                '{' => '&#123;',
                '}' => '&#125;',
            ]);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function sanitizeChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);

                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    if (in_array($tag, self::BLOCKED_TAGS, true)) {
                        $parent->removeChild($child);
                    } else {
                        while ($child->firstChild) {
                            $parent->insertBefore($child->firstChild, $child);
                        }

                        $parent->removeChild($child);
                    }

                    continue;
                }

                $this->sanitizeAttributes($child);
            }

            $this->sanitizeChildren($child);
        }
    }

    private function sanitizeAttributes(DOMElement $element): void
    {
        $allowed = self::ALLOWED_ATTRIBUTES[strtolower($element->tagName)] ?? [];

        foreach (iterator_to_array($element->attributes) as $attribute) {
            if (! in_array(strtolower($attribute->name), $allowed, true)) {
                $element->removeAttributeNode($attribute);
            }
        }

        if ($element->hasAttribute('href') && ! $this->isSafeUrl($element->getAttribute('href'))) {
            $element->removeAttribute('href');
        }

        if ($element->hasAttribute('src') && ! $this->isSafeUrl($element->getAttribute('src'))) {
            $element->removeAttribute('src');
        }

        if (strtolower($element->tagName) === 'a' && $element->hasAttribute('target')) {
            if (! in_array($element->getAttribute('target'), ['_blank', '_self'], true)) {
                $element->removeAttribute('target');
            } else {
                $element->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }

    private function isSafeUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '') {
            return false;
        }

        if (preg_match('/[\x00-\x20\x7f]/', $url) || str_starts_with($url, '//') || str_starts_with($url, '\\\\')) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return $scheme === '' || in_array($scheme, ['http', 'https', 'mailto'], true);
    }
}
