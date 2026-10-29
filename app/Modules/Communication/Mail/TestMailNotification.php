<?php

declare(strict_types=1);

namespace App\Modules\Communication\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestMailNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientEmail,
        public string $timestampString,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SMTP Connection Test Verification — E-Commerce Platform',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: "<h2>SMTP Pre-Flight Handshake Successful</h2><p>This is a verification test email dispatched at {$this->timestampString} to confirm active SMTP credentials.</p>",
        );
    }
}
