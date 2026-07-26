<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<body>
    <h1>{{ __('mail.registered.heading') }}</h1>
    <h2>{{ __('mail.details') }}</h2>
    <ul>
        <li><strong>{{ __('mail.field.title') }}:</strong> {{ $feed->title }}</li>
        <li><strong>{{ __('mail.field.feed_url') }}:</strong> <a href="{{ $feed->feed_url }}">{{ $feed->feed_url }}</a></li>
        <li><strong>{{ __('mail.field.site_url') }}:</strong> <a href="{{ $feed->site_url }}">{{ $feed->site_url }}</a></li>
        <li><strong>{{ __('mail.field.type') }}:</strong> {{ $feed->feed_type instanceof \App\Enums\FeedType ? $feed->feed_type->value : $feed->feed_type }}</li>
        <li><strong>{{ __('mail.field.language') }}:</strong> {{ $feed->language }}</li>
        <li><strong>{{ __('mail.field.status') }}:</strong> {{ $feed->status instanceof \App\Enums\FeedStatus ? $feed->status->value : $feed->status }}</li>
        <li><strong>{{ __('mail.field.registered_at') }}:</strong> {{ now()->format('d/m/Y H:i:s') }}</li>
    </ul>
</body>
</html>
