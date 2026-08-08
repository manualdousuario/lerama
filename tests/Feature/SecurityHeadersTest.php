<?php

namespace Tests\Feature;

it('allows third party scripts and beacons', function () {
    $csp = $this->get('/')->headers->get('Content-Security-Policy');

    expect($csp)->toContain("script-src 'self' https: 'unsafe-inline' 'unsafe-eval';")
        ->and($csp)->toContain("connect-src 'self' https:;");
});

it('keeps the frame src exception on shuffle', function () {
    $this->seedBasicData();

    expect($this->get('/shuffle')->headers->get('Content-Security-Policy'))
        ->toContain("frame-src 'self' https: http:;");

    expect($this->get('/')->headers->get('Content-Security-Policy'))
        ->toContain("frame-src 'self';");
});
