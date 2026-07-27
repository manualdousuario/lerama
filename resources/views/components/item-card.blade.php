@props(['item'])

@php
    $utm = fn (string $url) => $url.(parse_url($url, PHP_URL_QUERY) ? '&' : '?').'utm_source=lerama';
    $thumbnail = ! empty($item['image_url'])
        ? app(\App\Services\ThumbnailService::class)->getThumbnailDeferred($item['image_url'], 360, 200)
        : null;
@endphp

<div class="card-hover flex h-full flex-col">
    @if ($thumbnail)
        <div class="image-thumbnail item-thumb-zoom h-40 w-full shrink-0 overflow-hidden rounded-t-lg">
            <img src="{{ $thumbnail }}" class="h-full w-full object-cover" loading="lazy" decoding="async" alt="{{ $item['title'] }}">
        </div>
    @endif
    <div class="flex-1 p-4">
        <h5 class="card-title text-base">
            <a href="{{ $utm($item['url']) }}" target="_blank" class="text-brand no-underline hover:underline hover:decoration-1 hover:underline-offset-4 dark:text-night-brand">
                {{ $item['title'] }}
            </a>
        </h5>
        @if (! empty($item['excerpt']) && mb_strlen($item['excerpt']) >= 30)
            <p class="content item-content mt-2 mb-0">
                {{ mb_substr($item['excerpt'], 0, 150) }}...
            </p>
        @endif
    </div>
    <div class="shrink-0 border-t border-line px-4 py-3 text-sm dark:border-night-line">
        <div class="mb-1">
            <a href="{{ $utm($item['site_url']) }}" target="_blank" class="block truncate">
                {{ $item['feed_title'] }}
            </a>
        </div>
        <div class="flex items-center text-ink-soft dark:text-night-soft">
            @if (! empty($item['published_at']))
                <i class="ti ti-calendar me-1" aria-hidden="true"></i>
                <span>{{ date('j/m/Y', strtotime($item['published_at'])) }}</span>
            @endif
            @if (! empty($item['author']))
                <i class="ti ti-user ms-2 me-1" aria-hidden="true"></i>
                <span class="max-w-[100px] truncate">{{ $item['author'] }}</span>
            @endif
        </div>
    </div>
</div>
