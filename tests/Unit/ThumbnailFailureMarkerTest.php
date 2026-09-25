<?php

namespace Tests\Unit;

use App\Services\ThumbnailService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['lerama.proxy.urls' => '']);
    Storage::fake('public');
});

$broken = 'http://127.0.0.1:1/broken.jpg';

it('marks a failed thumbnail and skips it afterwards', function () use ($broken) {
    $service = new ThumbnailService;

    expect($service->recentlyFailed($broken, 180, 100))->toBeFalse()
        ->and($service->getThumbnail($broken, 180, 100))->toBe($broken)
        ->and($service->recentlyFailed($broken, 180, 100))->toBeTrue()
        ->and($service->recentlyFailed($broken, 360, 200))->toBeFalse()
        ->and($service->getThumbnailDeferred($broken, 180, 100))->toBe($broken);
});

it('retries once the marker expires', function () use ($broken) {
    $service = new ThumbnailService;
    $service->getThumbnail($broken, 180, 100);

    $marker = Storage::disk('public')->path('thumbnails/'.md5($broken.'180100').'.fail');
    touch($marker, time() - ThumbnailService::FAILURE_TTL_SECONDS - 1);

    expect($service->recentlyFailed($broken, 180, 100))->toBeFalse();
});
