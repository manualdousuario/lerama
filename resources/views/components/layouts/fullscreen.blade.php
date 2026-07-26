@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ? $title . ' | ' . config('app.name') : config('app.name') }}</title>
    <link rel="icon" type="image/png" href="/assets/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/assets/favicon.svg" />
    <link rel="shortcut icon" href="/assets/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="{{ $title ?? config('app.name') }}" />
    <link rel="manifest" href="/assets/site.webmanifest" />
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $title ?? config('app.name') }}">
    <meta property="og:url" content="{{ config('app.url') }}">
    <meta property="og:image" content="/assets/ogimage.png">
    <meta property="og:description" content="{{ __('meta.description') }}">
    <script>
        (function () {
            var saved = null;
            try { saved = localStorage.getItem('theme'); } catch (e) {}
            if (saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    <link rel="stylesheet" href="{{ asset('assets/css/app.min.css') }}?v={{ filemtime(public_path('assets/css/app.min.css')) }}">
</head>

<body class="flex min-h-screen flex-col bg-paper text-ink dark:bg-night dark:text-night-ink">
    <a href="#main-content" class="skip-link">{{ __('a11y.skip_to_content') }}</a>

    {{ $slot }}

    <script>
        window.LERAMA = {
            appUrl: @json(config('app.url')),
            csrfToken: @json(csrf_token()),
            i18n: {}
        };
    </script>
    <script src="{{ asset('js/lerama.js') }}?v={{ filemtime(public_path('js/lerama.js')) }}" defer></script>
</body>

</html>
