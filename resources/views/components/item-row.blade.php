@props(['item'])

@php
    $utm = fn (string $url) => $url.(parse_url($url, PHP_URL_QUERY) ? '&' : '?').'utm_source=lerama';
    $thumbnail = ! empty($item['image_url'])
        ? app(\App\Services\ThumbnailService::class)->getThumbnailDeferred($item['image_url'], 180, 100)
        : null;
@endphp

<li class="item-row px-3">
    <div class="block gap-3 md:flex">
        @if ($thumbnail)
            <div class="image-thumbnail item-thumb mb-2 h-[100px] w-[180px] md:mb-0">
                <img src="{{ $thumbnail }}" width="180" height="100" loading="lazy" decoding="async" alt="{{ $item['title'] }}">
            </div>
        @endif
        <div class="flex-1">
            <div class="pb-2 md:pb-0">
                <h4 class="m-0 text-lg">
                    <a href="{{ $utm($item['url']) }}" target="_blank" class="text-ink-strong no-underline hover:text-brand hover:underline hover:decoration-1 hover:underline-offset-4 dark:text-night-strong dark:hover:text-night-brand">
                        {{ $item['title'] }}
                    </a>
                </h4>
                <div class="block text-sm">
                    <span>{{ __('common.in') }}</span>
                    <a href="{{ $utm($item['site_url']) }}" target="_blank" class="truncate">
                        {{ $item['feed_title'] }}
                    </a>
                </div>
            </div>

            <p class="mb-0 flex items-center text-sm text-ink-soft dark:text-night-soft">
                @if (! empty($item['published_at']))
                    <i class="ti ti-calendar me-1" aria-hidden="true"></i>
                    {{ date('j/m/Y \à\s H:i', strtotime($item['published_at'])) }}
                @endif
                @if (! empty($item['author']))
                    <i class="ti ti-user ms-2 me-1" aria-hidden="true"></i>
                    {{ $item['author'] }}
                @endif
            </p>

            @if (! empty($item['excerpt']) && mb_strlen($item['excerpt']) >= 30)
                <div class="content item-content mt-2">
                    {{ mb_substr($item['excerpt'], 0, 300) }}...
                </div>
            @endif
        </div>
    </div>
</li>
