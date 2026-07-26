<?php

namespace App\Mail;

use App\Models\Feed;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FeedRegisteredMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Feed $feed) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Lerama] '.__('mail.registered.subject').': '.$this->feed->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.feed-registered',
        );
    }
}
