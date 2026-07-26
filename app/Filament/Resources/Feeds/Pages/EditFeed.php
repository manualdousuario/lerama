<?php

namespace App\Filament\Resources\Feeds\Pages;

use App\Filament\Resources\Feeds\FeedResource;
use App\Models\Feed;
use App\Services\FeedSlugService;
use App\Services\ItemCountService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Cache;

class EditFeed extends EditRecord
{
    protected static string $resource = FeedResource::class;

    /** @var array<int> */
    private array $previousCategoryIds = [];

    /** @var array<int> */
    private array $previousTagIds = [];

    public function getTitle(): string
    {
        return __('admin.feed_form.edit_title');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalDescription(__('admin.feeds.delete_confirm')),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var Feed $feed */
        $feed = $this->record;

        // Only regenerate the slug when site_url actually changed: it is part
        // of the public /feeds/{slug} URL.
        if ($feed->site_url !== $data['site_url']) {
            $data['slug'] = FeedSlugService::generateForFeed($data['site_url'], $feed->id);
        }

        // An empty select means "keep whatever the detector found", not "null".
        if (empty($data['feed_type'])) {
            unset($data['feed_type']);
        }

        return $data;
    }

    protected function beforeSave(): void
    {
        /** @var Feed $feed */
        $feed = $this->record;

        $this->previousCategoryIds = $feed->categories()->pluck('categories.id')->map(intval(...))->all();
        $this->previousTagIds = $feed->tags()->pluck('tags.id')->map(intval(...))->all();
    }

    protected function afterSave(): void
    {
        /** @var Feed $feed */
        $feed = $this->record;

        // Both sides of the change need recounting: categories the feed left
        // and categories it joined.
        app(ItemCountService::class)->recountTaxonomy(
            array_merge($this->previousCategoryIds, $feed->categories()->pluck('categories.id')->map(intval(...))->all()),
            array_merge($this->previousTagIds, $feed->tags()->pluck('tags.id')->map(intval(...))->all()),
        );

        Cache::flush();
    }
}
