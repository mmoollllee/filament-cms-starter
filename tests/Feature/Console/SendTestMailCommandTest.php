<?php

// MAIL_MAILER=array (phpunit.xml) — the mails land in the array transport,
// whose contents are asserted directly here.

it('sends a test mail to the given recipient', function () {
    $this->artisan('app:send-test-mail', ['recipient' => 'ziel@example.test'])
        ->assertSuccessful();

    $messages = app('mailer')->getSymfonyTransport()->messages();

    expect($messages)->toHaveCount(1)
        ->and($messages->first()->getEnvelope()->getRecipients()[0]->getAddress())->toBe('ziel@example.test');
});

it('falls back to the configured from address as recipient', function () {
    config(['mail.from.address' => 'kontaktformular@example.test']);

    $this->artisan('app:send-test-mail')->assertSuccessful();

    $messages = app('mailer')->getSymfonyTransport()->messages();

    expect($messages)->toHaveCount(1)
        ->and($messages->first()->getEnvelope()->getRecipients()[0]->getAddress())->toBe('kontaktformular@example.test');
});

it('fails cleanly when no recipient can be resolved', function () {
    config(['mail.from.address' => null]);

    $this->artisan('app:send-test-mail')->assertFailed();

    expect(app('mailer')->getSymfonyTransport()->messages())->toHaveCount(0);
});
