<?php

namespace Tests\Unit;

use App\Services\Feeds\FeedProcessor;
use App\Services\FeedTypeDetector;
use App\Services\ItemCountService;
use App\Services\ProxyService;
use ReflectionMethod;

beforeEach(function () {
    $this->detector = new FeedTypeDetector;
});

it('detects a json feed', function () {
    expect($this->detector->detectTypeFromContent('{"version":"https://jsonfeed.org/version/1","items":[]}'))
        ->toBe('json');
});

it('detects csv', function () {
    expect($this->detector->detectTypeFromContent("title,url,guid\nA,http://x,1"))->toBe('csv');
});

it('detects rss2', function () {
    $xml = '<?xml version="1.0"?><rss version="2.0"><channel><title>T</title><item><title>I</title></item></channel></rss>';

    expect($this->detector->detectTypeFromContent($xml))->toBe('rss2');
});

it('detects atom', function () {
    $xml = '<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom"><title>T</title><entry><id>1</id></entry></feed>';

    expect($this->detector->detectTypeFromContent($xml))->toBe('atom');
});

it('detects rdf', function () {
    $xml = '<?xml version="1.0"?><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"></rdf:RDF>';

    expect($this->detector->detectTypeFromContent($xml))->toBe('rdf');
});

it('returns null for invalid content', function () {
    expect($this->detector->detectTypeFromContent('plain text, no commas alone'))->toBeNull();
});

it('extracts the atom next link', function () {
    $processor = feedLogicProcessor();
    $method = new ReflectionMethod(FeedProcessor::class, 'extractNextLink');
    $method->setAccessible(true);

    $xml = '<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom"><link rel="next" href="http://example.com/feed?page=2"/></feed>';

    expect($method->invoke($processor, $xml, 'http://example.com/feed'))
        ->toBe('http://example.com/feed?page=2');
});

it('increments the page when extracting the next link', function () {
    $processor = feedLogicProcessor();
    $method = new ReflectionMethod(FeedProcessor::class, 'extractNextLink');
    $method->setAccessible(true);

    $xml = '<?xml version="1.0"?><rss version="2.0"><channel></channel></rss>';

    expect($method->invoke($processor, $xml, 'http://example.com/feed?page=2&x=1'))
        ->toBe('http://example.com/feed?page=3&x=1');
});

it('checks for real content', function () {
    $processor = feedLogicProcessor();

    expect($processor->checkRealContent('<p>Conteúdo normal</p>')['status'])->toBe('visible')
        ->and($processor->checkRealContent(null)['status'])->toBe('visible')
        ->and($processor->checkRealContent('<p>wp-login.php?action=postpass</p>')['status'])->toBe('invisible')
        ->and($processor->checkRealContent('<p>Este conteúdo exclusivo para assinantes</p>')['status'])->toBe('invisible')
        ->and($processor->checkRealContent('<p>resumo</p><p><a href="https://x.substack.com/p/1">Read more</a></p>', 'https://x.substack.com/p/1')['status'])->toBe('invisible');
});

function feedLogicProcessor(): FeedProcessor
{
    return new FeedProcessor(new ProxyService, new ItemCountService);
}
