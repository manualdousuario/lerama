<x-layouts.app :title="$title" active="categories">
    <div class="card">
        <div class="border-b border-line p-4 dark:border-night-line">
            <h1 class="mt-1 mb-1 text-lg">
                <i class="ti ti-folder me-1" aria-hidden="true"></i>
                {{ __('categories.title') }}
            </h1>
        </div>

        @if (empty($categories))
            <div class="p-6 text-center">
                <p class="mb-0 text-ink-soft dark:text-night-soft">{{ __('categories.no_categories') }}</p>
            </div>
        @else
            <div>
                @foreach ($categories as $category)
                    <a href="/category/{{ $category['slug'] }}" class="flex items-center justify-between border-b border-line px-4 py-4 text-ink no-underline transition-colors last:border-b-0 hover:bg-paper-3 dark:border-night-line dark:text-night-ink dark:hover:bg-night-2">
                        <div>
                            <h5 class="m-0 flex items-center text-base">
                                <i class="ti ti-folder me-2" aria-hidden="true"></i>
                                <span>{{ $category['name'] }}</span>
                            </h5>
                            @if (! empty($category['description']))
                                <p class="mb-0 text-sm text-ink-soft dark:text-night-soft">{{ $category['description'] }}</p>
                            @endif
                        </div>
                        <span class="badge bg-stone-warm/15 text-stone-warm dark:bg-night-soft/15 dark:text-night-soft">
                            {{ $category['item_count'] }}
                            {{ $category['item_count'] == 1 ? __('categories.article') : __('categories.articles') }}
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.app>
