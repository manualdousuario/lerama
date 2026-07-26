<?php

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use App\Support\UrlValidator;
use PHPUnit\Framework\TestCase;

class SupportTest extends TestCase
{
    public function test_sanitizer_removes_scripts_and_handlers(): void
    {
        $dirty = '<p onclick="x()">Hi</p><script>alert(1)</script><a href="javascript:alert(1)">x</a>';
        $clean = HtmlSanitizer::sanitize($dirty);

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringContainsString('href="#"', $clean);
        $this->assertStringContainsString('<p>Hi</p>', $clean);
    }

    public function test_sanitizer_neutralizes_cdata(): void
    {
        $this->assertSame('a]]]]><![CDATA[>b', HtmlSanitizer::sanitize('a]]>b'));
    }

    public function test_url_validator_accepts_public_http(): void
    {
        $this->assertTrue(UrlValidator::validate('https://example.com/feed')['valid']);
        $this->assertTrue(UrlValidator::validate('http://blog.example.com')['valid']);
    }

    public function test_url_validator_rejects_ssrf(): void
    {
        foreach (['', 'ftp://example.com', 'http://localhost', 'http://127.0.0.1/x', 'http://192.168.1.1', 'http://10.0.0.5', 'garbage'] as $url) {
            $this->assertFalse(UrlValidator::validate($url)['valid'], "URL should be rejected: {$url}");
        }
    }

    /**
     * Resolvers accept these alternate encodings of loopback/link-local
     * addresses, so the validator has to fold them before range checks.
     */
    public function test_url_validator_rejects_obfuscated_loopback(): void
    {
        $urls = [
            'http://[::1]/x',
            'http://[0:0:0:0:0:0:0:1]/',
            'http://[fe80::1]/',
            'http://[fc00::1]/',
            'http://2130706433/',
            'http://0177.0.0.1/',
            'http://0x7f.0.0.1/',
            'http://169.254.169.254/latest/meta-data/',
            'http://0.0.0.0/',
        ];

        foreach ($urls as $url) {
            $this->assertFalse(UrlValidator::validate($url)['valid'], "URL should be rejected: {$url}");
        }
    }
}
