<?php

namespace Tests;

use App\Models\Badge;
use App\Models\StoreItem;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Behave like the SPA: same-origin JSON requests, which is what makes
        // Sanctum treat /api calls as stateful (session + CSRF) requests.
        $this->withHeaders(['Origin' => 'http://localhost', 'Accept' => 'application/json']);

        // The migrations add starting content (a default badge, default store items).
        // Tests start from an empty catalogue so each one states what it relies on.
        if (Schema::hasTable('badges')) {
            Badge::query()->delete();
            StoreItem::query()->delete();
        }
    }
}
