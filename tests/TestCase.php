<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Assets are built by the "assets" container, not needed for HTTP tests.
        $this->withoutVite();

        // Never hit Discord / Albion for real from a test.
        Http::preventStrayRequests();
    }
}
