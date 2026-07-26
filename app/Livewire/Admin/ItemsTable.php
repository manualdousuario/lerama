<?php

namespace App\Livewire\Admin;

use App\Models\Feed;
use App\Models\FeedItem;
use App\Services\ThumbnailService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithPagination;

class ItemsTable extends Component
{
    use WithPagination;

    public string $search = '';

    public string $feedFilter = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFeedFilter(): void
    {
        $this->resetPage();
    }

    public function toggleVisibility(int $id): void
    {
        $item = FeedItem::query()->find($id);
        if (! $item) {
            return;
        }

        $item->is_visible = ! $item->is_visible;
        $item->save();
    }

    public function refreshThumbnail(int $id, ThumbnailService $thumbnails): void
    {
        $item = FeedItem::query()->find($id);
        if (! $item || empty($item->image_url)) {
            return;
        }

        $disk = Storage::disk('public');

        // Regenerate every size used across the site.
        foreach ([[120, 60], [180, 100], [360, 200]] as [$w, $h]) {
            $relative = 'thumbnails/'.md5($item->image_url.$w.$h).'.jpg';
            if ($disk->exists($relative)) {
                $disk->delete($relative);
            }
            $thumbnails->getThumbnail($item->image_url, $w, $h);
        }
    }

    public function getFeedsProperty(): array
    {
        return Cache::remember('feeds:dropdown', 300, fn () => Feed::orderBy('title')->get(['id', 'title'])->toArray());
    }

    public function render()
    {
        $query = FeedItem::query()
            ->join('feeds as f', 'feed_items.feed_id', '=', 'f.id')
            ->select('feed_items.*', 'f.title as feed_title')
            ->orderByDesc('feed_items.published_at');

        if ($this->search !== '') {
            $query->whereRaw('MATCH(feed_items.title, feed_items.content) AGAINST (? IN BOOLEAN MODE)', [$this->search]);
        }

        if ($this->feedFilter !== '') {
            $query->where('feed_items.feed_id', (int) $this->feedFilter);
        }

        return view('livewire.admin.items-table', [
            'items' => $query->paginate(20),
        ]);
    }
}
