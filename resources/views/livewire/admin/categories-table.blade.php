<div class="card">
    <div class="flex items-center justify-between border-b border-line p-4 dark:border-night-line">
        <h3 class="m-0 text-lg">
            <i class="ti ti-folder me-1" aria-hidden="true"></i>
            {{ __('admin.categories.title') }}
        </h3>
        <button wire:click="openCreate" class="btn-sm btn-primary">
            <i class="ti ti-plus" aria-hidden="true"></i> {{ __('admin.categories.new') }}
        </button>
    </div>

    @if ($categories->isEmpty())
        <div class="p-6 text-center">
            <p class="mb-0 text-ink-soft dark:text-night-soft">{{ __('admin.categories.no_categories') }}</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('common.name') }}</th>
                        <th scope="col">{{ __('common.slug') }}</th>
                        <th scope="col">{{ __('admin.categories.feeds') }}</th>
                        <th scope="col"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($categories as $category)
                        <tr wire:key="category-{{ $category->id }}">
                            <td class="font-medium">{{ $category->name }}</td>
                            <td class="text-ink-soft dark:text-night-soft">{{ $category->slug }}</td>
                            <td>
                                <span class="badge bg-stone-warm/15 text-stone-warm dark:bg-night-soft/15 dark:text-night-soft">
                                    {{ $category->feed_count }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="flex justify-end gap-1">
                                    <button wire:click="openEdit({{ $category->id }})" class="btn-sm btn-outline-primary" title="{{ __('common.edit') }}">
                                        <i class="ti ti-pencil" aria-hidden="true"></i>
                                    </button>
                                    <button wire:click="deleteCategory({{ $category->id }})"
                                        wire:confirm="{{ __('admin.categories.delete_confirm') }}"
                                        class="btn-sm btn-outline-danger" title="{{ __('common.delete') }}">
                                        <i class="ti ti-trash" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($showFormModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true">
            <div class="card w-full max-w-md">
                <div class="flex items-center justify-between border-b border-line p-4 dark:border-night-line">
                    <h5 class="m-0 text-base">{{ $editingId ? __('admin.category_form.edit_title') : __('admin.categories.new') }}</h5>
                    <button wire:click="$set('showFormModal', false)" class="btn-sm btn-outline" aria-label="{{ __('common.cancel') }}">
                        <i class="ti ti-x" aria-hidden="true"></i>
                    </button>
                </div>
                <form wire:submit="save" class="p-4">
                    <div class="mb-3">
                        <label for="category-name" class="label">{{ __('admin.category_form.name') }}</label>
                        <input type="text" wire:model="name" id="category-name" class="input @error('name') input-error @enderror" required>
                        @error('name') <div class="form-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label for="category-slug" class="label">{{ __('admin.category_form.slug') }}</label>
                        <input type="text" wire:model="slug" id="category-slug" class="input @error('slug') input-error @enderror">
                        <div class="form-help">{{ __('admin.category_form.slug_help') }}</div>
                        @error('slug') <div class="form-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showFormModal', false)" class="btn-sm btn-secondary">{{ __('common.cancel') }}</button>
                        <button type="submit" class="btn-sm btn-primary">{{ __('common.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
