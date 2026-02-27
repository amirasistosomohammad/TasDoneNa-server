<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OfficerApprovalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public ?string $remarks = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'TasDoneNa — Your personnel account has been approved',
            from: config('mail.from.address', 'noreply@tasdonena.local'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.officer-approved',
        );
    }
}

