<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * The public site caches whole model rows under categories:all, tags:all and
 * feeds:dropdown, and its Blade templates read $row['slug'] off them. The
 * panel's option lists need a flat id => name map instead, so they must use
 * their own keys — sharing them served strings to the homepage and 500'd it.
 */
class AdminCacheIsolationTest extends TestCase
{
    private const PUBLIC_KEYS = ['categories:all', 'tags:all', 'feeds:dropdown'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::create([
            'name' => 'admin',
            'email' => 'admin@lerama.local',
            'password' => Hash::make('strong-password-123'),
        ]));
    }

    /**
     * Static rather than behavioural: Filament resolves option closures lazily
     * at render time, so a test that merely visits pages would silently stop
     * covering the bulk-action lists.
     */
    public function test_no_panel_code_references_a_public_cache_key(): void
    {
        $offenders = [];

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(app_path('Filament'))
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            foreach (self::PUBLIC_KEYS as $key) {
                if (str_contains($contents, "'".$key."'")) {
                    $offenders[] = basename($file->getPathname()).' => '.$key;
                }
            }
        }

        $this->assertSame([], $offenders, 'Panel code must not reuse the public site cache keys.');
    }

    public function test_rendering_the_panel_leaves_the_public_cache_keys_untouched(): void
    {
        $this->seedBasicData();

        $this->get('/admin/feeds')->assertOk();
        $this->get('/admin/feed-items')->assertOk();
        $this->get('/admin/categories')->assertOk();
        $this->get('/admin/tags')->assertOk();

        foreach (self::PUBLIC_KEYS as $key) {
            $this->assertNull(Cache::get($key), "The panel wrote to the public cache key [{$key}].");
        }
    }

    public function test_the_public_pages_still_render_after_the_panel_warms_its_lookups(): void
    {
        $this->seedBasicData();

        $this->get('/admin/feed-items')->assertOk();

        $this->get('/')->assertOk();
        $this->get('/categories')->assertOk();
        $this->get('/tags')->assertOk();
    }
}
