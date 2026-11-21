<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Behave like the SPA: same-origin JSON requests, which is what makes
        // Sanctum treat /api calls as stateful (session + CSRF) requests.
        $this->withHeaders(['Origin' => 'http://localhost', 'Accept' => 'application/json']);
    }
}
