<?php

namespace App\Services;

class ProductDescription
{
    public static function clean(?string $html): string
    {
        if (! $html) {
            return '';
        }
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="utf-8" ?><body>'.$html.'</body>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $render = function ($node) use (&$render): string {
            if ($node instanceof \DOMText) {
                return htmlspecialchars($node->textContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
            $tag = strtolower($node->nodeName);
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'svg', 'math', 'template'], true)) {
                return '';
            }
            $content = '';
            foreach ($node->childNodes as $child) {
                $content .= $render($child);
            }
            if ($tag === 'br') {
                return '<br>';
            }

            $attributes = '';
            if ($node instanceof \DOMElement) {
                if (preg_match('/(?:^|;)\s*text-align\s*:\s*(left|center|right|justify)\s*(?:;|$)/i', $node->getAttribute('style'), $match)) {
                    $attributes .= ' style="text-align:'.strtolower($match[1]).'"';
                }
                if (in_array($tag, ['td', 'th'], true)) {
                    foreach (['colspan', 'rowspan'] as $attribute) {
                        $value = $node->getAttribute($attribute);
                        if (ctype_digit($value) && (int)$value > 0 && (int)$value <= 100) {
                            $attributes .= ' '.$attribute.'="'.(int)$value.'"';
                        }
                    }
                }
            }
            return in_array($tag, ['p', 'div', 'span', 'strong', 'b', 'em', 'i', 'u', 's', 'h2', 'h3', 'h4', 'blockquote', 'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'caption'], true) ? '<'.$tag.$attributes.'>'.$content.'</'.$tag.'>' : $content;
        };

        return $render($document->getElementsByTagName('body')->item(0) ?? $document);
    }
}
