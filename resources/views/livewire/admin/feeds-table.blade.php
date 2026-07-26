<div class="card">
    <div class="border-b border-line p-4 dark:border-night-line">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="m-0 text-lg">
                <i class="ti ti-rss me-1" aria-hidden="true"></i>
                {{ __('admin.feeds.title') }}
            </h3>
            <a href="/admin/feeds/new" class="btn-sm btn-primary">
                <i class="ti ti-plus" aria-hidden="true"></i> {{ __('admin.feeds.add_new') }}
            </a>
        </div>

        {{-- Filtros --}}
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <input type="text" wire:model.live.debounce.400ms="search" class="input max-w-56" placeholder="{{ __('common.search_placeholder') }}">
            <select wire:model.live="statusFilter" class="select max-w-44" aria-label="{{ __('admin.feeds.filter_status') }}">
                <option value="">{{ __('admin.feeds.all_status') }}</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}">{{ __('status.'.$status->value) }}</option>
                @endforeach
            </select>
            <select wire:model.live="shuffleFilter" class="select max-w-44" aria-label="{{ __('admin.feeds.filter_shuffle') }}">
                <option value="">{{ __('admin.feeds.all_shuffle') }}</option>
                <option value="1">{{ __('admin.feeds.shuffle_active') }}</option>
                <option value="0">{{ __('admin.feeds.shuffle_inactive') }}</option>
            </select>
        </div>

        {{-- Ações em massa --}}
        <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-line pt-3 dark:border-night-line">
            <select wire:model="bulkStatus" class="select max-w-40" aria-label="{{ __('admin.feeds.bulk_status') }}">
                <option value="">{{ __('admin.feeds.bulk_status') }}</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}">{{ __('status.'.$status->value) }}</option>
                @endforeach
            </select>
            <button wire:click="applyBulkStatus" class="btn-sm btn-outline-primary" @disabled(empty($selected) || $bulkStatus === '')>
                <i class="ti ti-checkbox" aria-hidden="true"></i> {{ __('common.save') }}
            </button>
            <button wire:click="$set('showBulkCategoriesModal', true)" class="btn-sm btn-outline-primary" @disabled(empty($selected))>
                <i class="ti ti-folder" aria-hidden="true"></i> {{ __('admin.feeds.bulk_categories') }}
            </button>
            <button wire:click="$set('showBulkTagsModal', true)" class="btn-sm btn-outline-primary" @disabled(empty($selected))>
                <i class="ti ti-tags" aria-hidden="true"></i> {{ __('admin.feeds.bulk_tags') }}
            </button>
            @if (! empty($selected))
                <span class="text-sm text-ink-soft dark:text-night-soft">
                    {{ count($selected) }} {{ __('admin.feeds.selected') }}
                </span>
            @endif
        </div>
    </div>

    @if ($feeds->isEmpty())
        <div class="p-6 text-center">
            <p class="mb-0 text-ink-soft dark:text-night-soft">{{ __('admin.feeds.no_feeds') }}</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th scope="col" class="w-8">
                            <input type="checkbox" wire:model.live="selectAll" class="accent-brand" aria-label="{{ __('admin.feeds.selected') }}">
                        </th>
                        <th scope="col"><i class="ti ti-rss me-1" aria-hidden="true"></i> {{ __('feeds.feed') }}</th>
                        <th scope="col"><i class="ti ti-clock me-1" aria-hidden="true"></i> {{ __('common.status') }}</th>
                        <th scope="col"><i class="ti ti-refresh me-1" aria-hidden="true"></i> {{ __('feeds.shuffle') }}</th>
                        <th scope="col"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($feeds as $feed)
                        <tr wire:key="feed-{{ $feed->id }}">
                            <td>
                                <input type="checkbox" wire:model.live="selected" value="{{ $feed->id }}" class="accent-brand">
                            </td>
                            <td>
                                <div class="font-medium">
                                    <a href="/feeds/{{ $feed->slug }}" target="_blank" class="text-ink-strong no-underline hover:text-brand dark:text-night-strong dark:hover:text-night-brand">
                                        {{ $feed->title }}
                                    </a>
                                    <span class="badge ms-1 bg-stone-warm/15 text-stone-warm dark:bg-night-soft/15 dark:text-night-soft">
                                        {{ $feed->item_count }} <i class="ti ti-stack-2" aria-hidden="true"></i>
                                    </span>
                                </div>
                                <div class="max-w-[280px] truncate text-sm text-ink-soft dark:text-night-soft">{{ $feed->feed_url }}</div>
                            </td>
                            <td>
                                <select wire:change="updateStatus({{ $feed->id }}, $event.target.value)" class="select w-auto py-1 text-xs">
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status->value }}" @selected($feed->status === $status)>{{ __('status.'.$status->value) }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <button wire:click="toggleShuffle({{ $feed->id }})"
                                    class="btn-sm {{ $feed->shuffle ? 'btn-outline-primary border-moss text-moss hover:bg-moss dark:border-night-moss dark:text-night-moss dark:hover:bg-night-moss' : 'btn-outline' }}"
                                    title="{{ $feed->shuffle ? __('admin.feeds.shuffle_disable') : __('admin.feeds.shuffle_enable') }}">
                                    <i class="ti {{ $feed->shuffle ? 'ti-refresh' : 'ti-refresh-off' }}" aria-hidden="true"></i>
                                </button>
                            </td>
                            <td class="text-end">
                                <div class="flex justify-end gap-1">
                                    <a href="/admin/feeds/{{ $feed->id }}/edit" class="btn-sm btn-outline-primary" title="{{ __('common.edit') }}">
                                        <i class="ti ti-pencil" aria-hidden="true"></i>
                                    </a>
                                    <button wire:click="deleteFeed({{ $feed->id }})"
                                        wire:confirm="{{ __('admin.feeds.delete_confirm') }}"
                                        class="btn-sm btn-outline-danger"
                                        title="{{ __('common.delete') }}">
                                        <i class="ti ti-trash" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="border-t border-line p-3 dark:border-night-line">
            {{ $feeds->links() }}
        </div>
    @endif

    {{-- Modal: categorias em massa --}}
    @if ($showBulkCategoriesModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="bulk-categories-title">
            <div class="card w-full max-w-md">
                <div class="flex items-center justify-between border-b border-line p-4 dark:border-night-line">
                    <h5 id="bulk-categories-title" class="m-0 text-base">{{ __('admin.feeds.bulk_categories_modal_title') }}</h5>
                    <button wire:click="$set('showBulkCategoriesModal', false)" class="btn-sm btn-outline" aria-label="{{ __('common.cancel') }}">
                        <i class="ti ti-x" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="max-h-72 overflow-y-auto p-4">
                    <p class="mb-3 text-sm text-ink-soft dark:text-night-soft">{{ __('admin.feeds.bulk_categories_description') }}</p>
                    <div class="flex flex-col gap-1">
                        @foreach ($this->allCategories as $category)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="bulkCategoryIds" value="{{ $category['id'] }}" class="accent-brand">
                                {{ $category['name'] }}
                            </label>
                        @endforeach
                    </div>
                    <p class="form-help mt-3">{{ __('admin.feeds.bulk_categories_note') }}</p>
                </div>
                <div class="flex justify-end gap-2 border-t border-line p-4 dark:border-night-line">
                    <button wire:click="$set('showBulkCategoriesModal', false)" class="btn-sm btn-secondary">{{ __('common.cancel') }}</button>
                    <button wire:click="applyBulkCategories" class="btn-sm btn-primary" @disabled(empty($bulkCategoryIds))>
                        {{ __('admin.feeds.apply_categories') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: tags em massa --}}
    @if ($showBulkTagsModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="bulk-tags-title">
            <div class="card w-full max-w-md">
                <div class="flex items-center justify-between border-b border-line p-4 dark:border-night-line">
                    <h5 id="bulk-tags-title" class="m-0 text-base">{{ __('admin.feeds.bulk_tags_modal_title') }}</h5>
                    <button wire:click="$set('showBulkTagsModal', false)" class="btn-sm btn-outline" aria-label="{{ __('common.cancel') }}">
                        <i class="ti ti-x" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="max-h-72 overflow-y-auto p-4">
                    <p class="mb-3 text-sm text-ink-soft dark:text-night-soft">{{ __('admin.feeds.bulk_tags_description') }}</p>
                    <div class="flex flex-col gap-1">
                        @foreach ($this->allTags as $tag)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="bulkTagIds" value="{{ $tag['id'] }}" class="accent-brand">
                                {{ $tag['name'] }}
                            </label>
                        @endforeach
                    </div>
                    <p class="form-help mt-3">{{ __('admin.feeds.bulk_tags_note') }}</p>
                </div>
                <div class="flex justify-end gap-2 border-t border-line p-4 dark:border-night-line">
                    <button wire:click="$set('showBulkTagsModal', false)" class="btn-sm btn-secondary">{{ __('common.cancel') }}</button>
                    <button wire:click="applyBulkTags" class="btn-sm btn-primary" @disabled(empty($bulkTagIds))>
                        {{ __('admin.feeds.apply_tags') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
