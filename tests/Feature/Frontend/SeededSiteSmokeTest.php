<?php

use App\Models\Content;
use App\Models\Tenant;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('renders the seeded home page', function () {
    $this->get(tenantUrl('/'))
        ->assertOk()
        ->assertSee('Willkommen im Filament-CMS-Starter');
});

it('renders the seeded kontakt page with the live contact form', function () {
    $this->get(tenantUrl('/kontakt'))
        ->assertOk()
        ->assertSeeLivewire('kontakt-form');
});

it('serves robots.txt and sitemap.xml for the tenant', function () {
    $this->get(tenantUrl('/robots.txt'))->assertOk();
    $this->get(tenantUrl('/sitemap.xml'))->assertOk();
});

it('lists published content in the sitemap and leaves unpublished out', function () {
    $tenant = Tenant::query()->where('site_key', 'default')->sole();

    Content::factory()->for($tenant)->create(['title' => 'Sichtbar', 'path' => '/sichtbare-seite']);
    Content::factory()->for($tenant)->draft()->create(['title' => 'Entwurf', 'path' => '/entwurfsseite']);

    $response = $this->get(tenantUrl('/sitemap.xml'))->assertOk();

    expect($response->getContent())
        ->toContain('/sichtbare-seite')
        ->not->toContain('/entwurfsseite');
});

/**
 * assertOk() above cannot catch the real failure mode: a static public/robots.txt is
 * served by the webserver before the request reaches Laravel, so the route is shadowed in
 * production while the test — which goes straight to the router — stays green. Assert the
 * controller's own output, and that no static file exists to shadow it.
 */
it('advertises the sitemap in robots.txt', function () {
    $this->get(tenantUrl('/robots.txt'))->assertSee('Sitemap: '.tenantUrl('/sitemap.xml'));
});

it('ships no static robots.txt that would shadow the route', function () {
    expect(public_path('robots.txt'))->not->toBeFile();
});

/**
 * Browsers request these at the document root whatever the page's <link> tags say, so a
 * project without them collects 404s no one sees. Placeholders ship with the starter —
 * replace them per project (see "Projekt anpassen" in the README).
 */
it('serves the root icons browsers request without being told to', function (string $file) {
    expect(public_path($file))->toBeFile()
        ->and(filesize(public_path($file)))->toBeGreaterThan(0);
})->with([
    'favicon.ico',
    'apple-touch-icon.png',
    'apple-touch-icon-precomposed.png',
]);

it('returns 404 for unknown paths', function () {
    $this->get(tenantUrl('/gibt-es-nicht'))->assertNotFound();
});
