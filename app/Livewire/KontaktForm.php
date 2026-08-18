<?php

namespace App\Livewire;

use App\Mail\KontaktAnfrage;
use App\Mail\KontaktBestaetigung;
use App\Support\Content\Blocks\contact_form\ContactFormBlock;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Locked;
use Mmoollllee\Cms\Support\Livewire\AbstractTenantAwareForm;
use Mmoollllee\Cms\Support\Livewire\Concerns\WithSpamQuiz;

/**
 * General contact form. Spam protection: honeypot + a rotating, tenant-defined
 * security question + rate limit. Sends the inquiry to the tenant's contact
 * address and a confirmation copy to the sender.
 *
 * SECURITY: every property the SERVER fills in mount() carries #[Locked]. They
 * are plain public properties, so without it the browser can rewrite them in
 * the Livewire snapshot — and this form is public, so "the browser" means any
 * anonymous visitor. $contactEmail is the sharp one: it feeds Mail::to(), so an
 * unlocked property turns the site into an open relay that sends from the
 * domain's verified MAIL_FROM_ADDRESS to any address the attacker picks.
 * $sourceUrl is rendered into both mails and would allow content injection.
 * Only $data (the actual form fields) and the honeypot may come from the client.
 */
class KontaktForm extends AbstractTenantAwareForm
{
    use WithSpamQuiz;

    /**
     * Measured as `contact-form-start` (first interaction, fired client-side by
     * the attributes on the <form> tag) and `contact-form-submit` (successful
     * submission), so the submit count reads as a completion rate. Renaming
     * this starts a fresh series in Umami — events under the old name stay
     * there. No-op without mmoollllee/filami.
     */
    protected ?string $analyticsName = 'contact-form';

    #[Locked]
    public ?int $contentId = null;

    /**
     * Per-block recipient override, set server-side by the contact_form block
     * ({@see ContactFormBlock}). Never client-supplied.
     */
    #[Locked]
    public ?string $contactEmail = null;

    /** Absolute URL of the page the form lives on — included in the operator mail. */
    #[Locked]
    public ?string $sourceUrl = null;

    /** @var array<string, mixed> */
    public array $data = [
        'name' => '',
        'email' => '',
        'phone' => '',
        'message' => '',
        'privacy_accepted' => false,
    ];

    public function mount(?int $contentId = null, ?string $contactEmail = null, ?string $sourceUrl = null): void
    {
        $this->contentId = $contentId;
        $this->contactEmail = $contactEmail;
        // Captured during the initial page render → the URL of the page the form is on.
        $this->sourceUrl = $sourceUrl ?: request()->url();

        $this->initSpamQuiz();
    }

    public function submit(): void
    {
        // Honeypot: silently accept bots without sending mail.
        if ($this->trippedHoneypot()) {
            return;
        }

        $this->validateSpamQuiz();

        $rateLimitKey = $this->rateLimitKey('kontakt');
        $this->ensureWithinRateLimit($rateLimitKey, 'data.email', 'Zu viele Anfragen in kurzer Zeit. Bitte versuchen Sie es später erneut.');

        $this->validate([
            'data.name' => 'required|string|max:120',
            'data.email' => 'required|email|max:255',
            'data.phone' => 'nullable|string|max:50',
            'data.message' => 'required|string|max:3000',
            'data.privacy_accepted' => 'accepted',
        ], [
            'data.name.required' => 'Bitte geben Sie Ihren Namen ein.',
            'data.email.required' => 'Bitte geben Sie Ihre E-Mail-Adresse ein.',
            'data.email.email' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
            'data.message.required' => 'Bitte schreiben Sie uns eine kurze Nachricht.',
            'data.privacy_accepted.accepted' => 'Bitte bestätigen Sie die Datenschutz-Hinweise.',
        ]);

        $tenant = $this->currentTenant();

        abort_unless($tenant !== null, 404);

        $payload = [
            'name' => trim((string) $this->data['name']),
            'email' => trim((string) $this->data['email']),
            'phone' => trim((string) $this->data['phone']),
            'message' => trim((string) $this->data['message']),
            'submitted_at' => now()->format('d.m.Y H:i'),
            'source_url' => $this->sourceUrl,
        ];

        $recipient = $this->resolveContactRecipient($this->contactEmail);

        abort_unless(filled($recipient), 500, 'No contact email configured for this tenant.');

        Mail::to($recipient)->send(new KontaktAnfrage($tenant, $payload));

        // Confirmation copy to the person who submitted the form.
        Mail::to($payload['email'])->send(new KontaktBestaetigung($tenant, $payload));

        $this->hitRateLimit($rateLimitKey);

        // Clear the fields and rotate to a fresh question under the thank-you banner.
        $this->reset(['data', 'quizAnswer']);
        $this->initSpamQuiz();

        $this->submitted = true;

        // Counted only once the mails are out, so the dashboard number matches
        // the inquiries actually received — a submission that fails validation
        // or trips the honeypot never gets here. Pass categories only; event
        // properties are stored next to the pageview in Umami, so nothing about
        // the sender may go in here.
        $this->trackConversion(['type' => 'general']);
    }

    public function render()
    {
        return view('livewire.kontakt-form');
    }
}
