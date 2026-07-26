<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_third_party_scripts_and_beacons_are_allowed(): void
    {
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("script-src 'self' https: 'unsafe-inline' 'unsafe-eval';", $csp);
        $this->assertStringContainsString("connect-src 'self' https:;", $csp);
    }

    public function test_shuffle_keeps_its_frame_src_exception(): void
    {
        $this->seedBasicData();

        $this->assertStringContainsString(
            "frame-src 'self' https: http:;",
            $this->get('/shuffle')->headers->get('Content-Security-Policy')
        );
        $this->assertStringContainsString(
            "frame-src 'self';",
            $this->get('/')->headers->get('Content-Security-Policy')
        );
    }
}
