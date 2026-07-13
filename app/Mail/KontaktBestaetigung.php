<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Confirmation copy sent to the person who submitted the contact form,
 * acknowledging receipt and echoing their message. The company itself gets
 * the separate {@see KontaktAnfrage} mail.
 */
class KontaktBestaetigung extends Mailable
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
        $contactEmail = (string) $this->tenant->resolvedSiteSetting('contact_email');

        return new Envelope(
            subject: 'Ihre Anfrage bei '.$this->tenant->displayName(),
            // Replies from the customer reach the company, not the sending mailbox.
            replyTo: filled($contactEmail)
                ? [new Address($contactEmail, $this->tenant->displayName())]
                : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.kontakt-bestaetigung',
        );
    }
}
