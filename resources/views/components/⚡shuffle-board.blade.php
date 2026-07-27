<?php

use App\Models\Feed;
use App\Support\UrlValidator;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

new class extends Component
{
    public string $currentUrl = '';

    public string $urlInput = '';

    public function mount(?string $initialUrl = null): void
    {
        $this->currentUrl = $initialUrl ?? $this->pickRandom();
        $this->urlInput = $this->currentUrl;
    }

    public function shuffle(): void
    {
        $this->currentUrl = $this->pickRandom();
        $this->urlInput = $this->currentUrl;
    }

    public function go(): void
    {
        $url = trim($this->urlInput);
        if ($url !== '' && UrlValidator::validateRedirectUrl($url)) {
            $this->currentUrl = $url;
        }
    }

    private function pickRandom(): string
    {
        $pool = Cache::remember('shuffle:pool', 300, fn () => Feed::shuffleable()->pluck('site_url')->all());

        $url = ! empty($pool) ? (string) ($pool[array_rand($pool)] ?? '') : '';

        if ($url !== '' && ! UrlValidator::validateRedirectUrl($url)) {
            return '';
        }

        return $url;
    }
};
?>

<div class="flex h-screen flex-col">
    <div class="border-b border-topbar-line bg-topbar px-4 py-2 shadow-[0_1px_0_rgba(0,0,0,0.15),0_2px_6px_rgba(0,0,0,0.08)]" role="toolbar" aria-label="{{ __('nav.shuffle') }}">
        <div class="flex items-center gap-2">
            <a href="/" class="btn-sm btn-topbar shrink-0" aria-label="{{ __('a11y.back_home') }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                    <path d="M8.5 2.687c.654-.689 1.782-.886 3.112-.752 1.234.124 2.503.523 3.388.893v9.923c-.918-.35-2.107-.692-3.287-.81-1.094-.111-2.278-.039-3.213.492zM8 1.783C7.015.936 5.587.81 4.287.94c-1.514.153-3.042.672-3.994 1.105A.5.5 0 0 0 0 2.5v11a.5.5 0 0 0 .707.455c.882-.4 2.303-.881 3.68-1.02 1.409-.142 2.59.087 3.223.877a.5.5 0 0 0 .78 0c.633-.79 1.814-1.019 3.222-.877 1.378.139 2.8.62 3.681 1.02A.5.5 0 0 0 16 13.5v-11a.5.5 0 0 0-.293-.455c-.952-.433-2.48-.952-3.994-1.105C10.413.809 8.985.936 8 1.783"/>
                </svg>
            </a>
            <button wire:click="shuffle" id="shuffleButton" class="btn-sm btn-primary shrink-0" type="button" wire:loading.attr="disabled" wire:target="shuffle">
                <i class="ti ti-refresh" aria-hidden="true"></i> <span class="hidden sm:inline">{{ __('shuffle.button') }}</span>
            </button>
            <div class="flex min-w-0 flex-1 items-stretch gap-1">
                <label for="urlInput" class="sr-only">{{ __('shuffle.go') }}</label>
                <input type="url" id="urlInput" wire:model="urlInput" wire:keydown.enter="go"
                    class="input min-w-0 border-topbar-btn bg-night-2 py-1 text-sm text-topbar-text placeholder:text-night-faint"
                    placeholder="https://example.com">
                <button wire:click="go" id="goButton" class="btn-sm btn-secondary shrink-0" type="button">{{ __('shuffle.go') }}</button>
                <a href="{{ $currentUrl }}" target="_blank" rel="noopener" id="openButton" class="btn-sm btn-topbar py-2 shrink-0">
                    <i class="ti ti-external-link" aria-hidden="true"></i> <span class="hidden sm:inline">{{ __('shuffle.open') }}</span>
                </a>
            </div>
            <button id="darkModeToggle" class="btn-sm btn-topbar py-2 shrink-0" type="button" aria-label="{{ __('a11y.toggle_dark_mode') }}" aria-pressed="false">
                <i class="ti ti-sun hidden" id="lightIcon" aria-hidden="true"></i>
                <i class="ti ti-moon" id="darkIcon" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <main id="main-content" class="relative flex-1">
        <iframe id="contentFrame" src="{{ $currentUrl }}" class="absolute h-full w-full border-0"
            title="{{ __('nav.shuffle') }}"
            sandbox="allow-downloads allow-forms allow-modals allow-pointer-lock allow-popups allow-same-origin allow-scripts"
            loading="lazy"></iframe>
    </main>
</div>
