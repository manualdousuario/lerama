<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\Cache;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Feature\Admin\Concerns\AdminUsers;

const PUBLIC_CACHE_KEYS = ['categories:all', 'tags:all', 'feeds:dropdown'];

beforeEach(function () {
    $this->actingAs(AdminUsers::admin());
});

it('has no panel code referencing a public cache key', function () {
    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(app_path('Filament'))
    );

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        foreach (PUBLIC_CACHE_KEYS as $key) {
            if (str_contains($contents, "'".$key."'")) {
                $offenders[] = basename($file->getPathname()).' => '.$key;
            }
        }
    }

    expect($offenders)->toBe([], 'Panel code must not reuse the public site cache keys.');
});

it('leaves the public cache keys untouched when rendering the panel', function () {
    $this->seedBasicData();

    $this->get('/admin/feeds')->assertOk();
    $this->get('/admin/feed-items')->assertOk();
    $this->get('/admin/categories')->assertOk();
    $this->get('/admin/tags')->assertOk();

    foreach (PUBLIC_CACHE_KEYS as $key) {
        expect(Cache::get($key))->toBeNull("The panel wrote to the public cache key [{$key}].");
    }
});

it('still renders the public pages after the panel warms its lookups', function () {
    $this->seedBasicData();

    $this->get('/admin/feed-items')->assertOk();

    $this->get('/')->assertOk();
    $this->get('/categories')->assertOk();
    $this->get('/tags')->assertOk();
});
