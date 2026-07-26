<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Feed;
use App\Models\FeedItem;
use App\Models\Tag;
use App\Observers\FeedItemObserver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        FeedItem::observe(FeedItemObserver::class);

        // Flush the whole cache on any content write: the `file` driver has no
        // tag support, and the feed processor warms it again after each run.
        $flush = fn () => Cache::flush();

        FeedItem::created($flush);
        FeedItem::updated($flush);
        FeedItem::deleted($flush);
        Feed::saved($flush);
        Feed::deleted($flush);
        Category::saved($flush);
        Category::deleted($flush);
        Tag::saved($flush);
        Tag::deleted($flush);
    }
}
