<?php

/*
 * The contact form reports a conversion to Umami through the `filami-track`
 * bridge that <x-filami::events /> installs in the site layout.
 *
 * Two things matter here. The event must fire only for submissions that
 * actually produced mail — otherwise the dashboard counts bots and typos as
 * inquiries. And its payload must stay free of anything about the sender: it is
 * stored next to the pageview in Umami, which is not where a name, an address
 * or a message belongs.
 */

use App\Livewire\KontaktForm;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();

    $this->tenant = kontaktFormTenant('kontakt-umami.test');
});

it('marks the form so the start of the funnel is measured too', function () {
    Livewire::test(KontaktForm::class)
        ->assertSeeHtml('data-filami-form="contact-form"');
});

it('reports the conversion once the mails are out', function () {
    Livewire::test(KontaktForm::class)
        ->set(validKontaktData())
        ->call('submit')
        ->assertHasNoErrors()
        ->assertDispatched(
            'filami-track',
            name: 'contact-form-submit',
            data: ['type' => 'general'],
        );
});

it('reports nothing when validation fails', function () {
    Livewire::test(KontaktForm::class)
        ->set('data.name', 'Erika')       // everything else missing
        ->set('quizAnswer', '7')
        ->call('submit')
        ->assertHasErrors()
        ->assertNotDispatched('filami-track');
});

it('reports nothing when the honeypot is tripped', function () {
    // Bots see the thank-you banner and send no mail — so there is no
    // conversion to count either.
    Livewire::test(KontaktForm::class)
        ->set(validKontaktData())
        ->set('website', 'https://spam.test')
        ->call('submit')
        ->assertNotDispatched('filami-track');

    Mail::assertNothingSent();
});
