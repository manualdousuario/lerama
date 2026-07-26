<?php

namespace Tests\Unit;

use App\Services\ProxyService;
use Tests\UnitTestCase as TestCase;

class ProxyServiceTest extends TestCase
{
    public function test_without_proxy_url_only_direct_attempt(): void
    {
        config(['lerama.proxy.urls' => '']);

        $attempts = (new ProxyService)->buildAttemptConfigs(['timeout' => 5]);

        $this->assertCount(1, $attempts);
        $this->assertFalse($attempts[0]['usingProxy']);
        $this->assertSame('direct', $attempts[0]['label']);
    }

    public function test_proxies_come_before_direct_attempt(): void
    {
        config(['lerama.proxy.urls' => 'http://proxy1:8080,http://user:pass@proxy2:3128']);

        $attempts = (new ProxyService)->buildAttemptConfigs(['timeout' => 5]);

        $this->assertCount(ProxyService::PROXY_ATTEMPTS + 1, $attempts);
        $this->assertTrue($attempts[0]['usingProxy']);
        $this->assertStringContainsString('proxy', $attempts[0]['config']['proxy']);
        $this->assertFalse($attempts[2]['usingProxy']);
    }

    public function test_parse_proxy_url_with_credentials(): void
    {
        $service = new ProxyService;
        $proxy = $service->parseProxyUrl('https://user:p%40ss@proxy.example.com:8443');

        $this->assertSame('https', $proxy['scheme']);
        $this->assertSame('proxy.example.com', $proxy['host']);
        $this->assertSame(8443, $proxy['port']);
        $this->assertSame('user', $proxy['username']);
        $this->assertSame('p@ss', $proxy['password']);
    }

    public function test_build_proxy_url(): void
    {
        $service = new ProxyService;

        $this->assertSame(
            'http://proxy:8080',
            $service->buildProxyUrl(['scheme' => 'http', 'host' => 'proxy', 'port' => 8080, 'username' => null, 'password' => null])
        );

        $this->assertSame(
            'https://u:p%40@proxy:3128',
            $service->buildProxyUrl(['scheme' => 'https', 'host' => 'proxy', 'port' => 3128, 'username' => 'u', 'password' => 'p@'])
        );
    }
}
