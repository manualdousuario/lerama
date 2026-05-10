<?php

declare(strict_types=1);

namespace Tests\Unit;

use Laminas\Feed\Reader\Reader;
use PHPUnit\Framework\TestCase;

class LaminasFeedReaderTest extends TestCase
{
    private function rss2Fixture(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/">
  <channel>
    <title>Test RSS 2.0 Feed</title>
    <link>https://example.com</link>
    <description>Feed description</description>
    <item>
      <title>Post Title One</title>
      <link>https://example.com/post-1</link>
      <guid>https://example.com/post-1</guid>
      <pubDate>Wed, 01 Jan 2025 12:00:00 +0000</pubDate>
      <author>author@example.com (John Doe)</author>
      <description>Short description fallback</description>
      <content:encoded><![CDATA[<p>Full HTML content here</p>]]></content:encoded>
    </item>
    <item>
      <title>Post Title Two</title>
      <link>https://example.com/post-2</link>
      <guid>unique-guid-post-2</guid>
      <pubDate>Tue, 31 Dec 2024 09:30:00 +0000</pubDate>
      <description>Second post description</description>
    </item>
  </channel>
</rss>
XML;
    }

    private function atomFixture(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test Atom 1.0 Feed</title>
  <link href="https://example.com"/>
  <id>https://example.com/feed</id>
  <entry>
    <title>Atom Entry One</title>
    <link href="https://example.com/atom-1"/>
    <id>urn:uuid:atom-entry-1-unique</id>
    <published>2025-03-15T10:30:00Z</published>
    <updated>2025-03-15T11:00:00Z</updated>
    <author>
      <name>Jane Smith</name>
      <email>jane@example.com</email>
    </author>
    <content type="html"><![CDATA[<p>Atom entry full content</p>]]></content>
  </entry>
  <entry>
    <title>Atom Entry Two</title>
    <link href="https://example.com/atom-2"/>
    <id>urn:uuid:atom-entry-2-unique</id>
    <updated>2025-02-10T08:00:00Z</updated>
    <content type="text">Plain text content</content>
  </entry>
</feed>
XML;
    }

    private function rss1Fixture(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns="http://purl.org/rss/1.0/"
         xmlns:dc="http://purl.org/dc/elements/1.1/">
  <channel rdf:about="https://example.com/rss1">
    <title>Test RSS 1.0 Feed</title>
    <link>https://example.com</link>
    <description>RSS 1.0 feed description</description>
    <items>
      <rdf:Seq>
        <rdf:li rdf:resource="https://example.com/rss1-item-1"/>
      </rdf:Seq>
    </items>
  </channel>
  <item rdf:about="https://example.com/rss1-item-1">
    <title>RSS 1.0 Item</title>
    <link>https://example.com/rss1-item-1</link>
    <description>RSS 1.0 item description</description>
    <dc:creator>RSS Author</dc:creator>
    <dc:date>2025-04-01T08:00:00+00:00</dc:date>
  </item>
</rdf:RDF>
XML;
    }

    public function testRss2FeedTitle(): void
    {
        $reader = Reader::importString($this->rss2Fixture());
        $this->assertSame('Test RSS 2.0 Feed', $reader->getTitle());
    }

    public function testRss2ItemCount(): void
    {
        $reader = Reader::importString($this->rss2Fixture());
        $this->assertCount(2, $reader);
    }

    public function testRss2EntryId(): void
    {
        $reader = Reader::importString($this->rss2Fixture());
        $reader->rewind();
        $this->assertSame('https://example.com/post-1', $reader->current()->getId());
    }

    public function testRss2EntryTitle(): void
    {
        $reader = Reader::importString($this->rss2Fixture());
        $reader->rewind();
        $this->assertSame('Post Title One', $reader->current()->getTitle());
    }

    public function testRss2EntryContentEncodedTakesPriority(): void
    {
        // content:encoded must win over <description> — same behaviour as SimplePie
        $reader = Reader::importString($this->rss2Fixture());
        $reader->rewind();
        $this->assertStringContainsString('Full HTML content here', $reader->current()->getContent());
    }

    public function testRss2EntryLink(): void
    {
        $reader = Reader::importString($this->rss2Fixture());
        $reader->rewind();
        $this->assertSame('https://example.com/post-1', $reader->current()->getLink());
    }

    public function testRss2EntryDateFormat(): void
    {
        $reader = Reader::importString($this->rss2Fixture());
        $reader->rewind();
        $date = $reader->current()->getDateCreated();
        $this->assertInstanceOf(\DateTime::class, $date);
        $this->assertSame('2025-01-01 12:00:00', $date->format('Y-m-d H:i:s'));
    }

    public function testRss2SecondEntryIdIsCustomGuid(): void
    {
        $reader = Reader::importString($this->rss2Fixture());
        $reader->rewind();
        $reader->next();
        $this->assertSame('unique-guid-post-2', $reader->current()->getId());
    }

    public function testRss2EntryWithNoContentEncodedFallsBackToDescription(): void
    {
        $reader = Reader::importString($this->rss2Fixture());
        $reader->rewind();
        $reader->next();
        $this->assertStringContainsString('Second post description', $reader->current()->getContent());
    }

    public function testAtomFeedTitle(): void
    {
        $reader = Reader::importString($this->atomFixture());
        $this->assertSame('Test Atom 1.0 Feed', $reader->getTitle());
    }

    public function testAtomItemCount(): void
    {
        $reader = Reader::importString($this->atomFixture());
        $this->assertCount(2, $reader);
    }

    public function testAtomEntryId(): void
    {
        $reader = Reader::importString($this->atomFixture());
        $reader->rewind();
        $this->assertSame('urn:uuid:atom-entry-1-unique', $reader->current()->getId());
    }

    public function testAtomEntryTitle(): void
    {
        $reader = Reader::importString($this->atomFixture());
        $reader->rewind();
        $this->assertSame('Atom Entry One', $reader->current()->getTitle());
    }

    public function testAtomEntryLink(): void
    {
        $reader = Reader::importString($this->atomFixture());
        $reader->rewind();
        $this->assertSame('https://example.com/atom-1', $reader->current()->getLink());
    }

    public function testAtomEntryAuthorName(): void
    {
        $reader = Reader::importString($this->atomFixture());
        $reader->rewind();
        $authors = $reader->current()->getAuthors();
        $name = null;
        foreach ($authors as $a) {
            $name = $a['name'] ?? null;
            break;
        }
        $this->assertSame('Jane Smith', $name);
    }

    public function testAtomEntryContent(): void
    {
        $reader = Reader::importString($this->atomFixture());
        $reader->rewind();
        $this->assertStringContainsString('Atom entry full content', $reader->current()->getContent());
    }

    public function testAtomEntryDateCreated(): void
    {
        $reader = Reader::importString($this->atomFixture());
        $reader->rewind();
        $date = $reader->current()->getDateCreated();
        $this->assertInstanceOf(\DateTime::class, $date);
        $this->assertSame('2025-03-15', $date->format('Y-m-d'));
    }

    public function testAuthorPatternReturnsNullWhenFeedHasNoAuthors(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Authorless Feed</title>
  <id>https://example.com/authorless</id>
  <entry>
    <title>Entry without author</title>
    <link href="https://example.com/no-author"/>
    <id>no-author-entry</id>
    <updated>2025-01-01T00:00:00Z</updated>
    <content type="text">Content here</content>
  </entry>
</feed>
XML;
        $reader = Reader::importString($xml);
        $reader->rewind();
        $authors = $reader->current()->getAuthors();
        $author = null;
        foreach ($authors as $a) {
            $author = $a['name'] ?? null;
            break;
        }
        $this->assertNull($author);
    }

    public function testRss1FeedTitle(): void
    {
        $reader = Reader::importString($this->rss1Fixture());
        $this->assertSame('Test RSS 1.0 Feed', $reader->getTitle());
    }

    public function testRss1EntryTitle(): void
    {
        $reader = Reader::importString($this->rss1Fixture());
        $reader->rewind();
        $this->assertSame('RSS 1.0 Item', $reader->current()->getTitle());
    }

    public function testRss1EntryLink(): void
    {
        $reader = Reader::importString($this->rss1Fixture());
        $reader->rewind();
        $this->assertSame('https://example.com/rss1-item-1', $reader->current()->getLink());
    }

    public function testRss1EntryDate(): void
    {
        $reader = Reader::importString($this->rss1Fixture());
        $reader->rewind();
        $date = $reader->current()->getDateCreated();
        $this->assertInstanceOf(\DateTime::class, $date);
        $this->assertSame('2025-04-01', $date->format('Y-m-d'));
    }

    public function testEntryWithNoDateReturnsNull(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>No Date Feed</title>
    <item>
      <title>Dateless Post</title>
      <link>https://example.com/dateless</link>
      <guid>dateless-guid</guid>
    </item>
  </channel>
</rss>
XML;
        $reader = Reader::importString($xml);
        $reader->rewind();
        $this->assertNull($reader->current()->getDateCreated());
    }

    public function testDateFormatsToMysqlDatetime(): void
    {
        $reader = Reader::importString($this->rss2Fixture());
        $reader->rewind();
        $dateObj = $reader->current()->getDateCreated() ?? $reader->current()->getDateModified();
        $date = $dateObj ? $dateObj->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date);
    }
}
