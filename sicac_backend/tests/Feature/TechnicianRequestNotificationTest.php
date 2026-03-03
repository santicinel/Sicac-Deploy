<?php

namespace Tests\Feature;

use App\Mail\TechnicianRequestStatusNotificationMail;
use App\Models\TechnicianRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TechnicianRequestNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_status_change_sends_notification_to_requesting_user(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $client = User::factory()->create([
            'role' => 'user',
            'email' => 'cliente@example.com',
        ]);

        $technicianRequest = TechnicianRequest::create([
            'requesting_user_id' => $client->id,
            'type' => TechnicianRequest::TYPE_TECHNICAL_SERVICE,
            'status' => TechnicianRequest::STATUS_PENDING,
            'subject' => 'No funciona la central',
            'description' => 'El equipo deja de responder',
            'wanted_date_start' => now()->toDateString(),
            'wanted_date_end' => now()->addDays(3)->toDateString(),
            'time_shift' => 'morning',
        ]);

        $this->actingAs($admin, 'sanctum');

        $response = $this->patchJson(
            "/api/technician-requests/{$technicianRequest->id}/status",
            ['status' => TechnicianRequest::STATUS_ASSIGNED]
        );

        $response->assertOk();

        Mail::assertSent(TechnicianRequestStatusNotificationMail::class, function (TechnicianRequestStatusNotificationMail $mail) use ($client, $technicianRequest) {
            return $mail->hasTo($client->email)
                && $mail->technicianRequest->id === $technicianRequest->id
                && $mail->statusLabel === 'Asignado';
        });
    }

    public function test_admin_visit_date_change_sends_notification_even_when_status_is_the_same(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $client = User::factory()->create([
            'role' => 'user',
            'email' => 'cliente2@example.com',
        ]);

        $technicianRequest = TechnicianRequest::create([
            'requesting_user_id' => $client->id,
            'type' => TechnicianRequest::TYPE_CLAIM,
            'status' => TechnicianRequest::STATUS_ASSIGNED,
            'subject' => 'Reclamo por falla',
            'description' => 'El sensor no detecta movimiento',
            'wanted_date_start' => now()->toDateString(),
            'wanted_date_end' => now()->addDays(5)->toDateString(),
            'time_shift' => 'afternoon',
        ]);

        $scheduledDate = now()->addDays(2)->toDateString();
        $scheduledTime = '15:30';

        $this->actingAs($admin, 'sanctum');

        $response = $this->patchJson(
            "/api/technician-requests/{$technicianRequest->id}/status",
            [
                'status' => TechnicianRequest::STATUS_ASSIGNED,
                'scheduled_visit_date' => $scheduledDate,
                'scheduled_visit_time' => $scheduledTime,
            ]
        );

        $response->assertOk();

        Mail::assertSent(TechnicianRequestStatusNotificationMail::class, function (TechnicianRequestStatusNotificationMail $mail) use ($client, $technicianRequest, $scheduledDate, $scheduledTime) {
            return $mail->hasTo($client->email)
                && $mail->technicianRequest->id === $technicianRequest->id
                && $mail->scheduledVisitDate === $scheduledDate
                && $mail->scheduledVisitTime === $scheduledTime;
        });
    }

    public function test_visit_schedule_requires_time_when_date_is_sent(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $client = User::factory()->create([
            'role' => 'user',
            'email' => 'cliente4@example.com',
        ]);

        $technicianRequest = TechnicianRequest::create([
            'requesting_user_id' => $client->id,
            'type' => TechnicianRequest::TYPE_TECHNICAL_SERVICE,
            'status' => TechnicianRequest::STATUS_ASSIGNED,
            'subject' => 'Programar visita',
            'description' => 'Falla intermitente',
            'wanted_date_start' => now()->toDateString(),
            'wanted_date_end' => now()->addDays(4)->toDateString(),
            'time_shift' => 'morning',
        ]);

        $this->actingAs($admin, 'sanctum');

        $response = $this->patchJson(
            "/api/technician-requests/{$technicianRequest->id}/status",
            [
                'status' => TechnicianRequest::STATUS_ASSIGNED,
                'scheduled_visit_date' => now()->addDays(1)->toDateString(),
            ]
        );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['scheduled_visit_time']);

        Mail::assertNothingSent();
    }

    public function test_visit_schedule_rejects_pm_hour_for_morning_shift(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $client = User::factory()->create([
            'role' => 'user',
            'email' => 'cliente5@example.com',
        ]);

        $technicianRequest = TechnicianRequest::create([
            'requesting_user_id' => $client->id,
            'type' => TechnicianRequest::TYPE_TECHNICAL_SERVICE,
            'status' => TechnicianRequest::STATUS_ASSIGNED,
            'subject' => 'Horario invalido manana',
            'description' => 'No debe aceptar horario PM',
            'wanted_date_start' => now()->toDateString(),
            'wanted_date_end' => now()->addDays(4)->toDateString(),
            'time_shift' => 'morning',
        ]);

        $this->actingAs($admin, 'sanctum');

        $response = $this->patchJson(
            "/api/technician-requests/{$technicianRequest->id}/status",
            [
                'status' => TechnicianRequest::STATUS_ASSIGNED,
                'scheduled_visit_date' => now()->addDays(1)->toDateString(),
                'scheduled_visit_time' => '15:30',
            ]
        );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['scheduled_visit_time']);

        Mail::assertNothingSent();
    }

    public function test_visit_schedule_rejects_am_hour_for_afternoon_shift(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $client = User::factory()->create([
            'role' => 'user',
            'email' => 'cliente6@example.com',
        ]);

        $technicianRequest = TechnicianRequest::create([
            'requesting_user_id' => $client->id,
            'type' => TechnicianRequest::TYPE_TECHNICAL_SERVICE,
            'status' => TechnicianRequest::STATUS_ASSIGNED,
            'subject' => 'Horario invalido tarde',
            'description' => 'No debe aceptar horario AM',
            'wanted_date_start' => now()->toDateString(),
            'wanted_date_end' => now()->addDays(4)->toDateString(),
            'time_shift' => 'afternoon',
        ]);

        $this->actingAs($admin, 'sanctum');

        $response = $this->patchJson(
            "/api/technician-requests/{$technicianRequest->id}/status",
            [
                'status' => TechnicianRequest::STATUS_ASSIGNED,
                'scheduled_visit_date' => now()->addDays(1)->toDateString(),
                'scheduled_visit_time' => '10:30',
            ]
        );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['scheduled_visit_time']);

        Mail::assertNothingSent();
    }

    public function test_admin_update_without_status_or_visit_date_change_does_not_send_notification(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $client = User::factory()->create([
            'role' => 'user',
            'email' => 'cliente3@example.com',
        ]);

        $technicianRequest = TechnicianRequest::create([
            'requesting_user_id' => $client->id,
            'type' => TechnicianRequest::TYPE_TECHNICAL_SERVICE,
            'status' => TechnicianRequest::STATUS_PENDING,
            'subject' => 'Solicitud inicial',
            'description' => 'Detalle inicial',
            'wanted_date_start' => now()->toDateString(),
            'wanted_date_end' => now()->addDays(2)->toDateString(),
            'time_shift' => 'morning',
        ]);

        $this->actingAs($admin, 'sanctum');

        $response = $this->patchJson(
            "/api/technician-requests/{$technicianRequest->id}",
            ['subject' => 'Solicitud actualizada']
        );

        $response->assertOk();
        Mail::assertNothingSent();
    }
}
