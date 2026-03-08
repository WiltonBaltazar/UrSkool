<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

class HtmlSanitizer
{
    /**
     * @var array<int, string>
     */
    private const ALLOWED_TAGS = [
        'a',
        'b',
        'blockquote',
        'br',
        'code',
        'div',
        'em',
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'hr',
        'i',
        'li',
        'ol',
        'p',
        'pre',
        'span',
        'strong',
        'u',
        'ul',
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title', 'target', 'rel'],
        'div' => ['class'],
        'span' => ['class'],
        'p' => ['class'],
        'ul' => ['class'],
        'ol' => ['class'],
        'li' => ['class'],
        'code' => ['class'],
        'pre' => ['class'],
        'blockquote' => ['class'],
        'h1' => ['class'],
        'h2' => ['class'],
        'h3' => ['class'],
        'h4' => ['class'],
        'h5' => ['class'],
        'h6' => ['class'],
    ];

    public static function sanitize(?string $html): string
    {
        if (!is_string($html) || trim($html) === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $dom = new DOMDocument('1.0', 'UTF-8');
            $wrappedHtml = '<!DOCTYPE html><html><body>'.$html.'</body></html>';

            $dom->loadHTML('<?xml encoding="utf-8" ?>'.$wrappedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);

            $body = $dom->getElementsByTagName('body')->item(0);
            if (!$body instanceof DOMElement) {
                return '';
            }

            self::sanitizeChildren($body);

            return self::innerHtml($body);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private static function sanitizeChildren(DOMNode $node): void
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);

                if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                    self::unwrapNode($child);
                    continue;
                }

                self::sanitizeAttributes($child, $tag);
                self::sanitizeChildren($child);
                continue;
            }

            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);
            }
        }
    }

    private static function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];

        $attributes = [];
        foreach ($element->attributes as $attribute) {
            $attributes[] = $attribute;
        }

        foreach ($attributes as $attribute) {
            $name = strtolower($attribute->name);
            $value = $attribute->value;

            if (str_starts_with($name, 'on') || !in_array($name, $allowed, true)) {
                $element->removeAttributeNode($attribute);
                continue;
            }

            if (in_array($name, ['href', 'src'], true) && !self::isSafeUrl($value, $name)) {
                $element->removeAttributeNode($attribute);
                continue;
            }

            if ($name === 'target') {
                if (!in_array($value, ['_self', '_blank'], true)) {
                    $element->removeAttributeNode($attribute);
                    continue;
                }

                if ($value === '_blank') {
                    $existingRel = strtolower(trim((string) $element->getAttribute('rel')));
                    $rels = array_filter(explode(' ', $existingRel));
                    $rels = array_values(array_unique([...$rels, 'noopener', 'noreferrer']));
                    $element->setAttribute('rel', implode(' ', $rels));
                }
            }
        }
    }

    private static function isSafeUrl(string $url, string $attribute): bool
    {
        $candidate = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($candidate === '') {
            return false;
        }

        if (
            str_starts_with($candidate, '/')
            || str_starts_with($candidate, '#')
            || str_starts_with($candidate, './')
            || str_starts_with($candidate, '../')
        ) {
            return true;
        }

        $scheme = parse_url($candidate, PHP_URL_SCHEME);
        if (!is_string($scheme)) {
            return true;
        }

        $scheme = strtolower($scheme);
        if ($attribute === 'href') {
            return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true);
        }

        return in_array($scheme, ['http', 'https'], true);
    }

    private static function unwrapNode(DOMElement $node): void
    {
        $parent = $node->parentNode;
        if (!$parent instanceof DOMNode) {
            return;
        }

        while ($node->firstChild instanceof DOMNode) {
            $parent->insertBefore($node->firstChild, $node);
        }

        $parent->removeChild($node);
    }

    private static function innerHtml(DOMElement $element): string
    {
        $html = '';

        foreach ($element->childNodes as $child) {
            $html .= $element->ownerDocument?->saveHTML($child) ?? '';
        }

        return $html;
    }
}
