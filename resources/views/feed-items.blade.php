<x-layouts.app :title="$title" active="feeds">
    <div class="card">
        <div class="border-b border-line p-4 dark:border-night-line">
            <div class="items-center gap-4 md:flex">
                <div class="md:w-2/3">
                    <h1 class="m-0 py-1 text-lg">
                        <i class="ti ti-rss me-1" aria-hidden="true"></i>
                        {{ $feed['title'] }}
                    </h1>
                    <p class="mb-0 text-sm text-ink-soft dark:text-night-soft">
                        {{ __('feeds.items') }}: {{ $feed['item_count'] ?? 0 }}
                        <span class="mx-1">|</span>
                        <a href="{{ $feed['site_url'] }}" target="_blank" class="no-underline">
                            {{ $feed['site_url'] }}
                            <i class="ti ti-external-link ms-1" aria-hidden="true"></i>
                        </a>
                    </p>
                </div>
                <div class="mt-2 md:mt-0 md:w-1/3 md:text-end">
                    <a href="/feeds" class="btn-sm btn-outline">
                        <i class="ti ti-arrow-left" aria-hidden="true"></i>
                        {{ __('nav.feeds') }}
                    </a>
                </div>
            </div>
        </div>

        @if (empty($items))
            <div class="p-6 text-center">
                <p class="mb-0 text-ink-soft dark:text-night-soft">
                    <i class="ti ti-alert-circle me-1" aria-hidden="true"></i>
                    {{ __('home.no_items') }}
                </p>
            </div>
        @else
            <ul id="list-view" class="list-none p-0" role="list">
                @foreach ($items as $item)
                    <x-item-row :item="$item" />
                @endforeach
            </ul>

            @if ($pagination['total'] > 1)
                <div class="border-t border-line p-3 dark:border-night-line">
                    <x-pagination :base-url="$pagination['baseUrl']" :current="$pagination['current']" :total="$pagination['total']" />
                </div>
            @endif
        @endif
    </div>
</x-layouts.app>
