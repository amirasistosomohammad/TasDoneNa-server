<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $resetUrl,
        public string $name,
        public int $expireMinutes = 60
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'TasDoneNa — Reset your password',
            from: config('mail.from.address', 'noreply@tasdonena.local'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reset-password',
        );
    }
}
