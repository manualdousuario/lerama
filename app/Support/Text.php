<?php

namespace App\Support;

class Text
{
    private const MAX_DECODE_PASSES = 3;

    public static function decodeEntities(?string $value): string
    {
        $value = (string) $value;

        for ($i = 0; $i < self::MAX_DECODE_PASSES; $i++) {
            $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($decoded === $value) {
                break;
            }

            $value = $decoded;
        }

        return $value;
    }

    public static function plain(?string $value): ?string
    {
        $value = trim(self::decodeEntities($value));

        return $value !== '' ? $value : null;
    }
}
