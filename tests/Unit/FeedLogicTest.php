<?php

namespace Tests\Unit;

use App\Services\Feeds\FeedProcessor;
use App\Services\FeedTypeDetector;
use App\Services\ItemCountService;
use App\Services\ProxyService;
use Tests\UnitTestCase as TestCase;

class FeedLogicTest extends TestCase
{
    private FeedTypeDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new FeedTypeDetector;
    }

    public function test_detects_json_feed(): void
    {
        $this->assertSame('json', $this->detector->detectTypeFromContent('{"version":"https://jsonfeed.org/version/1","items":[]}'));
    }

    public function test_detects_csv(): void
    {
        $this->assertSame('csv', $this->detector->detectTypeFromContent("title,url,guid\nA,http://x,1"));
    }

    public function test_detects_rss2(): void
    {
        $xml = '<?xml version="1.0"?><rss version="2.0"><channel><title>T</title><item><title>I</title></item></channel></rss>';
        $this->assertSame('rss2', $this->detector->detectTypeFromContent($xml));
    }

    public function test_detects_atom(): void
    {
        $xml = '<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom"><title>T</title><entry><id>1</id></entry></feed>';
        $this->assertSame('atom', $this->detector->detectTypeFromContent($xml));
    }

    public function test_detects_rdf(): void
    {
        $xml = '<?xml version="1.0"?><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"></rdf:RDF>';
        $this->assertSame('rdf', $this->detector->detectTypeFromContent($xml));
    }

    public function test_invalid_content_returns_null(): void
    {
        $this->assertNull($this->detector->detectTypeFromContent('plain text, no commas alone'));
    }

    public function test_extract_next_link_atom(): void
    {
        $processor = $this->processor();
        $method = new \ReflectionMethod(FeedProcessor::class, 'extractNextLink');
        $method->setAccessible(true);

        $xml = '<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom"><link rel="next" href="http://example.com/feed?page=2"/></feed>';
        $this->assertSame('http://example.com/feed?page=2', $method->invoke($processor, $xml, 'http://example.com/feed'));
    }

    public function test_extract_next_link_increments_page(): void
    {
        $processor = $this->processor();
        $method = new \ReflectionMethod(FeedProcessor::class, 'extractNextLink');
        $method->setAccessible(true);

        $xml = '<?xml version="1.0"?><rss version="2.0"><channel></channel></rss>';
        $this->assertSame(
            'http://example.com/feed?page=3&x=1',
            $method->invoke($processor, $xml, 'http://example.com/feed?page=2&x=1')
        );
    }

    public function test_check_real_content(): void
    {
        $processor = $this->processor();

        $this->assertSame('visible', $processor->checkRealContent('<p>Conteúdo normal</p>')['status']);
        $this->assertSame('visible', $processor->checkRealContent(null)['status']);
        $this->assertSame('invisible', $processor->checkRealContent('<p>wp-login.php?action=postpass</p>')['status']);
        $this->assertSame('invisible', $processor->checkRealContent('<p>Este conteúdo exclusivo para assinantes</p>')['status']);
        $this->assertSame(
            'invisible',
            $processor->checkRealContent('<p>resumo</p><p><a href="https://x.substack.com/p/1">Read more</a></p>', 'https://x.substack.com/p/1')['status']
        );
    }

    private function processor(): FeedProcessor
    {
        return new FeedProcessor(new ProxyService, new ItemCountService);
    }
}
