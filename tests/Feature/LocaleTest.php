<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The interface language is Laravel's own APP_LOCALE, and every locale in this
 * app follows Laravel's underscore convention (pt_BR) so that lang/pt_BR.json,
 * the package translations and feeds.language all name the same thing. Getting
 * that wrong makes pages render raw keys rather than throw, so it needs a test.
 */
class LocaleTest extends TestCase
{
    public function test_every_configured_feed_language_has_translations(): void
    {
        foreach (array_keys(config('lerama.languages')) as $locale) {
            $this->app->setLocale($locale);

            // The app's own strings.
            $this->assertNotSame('nav.feeds', __('nav.feeds'), "lang/{$locale}.json is missing or unreadable.");

            // A package that ships its own translations.
            $key = 'filament-panels::auth/pages/login.heading';
            $this->assertNotSame($key, __($key), "[{$locale}] has no Filament translations.");
        }
    }

    public function test_package_translations_are_not_silently_falling_back_to_english(): void
    {
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

            $this->assertNotSame(
                $english,
                $portuguese,
                "[{$key}] rendered in English under pt_BR — the locale does not match the package's directory name."
            );
        }
    }

    public function test_laravel_core_strings_reach_the_english_fallback(): void
    {
        $this->app->setLocale('pt_BR');
        $this->app->setFallbackLocale('en');

        // Laravel ships an `en` directory only.
        $this->assertNotSame('validation.required', __('validation.required', ['attribute' => 'nome']));
    }

    public function test_the_html_lang_attribute_is_a_bcp47_tag(): void
    {
        $this->app->setLocale('pt_BR');

        // The wire format keeps the hyphen even though the locale does not.
        $this->get('/')->assertOk()->assertSee('<html lang="pt-BR"', escape: false);
    }

    public function test_the_panel_renders_no_raw_translation_keys(): void
    {
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
    }
}
