<?php

namespace Tests\Unit;

use App\Services\FaviconService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    config(['lerama.favicon.cache_store' => 'array']);
    Cache::store('array')->flush();
    FaviconService::flushMemo();
});

function faviconService(MockHandler $mock): FaviconService
{
    return new FaviconService(new Client(['handler' => HandlerStack::create($mock), 'http_errors' => false]));
}

it('extracts the domain from a site url', function () {
    $service = new FaviconService;

    expect($service->domain('https://www.Exemplo.com/blog'))->toBe('exemplo.com')
        ->and($service->domain('https://blog.exemplo.com.br'))->toBe('blog.exemplo.com.br')
        ->and($service->domain('not a url'))->toBeNull()
        ->and($service->domain(''))->toBeNull();
});

it('returns the duckduckgo url when the favicon exists and caches it', function () {
    $mock = new MockHandler([new Response(200)]);
    $service = faviconService($mock);

    expect($service->url('https://www.exemplo.com/x'))->toBe('https://icons.duckduckgo.com/ip3/exemplo.com.ico')
        ->and(Cache::store('array')->get('favicon:exemplo.com'))->toBe(1);

    FaviconService::flushMemo();

    // Served from the cache: the mock has no responses left.
    expect($service->url('https://exemplo.com'))->toBe('https://icons.duckduckgo.com/ip3/exemplo.com.ico')
        ->and($mock->count())->toBe(0);
});

it('returns null on 404 and caches the miss', function () {
    $service = faviconService(new MockHandler([new Response(404)]));

    expect($service->url('https://semicone.com'))->toBeNull()
        ->and(Cache::store('array')->get('favicon:semicone.com'))->toBe(0);
});

it('returns null when duckduckgo is unreachable', function () {
    $service = faviconService(new MockHandler([
        new ConnectException('timeout', new Request('HEAD', 'https://icons.duckduckgo.com')),
    ]));

    expect($service->url('https://exemplo.com'))->toBeNull()
        ->and(Cache::store('array')->get('favicon:exemplo.com'))->toBe(0);
});
