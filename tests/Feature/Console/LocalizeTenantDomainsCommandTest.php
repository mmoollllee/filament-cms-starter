<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

it('appends .test only to primary domains that lack it', function () {
    $plain = Tenant::factory()->create(['primary_domain' => 'example.com']);
    $already = Tenant::factory()->create(['primary_domain' => 'sibling.test']);

    $this->artisan('app:localize-tenant-domains')->assertSuccessful();

    expect(DB::table('tenants')->where('id', $plain->id)->value('primary_domain'))->toBe('example.com.test')
        ->and(DB::table('tenants')->where('id', $already->id)->value('primary_domain'))->toBe('sibling.test');
});

it('is idempotent when run twice', function () {
    $tenant = Tenant::factory()->create(['primary_domain' => 'example.com']);

    $this->artisan('app:localize-tenant-domains')->assertSuccessful();
    $this->artisan('app:localize-tenant-domains')->assertSuccessful();

    expect(DB::table('tenants')->where('id', $tenant->id)->value('primary_domain'))->toBe('example.com.test');
});

it('refuses to run outside the local or testing environment', function () {
    $tenant = Tenant::factory()->create(['primary_domain' => 'example.com']);

    app()->detectEnvironment(fn () => 'production');

    $this->artisan('app:localize-tenant-domains')->assertFailed();

    expect(DB::table('tenants')->where('id', $tenant->id)->value('primary_domain'))->toBe('example.com');
});
