<?php

namespace App\Filament\Resources\Feeds\Tables;

use App\Enums\FeedStatus;
use App\Models\Category;
use App\Models\Feed;
use App\Models\Tag;
use App\Services\FeedTaxonomyService;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class FeedsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('title')
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->emptyStateHeading(__('admin.feeds.no_feeds'))
            ->columns([
                TextColumn::make('title')
                    ->label(__('feeds.feed'))
                    ->searchable(['title', 'feed_url', 'site_url'])
                    ->sortable()
                    ->weight('medium')
                    ->wrap()
                    ->description(fn (Feed $record): string => $record->feed_url)
                    ->url(fn (Feed $record): ?string => $record->slug ? url('/feeds/'.$record->slug) : null)
                    ->openUrlInNewTab(),

                TextColumn::make('item_count')
                    ->label(__('feeds.items'))
                    ->badge()
                    ->sortable()
                    ->alignEnd(),

                SelectColumn::make('status')
                    ->label(__('common.status'))
                    ->options(self::statusOptions())
                    ->selectablePlaceholder(false)
                    ->rules(['required', 'in:'.implode(',', FeedStatus::values())]),

                ToggleColumn::make('shuffle')
                    ->label(__('feeds.shuffle')),

                TextColumn::make('last_checked')
                    ->label(__('feeds.verification'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder(__('feeds.never'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.feeds.filter_status'))
                    ->placeholder(__('admin.feeds.all_status'))
                    ->options(self::statusOptions()),

                TernaryFilter::make('shuffle')
                    ->label(__('admin.feeds.filter_shuffle'))
                    ->placeholder(__('admin.feeds.all_shuffle'))
                    ->trueLabel(__('admin.feeds.shuffle_active'))
                    ->falseLabel(__('admin.feeds.shuffle_inactive')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription(__('admin.feeds.delete_confirm')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::bulkStatusAction(),
                    self::bulkCategoriesAction(),
                    self::bulkTagsAction(),
                    DeleteBulkAction::make()
                        ->modalDescription(__('admin.feeds.delete_confirm')),
                ]),
            ]);
    }

    /** @return array<string, string> */
    private static function statusOptions(): array
    {
        return collect(FeedStatus::cases())
            ->mapWithKeys(fn (FeedStatus $status): array => [$status->value => __('status.'.$status->value)])
            ->all();
    }

    /**
     * Bulk actions cannot bind a relationship (there is no single record), so
     * the checkbox lists read from a short-lived lookup instead.
     *
     * The keys are namespaced: `categories:all` and friends belong to the
     * public site, which caches whole model rows and breaks if handed this
     * id => name shape.
     *
     * @param  class-string<Model>  $model
     * @return array<int, string>
     */
    private static function taxonomyOptions(string $model, string $cacheKey): array
    {
        return Cache::remember(
            $cacheKey,
            300,
            fn (): array => $model::query()->orderBy('name')->pluck('name', 'id')->all()
        );
    }

    private static function bulkStatusAction(): BulkAction
    {
        return BulkAction::make('bulkStatus')
            ->label(__('admin.feeds.bulk_status'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->schema([
                Select::make('status')
                    ->label(__('common.status'))
                    ->options(self::statusOptions())
                    ->required()
                    ->selectablePlaceholder(false),
            ])
            ->action(function (Collection $records, array $data): void {
                Feed::query()->whereKey($records->modelKeys())->update(['status' => $data['status']]);

                // A mass update fires no model events, so flush by hand.
                Cache::flush();
            })
            ->deselectRecordsAfterCompletion();
    }

    private static function bulkCategoriesAction(): BulkAction
    {
        return BulkAction::make('bulkCategories')
            ->label(__('admin.feeds.bulk_categories'))
            ->modalHeading(__('admin.feeds.bulk_categories_modal_title'))
            ->modalDescription(__('admin.feeds.bulk_categories_description'))
            ->modalSubmitActionLabel(__('admin.feeds.apply_categories'))
            ->icon(Heroicon::OutlinedFolder)
            ->schema([
                CheckboxList::make('categories')
                    ->label(__('admin.feed_form.categories'))
                    ->helperText(__('admin.feeds.bulk_categories_note'))
                    ->options(fn (): array => self::taxonomyOptions(Category::class, 'admin:categories:options'))
                    ->searchable()
                    ->bulkToggleable()
                    ->required()
                    ->columns(2),
            ])
            ->action(function (Collection $records, array $data, FeedTaxonomyService $taxonomy): void {
                $taxonomy->replaceCategories(
                    array_map(intval(...), $records->modelKeys()),
                    array_map(intval(...), $data['categories'] ?? []),
                );
            })
            ->deselectRecordsAfterCompletion();
    }

    private static function bulkTagsAction(): BulkAction
    {
        return BulkAction::make('bulkTags')
            ->label(__('admin.feeds.bulk_tags'))
            ->modalHeading(__('admin.feeds.bulk_tags_modal_title'))
            ->modalDescription(__('admin.feeds.bulk_tags_description'))
            ->modalSubmitActionLabel(__('admin.feeds.apply_tags'))
            ->icon(Heroicon::OutlinedTag)
            ->schema([
                CheckboxList::make('tags')
                    ->label(__('admin.feed_form.tags'))
                    ->helperText(__('admin.feeds.bulk_tags_note'))
                    ->options(fn (): array => self::taxonomyOptions(Tag::class, 'admin:tags:options'))
                    ->searchable()
                    ->bulkToggleable()
                    ->required()
                    ->columns(2),
            ])
            ->action(function (Collection $records, array $data, FeedTaxonomyService $taxonomy): void {
                $taxonomy->replaceTags(
                    array_map(intval(...), $records->modelKeys()),
                    array_map(intval(...), $data['tags'] ?? []),
                );
            })
            ->deselectRecordsAfterCompletion();
    }
}
