<div class="card">
    <div class="border-b border-line p-4 dark:border-night-line">
        <div class="items-center gap-4 md:flex">
            <div class="flex items-center md:w-1/2">
                <h3 class="m-0 text-lg">
                    <i class="ti ti-stack-2 me-1" aria-hidden="true"></i>
                    {{ __('admin.items.title') }}
                </h3>
            </div>
            <div class="pt-3 md:w-1/2 md:pt-0">
                <div class="flex gap-2">
                    <select wire:model.live="feedFilter" class="select">
                        <option value="">{{ __('admin.items.feeds') }}</option>
                        @foreach ($this->feeds as $feed)
                            <option value="{{ $feed['id'] }}" @selected($feedFilter === (string) $feed['id'])>{{ $feed['title'] }}</option>
                        @endforeach
                    </select>
                    <div class="flex flex-1">
                        <input type="text" wire:model.live.debounce.400ms="search" class="input rounded-e-none" placeholder="{{ __('common.search_placeholder') }}">
                        <span class="btn-primary rounded-s-none pointer-events-none">
                            <i class="ti ti-search" aria-hidden="true"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($items->isEmpty())
        <div class="p-6 text-center">
            <p class="mb-0 text-ink-soft dark:text-night-soft">
                <i class="ti ti-alert-circle me-1" aria-hidden="true"></i>
                {{ __('admin.items.no_items') }}
            </p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th scope="col"><i class="ti ti-file-text me-1" aria-hidden="true"></i> {{ __('suggest.form.title') }}</th>
                        <th scope="col"><i class="ti ti-rss me-1" aria-hidden="true"></i> {{ __('admin.items.feed') }}</th>
                        <th scope="col"><i class="ti ti-user me-1" aria-hidden="true"></i> {{ __('admin.items.author') }}</th>
                        <th scope="col"><i class="ti ti-calendar me-1" aria-hidden="true"></i> {{ __('admin.items.published') }}</th>
                        <th scope="col"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr wire:key="item-{{ $item->id }}">
                            <td class="font-medium">
                                <a href="{{ $item->url . (parse_url($item->url, PHP_URL_QUERY) ? '&' : '?') . 'utm_source=lerama' }}" target="_blank" class="text-ink-strong no-underline hover:text-brand dark:text-night-strong dark:hover:text-night-brand">
                                    {{ $item->title }}
                                    <i class="ti ti-external-link ms-1" aria-hidden="true"></i>
                                </a>
                            </td>
                            <td>{{ $item->feed_title }}</td>
                            <td>{{ $item->author ?? __('admin.items.unknown_author') }}</td>
                            <td class="text-sm text-ink-soft dark:text-night-soft">
                                {{ $item->published_at ? $item->published_at->format('d/m/Y \à\s H:i') : __('feeds.never') }}
                            </td>
                            <td class="text-end">
                                <div class="flex justify-end gap-1">
                                    <button
                                        wire:click="toggleVisibility({{ $item->id }})"
                                        class="btn-sm {{ $item->is_visible ? 'btn-outline-primary border-moss text-moss hover:bg-moss dark:border-night-moss dark:text-night-moss dark:hover:bg-night-moss' : 'btn-outline-danger' }}"
                                        title="{{ $item->is_visible ? __('admin.items.hide_item') : __('admin.items.show_item') }}">
                                        <i class="ti {{ $item->is_visible ? 'ti-eye' : 'ti-eye-off' }}" aria-hidden="true"></i>
                                    </button>

                                    @if (! empty($item->image_url))
                                        <button
                                            wire:click="refreshThumbnail({{ $item->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="refreshThumbnail({{ $item->id }})"
                                            class="btn-sm btn-outline-primary"
                                            title="{{ __('admin.items.refresh_thumbnail') }}">
                                            <span wire:loading.remove wire:target="refreshThumbnail({{ $item->id }})"><i class="ti ti-photo" aria-hidden="true"></i></span>
                                            <span wire:loading wire:target="refreshThumbnail({{ $item->id }})"><i class="ti ti-loader-2 animate-spin" aria-hidden="true"></i></span>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="border-t border-line p-3 dark:border-night-line">
            {{ $items->links() }}
        </div>
    @endif
</div>
