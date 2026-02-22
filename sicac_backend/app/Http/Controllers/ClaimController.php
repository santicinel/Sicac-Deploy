<?php

namespace App\Http\Controllers;

use App\Http\Traits\CanLoadRelationship;
use App\Mail\TechnicianRequestStatusNotificationMail;
use App\Models\Claim;
use App\Models\TechnicianRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ClaimController extends Controller
{
    use CanLoadRelationship;

    protected array $relations = ['requestingUser'];

    /**
     * Obtener todos los reclamos (solo admins) (READ)
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $userId = $user->id;

        Log::info('Claim.index: Intento de obtener todos los reclamos', [
            'user_id' => $userId,
            'user_role' => $user->role,
        ]);

        try {
            $this->authorize('viewAny', Claim::class);

            $status = $request->query('status'); // pending, completed, cancelled, answered
            $search = $request->query('search');
            $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

            $query = Claim::query()
                ->with('category')
                ->orderBy('created_at', 'desc');

            if ($status) {
                Log::debug('Claim.index: Filtrando por status', ['status' => $status]);
                $query->where('status', $status);
            }

            if ($search) {
                Log::debug('Claim.index: Buscando por texto', ['search' => $search]);
                $query->where(function ($q) use ($search) {
                    $q->where('subject', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $claims = $this->loadRelationship($query)
                ->paginate($perPage)
                ->withQueryString();

            Log::info('Claim.index: ✅ Reclamos obtenidos exitosamente', [
                'user_id' => $userId,
                'claims_count' => $claims->count(),
                'claims_total' => $claims->total(),
                'current_page' => $claims->currentPage(),
                'per_page' => $claims->perPage(),
            ]);

            return response()->json($claims, 200);
        } catch (\Exception $e) {
            Log::error('Claim.index: ❌ Error al obtener reclamos', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Obtener estadísticas de reclamos (solo admins) (READ)
     */
    public function stats(Request $request)
    {
        $user = Auth::user();
        $userId = $user->id;

        Log::info('Claim.stats: Intento de obtener estadísticas', [
            'user_id' => $userId,
            'user_role' => $user->role,
        ]);

        try {
            $this->authorize('viewAny', Claim::class);

            $stats = [
                'total' => Claim::count(),
                'by_status' => [
                    Claim::STATUS_PENDING => Claim::where('status', Claim::STATUS_PENDING)->count(),
                    Claim::STATUS_COMPLETED => Claim::where('status', Claim::STATUS_COMPLETED)->count(),
                    Claim::STATUS_CANCELLED => Claim::where('status', Claim::STATUS_CANCELLED)->count(),
                    Claim::STATUS_ANSWERED => Claim::where('status', Claim::STATUS_ANSWERED)->count(),
                ],
            ];

            Log::info('Claim.stats: ✅ Estadísticas obtenidas exitosamente', [
                'user_id' => $userId,
                'stats' => $stats,
            ]);

            return response()->json([
                'data' => $stats,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Claim.stats: ❌ Error al obtener estadísticas', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Crear un nuevo reclamo (CREATE)
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $userId = $user->id;

        Log::info('Claim.store: Intento de crear reclamo', [
            'user_id' => $userId,
        ]);

        try {
            $this->authorize('create', Claim::class);

            $validatedData = $request->validate(Claim::storeRules());

            $claim = DB::transaction(function () use ($validatedData, $userId) {
                $createdClaim = Claim::create([
                    'requesting_user_id' => $userId,
                    'category_id' => $validatedData['category_id'],
                    'status' => Claim::STATUS_PENDING,
                    'subject' => $validatedData['subject'],
                    'description' => $validatedData['description'],
                ]);

                TechnicianRequest::create([
                    'requesting_user_id' => $userId,
                    'category_id' => $validatedData['category_id'],
                    'claim_id' => $createdClaim->id,
                    'type' => TechnicianRequest::TYPE_CLAIM,
                    'status' => TechnicianRequest::STATUS_PENDING,
                    'subject' => $validatedData['subject'],
                    'description' => $validatedData['description'],
                    'wanted_date_start' => $validatedData['wanted_date_start'],
                    'wanted_date_end' => $validatedData['wanted_date_end'],
                    'time_shift' => $validatedData['time_shift'],
                ]);

                return $createdClaim;
            });

            Log::info('Claim.store: ✅ Reclamo creado exitosamente', [
                'user_id' => $userId,
                'claim_id' => $claim->id,
                'status' => $claim->status,
            ]);

            return response()->json([
                'message' => 'Reclamo creado correctamente',
                'data' => $this->loadRelationship($claim->load('category')),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Claim.store: ❌ Error al crear reclamo', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Obtener los reclamos creados por el usuario autenticado (READ)
     */
    public function userClaims(Request $request)
    {
        $userId = Auth::id();

        Log::info('Claim.userClaims: Intento de obtener reclamos del usuario', [
            'user_id' => $userId,
        ]);

        try {
            $this->authorize('viewOwn', Claim::class);

            $query = Claim::where('requesting_user_id', $userId)
                ->with('category')
                ->orderBy('created_at', 'desc');

            $claims = $this->loadRelationship($query)->get();

            Log::info('Claim.userClaims: ✅ Reclamos obtenidos exitosamente', [
                'user_id' => $userId,
                'claims_count' => $claims->count(),
            ]);

            return response()->json([
                'data' => $claims,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Claim.userClaims: ❌ Error al obtener reclamos', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Actualizar reclamo (solo admin) (UPDATE)
     */
    public function update(Request $request, Claim $claim)
    {
        $user = Auth::user();
        $userId = $user->id;

        Log::info('Claim.update: Intento de actualizar reclamo', [
            'user_id' => $userId,
            'user_role' => $user->role,
            'claim_id' => $claim->id,
        ]);

        try {
            $this->authorize('update', $claim);

            $validatedData = $request->validate(Claim::updateRules());
            if (isset($validatedData['status'])) {
                $validatedData['status'] = Claim::normalizeUpdatedStatus($validatedData['status']);
            }

            $oldData = $claim->toArray();
            $claim->update($validatedData);

            if (isset($validatedData['status'])) {
                $this->syncLinkedTechnicianRequestsStatus($claim, $validatedData['status'], $user->role);
            }

            Log::info('Claim.update: ✅ Reclamo actualizado exitosamente', [
                'user_id' => $userId,
                'claim_id' => $claim->id,
                'changes' => array_diff_assoc($claim->toArray(), $oldData),
            ]);

            return response()->json([
                'message' => 'Reclamo actualizado correctamente',
                'data' => $this->loadRelationship($claim->load('category')),
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Claim.update: ⚠️ Error de validación', [
                'user_id' => $userId,
                'claim_id' => $claim->id,
                'errors' => $e->errors(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Claim.update: ❌ Error al actualizar reclamo', [
                'user_id' => $userId,
                'claim_id' => $claim->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function answer(Request $request, Claim $claim)
    {
        $user = Auth::user();
        $userId = $user->id;

        Log::info('Claim.answer: Intento de responder reclamo', [
            'user_id' => $userId,
            'user_role' => $user->role,
            'claim_id' => $claim->id,
        ]);

        try {
            $this->authorize('answer', $claim);

            $validatedData = $request->validate([
                'answer' => 'nullable|string',
            ]);

            $claim->setAnswer($validatedData['answer'] ?? null);
            $this->syncLinkedTechnicianRequestsStatus($claim, null, $user->role);

            Log::info('Claim.answer: ✅ Reclamo respondido exitosamente', [
                'user_id' => $userId,
                'claim_id' => $claim->id,
                'status' => $claim->status,
                'answered_at' => $claim->answered_at,
            ]);

            return response()->json([
                'message' => 'Reclamo respondido correctamente',
                'data' => $this->loadRelationship($claim->load('category')),
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Claim.answer: ⚠️ Error de validación', [
                'user_id' => $userId,
                'claim_id' => $claim->id,
                'errors' => $e->errors(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Claim.answer: ❌ Error al responder reclamo', [
                'user_id' => $userId,
                'claim_id' => $claim->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Eliminar reclamo (solo admin) (DELETE)
     */
    public function destroy(Request $request, Claim $claim)
    {
        $user = Auth::user();
        $userId = $user->id;

        Log::info('Claim.destroy: Intento de eliminar reclamo', [
            'user_id' => $userId,
            'user_role' => $user->role,
            'claim_id' => $claim->id,
        ]);

        try {
            $this->authorize('delete', $claim);

            $claimId = $claim->id;
            $claimData = $claim->toArray();

            $claim->delete();

            Log::info('Claim.destroy: ✅ Reclamo eliminado exitosamente', [
                'user_id' => $userId,
                'claim_id' => $claimId,
            ]);

            return response()->json([
                'message' => 'Reclamo eliminado correctamente',
                'data' => $claimData,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Claim.destroy: ❌ Error al eliminar reclamo', [
                'user_id' => $userId,
                'claim_id' => $claim->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function syncLinkedTechnicianRequestsStatus(
        Claim $claim,
        ?string $forcedClaimStatus = null,
        ?string $updatedByRole = null
    ): void
    {
        $claimStatus = $forcedClaimStatus ?? $claim->status;
        $requestStatus = match ($claimStatus) {
            Claim::STATUS_COMPLETED, Claim::STATUS_ANSWERED => TechnicianRequest::STATUS_COMPLETED,
            Claim::STATUS_CANCELLED => TechnicianRequest::STATUS_CANCELLED,
            default => TechnicianRequest::STATUS_PENDING,
        };

        $linkedRequests = TechnicianRequest::where('claim_id', $claim->id)->get();

        foreach ($linkedRequests as $technicianRequest) {
            $oldStatus = (string) $technicianRequest->status;
            if ($oldStatus === $requestStatus) {
                continue;
            }

            $technicianRequest->status = $requestStatus;
            $technicianRequest->save();

            $this->sendLinkedRequestStatusNotification(
                $technicianRequest,
                $oldStatus,
                $updatedByRole
            );
        }
    }

    private function sendLinkedRequestStatusNotification(
        TechnicianRequest $technicianRequest,
        string $oldStatus,
        ?string $updatedByRole
    ): void {
        $newStatus = (string) $technicianRequest->status;
        if ($oldStatus === $newStatus) {
            return;
        }

        $technicianRequest->loadMissing('requestingUser');
        $email = trim((string) ($technicianRequest->requestingUser?->email ?? ''));
        if ($email === '') {
            Log::warning('Claim.sync.notification: usuario sin email, se omite aviso', [
                'technician_request_id' => $technicianRequest->id,
                'requesting_user_id' => $technicianRequest->requesting_user_id,
            ]);
            return;
        }

        $scheduledVisitDate = $this->normalizeDateString($technicianRequest->scheduled_visit_date);
        $statusLabel = $this->requestStatusLabel($newStatus);
        $typeLabel = $this->requestTypeLabel((string) $technicianRequest->type);
        $updatedByLabel = $this->updatedByLabel($updatedByRole);

        try {
            Mail::to($email)->send(new TechnicianRequestStatusNotificationMail(
                technicianRequest: $technicianRequest,
                statusLabel: $statusLabel,
                typeLabel: $typeLabel,
                scheduledVisitDate: $scheduledVisitDate,
                updatedByLabel: $updatedByLabel
            ));

            Log::info('Claim.sync.notification: aviso enviado al cliente', [
                'technician_request_id' => $technicianRequest->id,
                'claim_id' => $technicianRequest->claim_id,
                'email' => $email,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Claim.sync.notification: error al enviar aviso', [
                'technician_request_id' => $technicianRequest->id,
                'claim_id' => $technicianRequest->claim_id,
                'email' => $email,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function normalizeDateString(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function requestStatusLabel(string $status): string
    {
        return match ($status) {
            TechnicianRequest::STATUS_PENDING => 'Pendiente',
            TechnicianRequest::STATUS_ASSIGNED => 'Asignado',
            TechnicianRequest::STATUS_COMPLETED => 'Completado',
            TechnicianRequest::STATUS_CANCELLED => 'Cancelado',
            default => ucfirst($status),
        };
    }

    private function requestTypeLabel(string $type): string
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
