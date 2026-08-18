<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Throwable;

#[Signature('app:send-test-mail {recipient? : Empfänger (Default: MAIL_FROM_ADDRESS)}')]
#[Description('Sendet eine Testmail über den konfigurierten Mailer (SMTP-Diagnose)')]
class SendTestMail extends Command
{
    public function handle(): int
    {
        $recipient = $this->argument('recipient') ?? config('mail.from.address');

        if (blank($recipient)) {
            $this->error('Kein Empfänger: Argument fehlt und mail.from.address ist leer.');

            return self::FAILURE;
        }

        $mailer = (string) config('mail.default');
        $transport = config("mail.mailers.{$mailer}", []);

        $this->info(sprintf(
            'Sende über Mailer "%s" (%s:%s) von %s an %s ...',
            $mailer,
            $transport['host'] ?? '-',
            $transport['port'] ?? '-',
            config('mail.from.address'),
            $recipient,
        ));

        try {
            Mail::raw(
                'Testmail von '.config('app.name').' — versendet am '.now()->format('d.m.Y H:i:s').'.',
                fn (Message $message) => $message->to($recipient)->subject('SMTP-Test '.config('app.name')),
            );
        } catch (Throwable $e) {
            // The transport exception carries the actual diagnosis (e.g. an SMTP
            // 535 when basic auth is switched off) — print it in full.
            $this->error('Versand fehlgeschlagen: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Testmail versendet.');

        return self::SUCCESS;
    }
}
