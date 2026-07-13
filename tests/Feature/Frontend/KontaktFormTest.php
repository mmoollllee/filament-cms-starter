<?php

use App\Livewire\KontaktForm;
use App\Mail\KontaktAnfrage;
use App\Mail\KontaktBestaetigung;
use App\Models\Tenant;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Mmoollllee\Cms\Support\Tenancy\CurrentTenant;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->tenant = Tenant::query()->where('site_key', 'default')->sole();
    app(CurrentTenant::class)->set($this->tenant);

    // Deterministic single spam question so the rotating quiz is testable.
    $this->tenant->update(['spam_questions' => [['question' => 'Was ergibt drei plus vier?', 'answer' => '7, sieben']]]);
});

it('sends the inquiry and a confirmation copy on valid submit', function () {
    Mail::fake();

    Livewire::test(KontaktForm::class)
        ->set('data.name', 'Max Mustermann')
        ->set('data.email', 'max@example.com')
        ->set('data.message', 'Bitte um Rückruf.')
        ->set('data.privacy_accepted', true)
        ->set('quizAnswer', '7')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true)
        ->assertSee('Vielen Dank für Ihre Anfrage!')
        ->assertSet('data.name', '');

    // Company gets the inquiry; the sender gets a confirmation copy.
    Mail::assertSent(KontaktAnfrage::class);
    Mail::assertSent(KontaktBestaetigung::class, fn ($mail) => $mail->hasTo('max@example.com'));
});

it('rejects a submit without required fields', function () {
    Mail::fake();

    Livewire::test(KontaktForm::class)
        ->set('quizAnswer', '7')
        ->call('submit')
        ->assertHasErrors(['data.name', 'data.email', 'data.message']);

    Mail::assertNothingSent();
});
