<?php

/*
 * HasDraft, HasVersions and HasLocks are opt-in traits: the engine hides every UI
 * element they drive on models that do not use them, so forgetting one on Content
 * or Fragment costs the panel its "Entwurf speichern" button, its Revisionen tab
 * or its edit lock without a single error. These tests pin all three to the app's
 * own models.
 */

use App\Models\Content;
use App\Models\Fragment;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Mmoollllee\Cms\Filament\Resources\Contents\Pages\EditContent;

it('stashes a draft without touching the live record', function () {
    [$tenant] = actingAsTenantAdmin();

    $page = Content::factory()->for($tenant)->create(['title' => 'Live-Titel']);

    $page->stashDraft(['title' => 'Entwurfs-Titel']);

    $fresh = $page->fresh();

    expect($fresh->hasDraft())->toBeTrue()
        ->and($fresh->title)->toBe('Live-Titel');
});

it('records a snapshot per applied change but never for a draft', function () {
    [$tenant] = actingAsTenantAdmin();

    $page = Content::factory()->for($tenant)->create(['title' => 'Erste Fassung']);

    expect($page->versions()->count())->toBe(1);

    $page->stashDraft(['title' => 'Entwurfs-Fassung']);
    expect($page->fresh()->versions()->count())->toBe(1);

    $page->update(['title' => 'Zweite Fassung']);

    $fresh = $page->fresh();

    expect($fresh->versions()->count())->toBe(2)
        // A draft is unapplied work; carrying the stash into the history would
        // offer editors a restore point that was never live.
        ->and($fresh->lastVersion->contents)->not->toHaveKey('draft');
});

it('versions fragments too', function () {
    [$tenant] = actingAsTenantAdmin();

    $fragment = Fragment::factory()->for($tenant)->create(['title' => 'Erste Fassung']);
    $fragment->update(['title' => 'Zweite Fassung']);

    expect($fragment->fresh()->versions()->count())->toBe(2);
});

it('offers the draft action on the content edit page', function () {
    [$tenant] = actingAsTenantAdmin();

    Filament::setCurrentPanel(Filament::getPanel('panel'));
    Filament::setTenant($tenant);

    $page = Content::factory()->for($tenant)->create(['title' => 'Live-Titel']);

    Livewire::test(EditContent::class, ['record' => $page->getKey()])
        ->assertOk()
        ->fillForm(['title' => 'Entwurfs-Titel'])
        ->call('saveDraft')
        ->assertHasNoFormErrors();

    expect($page->fresh()->title)->toBe('Live-Titel')
        ->and($page->fresh()->hasDraft())->toBeTrue();
});

it('claims an editorial lock so a second editor is blocked', function () {
    [$tenant, $user] = actingAsTenantAdmin();

    $page = Content::factory()->for($tenant)->create();

    // lock() claims the record for the authenticated user, which
    // actingAsTenantAdmin() has already set.
    expect($page->lock())->toBeTrue();

    // resourceLock() is a latestOfMany() relation and the table carries a unique
    // index on the lockable morph, so a record can only ever be held once —
    // without that, take-over would delete one row and leave the other blocking.
    expect($page->fresh()->resourceLock)->not->toBeNull()
        ->and($page->fresh()->resourceLock->user_id)->toBe($user->getKey());
});

it('locks fragments too', function () {
    [$tenant] = actingAsTenantAdmin();

    $fragment = Fragment::factory()->for($tenant)->create();

    expect($fragment->lock())->toBeTrue();

    expect($fragment->fresh()->resourceLock)->not->toBeNull();
});
