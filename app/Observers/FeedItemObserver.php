<?php

namespace App\Observers;

use App\Models\FeedItem;
use App\Services\ItemCountService;

/**
 * Replaces the item_count MySQL triggers. Feed processor bulk operations do
 * NOT go through here; they call ItemCountService::bulkItemsInserted().
 */
class FeedItemObserver
{
    public function __construct(private readonly ItemCountService $counts) {}

    public function created(FeedItem $item): void
    {
        $this->counts->itemCreated($item);
    }

    public function deleted(FeedItem $item): void
    {
        $this->counts->itemDeleted($item);
    }

    public function updated(FeedItem $item): void
    {
        if ($item->wasChanged('is_visible')) {
            $this->counts->itemVisibilityChanged($item);
        }
    }
}
