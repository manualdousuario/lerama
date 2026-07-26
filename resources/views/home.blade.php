<x-layouts.app :title="$title" active="home">
    <div class="card">
        <div class="border-b border-line p-4 dark:border-night-line">
            <h1 class="m-0 py-1 text-lg">
                <i class="ti ti-layout-grid me-1" aria-hidden="true"></i>
                {{ __('home.title') }}
            </h1>
            <div class="mt-2 border-t border-line pt-3 dark:border-night-line">
                <form action="/" method="GET">
                    <div class="items-center justify-between md:flex">
                        <div class="mb-2 me-md-2 flex items-center md:mb-0">
                            <div class="me-2">
                                <select id="view-mode" class="select w-auto text-sm">
                                    <option value="cards">{{ __('common.view_cards') }}</option>
                                    <option value="list">{{ __('common.view_list') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="md:flex">
                            <div class="mb-3 md:mb-0 md:me-2 md:flex">
                                <select name="category" id="category-select" class="select mb-2 md:me-2 md:mb-0">
                                    <option value="">{{ __('common.all_categories') }}</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category['slug'] }}" @selected(($selectedCategory ?? '') === $category['slug'])>
                                            {{ $category['name'] }}
                                        </option>
                                    @endforeach
                                </select>

                                <select name="tag" id="tag-select" class="select mb-2 md:me-2 md:mb-0">
                                    <option value="">{{ __('common.all_topics') }}</option>
                                    @foreach ($tags as $tag)
                                        <option value="{{ $tag['slug'] }}" @selected(($selectedTag ?? '') === $tag['slug'])>
                                            {{ $tag['name'] }}
                                        </option>
                                    @endforeach
                                </select>

                                <div class="flex">
                                    <button type="button" id="save-filter-btn" class="btn-outline flex-1 whitespace-nowrap" title="{{ __('common.save_filter') }}">
                                        <i class="ti ti-bookmark" aria-hidden="true"></i>
                                        {{ __('common.save_filter') }}
                                    </button>
                                    <button type="button" id="clear-filter-btn" class="btn-outline-danger ms-1 flex-1 whitespace-nowrap" title="{{ __('common.clear_filters') }}">
                                        <i class="ti ti-filter-off" aria-hidden="true"></i>
                                        {{ __('common.clear_filters') }}
                                    </button>
                                </div>
                            </div>
                            <div>
                                <div class="flex">
                                    <input type="text" name="search" value="{{ $search }}" class="input rounded-e-none" placeholder="{{ __('common.search_placeholder') }}">
                                    <button type="submit" class="btn-primary rounded-s-none whitespace-nowrap">
                                        <i class="ti ti-search" aria-hidden="true"></i>
                                        {{ __('common.search') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-2 justify-start md:flex">
                        <div class="mb-2 me-4 md:mb-0">
                            <input type="checkbox" id="simplified-view" class="accent-brand" />
                            <label for="simplified-view">{{ __('common.simplified') }}</label>
                        </div>
                        <div class="mb-2 md:mb-0">
                            <input type="checkbox" name="latest" value="1" id="latest-per-feed" class="accent-brand" @checked(! empty($latestPerFeed)) />
                            <label for="latest-per-feed">{{ __('common.latest_per_feed') }}</label>
                        </div>
                    </div>
                </form>
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
            {{-- List View --}}
            <ul id="list-view" class="list-none p-0" role="list">
                @foreach ($items as $item)
                    <x-item-row :item="$item" />
                @endforeach
            </ul>

            {{-- Cards View --}}
            <div id="cards-view" class="hidden grid grid-cols-1 gap-3 p-3 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($items as $item)
                    <x-item-card :item="$item" />
                @endforeach
            </div>

            @if ($pagination['total'] > 1)
                <div class="border-t border-line p-3 dark:border-night-line">
                    @php
                        $queryParams = array_filter([
                            'search' => $search,
                            'feed' => $selectedFeed,
                            'category' => empty($categoryInPath) ? ($selectedCategory ?? null) : null,
                            'tag' => empty($tagInPath) ? ($selectedTag ?? null) : null,
                            'latest' => ! empty($latestPerFeed) ? '1' : null,
                        ]);
                        $queryString = $queryParams ? '?'.http_build_query($queryParams) : '';
                    @endphp
                    <x-pagination :base-url="$pagination['baseUrl']" :current="$pagination['current']" :total="$pagination['total']" :query-string="$queryString" />
                </div>
            @endif
        @endif
    </div>
</x-layouts.app>
