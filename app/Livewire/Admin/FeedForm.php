<?php

namespace App\Livewire\Admin;

use App\Enums\FeedStatus;
use App\Enums\FeedType;
use App\Mail\FeedRegisteredMail;
use App\Models\Category;
use App\Models\Feed;
use App\Models\Tag;
use App\Services\FeedSlugService;
use App\Services\FeedTypeDetector;
use App\Services\ItemCountService;
use App\Support\UrlValidator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class FeedForm extends Component
{
    public ?int $feedId = null;

    public string $title = '';

    public string $feed_url = '';

    public string $site_url = '';

    public string $language = 'pt-BR';

    public ?string $feed_type = null;

    public string $status = 'online';

    public bool $proxy_only = false;

    public bool $shuffle = true;

    /** @var array<int> */
    public array $selectedCategories = [];

    /** @var array<int> */
    public array $selectedTags = [];

    public function mount(?int $feedId = null): void
    {
        if ($feedId) {
            $feed = Feed::query()->findOrFail($feedId);

            $this->feedId = $feed->id;
            $this->title = $feed->title;
            $this->feed_url = $feed->feed_url;
            $this->site_url = $feed->site_url;
            $this->language = $feed->language ?? 'pt-BR';
            $this->feed_type = $feed->feed_type?->value;
            $this->status = $feed->status?->value ?? 'online';
            $this->proxy_only = (bool) $feed->proxy_only;
            $this->shuffle = (bool) $feed->shuffle;
            $this->selectedCategories = $feed->categories()->pluck('categories.id')->map(fn ($id) => (int) $id)->all();
            $this->selectedTags = $feed->tags()->pluck('tags.id')->map(fn ($id) => (int) $id)->all();
        }
    }

    public function save(FeedTypeDetector $detector, ItemCountService $counts)
    {
        $messages = [
            'title.required' => __('validation.title_required'),
            'feed_url.required' => __('validation.feed_url_required'),
            'feed_url.url' => __('validation.feed_url_valid'),
            'site_url.required' => __('validation.site_url_required'),
            'site_url.url' => __('validation.site_url_valid'),
            'language.in' => __('validation.language_invalid'),
            'status.in' => __('validation.status_invalid'),
        ];

        $this->validate([
            'title' => ['required', 'string'],
            'feed_url' => ['required', 'url'],
            'site_url' => ['required', 'url'],
            'language' => ['required', 'in:en,pt-BR,es'],
            'feed_type' => ['nullable', 'in:'.implode(',', FeedType::values())],
            'status' => ['required', 'in:'.implode(',', FeedStatus::values())],
            'proxy_only' => ['boolean'],
            'shuffle' => ['boolean'],
            'selectedCategories' => ['array'],
            'selectedTags' => ['array'],
        ], $messages);

        foreach (['feed_url' => $this->feed_url, 'site_url' => $this->site_url] as $field => $url) {
            if (! UrlValidator::validate($url)['valid']) {
                $this->addError($field, __('validation.'.($field === 'feed_url' ? 'feed_url' : 'site_url').'_valid'));

                return;
            }
        }

        if ($this->feedId) {
            $this->updateFeed($detector, $counts);
        } else {
            $this->createFeed($detector, $counts);
        }

        return $this->redirect('/admin/feeds');
    }

    private function createFeed(FeedTypeDetector $detector, ItemCountService $counts): void
    {
        $feedType = $this->feed_type ?: ($detector->detectType($this->feed_url) ?? FeedType::Rss2->value);

        $feed = Feed::create([
            'title' => $this->title,
            'feed_url' => $this->feed_url,
            'site_url' => $this->site_url,
            'slug' => FeedSlugService::generateForFeed($this->site_url),
            'feed_type' => $feedType,
            'language' => $this->language,
            'status' => FeedStatus::Online,
            'proxy_only' => $this->proxy_only,
            'shuffle' => $this->shuffle,
        ]);

        $this->syncTaxonomy($feed, $counts);
        $this->notifyAdmin($feed);
    }

    private function updateFeed(FeedTypeDetector $detector, ItemCountService $counts): void
    {
        $feed = Feed::query()->findOrFail($this->feedId);

        $data = [
            'title' => $this->title,
            'feed_url' => $this->feed_url,
            'site_url' => $this->site_url,
            'language' => $this->language,
            'status' => $this->status,
            'proxy_only' => $this->proxy_only,
            'shuffle' => $this->shuffle,
        ];

        // Only regenerate the slug when site_url actually changed.
        if ($feed->site_url !== $this->site_url) {
            $data['slug'] = FeedSlugService::generateForFeed($this->site_url, $feed->id);
        }

        // feed_type is only updated when provided.
        if (! empty($this->feed_type)) {
            $data['feed_type'] = $this->feed_type;
        }

        $feed->update($data);

        $this->syncTaxonomy($feed, $counts);
    }

    private function syncTaxonomy(Feed $feed, ItemCountService $counts): void
    {
        $oldCategories = $feed->categories()->pluck('categories.id')->all();
        $oldTags = $feed->tags()->pluck('tags.id')->all();

        $feed->categories()->sync(array_map('intval', $this->selectedCategories));
        $feed->tags()->sync(array_map('intval', $this->selectedTags));

        $counts->recountTaxonomy(
            array_merge($oldCategories, array_map('intval', $this->selectedCategories)),
            array_merge($oldTags, array_map('intval', $this->selectedTags))
        );

        Cache::flush();
    }

    private function notifyAdmin(Feed $feed): void
    {
        $adminEmail = (string) config('lerama.admin.email', '');
        $smtpHost = (string) config('mail.mailers.smtp.host', '');

        if ($adminEmail === '' || $smtpHost === '') {
            return;
        }

        try {
            Mail::to($adminEmail)->send(new FeedRegisteredMail($feed));
        } catch (\Throwable $e) {
            Log::error('Failed to send feed registration notification: '.$e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.admin.feed-form', [
            'allCategories' => Cache::remember('categories:all', 300, fn () => Category::orderBy('name')->get(['id', 'name'])->toArray()),
            'allTags' => Cache::remember('tags:all', 300, fn () => Tag::orderBy('name')->get(['id', 'name'])->toArray()),
            'feedTypes' => FeedType::cases(),
            'statuses' => FeedStatus::cases(),
        ]);
    }
}
