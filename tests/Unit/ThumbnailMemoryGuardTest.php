<?php

use App\Services\ThumbnailService;

$limit = 256 * 1024 ** 2;

it('allows images that fit', function () use ($limit) {
    expect(ThumbnailService::fitsInMemory(2000, 1500, $limit, 20 * 1024 ** 2))->toBeTrue();
});

it('rejects images that would exhaust memory', function () use ($limit) {
    expect(ThumbnailService::fitsInMemory(12000, 9000, $limit, 20 * 1024 ** 2))->toBeFalse();
});

it('accounts for memory already in use', function () use ($limit) {
    expect(ThumbnailService::fitsInMemory(4000, 3000, $limit, 230 * 1024 ** 2))->toBeFalse();
});

it('allows anything when the limit is unlimited', function () {
    expect(ThumbnailService::fitsInMemory(50000, 50000, -1))->toBeTrue();
});
