<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Post text written in the rich-text editor. The HTML is stored (cleaned) for editing and display;
 * platforms only accept plain text, so toPlainText() produces what actually gets published.
 */
class RichText
{
    private const ALLOWED = ['p', 'br', 'strong', 'em', 'u', 's', 'ul', 'ol', 'li', 'a', 'blockquote', 'h2', 'h3'];
    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'noscript', 'svg', 'math', 'form'];
    private const NORMALIZE = ['b' => 'strong', 'i' => 'em', 'strike' => 's', 'del' => 's', 'h1' => 'h2', 'h4' => 'h3', 'h5' => 'h3', 'h6' => 'h3', 'div' => 'p'];

    /** Whitelist-only HTML: unknown tags are unwrapped, all attributes dropped except safe http(s)/mailto links. */
    public static function sanitize(string $html): string
    {
        $root = self::parse($html);
        self::clean($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $root->ownerDocument->saveHTML($child);
        }

        return trim($out);
    }

    /** What platforms receive: paragraphs separated by a blank line, bullets as "•", links as "text (url)". */
    public static function toPlainText(string $html): string
    {
        $root = self::parse(self::sanitize($html));
        $text = self::textOf($root);
        $text = preg_replace("/[ \t]+\n/", "\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    private static function parse(string $html): DOMElement
    {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><body>' . $html . '</body>', LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $doc->getElementsByTagName('body')->item(0);
    }

    private static function clean(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (!$child instanceof DOMElement) {
                if ($child->nodeType !== XML_TEXT_NODE) {
                    $node->removeChild($child); // comments, processing instructions, ...
                }
                continue;
            }

            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $node->removeChild($child);
                continue;
            }

            self::clean($child);
            $tag = self::NORMALIZE[$tag] ?? $tag;

            if (!in_array($tag, self::ALLOWED, true)) {
                // unwrap: keep the (already cleaned) children in place of the element
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            $href = $tag === 'a' ? trim($child->getAttribute('href')) : '';
            if ($tag !== strtolower($child->tagName)) {
                $renamed = $child->ownerDocument->createElement($tag);
                while ($child->firstChild) {
                    $renamed->appendChild($child->firstChild);
                }
                $node->replaceChild($renamed, $child);
                $child = $renamed;
            }
            while ($child->attributes->length) {
                $child->removeAttributeNode($child->attributes->item(0));
            }
            if ($tag === 'a') {
                if (preg_match('#^(https?://|mailto:)#i', $href)) {
                    $child->setAttribute('href', $href);
                    $child->setAttribute('rel', 'noopener noreferrer nofollow');
                    $child->setAttribute('target', '_blank');
                } else {
                    while ($child->firstChild) { // unsafe link (javascript:, data:, ...): keep only the text
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                }
            }
        }
    }

    private static function textOf(DOMNode $node, string $listType = '', int &$index = 0): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $out .= $child->nodeValue;
                continue;
            }
            if (!$child instanceof DOMElement) {
                continue;
            }

            switch ($child->tagName) {
                case 'br':
                    $out .= "\n";
                    break;
                case 'ul':
                case 'ol':
                    $i = 0;
                    $out .= self::textOf($child, $child->tagName, $i) . "\n\n";
                    break;
                case 'li':
                    $index++;
                    $out .= ($listType === 'ol' ? "{$index}. " : '• ') . trim(self::textOf($child)) . "\n";
                    break;
                case 'a':
                    $label = self::textOf($child);
                    $url = $child->getAttribute('href');
                    $out .= ($label === '' || $label === $url || 'mailto:' . $label === $url) ? $label ?: $url : "{$label} ({$url})";
                    break;
                case 'p':
                case 'blockquote':
                case 'h2':
                case 'h3':
                    $out .= self::textOf($child) . "\n\n";
                    break;
                default: // strong, em, u, s
                    $out .= self::textOf($child);
            }
        }

        return $out;
    }
}
