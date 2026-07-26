<?php

namespace App\Support;

class Excerpt
{
    public static function make(?string $html, int $length): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        return mb_substr(Text::decodeEntities(strip_tags($html)), 0, $length);
    }
}
