<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/** Absolute URL on the seeded demo tenant's domain (= APP_URL host). */
function tenantUrl(string $path = '/'): string
{
    return rtrim(config('app.url'), '/').'/'.ltrim($path, '/');
}
