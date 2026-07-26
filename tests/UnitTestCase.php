<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

// Base for unit tests that need the container (config(), app()) but no database.
abstract class UnitTestCase extends BaseTestCase
{
    //
}
