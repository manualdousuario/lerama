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

it('budgets PNG decoding for the libpng buffer on top of the GD image', function () {
    expect(ThumbnailService::decodeBytesPerPixel(['mime' => 'image/jpeg', 'bits' => 8]))->toBe(6.0)
        ->and(ThumbnailService::decodeBytesPerPixel(['mime' => 'image/png', 'bits' => 8]))->toBe(12.0)
        ->and(ThumbnailService::decodeBytesPerPixel(['mime' => 'image/png', 'bits' => 16]))->toBe(18.0);
});

it('rejects a 16-bit PNG that the flat estimate would have let through', function () {
    // ~6.4MP 16-bit RGBA: libpng buffer alone is ~51MB, as seen in production.
    $limit = 128 * 1024 ** 2;
    $used = 40 * 1024 ** 2;

    expect(ThumbnailService::fitsInMemory(2530, 2530, $limit, $used))->toBeTrue()
        ->and(ThumbnailService::fitsInMemory(2530, 2530, $limit, $used,
            ThumbnailService::decodeBytesPerPixel(['mime' => 'image/png', 'bits' => 16])))->toBeFalse();
});
