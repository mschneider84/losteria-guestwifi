<?php

namespace App\Mail;

use App\Models\Location;
use App\Models\NewsletterContact;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NewsletterConfirmation extends Mailable
{
    public function __construct(public NewsletterContact $contact, public Location $location) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: trans('portal.mail_subject', [], $this->contact->language));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.newsletter-confirm', with: [
            'lang' => $this->contact->language,
            'confirmUrl' => route('newsletter.confirm', $this->contact->token),
            'unsubscribeUrl' => route('newsletter.unsubscribe', $this->contact->token),
        ]);
    }
}
