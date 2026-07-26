<?php

namespace App\Enums;

enum FeedStatus: string
{
    case Online = 'online';
    case Offline = 'offline';
    case Paused = 'paused';
    case Pending = 'pending';
    case Rejected = 'rejected';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
