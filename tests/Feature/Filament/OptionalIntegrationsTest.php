<?php

/*
 * The media library and the Umami analytics page are OPTIONAL in the engine:
 * every code path gates on the package being installed, and hides itself
 * silently when it is not. The starter ships both, so "silently hidden" would
 * look exactly like "working" — these tests pin that they are actually on.
 */

use Filament\Facades\Filament;
use Mmoollllee\Cms\Support\Analytics\Umami;
use Mmoollllee\Cms\Support\Media\MediaLibrary;
use Mmoollllee\Filami\Filament\Pages\UmamiStatistics;

it('has the media library active, with the picker extensions', function () {
    expect(MediaLibrary::enabled())->toBeTrue()
        ->and(MediaLibrary::extensionsInstalled())->toBeTrue();
});

it('registers the Mediathek page on the panel', function () {
    $panel = Filament::getPanel('panel');

    expect($panel->hasPlugin('ralphjsmit/laravel-filament-media-library'))->toBeTrue();
});

it('registers the Umami statistics page on the panel', function () {
    expect(Umami::installed())->toBeTrue()
        ->and(Filament::getPanel('panel')->getPages())->toContain(UmamiStatistics::class);
});

it('hides the statistics page while Umami is unconfigured', function () {
    // No UMAMI_URL and no per-tenant website id — the page must not advertise
    // itself, or a fresh install shows a menu entry that leads nowhere.
    [$tenant] = actingAsTenantAdmin();

    Filament::setCurrentPanel(Filament::getPanel('panel'));
    Filament::setTenant($tenant);

    expect(UmamiStatistics::canAccess())->toBeFalse();
});
