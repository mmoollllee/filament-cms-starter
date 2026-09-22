<?php

/*
 * Site access (members + invitations) comes from filament-tenant-access, wired by
 * the engine. What the app owns is the schema — `tenant_invitations` is one of
 * the engine migrations this repo keeps its own copy of — and a queue/mailer the
 * invitation mail can go out through. These tests pin that wiring: inviting,
 * accepting the signed link, and that ending a membership leaves the account
 * alone.
 */

use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Mmoollllee\Cms\Enums\TenantUserRole;
use Mmoollllee\Cms\Filament\Resources\Users\Pages\ListUsers;
use Mmoollllee\Cms\Mail\TenantInvitationMail;
use Mmoollllee\FilamentTenantAccess\Support\TenantInvitations;

/**
 * actingAsTenantAdmin() primes CurrentTenant and the auth user, which is all an
 * HTTP panel request needs. A Livewire::test() call has no request to resolve
 * the panel and tenant from, so both are set explicitly.
 *
 * @return array{0: Tenant, 1: User}
 */
function actingAsPanelAdmin(string $domain): array
{
    [$tenant, $user] = actingAsTenantAdmin('default', $domain);

    Filament::setCurrentPanel(Filament::getPanel('panel'));
    Filament::setTenant($tenant);

    return [$tenant, $user];
}

it('allows tenant admins to see the user list', function () {
    [$tenant] = actingAsTenantAdmin('default', 'user-list-admin.test');

    $this->get("http://{$tenant->primary_domain}/panel/users")
        ->assertSuccessful()
        ->assertSee('Benutzer');
});

it('forbids tenant editors from seeing the user list', function () {
    $tenant = Tenant::factory()->create(['primary_domain' => 'user-list-editor.test']);

    $editor = User::factory()->create();
    $tenant->users()->attach($editor, ['role' => TenantUserRole::Editor->value]);

    $this->actingAs($editor)
        ->get("http://{$tenant->primary_domain}/panel/users")
        ->assertForbidden();
});

it('lets a tenant admin invite a user by mail', function () {
    Mail::fake();

    [$tenant] = actingAsPanelAdmin('user-invite.test');

    Livewire::test(ListUsers::class)
        ->callAction('invite', ['email' => 'neu@example.test', 'role' => TenantUserRole::Editor->value]);

    expect($tenant->tenantInvitations()->where('email', 'neu@example.test')->exists())->toBeTrue();

    Mail::assertQueued(TenantInvitationMail::class, fn (TenantInvitationMail $mail): bool => $mail->hasTo('neu@example.test'));
});

it('attaches an invited user on accepting the signed link', function () {
    Mail::fake();

    [$tenant] = actingAsPanelAdmin('user-accept.test');

    $invitation = app(TenantInvitations::class)
        ->invite($tenant, 'neu@example.test', TenantUserRole::Editor);

    $invited = User::factory()->create(['email' => 'neu@example.test']);

    $this->actingAs($invited)
        ->get($invitation->acceptUrl())
        ->assertRedirect();

    expect($invited->fresh()->tenantRole($tenant))->toBe(TenantUserRole::Editor);
});

it('ends a membership without deleting the account', function () {
    [$tenant] = actingAsPanelAdmin('user-remove.test');

    $member = User::factory()->create();
    $tenant->addUser($member, TenantUserRole::Editor);

    Livewire::test(ListUsers::class)
        // Members and pending invitations share one table, so rows are arrays
        // keyed by type rather than Eloquent records.
        ->callAction(TestAction::make('remove')->table('member-'.$member->getKey()));

    expect($tenant->fresh()->hasUser($member))->toBeFalse()
        ->and(User::query()->whereKey($member->getKey())->exists())->toBeTrue();
});

it('lists a pending invitation next to the members', function () {
    Mail::fake();

    [$tenant] = actingAsPanelAdmin('user-invite-row.test');

    app(TenantInvitations::class)->invite($tenant, 'wartet@example.test', TenantUserRole::Editor);

    Livewire::test(ListUsers::class)
        ->assertSuccessful()
        // AppServiceProvider::configureTable() defers table loading globally, so
        // the first render is a skeleton and the rows arrive on demand.
        ->call('loadTable')
        ->assertSee('wartet@example.test')
        ->assertSee('Eingeladen, gültig bis');
});

it('keeps account deletion and direct assignment for superadmins', function () {
    [$tenant] = actingAsPanelAdmin('user-delete-gate.test');

    $member = User::factory()->create();
    $tenant->addUser($member, TenantUserRole::Editor);

    expect(auth()->user()->can('delete', $member))->toBeFalse();

    Livewire::test(ListUsers::class)->assertActionHidden('assignDirectly');

    $this->actingAs(User::factory()->superadmin()->create());

    Livewire::test(ListUsers::class)->assertActionVisible('assignDirectly');
});
