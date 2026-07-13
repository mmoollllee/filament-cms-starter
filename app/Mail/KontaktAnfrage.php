<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content as MailContent;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Contact-form inquiry sent to the tenant's contact address. The sender gets
 * the separate {@see KontaktBestaetigung} confirmation copy.
 */
class KontaktAnfrage extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{name: string, email: string, phone: string, message: string, submitted_at: string, source_url: string}  $data
     */
    public function __construct(
        public Tenant $tenant,
        public array $data,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Neue Kontaktanfrage über die Website',
            replyTo: [new Address($this->data['email'], $this->data['name'])],
        );
    }

    public function content(): MailContent
    {
        // Branded HTML layout via <x-mail.branded> (a normal Blade component), so a
        // plain `view:` is correct here — no Markdown mail namespace required.
        return new MailContent(view: 'emails.kontakt-anfrage');
    }
}
