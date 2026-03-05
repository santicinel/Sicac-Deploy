<?php

namespace App\Http\Controllers;

use App\Jobs\SendTechnicianRequestStatusNotificationJob;
use App\Mail\TechnicianRequestStatusNotificationMail;
use App\Models\TechnicianRequest;
use App\Models\Technician;
use App\Models\Claim;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class TechnicianRequestController extends Controller
{
    private bool $notificationWorkerSpawned = false;

    /**
     * Obtener todas las solicitudes técnicas (solo admins) (READ)
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $userId = $user->id;

        Log::info('TechnicianRequest.index: Intento de obtener todas las solicitudes', [
            'user_id' => $userId,
            'user_role' => $user->role,
        ]);

        try {
            $this->authorize('viewAny', TechnicianRequest::class);

            // Obtener filtros opcionales
            $status = $request->query('status'); // pending, assigned, completed, cancelled
            $technicianId = $request->query('technician_id');
            $requestingUserId = $request->query('requesting_user_id');
            $search = $request->query('search');
            $type = $request->query('type');

            $query = TechnicianRequest::query()
                ->with('requestingUser', 'technician.user', 'category', 'claim', 'repairedProduct')
                ->orderBy('created_at', 'desc');

            if ($type) {
                Log::debug('TechnicianRequest.index: Filtrando por type', ['type' => $type]);
                $query->where('type', $type);
            }

            // Aplicar filtros
            if ($status) {
                Log::debug('TechnicianRequest.index: Filtrando por status', ['status' => $status]);
                $query->where('status', $status);
            }

            if ($technicianId) {
                Log::debug('TechnicianRequest.index: Filtrando por technician_id', ['technician_id' => $technicianId]);
                $query->where('technician_id', $technicianId);
            }

            if ($requestingUserId) {
                Log::debug('TechnicianRequest.index: Filtrando por requesting_user_id', ['requesting_user_id' => $requestingUserId]);
                $query->where('requesting_user_id', $requestingUserId);
            }

            if ($search) {
                Log::debug('TechnicianRequest.index: Buscando por texto', ['search' => $search]);
                $query->where(function ($q) use ($search) {
                    $q->where('subject', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $requests = $query->get();

            Log::info('TechnicianRequest.index: ✅ Todas las solicitudes obtenidas exitosamente', [
                'user_id' => $userId,
                'requests_count' => $requests->count(),
                'filters' => [
                    'status' => $status,
                    'technician_id' => $technicianId,
                    'requesting_user_id' => $requestingUserId,
                    'search' => $search,
                    'type' => $type,
                ],
            ]);

            return response()->json([
                'data' => $requests,
                'total' => $requests->count(),
            ], 200);
        } catch (\Exception $e) {
            Log::error('TechnicianRequest.index: ❌ Error al obtener todas las solicitudes', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Obtener estadísticas de solicitudes (solo admins) (READ)
     */
    public function stats(Request $request)
    {
        $user = Auth::user();
        $userId = $user->id;

        Log::info('TechnicianRequest.stats: Intento de obtener estadísticas', [
            'user_id' => $userId,
            'user_role' => $user->role,
        ]);

        try {
            $this->authorize('viewStats', TechnicianRequest::class);

            $stats = [
                'total' => TechnicianRequest::count(),
                'by_type' => [
                    TechnicianRequest::TYPE_TECHNICAL_SERVICE => TechnicianRequest::where('type', TechnicianRequest::TYPE_TECHNICAL_SERVICE)->count(),
                    TechnicianRequest::TYPE_CLAIM => TechnicianRequest::where('type', TechnicianRequest::TYPE_CLAIM)->count(),
                ],
                'by_status' => [
                    'pending' => TechnicianRequest::where('status', TechnicianRequest::STATUS_PENDING)->count(),
                    'assigned' => TechnicianRequest::where('status', TechnicianRequest::STATUS_ASSIGNED)->count(),
                    'completed' => TechnicianRequest::where('status', TechnicianRequest::STATUS_COMPLETED)->count(),
                    'cancelled' => TechnicianRequest::where('status', TechnicianRequest::STATUS_CANCELLED)->count(),
                ],
                'without_technician' => TechnicianRequest::whereNull('technician_id')->count(),
                'with_technician' => TechnicianRequest::whereNotNull('technician_id')->count(),
            ];

            Log::info('TechnicianRequest.stats: ✅ Estadísticas obtenidas exitosamente', [
                'user_id' => $userId,
                'stats' => $stats,
            ]);

            return response()->json([
                'data' => $stats,
            ], 200);
        } catch (\Exception $e) {
            Log::error('TechnicianRequest.stats: ❌ Error al obtener estadísticas', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Crear una nueva solicitud técnica (CREATE)
     */
    public function store(Request $request)
    {
        $userId = Auth::id();
        
        Log::info('TechnicianRequest.store: Intento de crear solicitud', [
            'user_id' => $userId,
        ]);

        try {
            $this->authorize('create', TechnicianRequest::class);

            $validatedData = $request->validate(TechnicianRequest::storeRules());

            Log::debug('TechnicianRequest.store: Validación exitosa', [
                'user_id' => $userId,
                'category_id' => $validatedData['category_id'] ?? null,
            ]);

            $requestType = $validatedData['type'] ?? TechnicianRequest::TYPE_TECHNICAL_SERVICE;

            $technicianRequest = TechnicianRequest::create([
                'requesting_user_id' => $userId,
                'technician_id' => $validatedData['technician_id'] ?? null,
                'category_id' => $validatedData['category_id'] ?? null,
                'claim_id' => $validatedData['claim_id'] ?? null,
                'type' => $requestType,
                'status' => TechnicianRequest::STATUS_PENDING,
                'subject' => $validatedData['subject'],
                'description' => $validatedData['description'],
                'wanted_date_start' => $validatedData['wanted_date_start'],
                'wanted_date_end' => $validatedData['wanted_date_end'],
                'time_shift' => $validatedData['time_shift'],
            ]);

            Log::info('TechnicianRequest.store: ✅ Solicitud creada exitosamente', [
                'user_id' => $userId,
                'technician_request_id' => $technicianRequest->id,
                'status' => $technicianRequest->status,
            ]);

            return response()->json([
                'message' => 'Solicitud creada correctamente',
                'data' => $technicianRequest->load('requestingUser', 'technician.user', 'category', 'claim', 'repairedProduct'),
            ], 201);
        } catch (\Exception $e) {
            Log::error('TechnicianRequest.store: ❌ Error al crear solicitud', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Obtener las solicitudes creadas por el usuario autenticado (READ)
     */
    public function userRequests(Request $request)
    {
        $userId = Auth::id();

        Log::info('TechnicianRequest.userRequests: Intento de obtener solicitudes del usuario', [
            'user_id' => $userId,
        ]);

        try {
            $this->authorize('viewOwn', TechnicianRequest::class);

            $requests = TechnicianRequest::where('requesting_user_id', $userId)
                ->with([
                    'technician.user',
                    'category',
                    'claim',
                    'repairedProduct',
                    'rating' => function ($query) use ($userId) {
                        $query->where('user_id', $userId)->orderByDesc('id');
                    },
                ])
                ->orderBy('created_at', 'desc')
                ->get();

            Log::info('TechnicianRequest.userRequests: ✅ Solicitudes obtenidas exitosamente', [
                'user_id' => $userId,
                'requests_count' => $requests->count(),
            ]);

            return response()->json([
                'data' => $requests,
            ], 200);
        } catch (\Exception $e) {
            Log::error('TechnicianRequest.userRequests: ❌ Error al obtener solicitudes', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Obtener todas las solicitudes sin técnico asignado (disponibles) (READ)
     */
    public function unassignedRequests(Request $request)
    {
        $user = Auth::user();
        $userId = $user->id;

        Log::info('TechnicianRequest.unassignedRequests: Intento de obtener solicitudes sin asignar', [
            'user_id' => $userId,
            'user_role' => $user->role,
        ]);

        try {
            $this->authorize('viewUnassigned', TechnicianRequest::class);

            $requests = TechnicianRequest::whereNull('technician_id')
                ->where('status', TechnicianRequest::STATUS_PENDING)
                ->orderBy('created_at', 'desc')
                ->with('requestingUser', 'technician.user', 'category', 'claim', 'repairedProduct')
                ->get();

            Log::info('TechnicianRequest.unassignedRequests: ✅ Solicitudes disponibles obtenidas exitosamente', [
                'user_id' => $userId,
                'requests_count' => $requests->count(),
            ]);

            return response()->json([
                'data' => $requests,
            ], 200);
        } catch (\Exception $e) {
            Log::error('TechnicianRequest.unassignedRequests: ❌ Error al obtener solicitudes', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Obtener las solicitudes asignadas al technician autenticado (READ)
     */
    public function myRequests(Request $request)
    {
        $user = Auth::user();
        $userId = $user->id;

        Log::info('TechnicianRequest.myRequests: Intento de obtener solicitudes', [
            'user_id' => $userId,
            'user_role' => $user->role,
        ]);

        try {
            $this->authorize('viewMyRequests', TechnicianRequest::class);

            $technician = Technician::where('user_id', $userId)->firstOrFail();

            $requests = TechnicianRequest::where('technician_id', $technician->id)
                ->with('requestingUser', 'technician.user', 'category', 'claim', 'repairedProduct')
                ->get();

            Log::info('TechnicianRequest.myRequests: ✅ Solicitudes obtenidas exitosamente', [
                'user_id' => $userId,
                'technician_id' => $technician->id,
                'requests_count' => $requests->count(),
            ]);

            return response()->json([
                'data' => $requests,
            ], 200);
        } catch (\Exception $e) {
            Log::error('TechnicianRequest.myRequests: ❌ Error al obtener solicitudes', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Actualizar el estado de una solicitud (UPDATE)
     */
    public function updateStatus(Request $request, TechnicianRequest $technicianRequest)
    {
        $user = Auth::user();
        $userId = $user->id;

        Log::info('TechnicianRequest.updateStatus: Intento de actualizar estado', [
            'user_id' => $userId,
            'user_role' => $user->role,
            'technician_request_id' => $technicianRequest->id,
            'current_status' => $technicianRequest->status,
        ]);

        try {
            $this->authorize('updateStatus', $technicianRequest);

            $validatedData = $request->validate(TechnicianRequest::statusUpdateRules());

            $this->validateScheduledVisitSchedule($technicianRequest, $validatedData);
            $this->validateResolutionSummaryOnComplete($technicianRequest, $validatedData);
            $this->validateCompletionDetailsOnComplete($technicianRequest, $validatedData);
            $this->validateCancellationReasonOnCancel($technicianRequest, $validatedData);
            $this->normalizeCompletionDate($technicianRequest, $validatedData);

            Log::debug('TechnicianRequest.updateStatus: Validación exitosa', [
                'user_id' => $userId,
                'new_status' => $validatedData['status'],
                'technician_request_id' => $technicianRequest->id,
            ]);

            $oldStatus = $technicianRequest->status;
            $oldScheduledVisitDate = $this->normalizeDateString($technicianRequest->scheduled_visit_date);
            $oldScheduledVisitTime = $this->normalizeTimeString($technicianRequest->scheduled_visit_time);
            $technicianRequest->update($validatedData);
            $this->syncClaimStatusFromRequest($technicianRequest);
            $this->sendStatusNotificationIfNeeded(
                $technicianRequest,
                $oldStatus,
                $oldScheduledVisitDate,
                $oldScheduledVisitTime,
                $user->role
            );

            Log::info('TechnicianRequest.updateStatus: ✅ Estado actualizado exitosamente', [
                'user_id' => $userId,
                'user_role' => $user->role,
                'technician_request_id' => $technicianRequest->id,
                'old_status' => $oldStatus,
                'new_status' => $technicianRequest->status,
            ]);

            return response()->json([
                'message' => 'Estado actualizado correctamente',
                'data' => $technicianRequest->load('requestingUser', 'technician.user', 'category', 'claim', 'repairedProduct'),
            ], 200);
        } catch (\Exception $e) {
            Log::error('TechnicianRequest.updateStatus: ❌ Error al actualizar estado', [
                'user_id' => $userId,
                'technician_request_id' => $technicianRequest->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Actualizar una solicitud técnica (admin puede modificar todo) (UPDATE)
     */
    public function update(Request $request, TechnicianRequest $technicianRequest)
    {
        $user = Auth::user();
        $userId = $user->id;

        Log::info('TechnicianRequest.update: Intento de actualizar solicitud', [
            'user_id' => $userId,
            'user_role' => $user->role,
            'technician_request_id' => $technicianRequest->id,
        ]);

        try {
            $this->authorize('update', $technicianRequest);

            $validatedData = $request->validate(TechnicianRequest::updateRules());
            $this->validateScheduledVisitSchedule($technicianRequest, $validatedData);
            $this->validateCompletionDetailsOnComplete($technicianRequest, $validatedData);
            $this->validateCancellationReasonOnCancel($technicianRequest, $validatedData);
            $this->normalizeCompletionDate($technicianRequest, $validatedData);

            Log::debug('TechnicianRequest.update: Validación exitosa', [
                'user_id' => $userId,
                'technician_request_id' => $technicianRequest->id,
                'updated_fields' => array_keys($validatedData),
            ]);

            $oldData = $technicianRequest->toArray();
            $oldStatus = $technicianRequest->status;
            $oldScheduledVisitDate = $this->normalizeDateString($technicianRequest->scheduled_visit_date);
            $oldScheduledVisitTime = $this->normalizeTimeString($technicianRequest->scheduled_visit_time);
            $technicianRequest->update($validatedData);
            $this->syncClaimStatusFromRequest($technicianRequest);
            $this->sendStatusNotificationIfNeeded(
                $technicianRequest,
                $oldStatus,
                $oldScheduledVisitDate,
                $oldScheduledVisitTime,
                $user->role
            );

            Log::info('TechnicianRequest.update: ✅ Solicitud actualizada exitosamente', [
                'user_id' => $userId,
                'technician_request_id' => $technicianRequest->id,
                'changes' => array_diff_assoc($technicianRequest->toArray(), $oldData),
            ]);

            return response()->json([
                'message' => 'Solicitud actualizada correctamente',
                'data' => $technicianRequest->load('requestingUser', 'technician.user', 'category', 'claim', 'repairedProduct'),
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('TechnicianRequest.update: ⚠️ Error de validación', [
                'user_id' => $userId,
                'technician_request_id' => $technicianRequest->id,
                'errors' => $e->errors(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('TechnicianRequest.update: ❌ Error al actualizar solicitud', [
                'user_id' => $userId,
                'technician_request_id' => $technicianRequest->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Asignar una solicitud a sí mismo (solo para técnicos con solicitudes sin asignar)
     */
    public function assignToMyself(Request $request, TechnicianRequest $technicianRequest)
    {
        $user = Auth::user();
        $userId = $user->id;

        Log::info('TechnicianRequest.assignToMyself: Intento de asignarse una solicitud', [
            'user_id' => $userId,
            'user_role' => $user->role,
            'technician_request_id' => $technicianRequest->id,
        ]);

        try {
            $this->authorize('assignToMyself', $technicianRequest);

            $technician = Technician::where('user_id', $userId)->firstOrFail();

            // Verificar que la solicitud no tiene technician asignado
            if ($technicianRequest->technician_id !== null) {
                Log::warning('TechnicianRequest.assignToMyself: ⚠️ Solicitud ya tiene technician asignado', [
                    'user_id' => $userId,
                    'technician_id' => $technician->id,
                    'request_technician_id' => $technicianRequest->technician_id,
                    'technician_request_id' => $technicianRequest->id,
                ]);

                return response()->json([
                    'message' => 'Esta solicitud ya tiene un technician asignado',
                ], 403);
            }

            // Verificar que la solicitud está en estado pending
            if ($technicianRequest->status !== TechnicianRequest::STATUS_PENDING) {
                Log::warning('TechnicianRequest.assignToMyself: ⚠️ Solicitud no está en estado pending', [
                    'user_id' => $userId,
                    'technician_id' => $technician->id,
                    'current_status' => $technicianRequest->status,
                    'technician_request_id' => $technicianRequest->id,
                ]);

                return response()->json([
                    'message' => 'Solo puedes asignarte solicitudes en estado pendiente',
                ], 422);
            }

            // Asignar la solicitud al technician y cambiar estado a assigned
            $oldStatus = $technicianRequest->status;
            $oldScheduledVisitDate = $this->normalizeDateString($technicianRequest->scheduled_visit_date);
            $oldScheduledVisitTime = $this->normalizeTimeString($technicianRequest->scheduled_visit_time);
            $technicianRequest->update([
                'technician_id' => $technician->id,
                'status' => TechnicianRequest::STATUS_ASSIGNED,
            ]);
            $this->syncClaimStatusFromRequest($technicianRequest);
            $this->sendStatusNotificationIfNeeded(
                $technicianRequest,
                $oldStatus,
                $oldScheduledVisitDate,
                $oldScheduledVisitTime,
                $user->role
            );

            Log::info('TechnicianRequest.assignToMyself: ✅ Solicitud asignada exitosamente', [
                'user_id' => $userId,
                'technician_id' => $technician->id,
                'technician_request_id' => $technicianRequest->id,
                'new_status' => TechnicianRequest::STATUS_ASSIGNED,
            ]);

            return response()->json([
                'message' => 'Te has asignado la solicitud correctamente',
                'data' => $technicianRequest->load('requestingUser', 'technician.user', 'category', 'claim', 'repairedProduct'),
            ], 200);
        } catch (\Exception $e) {
            Log::error('TechnicianRequest.assignToMyself: ❌ Error al asignarse la solicitud', [
                'user_id' => $userId,
                'technician_request_id' => $technicianRequest->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Eliminar una solicitud técnica (solo admins) (DELETE)
     */
    public function destroy(Request $request, TechnicianRequest $technicianRequest)
    {
        $user = Auth::user();
        $userId = $user->id;

        Log::info('TechnicianRequest.destroy: Intento de eliminar solicitud', [
            'user_id' => $userId,
            'user_role' => $user->role,
            'technician_request_id' => $technicianRequest->id,
        ]);

        try {
            $this->authorize('delete', $technicianRequest);

            $requestId = $technicianRequest->id;
            $requestData = $technicianRequest->toArray();

            $technicianRequest->delete();

            Log::info('TechnicianRequest.destroy: ✅ Solicitud eliminada exitosamente', [
                'user_id' => $userId,
                'technician_request_id' => $requestId,
                'deleted_data' => $requestData,
            ]);

            return response()->json([
                'message' => 'Solicitud eliminada correctamente',
                'data' => $requestData,
            ], 200);
        } catch (\Exception $e) {
            Log::error('TechnicianRequest.destroy: ❌ Error al eliminar solicitud', [
                'user_id' => $userId,
                'technician_request_id' => $technicianRequest->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function syncClaimStatusFromRequest(TechnicianRequest $technicianRequest): void
    {
        if ((int) $technicianRequest->claim_id <= 0) {
            return;
        }

        $claimStatus = match ($technicianRequest->status) {
            TechnicianRequest::STATUS_COMPLETED => Claim::STATUS_COMPLETED,
            TechnicianRequest::STATUS_CANCELLED => Claim::STATUS_CANCELLED,
            default => Claim::STATUS_PENDING,
        };

        Claim::where('id', $technicianRequest->claim_id)->update([
            'status' => $claimStatus,
        ]);
    }

    private function validateScheduledVisitSchedule(TechnicianRequest $technicianRequest, array &$validatedData): void
    {
        $hasDateKey = array_key_exists('scheduled_visit_date', $validatedData);
        $hasTimeKey = array_key_exists('scheduled_visit_time', $validatedData);
        $existingScheduledDate = $this->normalizeDateString($technicianRequest->scheduled_visit_date);

        if (! $hasDateKey && ! $hasTimeKey) {
            return;
        }

        if ($hasDateKey) {
            $scheduledVisitDate = trim((string) ($validatedData['scheduled_visit_date'] ?? ''));
            if ($scheduledVisitDate === '') {
                if ($existingScheduledDate !== null) {
                    throw ValidationException::withMessages([
                        'scheduled_visit_date' => 'La fecha de visita ya fue guardada y no puede modificarse.',
                    ]);
                }
                $validatedData['scheduled_visit_date'] = null;
            } else {
                $normalizedScheduledDate = Carbon::parse($scheduledVisitDate)->toDateString();

                if ($existingScheduledDate !== null && $normalizedScheduledDate !== $existingScheduledDate) {
                    throw ValidationException::withMessages([
                        'scheduled_visit_date' => 'La fecha de visita ya fue guardada y no puede modificarse.',
                    ]);
                }

                $scheduled = Carbon::parse($normalizedScheduledDate)->startOfDay();
                $wantedStart = Carbon::parse($technicianRequest->wanted_date_start)->startOfDay();
                $wantedEnd = Carbon::parse($technicianRequest->wanted_date_end)->startOfDay();

                if ($scheduled->lt($wantedStart) || $scheduled->gt($wantedEnd)) {
                    throw ValidationException::withMessages([
                        'scheduled_visit_date' => 'La fecha elegida debe estar dentro del rango solicitado por el cliente.',
                    ]);
                }

                $validatedData['scheduled_visit_date'] = $normalizedScheduledDate;
            }
        }

        if ($hasTimeKey) {
            $scheduledVisitTime = trim((string) ($validatedData['scheduled_visit_time'] ?? ''));
            if ($scheduledVisitTime === '') {
                $validatedData['scheduled_visit_time'] = null;
            } else {
                $normalizedVisitTime = $this->normalizeTimeForStorage($scheduledVisitTime);
                if ($normalizedVisitTime === null) {
                    throw ValidationException::withMessages([
                        'scheduled_visit_time' => 'La hora estimada de visita no tiene un formato valido.',
                    ]);
                }

                $this->validateScheduledVisitTimeMatchesRequestedShift($technicianRequest, $normalizedVisitTime);
                $validatedData['scheduled_visit_time'] = $normalizedVisitTime;
            }
        }

        $nextDate = array_key_exists('scheduled_visit_date', $validatedData)
            ? $validatedData['scheduled_visit_date']
            : $technicianRequest->scheduled_visit_date;
        $nextTime = array_key_exists('scheduled_visit_time', $validatedData)
            ? $validatedData['scheduled_visit_time']
            : $technicianRequest->scheduled_visit_time;
        $nextTechnicianId = array_key_exists('technician_id', $validatedData)
            ? $validatedData['technician_id']
            : $technicianRequest->technician_id;

        if (! empty($nextTime) && empty($nextDate)) {
            throw ValidationException::withMessages([
                'scheduled_visit_date' => 'Primero debes guardar la fecha de visita antes de asignar la hora.',
            ]);
        }

        $this->validateScheduledVisitSlotAvailability(
            currentRequestId: (int) $technicianRequest->id,
            technicianId: $nextTechnicianId,
            scheduledVisitDate: $nextDate,
            scheduledVisitTime: $nextTime,
            nextStatus: $validatedData['status'] ?? $technicianRequest->status,
        );

        if (empty($nextDate) && empty($nextTime)) {
            $validatedData['scheduled_visit_date'] = null;
            $validatedData['scheduled_visit_time'] = null;
        }
    }

    private function validateScheduledVisitSlotAvailability(
        int $currentRequestId,
        mixed $technicianId,
        mixed $scheduledVisitDate,
        mixed $scheduledVisitTime,
        mixed $nextStatus
    ): void {
        $status = (string) $nextStatus;
        if (
            $status === TechnicianRequest::STATUS_COMPLETED
            || $status === TechnicianRequest::STATUS_CANCELLED
        ) {
            return;
        }

        $normalizedDate = $this->normalizeDateString($scheduledVisitDate);
        $normalizedTimeForStorage = $this->normalizeTimeForStorage($scheduledVisitTime);
        $normalizedTimeWithoutSeconds = $this->normalizeTimeString($scheduledVisitTime);
        $normalizedTechnicianId = (int) $technicianId;

        if (
            $normalizedTechnicianId <= 0
            || $normalizedDate === null
            || $normalizedTimeForStorage === null
        ) {
            return;
        }

        $conflictExists = TechnicianRequest::query()
            ->where('id', '!=', $currentRequestId)
            ->where('technician_id', $normalizedTechnicianId)
            ->where('scheduled_visit_date', $normalizedDate)
            ->whereNotIn('status', [
                TechnicianRequest::STATUS_COMPLETED,
                TechnicianRequest::STATUS_CANCELLED,
            ])
            ->where(function ($query) use ($normalizedTimeForStorage, $normalizedTimeWithoutSeconds) {
                $query->where('scheduled_visit_time', $normalizedTimeForStorage);
                if ($normalizedTimeWithoutSeconds !== null) {
                    $query->orWhere('scheduled_visit_time', $normalizedTimeWithoutSeconds);
                }
            })
            ->exists();

        if ($conflictExists) {
            throw ValidationException::withMessages([
                'scheduled_visit_time' => 'Ese horario ya esta asignado para otra visita del tecnico.',
            ]);
        }
    }

    private function validateResolutionSummaryOnComplete(TechnicianRequest $technicianRequest, array &$validatedData): void
    {
        $nextStatus = $validatedData['status'] ?? $technicianRequest->status;
        if ($nextStatus !== TechnicianRequest::STATUS_COMPLETED) {
            return;
        }

        $scheduledVisitDate = $validatedData['scheduled_visit_date'] ?? $technicianRequest->scheduled_visit_date;
        if (empty($scheduledVisitDate)) {
            throw ValidationException::withMessages([
                'scheduled_visit_date' => 'Debes seleccionar la fecha en la que visitaras al cliente antes de completar la tarea.',
            ]);
        }

        $scheduledVisitTime = $validatedData['scheduled_visit_time'] ?? $technicianRequest->scheduled_visit_time;
        $normalizedVisitTime = $this->normalizeTimeForStorage($scheduledVisitTime);
        if ($normalizedVisitTime === null) {
            throw ValidationException::withMessages([
                'scheduled_visit_time' => 'Debes indicar la hora estimada en la que visitaras al cliente antes de completar la tarea.',
            ]);
        }

        $this->validateScheduledVisitTimeMatchesRequestedShift($technicianRequest, $normalizedVisitTime);

        $scheduled = Carbon::parse($scheduledVisitDate)->startOfDay();
        $wantedStart = Carbon::parse($technicianRequest->wanted_date_start)->startOfDay();
        $wantedEnd = Carbon::parse($technicianRequest->wanted_date_end)->startOfDay();
        if ($scheduled->lt($wantedStart) || $scheduled->gt($wantedEnd)) {
            throw ValidationException::withMessages([
                'scheduled_visit_date' => 'La fecha elegida debe estar dentro del rango solicitado por el cliente.',
            ]);
        }

        $validatedData['scheduled_visit_time'] = $normalizedVisitTime;

        $summary = array_key_exists('resolution_summary', $validatedData)
            ? trim((string) $validatedData['resolution_summary'])
            : trim((string) ($technicianRequest->resolution_summary ?? ''));

        if ($summary === '') {
            throw ValidationException::withMessages([
                'resolution_summary' => 'Debes ingresar una descripcion de la solucion antes de completar la tarea.',
            ]);
        }

        $validatedData['resolution_summary'] = $summary;

        $repairedProductId = $validatedData['repaired_product_id'] ?? $technicianRequest->repaired_product_id;
        if (empty($repairedProductId)) {
            throw ValidationException::withMessages([
                'repaired_product_id' => 'Debes seleccionar el producto reparado antes de completar la tarea.',
            ]);
        }

        $validatedData['repaired_product_id'] = (int) $repairedProductId;
    }

    private function validateCompletionDetailsOnComplete(TechnicianRequest $technicianRequest, array &$validatedData): void
    {
        $nextStatus = $validatedData['status'] ?? $technicianRequest->status;
        if ($nextStatus !== TechnicianRequest::STATUS_COMPLETED) {
            if (array_key_exists('status', $validatedData)) {
                $validatedData['charged_amount'] = null;
            }
            return;
        }

        $summary = array_key_exists('resolution_summary', $validatedData)
            ? trim((string) $validatedData['resolution_summary'])
            : trim((string) ($technicianRequest->resolution_summary ?? ''));

        if ($summary === '') {
            throw ValidationException::withMessages([
                'resolution_summary' => 'Debes ingresar una descripcion de la solucion antes de completar la tarea.',
            ]);
        }
        $validatedData['resolution_summary'] = $summary;

        $chargedAmountRaw = array_key_exists('charged_amount', $validatedData)
            ? $validatedData['charged_amount']
            : $technicianRequest->charged_amount;

        if ($chargedAmountRaw === null || $chargedAmountRaw === '' || ! is_numeric($chargedAmountRaw)) {
            throw ValidationException::withMessages([
                'charged_amount' => 'Debes indicar cuanto se cobro por la tarea antes de completarla.',
            ]);
        }

        $chargedAmount = round((float) $chargedAmountRaw, 2);
        if ($chargedAmount <= 0) {
            throw ValidationException::withMessages([
                'charged_amount' => 'El monto cobrado debe ser mayor a 0.',
            ]);
        }

        $validatedData['charged_amount'] = $chargedAmount;
    }


    private function validateCancellationReasonOnCancel(TechnicianRequest $technicianRequest, array &$validatedData): void
    {
        $nextStatus = $validatedData['status'] ?? $technicianRequest->status;
        if ($nextStatus === TechnicianRequest::STATUS_CANCELLED) {
            $reason = array_key_exists('cancellation_reason', $validatedData)
                ? trim((string) $validatedData['cancellation_reason'])
                : trim((string) ($technicianRequest->cancellation_reason ?? ''));

            if ($reason === '') {
                throw ValidationException::withMessages([
                    'cancellation_reason' => 'Debes ingresar una justificacion para cancelar el reclamo.',
                ]);
            }

            $validatedData['cancellation_reason'] = $reason;
            return;
        }

        if (array_key_exists('status', $validatedData)) {
            $validatedData['cancellation_reason'] = null;
        }
    }

    private function normalizeCompletionDate(TechnicianRequest $technicianRequest, array &$validatedData): void
    {
        $nextStatus = $validatedData['status'] ?? $technicianRequest->status;

        if (
            $nextStatus === TechnicianRequest::STATUS_COMPLETED
            || $nextStatus === TechnicianRequest::STATUS_CANCELLED
        ) {
            $validatedData['completed_at'] = $technicianRequest->completed_at ?? now();
            return;
        }

        if (
            $technicianRequest->status === TechnicianRequest::STATUS_COMPLETED
            || $technicianRequest->status === TechnicianRequest::STATUS_CANCELLED
        ) {
            $validatedData['completed_at'] = null;
        }
    }

    private function sendStatusNotificationIfNeeded(
        TechnicianRequest $technicianRequest,
        string $oldStatus,
        ?string $oldScheduledVisitDate,
        ?string $oldScheduledVisitTime,
        ?string $updatedByRole
    ): void {
        $newStatus = (string) $technicianRequest->status;
        $newScheduledVisitDate = $this->normalizeDateString($technicianRequest->scheduled_visit_date);
        $newScheduledVisitTime = $this->normalizeTimeString($technicianRequest->scheduled_visit_time);

        $statusChanged = $oldStatus !== $newStatus;
        $visitScheduleChanged = $oldScheduledVisitDate !== $newScheduledVisitDate
            || $oldScheduledVisitTime !== $newScheduledVisitTime;

        if (! $statusChanged && ! $visitScheduleChanged) {
            return;
        }

        $technicianRequest->loadMissing('requestingUser');
        $email = trim((string) ($technicianRequest->requestingUser?->email ?? ''));
        if ($email === '') {
            Log::warning('TechnicianRequest.notification: usuario sin email, se omite aviso', [
                'technician_request_id' => $technicianRequest->id,
                'requesting_user_id' => $technicianRequest->requesting_user_id,
            ]);
            return;
        }

        $statusLabel = $this->statusLabel($newStatus);
        $typeLabel = $this->typeLabel($technicianRequest->type);
        $updatedByLabel = $this->updatedByLabel($updatedByRole);

        if (app()->environment('testing')) {
            try {
                Mail::to($email)->send(new TechnicianRequestStatusNotificationMail(
                    technicianRequest: $technicianRequest,
                    statusLabel: $statusLabel,
                    typeLabel: $typeLabel,
                    scheduledVisitDate: $newScheduledVisitDate,
                    scheduledVisitTime: $newScheduledVisitTime,
                    updatedByLabel: $updatedByLabel
                ));

                Log::info('TechnicianRequest.notification: aviso enviado al cliente', [
                    'technician_request_id' => $technicianRequest->id,
                    'email' => $email,
                    'status_changed' => $statusChanged,
                    'visit_schedule_changed' => $visitScheduleChanged,
                    'status' => $newStatus,
                    'scheduled_visit_date' => $newScheduledVisitDate,
                    'scheduled_visit_time' => $newScheduledVisitTime,
                ]);
            } catch (\Throwable $exception) {
                Log::error('TechnicianRequest.notification: error al enviar aviso', [
                    'technician_request_id' => $technicianRequest->id,
                    'email' => $email,
                    'error' => $exception->getMessage(),
                ]);
            }

            return;
        }

        $this->enqueueStatusNotification(
            technicianRequestId: (int) $technicianRequest->id,
            email: $email,
            statusLabel: $statusLabel,
            typeLabel: $typeLabel,
            scheduledVisitDate: $newScheduledVisitDate,
            scheduledVisitTime: $newScheduledVisitTime,
            updatedByLabel: $updatedByLabel,
            logContext: 'TechnicianRequest.notification',
            logData: [
                'technician_request_id' => $technicianRequest->id,
                'status_changed' => $statusChanged,
                'visit_schedule_changed' => $visitScheduleChanged,
                'status' => $newStatus,
                'scheduled_visit_date' => $newScheduledVisitDate,
                'scheduled_visit_time' => $newScheduledVisitTime,
            ],
        );
    }

    private function enqueueStatusNotification(
        int $technicianRequestId,
        string $email,
        string $statusLabel,
        string $typeLabel,
        ?string $scheduledVisitDate,
        ?string $scheduledVisitTime,
        string $updatedByLabel,
        string $logContext,
        array $logData = []
    ): void {
        SendTechnicianRequestStatusNotificationJob::dispatch(
            technicianRequestId: $technicianRequestId,
            email: $email,
            statusLabel: $statusLabel,
            typeLabel: $typeLabel,
            scheduledVisitDate: $scheduledVisitDate,
            scheduledVisitTime: $scheduledVisitTime,
            updatedByLabel: $updatedByLabel,
            logContext: $logContext,
            logData: $logData,
        );

        $this->spawnNotificationWorkerIfNeeded();

        Log::debug("{$logContext}: aviso encolado en database/mail-notifications", $logData + [
            'technician_request_id' => $technicianRequestId,
            'email' => $email,
        ]);
    }

    private function spawnNotificationWorkerIfNeeded(): void
    {
        if (filter_var(env('DISABLE_NOTIFICATION_WORKER_SPAWN', false), FILTER_VALIDATE_BOOL)) {
            return;
        }

        if ($this->notificationWorkerSpawned || app()->environment('testing')) {
            return;
        }

        $this->notificationWorkerSpawned = true;

        $phpBinary = escapeshellarg(PHP_BINARY ?: 'php');
        $artisanPath = escapeshellarg(base_path('artisan'));
        $workerCommand = "{$phpBinary} {$artisanPath} queue:work database --queue=mail-notifications --stop-when-empty --tries=1 --timeout=120 --no-interaction";

        if (DIRECTORY_SEPARATOR === '\\') {
            $processHandle = @popen("cmd /C start /B \"\" {$workerCommand} >NUL 2>&1", 'r');
            if ($processHandle === false) {
                Log::warning('TechnicianRequest.notification: no se pudo iniciar worker de cola', [
                    'queue' => 'mail-notifications',
                ]);
                return;
            }

            @pclose($processHandle);
            Log::debug('TechnicianRequest.notification: worker de cola lanzado en background', [
                'queue' => 'mail-notifications',
                'connection' => 'database',
            ]);
            return;
        }

        @exec("{$workerCommand} > /dev/null 2>&1 &");
        Log::debug('TechnicianRequest.notification: worker de cola lanzado en background', [
            'queue' => 'mail-notifications',
            'connection' => 'database',
        ]);
    }

    private function normalizeDateString(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeTimeString(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $normalized) === 1) {
            return $normalized;
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $normalized) === 1) {
            return substr($normalized, 0, 5);
        }

        try {
            return Carbon::createFromFormat('H:i:s', $normalized)->format('H:i');
        } catch (\Throwable) {
            try {
                return Carbon::createFromFormat('H:i', $normalized)->format('H:i');
            } catch (\Throwable) {
                return null;
            }
        }
    }

    private function normalizeTimeForStorage(mixed $value): ?string
    {
        $normalized = $this->normalizeTimeString($value);
        if ($normalized === null) {
            return null;
        }

        return "{$normalized}:00";
    }

    private function validateScheduledVisitTimeMatchesRequestedShift(
        TechnicianRequest $technicianRequest,
        string $scheduledVisitTime
    ): void {
        $shift = $this->normalizeShiftValue($technicianRequest->time_shift);
        if ($shift === null) {
            return;
        }

        $normalizedTime = $this->normalizeTimeString($scheduledVisitTime);
        if ($normalizedTime === null) {
            return;
        }

        if ($shift === 'morning' && $normalizedTime >= '12:00') {
            throw ValidationException::withMessages([
                'scheduled_visit_time' => 'El turno solicitado por el cliente es manana. Solo puedes indicar horarios AM.',
            ]);
        }

        if ($shift === 'afternoon' && $normalizedTime < '12:00') {
            throw ValidationException::withMessages([
                'scheduled_visit_time' => 'El turno solicitado por el cliente es tarde. Solo puedes indicar horarios PM.',
            ]);
        }
    }

    private function normalizeShiftValue(mixed $value): ?string
    {
        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            'morning', 'manana', 'mañana' => 'morning',
            'afternoon', 'tarde' => 'afternoon',
            default => null,
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            TechnicianRequest::STATUS_PENDING => 'Pendiente',
            TechnicianRequest::STATUS_ASSIGNED => 'Asignado',
            TechnicianRequest::STATUS_COMPLETED => 'Completado',
            TechnicianRequest::STATUS_CANCELLED => 'Cancelado',
            default => ucfirst($status),
        };
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            TechnicianRequest::TYPE_CLAIM => 'reclamo',
            TechnicianRequest::TYPE_TECHNICAL_SERVICE => 'solicitud tecnica',
            default => 'solicitud',
        };
    }

    private function updatedByLabel(?string $role): string
    {
        return match ($role) {
            'admin' => 'administracion',
            'technician' => 'tecnico',
            default => 'equipo de soporte',
        };
    }
}
