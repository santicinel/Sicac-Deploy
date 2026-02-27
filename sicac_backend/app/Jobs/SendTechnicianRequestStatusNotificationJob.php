<?php

namespace App\Jobs;

use App\Mail\TechnicianRequestStatusNotificationMail;
use App\Models\TechnicianRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendTechnicianRequestStatusNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public int $technicianRequestId,
        public string $email,
        public string $statusLabel,
        public string $typeLabel,
        public ?string $scheduledVisitDate,
        public string $updatedByLabel,
        public string $logContext,
        public array $logData = []
    ) {
        $this->onConnection('database');
        $this->onQueue('mail-notifications');
    }

    public function handle(): void
    {
        $technicianRequest = TechnicianRequest::find($this->technicianRequestId);
        if (! $technicianRequest) {
            Log::warning("{$this->logContext}: solicitud no encontrada, se omite aviso", $this->logData + [
                'technician_request_id' => $this->technicianRequestId,
                'email' => $this->email,
            ]);
            return;
        }

        try {
            Mail::to($this->email)->send(new TechnicianRequestStatusNotificationMail(
                technicianRequest: $technicianRequest,
                statusLabel: $this->statusLabel,
                typeLabel: $this->typeLabel,
                scheduledVisitDate: $this->scheduledVisitDate,
                updatedByLabel: $this->updatedByLabel
            ));

            Log::info("{$this->logContext}: aviso enviado al cliente", $this->logData + [
                'technician_request_id' => $technicianRequest->id,
                'email' => $this->email,
            ]);
        } catch (\Throwable $exception) {
            Log::error("{$this->logContext}: error al enviar aviso", $this->logData + [
                'technician_request_id' => $this->technicianRequestId,
                'email' => $this->email,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
