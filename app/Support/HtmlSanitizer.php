<?php

namespace App\Support;

// Regex-based sanitizer for feed item HTML embedded in RSS/JSON output.
class HtmlSanitizer
{
    public static function sanitize(?string $html): string
    {
        if (empty($html)) {
            return '';
        }

        $html = preg_replace('#<script[^>]*>.*?</script>#is', '', $html);
        $html = preg_replace('#<style[^>]*>.*?</style>#is', '', $html);

        $html = preg_replace('#\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#is', '', $html);

        $html = preg_replace_callback(
            '#(<[^>]+\s(?:href|src)\s*=\s*)("[^"]*"|\'[^\']*\'|[^\s>]+)#is',
            function (array $matches): string {
                $value = trim($matches[2], '"\'');
                $scheme = strtolower(parse_url($value, PHP_URL_SCHEME) ?? '');
                if (in_array($scheme, ['javascript', 'data', 'vbscript'], true)) {
                    return $matches[1].'"#"';
                }

                return $matches[0];
            },
            $html
        );

        return str_replace(']]>', ']]]]><![CDATA[>', $html);
    }
}
