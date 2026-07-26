<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<body>
    <h1>{{ __('mail.offline.heading') }}</h1>
    <h2>{{ __('mail.details') }}</h2>
    <ul>
        <li><strong>{{ __('mail.field.title') }}:</strong> {{ $feed->title }}</li>
        <li><strong>{{ __('mail.field.feed_url') }}:</strong> {{ $feed->feed_url }}</li>
        <li><strong>{{ __('mail.field.type') }}:</strong> {{ $feed->feed_type instanceof \App\Enums\FeedType ? $feed->feed_type->value : $feed->feed_type }}</li>
        <li><strong>{{ __('mail.field.last_checked') }}:</strong> {{ $feed->last_checked?->format('d/m/Y H:i:s') }}</li>
    </ul>
    <p>{{ __('mail.offline.body', ['date' => $feed->paused_at?->format('d/m/Y H:i:s')]) }}</p>
</body>
</html>
