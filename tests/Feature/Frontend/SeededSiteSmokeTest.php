<?php

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

it('returns 404 for unknown paths', function () {
    $this->get(tenantUrl('/gibt-es-nicht'))->assertNotFound();
});
