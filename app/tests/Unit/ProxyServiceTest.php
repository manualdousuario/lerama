<?php

declare(strict_types=1);

namespace Tests\Unit;

use Lerama\Services\ProxyService;
use PHPUnit\Framework\TestCase;

class ProxyServiceTest extends TestCase
{
    private ?string $originalProxyUrl;

    protected function setUp(): void
    {
        $this->originalProxyUrl = $_ENV['PROXY_URL'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->originalProxyUrl === null) {
            unset($_ENV['PROXY_URL']);
        } else {
            $_ENV['PROXY_URL'] = $this->originalProxyUrl;
        }
    }

    public function testDirectOnlyWhenProxyUrlIsEmpty(): void
    {
        $_ENV['PROXY_URL'] = '';
        $service = new ProxyService();

        $attempts = $service->buildAttemptConfigs(['timeout' => 30]);

        $this->assertCount(1, $attempts);
        $this->assertFalse($attempts[0]['usingProxy']);
        $this->assertSame('direct', $attempts[0]['label']);
        $this->assertArrayNotHasKey('proxy', $attempts[0]['config']);
        // The base config must be preserved untouched.
        $this->assertSame(30, $attempts[0]['config']['timeout']);
    }

    public function testProxyFirstThenDirectFallbackWhenProxyUrlIsSet(): void
    {
        $_ENV['PROXY_URL'] = 'http://user:pass@proxy1:8080,http://proxy2:3128';
        $service = new ProxyService();

        $attempts = $service->buildAttemptConfigs(['timeout' => 30]);

        // PROXY_ATTEMPTS proxy attempts + one direct fallback.
        $this->assertCount(ProxyService::PROXY_ATTEMPTS + 1, $attempts);

        for ($i = 0; $i < ProxyService::PROXY_ATTEMPTS; $i++) {
            $this->assertTrue($attempts[$i]['usingProxy'], "attempt {$i} should use proxy");
            $this->assertArrayHasKey('proxy', $attempts[$i]['config']);
            $this->assertStringStartsWith('http://', $attempts[$i]['config']['proxy']);
            $this->assertSame(30, $attempts[$i]['config']['timeout']);
        }

        $direct = $attempts[ProxyService::PROXY_ATTEMPTS];
        $this->assertFalse($direct['usingProxy']);
        $this->assertSame('direct', $direct['label']);
        $this->assertArrayNotHasKey('proxy', $direct['config']);
    }

    public function testBuildProxyUrlWithCredentials(): void
    {
        $service = new ProxyService();

        $withAuth = $service->buildProxyUrl([
            'host' => 'proxy.example.com',
            'port' => 8080,
            'username' => 'user',
            'password' => 'pass',
        ]);
        $this->assertSame('http://user:pass@proxy.example.com:8080', $withAuth);

        $noAuth = $service->buildProxyUrl([
            'host' => 'proxy.example.com',
            'port' => 3128,
            'username' => null,
            'password' => null,
        ]);
        $this->assertSame('http://proxy.example.com:3128', $noAuth);
    }
}
