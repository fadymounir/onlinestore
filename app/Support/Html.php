<?php

namespace App\Support;

class Html
{
    /** Tags the rich editor legitimately produces for a product description. */
    private const ALLOWED = '<p><br><strong><b><em><i><u><s><ul><ol><li><h2><h3><h4><blockquote><a><span>';

    /**
     * Product descriptions are stored as HTML from the panel's rich editor.
     * Staff are trusted but not all are super admins, so strip anything that
     * could execute before echoing it unescaped.
     */
    public static function clean(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        // Drop whole dangerous elements including their contents.
        $html = preg_replace(
            '#<(script|style|iframe|object|embed|form|input|svg)\b[^>]*>.*?</\1>#is',
            '',
            $html,
        ) ?? '';

        $html = strip_tags($html, self::ALLOWED);

        // Kill inline event handlers and javascript: / data: URLs.
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $html = preg_replace('/\s(href|src)\s*=\s*("|\')\s*(javascript|data|vbscript):[^"\']*\2/i', '', $html) ?? '';

        return $html;
    }
}
