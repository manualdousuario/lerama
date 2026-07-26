<?php

use App\Enums\FeedStatus;
use App\Mail\FeedRegisteredMail;
use App\Models\Category;
use App\Models\Feed;
use App\Models\Tag;
use App\Services\FeedSlugService;
use App\Services\FeedTypeDetector;
use App\Support\UrlValidator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $title = '';

    public string $feed_url = '';

    public string $site_url = '';

    public string $language = 'pt-BR';

    public ?int $category = null;

    // String for a single select, array when multiple.
    public $selectedTags = [];

    public ?string $successMessage = null;

    public function mount(): void
    {
        $categories = $this->categories;
        if (! empty($categories)) {
            $this->category = $categories[0]['id'];
        }
    }

    #[Computed]
    public function categories(): array
    {
        return Cache::remember('categories:all', 300, fn () => Category::orderBy('name')->get(['id', 'name'])->toArray());
    }

    #[Computed]
    public function tags(): array
    {
        return Cache::remember('tags:all', 300, fn () => Tag::orderBy('name')->get(['id', 'name'])->toArray());
    }

    public function submit(FeedTypeDetector $detector): void
    {
        $this->successMessage = null;

        $key = 'suggest-feed:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('general', __('validation.too_many_attempts'));

            return;
        }
        RateLimiter::hit($key, 60);

        $messages = [
            'title.required' => __('validation.title_required'),
            'title.min' => __('validation.title_min_length'),
            'feed_url.required' => __('validation.feed_url_required'),
            'feed_url.url' => __('validation.feed_url_valid'),
            'feed_url.different' => __('suggest.form.feed_url_same_as_site'),
            'site_url.required' => __('validation.site_url_required'),
            'site_url.url' => __('validation.site_url_valid'),
            'language.in' => __('validation.language_invalid'),
            'category.required' => __('validation.category_required'),
            'selectedTags.required' => __('validation.tag_required'),
        ];

        $this->validate([
            'title' => ['required', 'min:3'],
            'feed_url' => ['required', 'url', 'different:site_url'],
            'site_url' => ['required', 'url'],
            'language' => ['required', 'in:en,pt-BR,es'],
            'category' => empty($this->categories) ? ['nullable'] : ['required', 'integer', 'exists:categories,id'],
            'selectedTags' => empty($this->tags) ? ['nullable'] : ['required'],
        ], $messages);

        // Anti-SSRF
        if (! UrlValidator::validate($this->feed_url)['valid'] || ! UrlValidator::validate($this->site_url)['valid']) {
            $this->addError('feed_url', __('validation.feed_url_valid'));

            return;
        }

        // Detect the feed type, which fetches the URL.
        try {
            $feedType = $detector->detectType($this->feed_url);
        } catch (\Throwable $e) {
            $this->addError('feed_url', __('error.feed_validate').': '.$e->getMessage());

            return;
        }

        if ($feedType === null) {
            $this->addError('feed_url', __('error.feed_invalid'));

            return;
        }

        // Reject duplicates.
        $existing = Feed::query()->where('feed_url', $this->feed_url)->first(['id', 'status']);
        if ($existing) {
            $this->addError(
                'feed_url',
                $existing->status === FeedStatus::Pending
                    ? __('feed.already_pending')
                    : __('feed.already_registered')
            );

            return;
        }

        try {
            $feed = Feed::create([
                'title' => $this->title,
                'feed_url' => $this->feed_url,
                'site_url' => $this->site_url,
                'slug' => FeedSlugService::generateForFeed($this->site_url),
                'language' => $this->language,
                'feed_type' => $feedType,
                'status' => FeedStatus::Pending,
            ]);

            if ($this->category) {
                $feed->categories()->syncWithoutDetaching([$this->category]);
            }

            $selectedTagIds = array_map('intval', array_filter((array) $this->selectedTags));
            if (! empty($selectedTagIds)) {
                $feed->tags()->syncWithoutDetaching($selectedTagIds);
            }

            $this->notifyAdmin($feed);

            $this->reset(['title', 'feed_url', 'site_url', 'selectedTags']);
            $this->language = 'pt-BR';
            $this->successMessage = __('success.suggestion_sent');
        } catch (\Throwable $e) {
            report($e);
            $this->addError('general', __('error.suggestion_send').': '.$e->getMessage());
        }
    }

    private function notifyAdmin(Feed $feed): void
    {
        $adminEmail = (string) config('lerama.admin.email', '');
        $smtpHost = (string) config('mail.mailers.smtp.host', '');

        if ($adminEmail === '' || $smtpHost === '') {
            Log::info("New feed registered (no e-mail sent, SMTP or ADMIN_EMAIL missing): {$feed->title}");

            return;
        }

        try {
            Mail::to($adminEmail)->send(new FeedRegisteredMail($feed));
        } catch (\Throwable $e) {
            Log::error('Failed to send feed registration notification: '.$e->getMessage());
        }
    }
};
?>

<div class="card">
    <div class="border-b border-line p-4 dark:border-night-line">
        <h1 class="mt-1 mb-1 text-lg">
            <i class="ti ti-speakerphone me-1" aria-hidden="true"></i>
            {{ __('suggest.heading') }}
        </h1>
    </div>

    <div class="p-4">
        @if ($successMessage)
            <div class="mb-4 flex items-center rounded-md border border-moss/30 bg-moss/10 p-3 text-moss dark:border-night-moss/30 dark:bg-night-moss/10 dark:text-night-moss" role="status" aria-live="polite">
                <i class="ti ti-circle-check-filled me-2" aria-hidden="true"></i>
                <div>{{ $successMessage }}</div>
            </div>
        @endif

        @error('general')
            <div class="mb-4 flex items-center rounded-md border border-clay/30 bg-clay/10 p-3 text-clay dark:border-night-clay/30 dark:bg-night-clay/10 dark:text-night-clay" role="alert" aria-live="assertive">
                <i class="ti ti-alert-circle-filled me-2" aria-hidden="true"></i>
                <div>{{ $message }}</div>
            </div>
        @enderror

        <p class="mb-4 text-ink-soft dark:text-night-soft">
            {{ __('suggest.description') }}
        </p>

        <form wire:submit="submit">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label for="title" class="label">{{ __('suggest.form.title') }}</label>
                    <input type="text" wire:model="title" id="title" required
                        class="input @error('title') input-error @enderror"
                        @error('title') aria-invalid="true" @enderror
                        placeholder="{{ __('suggest.form.title_placeholder') }}">
                    @error('title') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label for="site_url" class="label">{{ __('suggest.form.site_url') }}</label>
                    <input type="url" wire:model="site_url" id="site_url" required
                        class="input @error('site_url') input-error @enderror"
                        @error('site_url') aria-invalid="true" @enderror
                        placeholder="https://exemplo.com">
                    <div class="form-help">{{ __('suggest.form.site_url_help') }}</div>
                    @error('site_url') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label for="feed_url" class="label">{{ __('suggest.form.feed_url') }}</label>
                    <input type="url" wire:model="feed_url" id="feed_url" required
                        class="input @error('feed_url') input-error @enderror"
                        @error('feed_url') aria-invalid="true" @enderror
                        placeholder="https://exemplo.com/feed.xml">
                    <div class="form-help">{{ __('suggest.form.feed_url_help') }}</div>
                    @error('feed_url') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label for="language" class="label">{{ __('common.language') }}</label>
                    <select wire:model="language" id="language" class="select" required>
                        <option value="pt-BR">{{ __('lang.pt-BR') }}</option>
                        <option value="en">{{ __('lang.en') }}</option>
                        <option value="es">{{ __('lang.es') }}</option>
                    </select>
                    @error('language') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                @if (! empty($this->categories))
                    <div>
                        <label for="category" class="label">{{ __('suggest.form.category') }}</label>
                        <select wire:model="category" id="category" class="select" required>
                            @foreach ($this->categories as $cat)
                                <option value="{{ $cat['id'] }}">{{ $cat['name'] }}</option>
                            @endforeach
                        </select>
                        @error('category') <div class="form-error">{{ $message }}</div> @enderror
                    </div>
                @endif

                @if (! empty($this->tags))
                    <div>
                        <label for="selectedTags" class="label">{{ __('suggest.form.tags') }}</label>
                        <select wire:model="selectedTags" id="selectedTags" class="select" required>
                            <option value="">{{ __('suggest.form.select_tag') }}</option>
                            @foreach ($this->tags as $tag)
                                <option value="{{ $tag['id'] }}">{{ $tag['name'] }}</option>
                            @endforeach
                        </select>
                        @error('selectedTags') <div class="form-error">{{ $message }}</div> @enderror
                    </div>
                @endif
            </div>

            <div class="mt-4 flex justify-end gap-2">
                <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="submit">
                    <span wire:loading.remove wire:target="submit" class="inline-flex items-center gap-1.5">
                        <i class="ti ti-send" aria-hidden="true"></i> {{ __('suggest.form.submit') }}
                    </span>
                    <span wire:loading wire:target="submit" class="inline-flex items-center gap-1.5">
                        <i class="ti ti-loader-2 animate-spin" aria-hidden="true"></i> {{ __('suggest.form.validating') }}
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>
