<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('has translations for every configured feed language', function () {
    foreach (array_keys(config('lerama.languages')) as $locale) {
        $this->app->setLocale($locale);

        expect(__('nav.feeds'))->not->toBe('nav.feeds', "lang/{$locale}.json is missing or unreadable.");

        $key = 'filament-panels::auth/pages/login.heading';
        expect(__($key))->not->toBe($key, "[{$locale}] has no Filament translations.");
    }
});

it('does not let package translations silently fall back to english', function () {
    $keys = [
        'filament-panels::auth/pages/login.heading',
        'filament-actions::delete.single.label',
        'filament-tables::table.actions.filter.label',
    ];

    foreach ($keys as $key) {
        $this->app->setLocale('en');
        $english = __($key);

        $this->app->setLocale('pt_BR');
        $portuguese = __($key);

        expect($portuguese)->not->toBe(
            $english,
            "[{$key}] rendered in English under pt_BR — the locale does not match the package's directory name."
        );
    }
});

it('reaches the english fallback for laravel core strings', function () {
    $this->app->setLocale('pt_BR');
    $this->app->setFallbackLocale('en');

    expect(__('validation.required', ['attribute' => 'nome']))->not->toBe('validation.required');
});

it('renders the html lang attribute as a bcp47 tag', function () {
    $this->app->setLocale('pt_BR');

    $this->get('/')->assertOk()->assertSee('<html lang="pt-BR"', escape: false);
});

it('renders no raw translation keys in the panel', function () {
    $this->app->setLocale('pt_BR');

    $this->get('/admin/login')->assertOk()->assertDontSee('filament-panels::', escape: false);

    $this->actingAs(User::create([
        'name' => 'admin',
        'email' => 'admin@lerama.local',
        'password' => Hash::make('strong-password-123'),
    ]));

    $this->seedBasicData();

    foreach (['/admin/feeds', '/admin/feed-items', '/admin/categories', '/admin/tags'] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertDontSee('filament-panels::', escape: false)
            ->assertDontSee('filament-tables::', escape: false)
            ->assertDontSee('filament-actions::', escape: false);
    }
});
