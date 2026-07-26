<?php

namespace Tests\Unit;

use App\Support\Excerpt;
use App\Support\HtmlSanitizer;
use App\Support\Text;
use App\Support\UrlValidator;
use PHPUnit\Framework\TestCase;

class SupportTest extends TestCase
{
    public function test_excerpt_decodes_entities_and_strips_tags(): void
    {
        $html = '<p>Dia Sim, Dia N&#227;o? Custa s&#243; R$ 2,50, tamb&#233;m &amp; mais.</p>';

        $this->assertSame(
            'Dia Sim, Dia Não? Custa só R$ 2,50, também & mais.',
            Excerpt::make($html, 300)
        );
    }

    public function test_excerpt_truncates_by_characters(): void
    {
        $this->assertSame('Não', Excerpt::make('<b>N&#227;o</b>ta', 3));
        $this->assertSame('', Excerpt::make(null, 10));
    }

    /** A fair number of feeds double-encode, so one decode pass is not enough. */
    public function test_decode_entities_handles_double_encoding(): void
    {
        $this->assertSame('Sobre Flow e a crise climática', Text::decodeEntities('Sobre Flow e a crise clim&amp;aacute;tica'));
        $this->assertSame('D&D: Honra entre ladrões', Text::decodeEntities('D&amp;D: Honra entre ladr&#245;es'));
        $this->assertSame('climática', Excerpt::make('<p>clim&amp;aacute;tica</p>', 50));
    }

    public function test_decode_entities_leaves_plain_text_untouched(): void
    {
        $this->assertSame('AT&T & Cia', Text::decodeEntities('AT&T & Cia'));
        $this->assertSame('', Text::decodeEntities(null));
    }

    public function test_plain_trims_and_nulls_empty_fields(): void
    {
        $this->assertSame('Edney "InterNey" Souza', Text::plain('  Edney &quot;InterNey&quot; Souza '));
        $this->assertNull(Text::plain('   '));
        $this->assertNull(Text::plain('&#32;'));
        $this->assertNull(Text::plain(null));
    }

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
