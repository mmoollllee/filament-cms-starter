<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tenant/menu/branding lookups are cached "forever"; the array store leaks across
        // tests (RefreshDatabase rolls back the DB but not the cache), so start each test
        // with a clean cache to keep tests order-independent.
        Cache::flush();

        // Saving a Content walks its media blocks (ConvertsUploadedVideos →
        // VideoConversionHelper), which reads the public disk — and any test that
        // uploads would otherwise leave its fixture in the real storage/app/public,
        // next to production media. Faked here rather than per test so that stays
        // true for tests written later.
        Storage::fake('public');
    }
}
