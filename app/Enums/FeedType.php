<?php

namespace App\Enums;

enum FeedType: string
{
    case Rss1 = 'rss1';
    case Rss2 = 'rss2';
    case Atom = 'atom';
    case Rdf = 'rdf';
    case Csv = 'csv';
    case Json = 'json';
    case Xml = 'xml';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
