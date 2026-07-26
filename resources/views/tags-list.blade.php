<x-layouts.app :title="$title" active="tags">
    <div class="card">
        <div class="border-b border-line p-4 dark:border-night-line">
            <h1 class="mt-1 mb-1 text-lg">
                <i class="ti ti-tags me-1" aria-hidden="true"></i>
                {{ __('tags.title') }}
            </h1>
        </div>

        @if (empty($tags))
            <div class="p-6 text-center">
                <p class="mb-0 text-ink-soft dark:text-night-soft">{{ __('tags.no_tags') }}</p>
            </div>
        @else
            <div>
                @foreach ($tags as $tag)
                    <a href="/tag/{{ $tag['slug'] }}" class="flex items-center justify-between border-b border-line px-4 py-4 text-ink no-underline transition-colors last:border-b-0 hover:bg-paper-3 dark:border-night-line dark:text-night-ink dark:hover:bg-night-2">
                        <div>
                            <h5 class="m-0 flex items-center text-base">
                                <i class="ti ti-tag me-2" aria-hidden="true"></i>
                                <span>{{ $tag['name'] }}</span>
                            </h5>
                            @if (! empty($tag['description']))
                                <p class="mb-0 text-sm text-ink-soft dark:text-night-soft">{{ $tag['description'] }}</p>
                            @endif
                        </div>
                        <span class="badge bg-stone-warm/15 text-stone-warm dark:bg-night-soft/15 dark:text-night-soft">
                            {{ $tag['item_count'] }}
                            {{ $tag['item_count'] == 1 ? __('tags.article') : __('tags.articles') }}
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.app>
