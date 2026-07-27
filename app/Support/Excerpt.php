<?php

namespace App\Support;

class Excerpt
{
    public const STORAGE_LENGTH = 320;

    public static function make(?string $html, int $length): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        return mb_substr(Text::decodeEntities(strip_tags($html)), 0, $length);
    }

    public static function forStorage(?string $html): ?string
    {
        $excerpt = self::make($html, self::STORAGE_LENGTH);

        return $excerpt !== '' ? $excerpt : null;
    }
}
