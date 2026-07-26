@props(['title' => null, 'active' => ''])

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
        // Anti-FOUC: apply the theme before first paint.
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

    <nav class="border-b border-topbar-line bg-topbar shadow-[0_1px_0_rgba(0,0,0,0.15),0_2px_6px_rgba(0,0,0,0.08)]" aria-label="{{ __('nav.home') }}">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-4 px-4">
            <div class="flex flex-wrap items-center gap-x-5">
                <div class="flex items-center py-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="text-topbar-text" viewBox="0 0 16 16" aria-hidden="true">
                        <path d="M8.5 2.687c.654-.689 1.782-.886 3.112-.752 1.234.124 2.503.523 3.388.893v9.923c-.918-.35-2.107-.692-3.287-.81-1.094-.111-2.278-.039-3.213.492zM8 1.783C7.015.936 5.587.81 4.287.94c-1.514.153-3.042.672-3.994 1.105A.5.5 0 0 0 0 2.5v11a.5.5 0 0 0 .707.455c.882-.4 2.303-.881 3.68-1.02 1.409-.142 2.59.087 3.223.877a.5.5 0 0 0 .78 0c.633-.79 1.814-1.019 3.222-.877 1.378.139 2.8.62 3.681 1.02A.5.5 0 0 0 16 13.5v-11a.5.5 0 0 0-.293-.455c-.952-.433-2.48-.952-3.994-1.105C10.413.809 8.985.936 8 1.783"/>
                    </svg>
                    <a href="/" class="ml-2 text-xl font-bold text-topbar-text no-underline hover:text-white">Lerama</a>
                </div>
                <nav class="flex flex-wrap items-center gap-x-1">
                    <x-nav-link href="/" :active="$active === 'home'" icon="ti-home">{{ __('nav.home') }}</x-nav-link>
                    <x-nav-link href="/feeds" :active="$active === 'feeds'" icon="ti-rss">{{ __('nav.feeds') }}</x-nav-link>
                    <x-nav-link href="/suggest-feed" :active="$active === 'suggest-feed'" icon="ti-circle-plus">{{ __('nav.suggest') }}</x-nav-link>
                    <x-nav-link href="/shuffle" :active="$active === 'shuffle'" icon="ti-refresh">{{ __('nav.shuffle') }}</x-nav-link>
                    <x-nav-link href="/random" target="_blank" icon="ti-arrow-up-right">{{ __('nav.random') }}</x-nav-link>
                </nav>
            </div>
            <div class="flex items-center gap-2 py-2">
                <a href="/feed-builder" class="btn-sm btn-topbar" title="{{ __('nav.feed_builder') }}">
                    <i class="ti ti-braces" aria-hidden="true"></i> Feed
                </a>
                <button id="darkModeToggle" class="btn-sm btn-topbar" type="button" aria-label="{{ __('a11y.toggle_dark_mode') }}" aria-pressed="false">
                    <i class="ti ti-sun hidden" id="lightIcon" aria-hidden="true"></i>
                    <i class="ti ti-moon" id="darkIcon" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </nav>

    @includeIf('partials.partner')

    <main id="main-content" tabindex="-1" class="flex-1">
        <div class="mx-auto max-w-6xl px-4 py-6">
            {{ $slot }}
        </div>
    </main>

    <footer class="mt-auto border-t border-line py-6 dark:border-night-line">
        <div class="mx-auto max-w-6xl px-4">
            <p class="m-0 p-0 text-center text-sm text-ink-soft dark:text-night-soft">
                &copy; {{ date('Y') }} - {{ __('footer.description') }}
            </p>
            <p class="mt-3 mb-0 text-center">
                <a href="https://github.com/manualdousuario/lerama" target="_blank" class="btn-sm btn-outline mx-1" title="GitHub">
                    <i class="ti ti-brand-github" aria-hidden="true"></i> GitHub
                </a>
                <a href="/categories" class="btn-sm btn-outline mx-1" title="{{ __('nav.categories') }}">
                    <i class="ti ti-folder" aria-hidden="true"></i> {{ __('nav.categories') }}
                </a>
                <a href="/tags" class="btn-sm btn-outline mx-1" title="{{ __('nav.topics') }}">
                    <i class="ti ti-tags" aria-hidden="true"></i> {{ __('nav.topics') }}
                </a>
                <button id="copySeloLerama" class="btn-sm btn-outline mx-1" title="{{ __('footer.badge') }}">
                    <i class="ti ti-clipboard" aria-hidden="true"></i> {{ __('footer.badge') }}
                </button>
            </p>
        </div>
    </footer>

    <script>
        window.LERAMA = {
            appUrl: @json(config('app.url')),
            csrfToken: @json(csrf_token()),
            i18n: {
                footerCopied: @json(__('footer.copied')),
                footerCopyError: @json(__('footer.copy_error'))
            }
        };
    </script>
    <script src="{{ asset('js/lerama.js') }}?v={{ filemtime(public_path('js/lerama.js')) }}" defer></script>
</body>

</html>
