<?php

namespace Tests\Feature;

it('responds 200 on the home', function () {
    $this->seedBasicData();

    $this->get('/')->assertOk();
    $this->get('/page/1')->assertOk();
});

it('redirects the query string category 301 to the path', function () {
    $this->seedBasicData();

    $response = $this->get('/?category=blogs');
    $response->assertStatus(301);
    $response->assertRedirect('/category/blogs');
});

it('filters by category and tag', function () {
    $this->seedBasicData();

    $this->get('/category/blogs')->assertOk();
    $this->get('/tag/tecnologia')->assertOk();
    $this->get('/category/blogs/page/1')->assertOk();
});

it('lists items on paginated and filtered routes', function () {
    $this->seedBasicData();

    $urls = [
        '/',
        '/page/1',
        '/tag/tecnologia',
        '/tag/tecnologia/page/1',
        '/category/blogs',
        '/category/blogs/page/1',
    ];

    foreach ($urls as $url) {
        $this->get($url)->assertOk()->assertSee('Artigo de Teste', false);
    }
});

it('returns no items for unknown filters', function () {
    $this->seedBasicData();

    $this->get('/tag/inexistente')->assertOk()->assertDontSee('Artigo de Teste', false);
    $this->get('/category/inexistente')->assertOk()->assertDontSee('Artigo de Teste', false);
});

it('serves the feed listing and detail', function () {
    $this->seedBasicData();

    $this->get('/feeds')->assertOk();
    $this->get('/feeds/page/1')->assertOk();
    $this->get('/feeds/example-com')->assertOk();
    $this->get('/feeds/example-com/page/1')->assertOk();
});

it('redirects an unknown slug to feeds', function () {
    $this->seedBasicData();

    $this->get('/feeds/nao-existe')->assertRedirect('/feeds');
});

it('serves the auxiliary pages', function () {
    $this->seedBasicData();

    $this->get('/categories')->assertOk();
    $this->get('/tags')->assertOk();
    $this->get('/feed-builder')->assertOk();
    $this->get('/suggest-feed')->assertOk();
    $this->get('/shuffle')->assertOk();
});

it('redirects on random', function () {
    $this->seedBasicData();

    $response = $this->get('/random');
    $response->assertStatus(302);

    expect(
        str_starts_with($response->headers->get('Location'), 'https://example.com/') || $response->headers->get('Location') === '/'
    )->toBeTrue();
});

it('returns json with cors on the shuffle ajax', function () {
    $this->seedBasicData();

    $response = $this->get('/shuffle?ajax=1');
    $response->assertOk();
    $response->assertHeader('Access-Control-Allow-Origin', '*');
    $response->assertJsonStructure(['url']);
});
