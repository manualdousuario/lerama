<?php

namespace App\Filament\Resources\FeedItems\Pages;

use App\Filament\Resources\FeedItems\FeedItemResource;
use Filament\Resources\Pages\ListRecords;

class ListFeedItems extends ListRecords
{
    protected static string $resource = FeedItemResource::class;

    public function getTitle(): string
    {
        return __('admin.items.title');
    }
}
