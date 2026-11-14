<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mmoollllee\Cms\Support\CacheKeys;
use Mmoollllee\Cms\Support\ModelCache;

/*
 * A DB pull replaces the tenants under the running local copy, and the pulled
 * tenant answers on the *.test host that copy already served (deploy.php,
 * pull:localize-domains). The host → tenant lookup is cached forever, so the hook
 * has to clear the tenants' caches too — or the site keeps the pre-pull tenant.
 */

/**
 * The artisan commands deploy.php's pull:localize-domains task runs, in order.
 *
 * @return list<string>
 */
function pullLocalizeCommands(): array
{
    preg_match("/task\('pull:localize-domains', function \(\) \{(.*?)\n\}\);/s", file_get_contents(base_path('deploy.php')), $task);
    preg_match_all("/runLocally\('php artisan (.+?)'\);/", $task[1] ?? '', $commands);

    return $commands[1];
}

it('serves the pulled tenant on the local host once the pull hook ran', function () {
    $localHost = 'pull-fixture.de.test';
    $tenant = Tenant::factory()->create(['primary_domain' => $localHost, 'brand_name' => 'Vor dem Pull']);

    // A request before the pull cached the host's tenant.
    Cache::forever(CacheKeys::tenantDomain($localHost), ModelCache::pack($tenant));

    // The pull brings production's row, on the production domain.
    DB::table('tenants')->where('id', $tenant->id)->update(['primary_domain' => 'pull-fixture.de', 'brand_name' => 'Aus dem Pull']);

    expect(pullLocalizeCommands())->not->toBeEmpty();

    foreach (pullLocalizeCommands() as $command) {
        expect(Artisan::call($command))->toBe(0);
    }

    $resolved = ModelCache::unpack(Tenant::class, Cache::get(CacheKeys::tenantDomain($localHost)));

    expect(Tenant::find($tenant->id)->primary_domain)->toBe($localHost)
        ->and($resolved?->brand_name)->not->toBe('Vor dem Pull');
});
