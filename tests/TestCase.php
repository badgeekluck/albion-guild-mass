<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Assets are built by the "assets" container, not needed for HTTP tests.
        $this->withoutVite();
    }
}
