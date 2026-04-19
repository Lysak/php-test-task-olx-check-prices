<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UnsubscribedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct() {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ви успішно відписалися від сповіщень OLX',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.unsubscribed',
        );
    }
}
