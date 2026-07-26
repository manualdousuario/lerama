<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Filament\Support\TaxonomySchema;
use App\Filament\Support\TaxonomyTable;
use App\Models\Category;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('nav.categories');
    }

    public static function getModelLabel(): string
    {
        return __('suggest.form.category');
    }

    public static function getPluralModelLabel(): string
    {
        return __('nav.categories');
    }

    public static function form(Schema $schema): Schema
    {
        return TaxonomySchema::configure($schema, Category::class);
    }

    public static function table(Table $table): Table
    {
        return TaxonomyTable::configure(
            $table,
            feedCountLabel: __('admin.categories.feeds'),
            editHeading: __('admin.category_form.edit_title'),
            emptyStateHeading: __('admin.categories.no_categories'),
            deleteDescription: __('admin.categories.delete_confirm'),
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCategories::route('/'),
        ];
    }
}
