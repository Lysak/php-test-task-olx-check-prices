<?php

namespace App\Mail;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PriceChangedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Listing $listing,
        public readonly float $oldPrice,
        public readonly float $newPrice,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Ціна змінилась: {$this->listing->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.price-changed',
        );
    }
}
