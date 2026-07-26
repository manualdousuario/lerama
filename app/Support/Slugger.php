<?php

namespace App\Support;

/**
 * Portable slug transliteration. Prefers intl (ICU), which matches iconv on
 * Linux; iconv itself is inconsistent on Windows.
 */
class Slugger
{
    public static function transliterate(string $value): string
    {
        if (class_exists(\Transliterator::class)) {
            $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII');
            if ($transliterator !== null) {
                return $transliterator->transliterate($value);
            }
        }

        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT', $value);

        return $converted !== false ? $converted : $value;
    }

    public static function slug(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $value = self::transliterate($value);

        return trim((string) preg_replace('/[^a-z0-9]+/', '-', $value), '-');
    }
}
