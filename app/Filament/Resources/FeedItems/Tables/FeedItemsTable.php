<?php

namespace App\Filament\Resources\FeedItems\Tables;

use App\Models\Feed;
use App\Models\FeedItem;
use App\Services\ThumbnailService;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class FeedItemsTable
{
    private const THUMBNAIL_SIZES = [[120, 60], [180, 100], [360, 200]];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->paginationPageOptions([20, 50, 100])
            ->defaultPaginationPageOption(20)
            ->emptyStateHeading(__('admin.items.no_items'))
            ->columns([
                ImageColumn::make('image_url')
                    ->label(__('admin.items.image'))
                    ->height(60)
                    ->width(120)
                    ->getStateUsing(function (FeedItem $record): ?string {
                        if (empty($record->image_url)) {
                            return null;
                        }

                        $thumbnail = app(ThumbnailService::class)->getThumbnailDeferred($record->image_url, 120, 60);

                        return str_starts_with($thumbnail, '/') ? url($thumbnail) : $thumbnail;
                    }),

                TextColumn::make('title')
                    ->label(__('suggest.form.title'))
                    ->weight('medium')
                    ->wrap()
                    ->url(fn (FeedItem $record): string => self::taggedUrl($record->url))
                    ->openUrlInNewTab()
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereRaw(
                        'MATCH(feed_items.title, feed_items.content) AGAINST (? IN BOOLEAN MODE)',
                        [$search]
                    )),

                TextColumn::make('feed.title')
                    ->label(__('admin.items.feed'))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('author')
                    ->label(__('admin.items.author'))
                    ->placeholder(__('admin.items.unknown_author'))
                    ->toggleable(),

                TextColumn::make('published_at')
                    ->label(__('admin.items.published'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder(__('feeds.never'))
                    ->sortable(),

                ToggleColumn::make('is_visible')
                    ->label(__('admin.items.show_item')),
            ])
            ->filters([
                SelectFilter::make('feed_id')
                    ->label(__('admin.items.feed'))
                    ->options(fn (): array => Cache::remember(
                        'admin:feeds:options',
                        300,
                        fn (): array => Feed::query()->orderBy('title')->pluck('title', 'id')->all()
                    ))
                    ->searchable(),
            ])
            ->recordActions([
                Action::make('refreshThumbnail')
                    ->label(__('admin.items.refresh_thumbnail'))
                    ->icon(Heroicon::OutlinedPhoto)
                    ->iconButton()
                    ->visible(fn (FeedItem $record): bool => ! empty($record->image_url))
                    ->action(function (FeedItem $record, ThumbnailService $thumbnails): void {
                        $disk = Storage::disk('public');

                        foreach (self::THUMBNAIL_SIZES as [$width, $height]) {
                            $relative = 'thumbnails/'.md5($record->image_url.$width.$height).'.jpg';

                            if ($disk->exists($relative)) {
                                $disk->delete($relative);
                            }

                            $thumbnails->getThumbnail($record->image_url, $width, $height);
                        }
                    })
                    ->successNotificationTitle(__('admin.items.refresh_thumbnail')),
            ]);
    }

    private static function taggedUrl(string $url): string
    {
        return $url.(parse_url($url, PHP_URL_QUERY) ? '&' : '?').'utm_source=lerama';
    }
}
