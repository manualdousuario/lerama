<?php

namespace App\Livewire\Admin;

use App\Enums\FeedStatus;
use App\Models\Category;
use App\Models\Feed;
use App\Models\Tag;
use App\Services\ItemCountService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class FeedsTable extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $shuffleFilter = '';

    /** @var array<int> */
    public array $selected = [];

    public bool $selectAll = false;

    public bool $showBulkCategoriesModal = false;

    public bool $showBulkTagsModal = false;

    /** @var array<int> */
    public array $bulkCategoryIds = [];

    /** @var array<int> */
    public array $bulkTagIds = [];

    public string $bulkStatus = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingShuffleFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSelectAll(bool $value): void
    {
        $this->selected = $value
            ? $this->query()->forPage($this->getPage(), 50)->pluck('id')->map(fn ($id) => (int) $id)->all()
            : [];
    }

    public function toggleShuffle(int $id): void
    {
        $feed = Feed::query()->find($id);
        if (! $feed) {
            return;
        }

        $feed->shuffle = ! $feed->shuffle;
        $feed->save();
    }

    public function updateStatus(int $id, string $status): void
    {
        if (! in_array($status, FeedStatus::values(), true)) {
            return;
        }

        Feed::query()->whereKey($id)->update(['status' => $status]);

        Cache::flush();
    }

    public function deleteFeed(int $id, ItemCountService $counts): void
    {
        $feed = Feed::query()->find($id);
        if (! $feed) {
            return;
        }

        $categoryIds = $feed->categories()->pluck('categories.id')->all();
        $tagIds = $feed->tags()->pluck('tags.id')->all();

        $feed->delete(); // FK cascades remove items and pivots

        // Cascades don't fire events, so recount the affected taxonomy.
        $counts->recountTaxonomy($categoryIds, $tagIds);

        $this->selected = array_values(array_diff($this->selected, [$id]));
    }

    public function applyBulkStatus(ItemCountService $counts): void
    {
        if (empty($this->selected) || ! in_array($this->bulkStatus, FeedStatus::values(), true)) {
            return;
        }

        Feed::query()->whereIn('id', $this->selected)->update(['status' => $this->bulkStatus]);

        Cache::flush();

        $this->resetSelection();
    }

    public function applyBulkCategories(ItemCountService $counts): void
    {
        if (empty($this->selected) || empty($this->bulkCategoryIds)) {
            return;
        }

        $this->replaceTaxonomy('feed_categories', 'category_id', $this->selected, $this->bulkCategoryIds);

        $affected = DB::table('categories')->pluck('id')->all();
        $counts->recountTaxonomy($affected, []);

        Cache::flush();

        $this->showBulkCategoriesModal = false;
        $this->bulkCategoryIds = [];
        $this->resetSelection();
    }

    public function applyBulkTags(ItemCountService $counts): void
    {
        if (empty($this->selected) || empty($this->bulkTagIds)) {
            return;
        }

        $this->replaceTaxonomy('feed_tags', 'tag_id', $this->selected, $this->bulkTagIds);

        $affected = DB::table('tags')->pluck('id')->all();
        $counts->recountTaxonomy([], $affected);

        Cache::flush();

        $this->showBulkTagsModal = false;
        $this->bulkTagIds = [];
        $this->resetSelection();
    }

    /**
     * Delete-all + re-insert, matching the legacy bulk strategy.
     *
     * @param  array<int>  $feedIds
     * @param  array<int>  $taxonomyIds
     */
    private function replaceTaxonomy(string $table, string $taxonomyColumn, array $feedIds, array $taxonomyIds): void
    {
        DB::table($table)->whereIn('feed_id', $feedIds)->delete();

        $rows = [];
        foreach ($feedIds as $feedId) {
            foreach ($taxonomyIds as $taxonomyId) {
                $rows[] = ['feed_id' => $feedId, $taxonomyColumn => $taxonomyId];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($table)->insertOrIgnore($chunk);
        }
    }

    public function resetSelection(): void
    {
        $this->selected = [];
        $this->selectAll = false;
    }

    public function getAllCategoriesProperty(): array
    {
        return Cache::remember('categories:all', 300, fn () => Category::orderBy('name')->get(['id', 'name'])->toArray());
    }

    public function getAllTagsProperty(): array
    {
        return Cache::remember('tags:all', 300, fn () => Tag::orderBy('name')->get(['id', 'name'])->toArray());
    }

    private function query(): Builder
    {
        return Feed::query()
            ->when($this->search !== '', function ($q): void {
                $q->where(function ($sub): void {
                    $sub->where('title', 'like', '%'.$this->search.'%')
                        ->orWhere('feed_url', 'like', '%'.$this->search.'%')
                        ->orWhere('site_url', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->shuffleFilter !== '', fn ($q) => $q->where('shuffle', $this->shuffleFilter === '1'))
            ->orderBy('title');
    }

    public function render()
    {
        return view('livewire.admin.feeds-table', [
            'feeds' => $this->query()->paginate(50),
            'statuses' => FeedStatus::cases(),
        ]);
    }
}
