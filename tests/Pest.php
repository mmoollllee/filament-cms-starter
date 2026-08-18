<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mmoollllee\Cms\Enums\TenantUserRole;
use Mmoollllee\Cms\Support\Tenancy\CurrentTenant;
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

/**
 * Shared panel-test bootstrap: creates a tenant with an attached admin user,
 * primes the CurrentTenant singleton the policies read, and authenticates.
 *
 * @return array{0: Tenant, 1: User}
 */
function actingAsTenantAdmin(string $siteKey = 'default', string $domain = 'panel-wiring.test'): array
{
    $tenant = Tenant::factory()->create([
        'site_key' => $siteKey,
        'primary_domain' => $domain,
    ]);

    $user = User::factory()->create();
    $tenant->users()->attach($user, ['role' => TenantUserRole::Admin->value]);

    app(CurrentTenant::class)->set($tenant);

    test()->actingAs($user);

    return [$tenant, $user];
}

/**
 * A tenant the contact form can actually be submitted against: a recipient for
 * the operator mail, plus the one spam question {@see validKontaktData()}
 * answers. The two belong together — TenantFactory's default question has a
 * different answer, and a mismatch surfaces as "no mail sent" rather than as a
 * wrong fixture — so they are built in one place instead of copied into every
 * form test's setup.
 */
function kontaktFormTenant(string $domain): Tenant
{
    $tenant = Tenant::factory()->create([
        'site_key' => 'default',
        'primary_domain' => $domain,
        'contact_email' => 'betreiber@example.test',
        'spam_questions' => [['question' => 'Was ergibt drei plus vier?', 'answer' => '7, sieben']],
    ]);

    app(CurrentTenant::class)->set($tenant);

    return $tenant;
}

/**
 * A complete, valid contact-form submission, paired with the tenant above.
 * Lives here rather than in a test file so every suite shares one fixture — a
 * stale second copy left behind by a new required field fails as "no event
 * dispatched", which reads as broken analytics rather than an outdated payload.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validKontaktData(array $overrides = []): array
{
    return array_merge([
        'data.name' => 'Erika Musterfrau',
        'data.email' => 'erika@besucher.test',
        'data.phone' => '+49 700 1234',
        'data.message' => 'Bitte um ein Angebot.',
        'data.privacy_accepted' => true,
        'quizAnswer' => '7',
    ], $overrides);
}
