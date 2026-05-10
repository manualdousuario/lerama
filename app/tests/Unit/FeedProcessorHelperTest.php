<?php

declare(strict_types=1);

namespace Tests\Unit;

use League\CLImate\CLImate;
use Lerama\Commands\FeedProcessor;
use PHPUnit\Framework\TestCase;

class FeedProcessorHelperTest extends TestCase
{
    private \ReflectionMethod $extractNextLink;
    private FeedProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new FeedProcessor(new CLImate());

        $this->extractNextLink = new \ReflectionMethod(FeedProcessor::class, 'extractNextLink');
        $this->extractNextLink->setAccessible(true);
    }

    private function callExtractNextLink(string $feedXml, string $feedUrl): ?string
    {
        return $this->extractNextLink->invoke($this->processor, $feedXml, $feedUrl);
    }

    // -------------------------------------------------------------------------
    // extractNextLink — Atom <link rel="next">
    // -------------------------------------------------------------------------

    public function testExtractsAtomLinkRelNext(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Paginated Atom Feed</title>
  <link rel="self" href="https://example.com/feed?page=1"/>
  <link rel="next" href="https://example.com/feed?page=2"/>
  <entry>
    <id>entry-1</id>
    <title>Entry 1</title>
    <link href="https://example.com/1"/>
    <updated>2025-01-01T00:00:00Z</updated>
  </entry>
</feed>
XML;
        $result = $this->callExtractNextLink($xml, 'https://example.com/feed?page=1');
        $this->assertSame('https://example.com/feed?page=2', $result);
    }

    public function testAtomNextLinkTakesPriorityOverPageQueryParam(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Feed</title>
  <link rel="next" href="https://example.com/feed?cursor=abc123"/>
</feed>
XML;
        $result = $this->callExtractNextLink($xml, 'https://example.com/feed?page=1');
        $this->assertSame('https://example.com/feed?cursor=abc123', $result);
    }

    public function testIncrementsPageQueryParamWhenNoLinkRelNext(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel><title>Paginated RSS</title></channel>
</rss>
XML;
        $result = $this->callExtractNextLink($xml, 'https://example.com/feed?page=3');
        $this->assertSame('https://example.com/feed?page=4', $result);
    }

    public function testPageParamIncrementPreservesOtherQueryParams(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel><title>Feed</title></channel>
</rss>
XML;
        $result = $this->callExtractNextLink($xml, 'https://example.com/feed?cat=tech&page=1&per_page=20');
        $this->assertNotNull($result);
        parse_str((string)parse_url($result, PHP_URL_QUERY), $params);
        $this->assertSame('2', $params['page']);
        $this->assertSame('tech', $params['cat']);
        $this->assertSame('20', $params['per_page']);
    }
    public function testReturnsNullForSimpleFeedWithNoNextLink(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Simple Feed</title>
    <item>
      <title>Only Item</title>
      <link>https://example.com/1</link>
      <guid>only-item</guid>
    </item>
  </channel>
</rss>
XML;
        $result = $this->callExtractNextLink($xml, 'https://example.com/feed');
        $this->assertNull($result);
    }

    public function testReturnsNullWhenAtomHasNoNextLink(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Last Page Feed</title>
  <link rel="self" href="https://example.com/feed?page=5"/>
  <link rel="prev" href="https://example.com/feed?page=4"/>
</feed>
XML;
        $result = $this->callExtractNextLink($xml, 'https://example.com/feed');
        $this->assertNull($result);
    }

    public function testReturnsNullWhenNoPageParamAndNoNextLink(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel><title>Feed</title></channel>
</rss>
XML;
        $result = $this->callExtractNextLink($xml, 'https://example.com/feed?offset=10');
        $this->assertNull($result);
    }
}
