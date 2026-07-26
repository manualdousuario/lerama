<?php

namespace App\Filament\Resources\FeedItems;

use App\Filament\Resources\FeedItems\Pages\ListFeedItems;
use App\Filament\Resources\FeedItems\Tables\FeedItemsTable;
use App\Models\FeedItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FeedItemResource extends Resource
{
    protected static ?string $model = FeedItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationLabel(): string
    {
        return __('nav.articles');
    }

    public static function getModelLabel(): string
    {
        return __('suggest.form.title');
    }

    public static function getPluralModelLabel(): string
    {
        return __('nav.articles');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return FeedItemsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeedItems::route('/'),
        ];
    }
}
