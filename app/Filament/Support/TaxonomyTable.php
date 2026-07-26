<?php

namespace App\Filament\Support;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TaxonomyTable
{
    public static function configure(
        Table $table,
        string $feedCountLabel,
        string $editHeading,
        string $emptyStateHeading,
        string $deleteDescription,
    ): Table {
        return $table
            ->defaultSort('name')
            ->emptyStateHeading($emptyStateHeading)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('feeds as feed_count'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('common.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('slug')
                    ->label(__('common.slug'))
                    ->searchable()
                    ->sortable()
                    ->color('gray'),

                TextColumn::make('feed_count')
                    ->label($feedCountLabel)
                    ->badge()
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('item_count')
                    ->label(__('feeds.items'))
                    ->badge()
                    ->sortable()
                    ->alignEnd(),
            ])
            ->recordActions([
                EditAction::make()->modalHeading($editHeading),
                DeleteAction::make()->modalDescription($deleteDescription),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->modalDescription($deleteDescription),
                ]),
            ]);
    }
}
