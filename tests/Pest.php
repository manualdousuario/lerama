<?php

use Tests\TestCase;
use Tests\UnitTestCase;

pest()->extend(TestCase::class)->in('Feature');

pest()->extend(UnitTestCase::class)->in(
    'Unit/FeedLogicTest.php',
    'Unit/FeedSlugServiceTest.php',
    'Unit/ProxyServiceTest.php',
);
