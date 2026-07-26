<div class="card">
    <div class="border-b border-line p-4 dark:border-night-line">
        <h3 class="m-0 text-lg">
            <i class="ti ti-rss me-1" aria-hidden="true"></i>
            {{ $feedId ? __('admin.feed_form.edit_title') : __('admin.feed_form.add_title') }}
        </h3>
    </div>

    <form wire:submit="save" class="p-4">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="md:col-span-2">
                <label for="title" class="label">{{ __('suggest.form.title') }}</label>
                <input type="text" wire:model="title" id="title" class="input @error('title') input-error @enderror" required>
                @error('title') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div>
                <label for="site_url" class="label">{{ __('admin.feed_form.site_url') }}</label>
                <input type="url" wire:model="site_url" id="site_url" class="input @error('site_url') input-error @enderror" required placeholder="https://exemplo.com">
                @error('site_url') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div>
                <label for="feed_url" class="label">{{ __('admin.feed_form.feed_url') }}</label>
                <input type="url" wire:model="feed_url" id="feed_url" class="input @error('feed_url') input-error @enderror" required placeholder="https://exemplo.com/feed.xml">
                @error('feed_url') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div>
                <label for="language" class="label">{{ __('common.language') }}</label>
                <select wire:model="language" id="language" class="select">
                    <option value="pt-BR">{{ __('lang.pt-BR') }}</option>
                    <option value="en">{{ __('lang.en') }}</option>
                    <option value="es">{{ __('lang.es') }}</option>
                </select>
                @error('language') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div>
                <label for="feed_type" class="label">{{ __('admin.feed_form.feed_type') }}</label>
                <select wire:model="feed_type" id="feed_type" class="select">
                    <option value="">{{ __('admin.feed_form.auto_detect') }}</option>
                    @foreach ($feedTypes as $type)
                        <option value="{{ $type->value }}">{{ strtoupper($type->value) }}</option>
                    @endforeach
                </select>
                <div class="form-help">{{ __('admin.feed_form.feed_type_help') }}</div>
                @error('feed_type') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            @if ($feedId)
                <div>
                    <label for="status" class="label">{{ __('common.status') }}</label>
                    <select wire:model="status" id="status" class="select">
                        @foreach ($statuses as $s)
                            <option value="{{ $s->value }}">{{ __('status.'.$s->value) }}</option>
                        @endforeach
                    </select>
                    @error('status') <div class="form-error">{{ $message }}</div> @enderror
                </div>
            @endif

            <div class="flex items-center gap-6">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="proxy_only" class="accent-brand">
                    {{ __('admin.feed_form.proxy_only') }}
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="shuffle" class="accent-brand">
                    {{ __('admin.feed_form.shuffle') }}
                </label>
            </div>

            <div>
                <span class="label">{{ __('admin.feed_form.categories') }}</span>
                <div class="flex max-h-40 flex-col gap-1 overflow-y-auto rounded-md border border-line p-2 dark:border-night-line">
                    @forelse ($allCategories as $category)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="selectedCategories" value="{{ $category['id'] }}" class="accent-brand">
                            {{ $category['name'] }}
                        </label>
                    @empty
                        <span class="text-sm text-ink-soft dark:text-night-soft">-</span>
                    @endforelse
                </div>
                <div class="form-help">{{ __('admin.feed_form.categories_help') }}</div>
            </div>

            <div>
                <span class="label">{{ __('admin.feed_form.tags') }}</span>
                <div class="flex max-h-40 flex-col gap-1 overflow-y-auto rounded-md border border-line p-2 dark:border-night-line">
                    @forelse ($allTags as $tag)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="selectedTags" value="{{ $tag['id'] }}" class="accent-brand">
                            {{ $tag['name'] }}
                        </label>
                    @empty
                        <span class="text-sm text-ink-soft dark:text-night-soft">-</span>
                    @endforelse
                </div>
                <div class="form-help">{{ __('admin.feed_form.tags_help') }}</div>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <a href="/admin/feeds" class="btn-secondary">{{ __('common.cancel') }}</a>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-1.5">
                    <i class="ti ti-device-floppy" aria-hidden="true"></i>
                    {{ $feedId ? __('admin.feed_form.update') : __('admin.feed_form.add') }}
                </span>
                <span wire:loading wire:target="save" class="inline-flex items-center gap-1.5">
                    <i class="ti ti-loader-2 animate-spin" aria-hidden="true"></i> {{ __('admin.feed_form.saving') }}
                </span>
            </button>
        </div>
    </form>
</div>
