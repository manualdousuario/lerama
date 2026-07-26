<x-layouts.app :title="$title" active="feeds">
    <div class="card">
        <div class="border-b border-line p-4 dark:border-night-line">
            <div class="items-center gap-4 md:flex">
                <div class="md:w-1/3">
                    <h1 class="mt-1 mb-0 text-lg">
                        <i class="ti ti-rss me-1" aria-hidden="true"></i>
                        {{ __('feeds.title') }}
                    </h1>
                </div>
                <div class="pt-3 md:w-2/3 md:pt-0">
                    <form action="/feeds" method="GET" class="grid grid-cols-2 gap-2 md:grid-cols-[1fr_1fr_auto]">
                        <select name="category" class="select">
                            <option value="">{{ __('common.all_categories') }}</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category['slug'] }}" @selected(($selectedCategory ?? '') === $category['slug'])>
                                    {{ $category['name'] }}
                                </option>
                            @endforeach
                        </select>
                        <select name="tag" class="select">
                            <option value="">{{ __('common.all_topics') }}</option>
                            @foreach ($tags as $tag)
                                <option value="{{ $tag['slug'] }}" @selected(($selectedTag ?? '') === $tag['slug'])>
                                    {{ $tag['name'] }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn-primary col-span-2 justify-center md:col-span-1">
                            <i class="ti ti-filter" aria-hidden="true"></i>
                            {{ __('common.filter') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        @if (empty($feeds))
            <div class="p-6 text-center">
                <p class="mb-0 text-ink-soft dark:text-night-soft">{{ __('feeds.no_feeds') }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th scope="col"><i class="ti ti-rss me-1" aria-hidden="true"></i> {{ __('feeds.feed') }}</th>
                            <th scope="col"><i class="ti ti-folder me-1" aria-hidden="true"></i> {{ __('feeds.categories') }}</th>
                            <th scope="col"><i class="ti ti-tags me-1" aria-hidden="true"></i> {{ __('feeds.topics') }}</th>
                            <th scope="col"><i class="ti ti-clock me-1" aria-hidden="true"></i> {{ __('common.status') }}</th>
                            <th scope="col"><i class="ti ti-clock-history me-1" aria-hidden="true"></i> {{ __('feeds.verification') }}/{{ __('feeds.update') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($feeds as $feed)
                            @php
                                $utm = fn (string $url) => $url.(parse_url($url, PHP_URL_QUERY) ? '&' : '?').'utm_source=lerama';
                            @endphp
                            <tr>
                                <td>
                                    <div class="font-medium">
                                        <a href="{{ $utm($feed['site_url']) }}" target="_blank" class="text-ink-strong no-underline hover:text-brand dark:text-night-strong dark:hover:text-night-brand">
                                            {{ $feed['title'] }}
                                            <i class="ti ti-external-link ms-1" aria-hidden="true"></i>
                                        </a>
                                        <a href="/feeds/{{ $feed['slug'] }}" class="badge ms-1 bg-stone-warm/15 text-stone-warm no-underline dark:bg-night-soft/15 dark:text-night-soft" title="{{ __('feeds.items') }}">
                                            {{ $feed['item_count'] ?? 0 }} <i class="ti ti-stack-2" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                    <div class="max-w-[250px] truncate text-sm text-ink-soft dark:text-night-soft">
                                        <a href="{{ $feed['feed_url'] }}" target="_blank" class="text-ink-soft no-underline hover:text-brand dark:text-night-soft dark:hover:text-night-brand">
                                            {{ $feed['feed_url'] }}
                                            <i class="ti ti-rss ms-1" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                </td>
                                <td>
                                    @if (! empty($feed['categories']))
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($feed['categories'] as $category)
                                                <span class="badge bg-brand/10 text-brand dark:bg-night-brand/10 dark:text-night-brand">{{ $category['name'] }}</span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-sm text-ink-soft dark:text-night-soft">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if (! empty($feed['tags']))
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($feed['tags'] as $tag)
                                                <span class="badge bg-stone-warm/15 text-stone-warm dark:bg-night-soft/15 dark:text-night-soft">{{ $tag['name'] }}</span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-sm text-ink-soft dark:text-night-soft">-</span>
                                    @endif
                                </td>
                                <td>
                                    @php $status = $feed['status'] instanceof \App\Enums\FeedStatus ? $feed['status']->value : $feed['status']; @endphp
                                    @if ($status === 'online')
                                        <span class="badge-online"><i class="ti ti-circle-check" aria-hidden="true"></i> {{ __('status.online') }}</span>
                                    @elseif ($status === 'offline')
                                        <span class="badge-offline"><i class="ti ti-circle-x" aria-hidden="true"></i> {{ __('status.offline') }}</span>
                                    @else
                                        <span class="badge-paused"><i class="ti ti-player-pause" aria-hidden="true"></i> {{ __('status.paused') }}</span>
                                    @endif
                                </td>
                                <td class="text-sm text-ink-soft dark:text-night-soft">
                                    <div><strong>{{ __('feeds.verified') }}:</strong> {{ $feed['last_checked'] ? date('d/m/Y H:i', strtotime((string) $feed['last_checked'])) : __('feeds.never') }}</div>
                                    <div><strong>{{ __('feeds.updated') }}:</strong> {{ $feed['last_updated'] ? date('d/m/Y H:i', strtotime((string) $feed['last_updated'])) : __('feeds.never') }}</div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($pagination['total'] > 1)
                <div class="border-t border-line p-3 dark:border-night-line">
                    @php
                        $queryParams = array_filter([
                            'category' => $selectedCategory ?? null,
                            'tag' => $selectedTag ?? null,
                        ]);
                        $queryString = $queryParams ? '?'.http_build_query($queryParams) : '';
                    @endphp
                    <x-pagination :base-url="$pagination['baseUrl']" :current="$pagination['current']" :total="$pagination['total']" :query-string="$queryString" />
                </div>
            @endif
        @endif
    </div>
</x-layouts.app>
