<?php

/*
 * The contact form is public — anyone on the internet can drive its Livewire
 * component. Everything the SERVER fills in mount() must therefore be #[Locked]:
 * plain public properties are rewritable from the browser snapshot.
 *
 * $contactEmail is the dangerous one. It feeds Mail::to(), so an unlocked
 * property lets an anonymous visitor pick the recipient and send mail from the
 * site's own verified MAIL_FROM_ADDRESS — an open relay burning the domain's
 * mail reputation. $sourceUrl is rendered into the operator mail and would
 * allow content injection.
 */

use App\Livewire\KontaktForm;
use App\Mail\KontaktAnfrage;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();

    $this->tenant = kontaktFormTenant('kontakt-locked.test');
});

it('refuses client writes to every server-filled property', function (string $property, mixed $value) {
    Livewire::test(KontaktForm::class)->set($property, $value);
})
    ->throws(CannotUpdateLockedPropertyException::class)
    ->with([
        'recipient override' => ['contactEmail', 'angreifer@evil.test'],
        'source url' => ['sourceUrl', 'https://evil.test/phish'],
        'content id' => ['contentId', 999],
    ]);

it('sends to the tenant recipient, not to an attacker-chosen address', function () {
    Livewire::test(KontaktForm::class)
        ->set(validKontaktData())
        ->call('submit')
        ->assertHasNoErrors();

    Mail::assertSent(KontaktAnfrage::class, fn ($mail) => $mail->hasTo('betreiber@example.test'));
});
