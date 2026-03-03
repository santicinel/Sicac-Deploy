<?php

namespace App\Mail;

use App\Models\TechnicianRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TechnicianRequestStatusNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TechnicianRequest $technicianRequest,
        public string $statusLabel,
        public string $typeLabel,
        public ?string $scheduledVisitDate,
        public ?string $scheduledVisitTime,
        public string $updatedByLabel
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                'Aviso: %s #%d en estado %s',
                ucfirst($this->typeLabel),
                $this->technicianRequest->id,
                $this->statusLabel
            )
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.technician-request-status-notification',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
