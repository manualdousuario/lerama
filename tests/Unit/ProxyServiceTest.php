<?php

namespace Tests\Unit;

use App\Services\ProxyService;

it('makes only a direct attempt without a proxy url', function () {
    config(['lerama.proxy.urls' => '']);

    $attempts = (new ProxyService)->buildAttemptConfigs(['timeout' => 5]);

    expect($attempts)->toHaveCount(1)
        ->and($attempts[0]['usingProxy'])->toBeFalse()
        ->and($attempts[0]['label'])->toBe('direct');
});

it('puts the proxies before the direct attempt', function () {
    config(['lerama.proxy.urls' => 'http://proxy1:8080,http://user:pass@proxy2:3128']);

    $attempts = (new ProxyService)->buildAttemptConfigs(['timeout' => 5]);

    expect($attempts)->toHaveCount(ProxyService::PROXY_ATTEMPTS + 1)
        ->and($attempts[0]['usingProxy'])->toBeTrue()
        ->and($attempts[0]['config']['proxy'])->toContain('proxy')
        ->and($attempts[2]['usingProxy'])->toBeFalse();
});

it('parses a proxy url with credentials', function () {
    $service = new ProxyService;
    $proxy = $service->parseProxyUrl('https://user:p%40ss@proxy.example.com:8443');

    expect($proxy['scheme'])->toBe('https')
        ->and($proxy['host'])->toBe('proxy.example.com')
        ->and($proxy['port'])->toBe(8443)
        ->and($proxy['username'])->toBe('user')
        ->and($proxy['password'])->toBe('p@ss');
});

it('builds a proxy url', function () {
    $service = new ProxyService;

    expect($service->buildProxyUrl(['scheme' => 'http', 'host' => 'proxy', 'port' => 8080, 'username' => null, 'password' => null]))
        ->toBe('http://proxy:8080');

    expect($service->buildProxyUrl(['scheme' => 'https', 'host' => 'proxy', 'port' => 3128, 'username' => 'u', 'password' => 'p@']))
        ->toBe('https://u:p%40@proxy:3128');
});
