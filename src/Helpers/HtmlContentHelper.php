<?php

namespace NextDeveloper\Marketplace\Helpers;

class HtmlContentHelper
{
    public static function cleanContent(string $content): string
    {
        // Decode HTML entities
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Replace common HTML tags with appropriate whitespace
        $content = self::replaceHtmlTags($content);

        // Remove all remaining HTML tags
        $content = strip_tags($content);

        // Clean up whitespace and control characters
        $content = self::cleanWhitespace($content);

        return $content;
    }

    private static function replaceHtmlTags(string $content): string
    {
        $replacements = [
            '/<br\s*\/?>/i' => "\n",
            '/<\/p>/i' => "\n",
            '/<\/div>/i' => "\n",
            '/<\/tr>/i' => "\n",
            '/<\/td>/i' => " ",
            '/<\/th>/i' => " "
        ];

        return preg_replace(array_keys($replacements), array_values($replacements), $content);
    }

    private static function cleanWhitespace(string $content): string
    {
        // Clean up whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = preg_replace('/\n\s*\n/', "\n", $content);

        // Remove control characters
        $content = preg_replace('/[\x00-\x1F\x7F]/', '', $content);

        return trim($content);
    }

    public static function normalizeKey(string $key): string
    {
        // Convert to lowercase
        $key = mb_strtolower($key, 'UTF-8');

        // Replace non-alphanumeric characters with underscore
        $key = preg_replace('/[^a-z0-9]+/u', '_', $key);

        // Clean up underscores
        $key = trim($key, '_');
        $key = preg_replace('/_+/', '_', $key);

        return $key;
    }
}
