<?php

namespace App\Filament\Resources\Tags;

use App\Filament\Resources\Tags\Pages\ManageTags;
use App\Filament\Support\TaxonomySchema;
use App\Filament\Support\TaxonomyTable;
use App\Models\Tag;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('nav.topics');
    }

    public static function getModelLabel(): string
    {
        return __('suggest.form.select_tag');
    }

    public static function getPluralModelLabel(): string
    {
        return __('nav.topics');
    }

    public static function form(Schema $schema): Schema
    {
        return TaxonomySchema::configure($schema, Tag::class);
    }

    public static function table(Table $table): Table
    {
        return TaxonomyTable::configure(
            $table,
            feedCountLabel: __('admin.tags.feeds'),
            editHeading: __('admin.tag_form.edit_title'),
            emptyStateHeading: __('admin.tags.no_tags'),
            deleteDescription: __('admin.tags.delete_confirm'),
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTags::route('/'),
        ];
    }
}
