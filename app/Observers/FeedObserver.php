<?php

namespace App\Observers;

use App\Models\Feed;
use App\Services\ItemCountService;

class FeedObserver
{
    private static array $snapshots = [];

    public function __construct(private ItemCountService $counts) {}

    public function deleting(Feed $feed): void
    {
        self::$snapshots[$feed->getKey()] = [
            'categories' => $feed->categories()->pluck('categories.id')->map(intval(...))->all(),
            'tags' => $feed->tags()->pluck('tags.id')->map(intval(...))->all(),
        ];
    }

    public function deleted(Feed $feed): void
    {
        $snapshot = self::$snapshots[$feed->getKey()] ?? ['categories' => [], 'tags' => []];
        unset(self::$snapshots[$feed->getKey()]);

        $this->counts->recountTaxonomy($snapshot['categories'], $snapshot['tags']);
    }
}
