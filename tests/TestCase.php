<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tenant/menu/branding lookups are cached "forever"; the array store leaks across
        // tests (RefreshDatabase rolls back the DB but not the cache), so start each test
        // with a clean cache to keep tests order-independent.
        Cache::flush();
    }
}
