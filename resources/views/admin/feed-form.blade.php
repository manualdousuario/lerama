<x-layouts.app :title="isset($feedId) ? __('admin.feed_form.edit_title') : __('admin.feed_form.add_title')" active="admin-feeds">
    <livewire:admin.feed-form :feed-id="$feedId ?? null" />
</x-layouts.app>
