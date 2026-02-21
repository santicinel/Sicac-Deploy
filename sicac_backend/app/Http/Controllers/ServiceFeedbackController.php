<?php

namespace App\Http\Controllers;

use App\Models\ClientRating;
use App\Models\Technician;
use App\Models\TechnicianRequest;
use Illuminate\Http\Request;

class ServiceFeedbackController extends Controller
{
    public function storeClientRating(Request $request, TechnicianRequest $technicianRequest)
    {
        $user = $request->user();

        if (! $user->isTechnician() && ! $user->isAdmin()) {
            return response()->json([
                'message' => 'No autorizado para puntuar clientes',
            ], 403);
        }

        if ($technicianRequest->status !== TechnicianRequest::STATUS_COMPLETED) {
            return response()->json([
                'message' => 'Solo se puede puntuar al cliente una vez completada la solicitud',
            ], 422);
        }

        $validated = $request->validate([
            'score' => 'required|integer|min:1|max:5',
            'description' => 'nullable|string|max:1000',
            'technician_id' => 'nullable|exists:technicians,id',
        ]);

        $technicianId = null;
        if ($user->isTechnician()) {
            $technician = Technician::where('user_id', $user->id)->first();
            if (! $technician) {
                return response()->json([
                    'message' => 'No se encontro un perfil tecnico para el usuario autenticado',
                ], 404);
            }

            if ((int) $technicianRequest->technician_id !== (int) $technician->id) {
                return response()->json([
                    'message' => 'Solo puedes puntuar clientes de solicitudes que te pertenecen',
                ], 403);
            }

            $technicianId = (int) $technician->id;
        } else {
            $technicianId = (int) ($validated['technician_id'] ?? $technicianRequest->technician_id ?? 0);
            if ($technicianId <= 0) {
                return response()->json([
                    'message' => 'La solicitud no tiene tecnico asignado',
                ], 422);
            }
        }

        $clientRating = ClientRating::updateOrCreate(
            [
                'technician_request_id' => $technicianRequest->id,
                'technician_id' => $technicianId,
            ],
            [
                'client_user_id' => $technicianRequest->requesting_user_id,
                'score' => $validated['score'],
                'description' => $validated['description'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'Puntaje del cliente guardado correctamente',
            'data' => $clientRating,
        ], 201);
    }
}
