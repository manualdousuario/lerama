<?php

namespace Tests\Feature;

use Tests\TestCase;

class FeedOutputContractTest extends TestCase
{
    public function test_feed_json_keeps_legacy_contract(): void
    {
        $this->seedBasicData();

        $response = $this->get('/feed/json');
        $response->assertOk();
        $response->assertJsonStructure([
            'items' => [
                '*' => [
                    'id', 'title', 'author', 'content', 'url', 'image_url', 'published_at',
                    'feed' => ['title', 'site_url'],
                ],
            ],
            'pagination' => ['total_items', 'total_pages', 'current_page', 'per_page'],
        ]);

        $item = $response->json('items.0');
        $this->assertSame('Autor em Blog de Teste', $item['author']);
        $this->assertStringContainsString('Leia no <a href=', $item['content']);
    }

    public function test_feed_json_filters_by_category_and_tag(): void
    {
        $this->seedBasicData();

        $this->get('/feed/json?category=blogs')->assertJsonCount(1, 'items');
        $this->get('/feed/json?tag=tecnologia')->assertJsonCount(1, 'items');
        $this->get('/feed/json?category=nao-existe')->assertJsonCount(0, 'items');
    }

    public function test_feed_rss_is_valid_xml_with_channel(): void
    {
        $this->seedBasicData();

        $response = $this->get('/feed/rss');
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);
        $this->assertSame('Lerama', (string) $xml->channel->title);
        $this->assertCount(1, $xml->channel->item);
        $this->assertSame('Artigo de Teste', (string) $xml->channel->item[0]->title);
    }

    public function test_feed_root_alias(): void
    {
        $this->seedBasicData();

        $this->get('/feed')->assertOk();
    }
}
