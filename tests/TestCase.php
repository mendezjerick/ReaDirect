<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        @set_time_limit(0);

        parent::setUp();

        $this->withoutVite();
    }
}
