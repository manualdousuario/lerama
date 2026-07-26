<?php

use App\Models\Category;
use App\Models\Tag;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public array $selectedCategories = [];

    public array $selectedTags = [];

    #[Computed]
    public function categories(): array
    {
        return Cache::remember('categories:all', 300, fn () => Category::orderBy('name')->get(['id', 'name', 'slug'])->toArray());
    }

    #[Computed]
    public function tags(): array
    {
        return Cache::remember('tags:all', 300, fn () => Tag::orderBy('name')->get(['id', 'name', 'slug'])->toArray());
    }

    #[Computed]
    public function rssUrl(): string
    {
        return $this->buildUrl('/feed/rss');
    }

    #[Computed]
    public function jsonUrl(): string
    {
        return $this->buildUrl('/feed/json');
    }

    private function buildUrl(string $path): string
    {
        $params = [];

        $categories = $this->selectedSlugs($this->categories, $this->selectedCategories);
        $tags = $this->selectedSlugs($this->tags, $this->selectedTags);

        if ($categories !== '') {
            $params[] = 'categories='.$categories;
        }
        if ($tags !== '') {
            $params[] = 'tags='.$tags;
        }

        return rtrim((string) config('app.url'), '/').$path.($params ? '?'.implode('&', $params) : '');
    }

    private function selectedSlugs(array $items, array $selectedIds): string
    {
        $slugs = [];
        foreach ($items as $item) {
            if (in_array($item['id'], $selectedIds, false)) {
                $slugs[] = $item['slug'];
            }
        }

        return implode(',', $slugs);
    }
};
?>

<div class="card">
    <div class="border-b border-line p-4 dark:border-night-line">
        <h1 class="mt-1 mb-1 text-lg">
            <i class="ti ti-braces me-1" aria-hidden="true"></i>
            {{ __('feed_builder.title') }}
        </h1>
    </div>

    <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2">
        <div>
            <h2 class="mb-2 text-base">
                <i class="ti ti-folder me-1" aria-hidden="true"></i>
                {{ __('feed_builder.categories') }}
            </h2>
            @if (empty($this->categories))
                <p class="text-sm text-ink-soft dark:text-night-soft">-</p>
            @else
                <div class="flex flex-col gap-1">
                    @foreach ($this->categories as $category)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model.live="selectedCategories" value="{{ $category['id'] }}" class="category-checkbox accent-brand">
                            {{ $category['name'] }}
                        </label>
                    @endforeach
                </div>
            @endif
        </div>

        <div>
            <h2 class="mb-2 text-base">
                <i class="ti ti-tags me-1" aria-hidden="true"></i>
                {{ __('feed_builder.topics') }}
            </h2>
            @if (empty($this->tags))
                <p class="text-sm text-ink-soft dark:text-night-soft">-</p>
            @else
                <div class="flex flex-col gap-1">
                    @foreach ($this->tags as $tag)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model.live="selectedTags" value="{{ $tag['id'] }}" class="tag-checkbox accent-brand">
                            {{ $tag['name'] }}
                        </label>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="border-t border-line p-4 dark:border-night-line">
        <div class="mb-3">
            <label for="rssUrl" class="label">
                <i class="ti ti-rss me-1" aria-hidden="true"></i> {{ __('feed_builder.rss_feed') }}
            </label>
            <div class="flex gap-2">
                <input type="text" id="rssUrl" class="input flex-1 font-mono text-xs" readonly value="{{ $this->rssUrl }}">
                <a href="{{ $this->rssUrl }}" target="_blank" id="rssLink" class="btn-outline shrink-0" title="{{ __('shuffle.open') }}">
                    <i class="ti ti-external-link" aria-hidden="true"></i>
                </a>
                <button type="button" class="btn-primary shrink-0" onclick="navigator.clipboard.writeText(document.getElementById('rssUrl').value).then(() => { this.innerHTML = '<i class=\'ti ti-check\'></i>'; setTimeout(() => this.innerHTML = '<i class=\'ti ti-clipboard\'></i>', 2000); })">
                    <i class="ti ti-clipboard" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <div>
            <label for="jsonUrl" class="label">
                <i class="ti ti-json me-1" aria-hidden="true"></i> {{ __('feed_builder.json_feed') }}
            </label>
            <div class="flex gap-2">
                <input type="text" id="jsonUrl" class="input flex-1 font-mono text-xs" readonly value="{{ $this->jsonUrl }}">
                <a href="{{ $this->jsonUrl }}" target="_blank" id="jsonLink" class="btn-outline shrink-0" title="{{ __('shuffle.open') }}">
                    <i class="ti ti-external-link" aria-hidden="true"></i>
                </a>
                <button type="button" class="btn-primary shrink-0" onclick="navigator.clipboard.writeText(document.getElementById('jsonUrl').value).then(() => { this.innerHTML = '<i class=\'ti ti-check\'></i>'; setTimeout(() => this.innerHTML = '<i class=\'ti ti-clipboard\'></i>', 2000); })">
                    <i class="ti ti-clipboard" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </div>
</div>
