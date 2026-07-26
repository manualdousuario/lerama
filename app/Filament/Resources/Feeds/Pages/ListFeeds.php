<?php

namespace App\Filament\Resources\Feeds\Pages;

use App\Filament\Resources\Feeds\FeedResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFeeds extends ListRecords
{
    protected static string $resource = FeedResource::class;

    public function getTitle(): string
    {
        return __('admin.feeds.title');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('admin.feeds.add_new')),
        ];
    }
}
