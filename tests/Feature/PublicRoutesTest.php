<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicRoutesTest extends TestCase
{
    public function test_home_responds_200(): void
    {
        $this->seedBasicData();

        $this->get('/')->assertOk();
        $this->get('/page/1')->assertOk();
    }

    public function test_query_string_category_redirects_301_to_path(): void
    {
        $this->seedBasicData();

        $response = $this->get('/?category=blogs');
        $response->assertStatus(301);
        $response->assertRedirect('/category/blogs');
    }

    public function test_category_and_tag_filters(): void
    {
        $this->seedBasicData();

        $this->get('/category/blogs')->assertOk();
        $this->get('/tag/tecnologia')->assertOk();
        $this->get('/category/blogs/page/1')->assertOk();
    }

    public function test_feed_listing_and_detail(): void
    {
        $this->seedBasicData();

        $this->get('/feeds')->assertOk();
        $this->get('/feeds/page/1')->assertOk();
        $this->get('/feeds/example-com')->assertOk();
        $this->get('/feeds/example-com/page/1')->assertOk();
    }

    public function test_unknown_slug_redirects_to_feeds(): void
    {
        $this->seedBasicData();

        $this->get('/feeds/nao-existe')->assertRedirect('/feeds');
    }

    public function test_auxiliary_pages(): void
    {
        $this->seedBasicData();

        $this->get('/categories')->assertOk();
        $this->get('/tags')->assertOk();
        $this->get('/feed-builder')->assertOk();
        $this->get('/suggest-feed')->assertOk();
        $this->get('/shuffle')->assertOk();
    }

    public function test_random_redirects(): void
    {
        $this->seedBasicData();

        $response = $this->get('/random');
        $response->assertStatus(302);
        $this->assertTrue(
            str_starts_with($response->headers->get('Location'), 'https://example.com/') || $response->headers->get('Location') === '/'
        );
    }

    public function test_shuffle_ajax_returns_json_with_cors(): void
    {
        $this->seedBasicData();

        $response = $this->get('/shuffle?ajax=1');
        $response->assertOk();
        $response->assertHeader('Access-Control-Allow-Origin', '*');
        $response->assertJsonStructure(['url']);
    }
}
