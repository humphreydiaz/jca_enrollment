<?php

namespace App\Modules\Enrollment\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EnrollmentContactVerificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $applicationNumber, public readonly string $verificationCode) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Verify your JCA enrollment contact');
    }

    public function content(): Content
    {
        return new Content(view: 'enrollment.mail.verify-contact');
    }
}
