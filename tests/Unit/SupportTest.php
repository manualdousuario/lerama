<?php

namespace Tests\Unit;

use App\Support\Excerpt;
use App\Support\HtmlSanitizer;
use App\Support\Text;
use App\Support\UrlValidator;

it('decodes entities and strips tags on the excerpt', function () {
    $html = '<p>Dia Sim, Dia N&#227;o? Custa s&#243; R$ 2,50, tamb&#233;m &amp; mais.</p>';

    expect(Excerpt::make($html, 300))->toBe('Dia Sim, Dia Não? Custa só R$ 2,50, também & mais.');
});

it('truncates the excerpt by characters', function () {
    expect(Excerpt::make('<b>N&#227;o</b>ta', 3))->toBe('Não')
        ->and(Excerpt::make(null, 10))->toBe('');
});

/** A fair number of feeds double-encode, so one decode pass is not enough. */
it('handles double encoding when decoding entities', function () {
    expect(Text::decodeEntities('Sobre Flow e a crise clim&amp;aacute;tica'))->toBe('Sobre Flow e a crise climática')
        ->and(Text::decodeEntities('D&amp;D: Honra entre ladr&#245;es'))->toBe('D&D: Honra entre ladrões')
        ->and(Excerpt::make('<p>clim&amp;aacute;tica</p>', 50))->toBe('climática');
});

it('leaves plain text untouched when decoding entities', function () {
    expect(Text::decodeEntities('AT&T & Cia'))->toBe('AT&T & Cia')
        ->and(Text::decodeEntities(null))->toBe('');
});

it('trims and nulls empty fields on plain', function () {
    expect(Text::plain('  Edney &quot;InterNey&quot; Souza '))->toBe('Edney "InterNey" Souza')
        ->and(Text::plain('   '))->toBeNull()
        ->and(Text::plain('&#32;'))->toBeNull()
        ->and(Text::plain(null))->toBeNull();
});

it('removes scripts and handlers in the sanitizer', function () {
    $dirty = '<p onclick="x()">Hi</p><script>alert(1)</script><a href="javascript:alert(1)">x</a>';
    $clean = HtmlSanitizer::sanitize($dirty);

    expect($clean)->not->toContain('<script')
        ->and($clean)->not->toContain('onclick')
        ->and($clean)->toContain('href="#"')
        ->and($clean)->toContain('<p>Hi</p>');
});

it('neutralizes cdata in the sanitizer', function () {
    expect(HtmlSanitizer::sanitize('a]]>b'))->toBe('a]]]]><![CDATA[>b');
});

it('accepts public http in the url validator', function () {
    expect(UrlValidator::validate('https://example.com/feed')['valid'])->toBeTrue()
        ->and(UrlValidator::validate('http://blog.example.com')['valid'])->toBeTrue();
});

it('rejects ssrf in the url validator', function () {
    foreach (['', 'ftp://example.com', 'http://localhost', 'http://127.0.0.1/x', 'http://192.168.1.1', 'http://10.0.0.5', 'garbage'] as $url) {
        expect(UrlValidator::validate($url)['valid'])->toBeFalse("URL should be rejected: {$url}");
    }
});

it('rejects obfuscated loopback in the url validator', function () {
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
        expect(UrlValidator::validate($url)['valid'])->toBeFalse("URL should be rejected: {$url}");
    }
});
