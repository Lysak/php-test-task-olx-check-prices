<?php

namespace App\Mail;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ListingUnavailableMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Listing $listing) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Оголошення більше недоступне: {$this->listing->title}",
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.listing-unavailable');
    }
}
