<?php

/*
 * Notice banners ("Hinweise") are on in the starter (Cms::enableNotices()): the
 * package's page template shows a live notice at the top of the start section,
 * and nowhere else — every other section renders the same template.
 */

use App\Models\Content;
use App\Models\Tenant;
use Database\Seeders\DatabaseSeeder;
use Mmoollllee\Cms\Sites\Notice\Blueprint as NoticeBlueprint;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->tenant = Tenant::query()->where('site_key', 'default')->sole();
});

it('shows a live notice at the top of the start section only', function () {
    Content::factory()->for($this->tenant)->create([
        'content_type' => NoticeBlueprint::KEY,
        'title' => 'Betriebsurlaub',
        'path' => null,
        'payload' => ['content' => '<p>Vom 23.12. bis 06.01. sind wir im Urlaub.</p>'],
        'publish_from' => now()->subDay(),
    ]);

    $this->get(tenantUrl('/'))
        ->assertOk()
        ->assertSeeInOrder(['Vom 23.12. bis 06.01. sind wir im Urlaub.', 'Willkommen im Filament-CMS-Starter']);

    // Deep links render another section inline (the start section loads lazily),
    // and the legal pages carry no banner.
    foreach (['/kontakt', '/impressum'] as $path) {
        $this->get(tenantUrl($path))->assertOk()->assertDontSee('Vom 23.12. bis 06.01. sind wir im Urlaub.');
    }
});

it('renders no banner without a live notice', function () {
    $this->get(tenantUrl('/'))
        ->assertOk()
        ->assertDontSee('role="status"', false);
});
