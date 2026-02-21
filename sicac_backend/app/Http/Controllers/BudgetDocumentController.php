<?php

namespace App\Http\Controllers;

use App\Models\BudgetDocument;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BudgetDocumentController extends Controller
{
    public function myBudgets(Request $request)
    {
        $user = $request->user();

        $query = BudgetDocument::query()->orderBy('generated_at', 'desc');

        if ($user->isAdmin() && $request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        } else {
            $query->where('user_id', $user->id);
        }

        return response()->json([
            'data' => $query->get(),
        ], 200);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user->isUser() && ! $user->isAdmin()) {
            return response()->json([
                'message' => 'No autorizado para guardar presupuestos',
            ], 403);
        }

        $validated = $request->validate([
            'pdf_base64' => 'required|string',
            'file_name' => 'nullable|string|max:255',
            'total_amount' => 'nullable|numeric|min:0',
            'items_count' => 'nullable|integer|min:0',
            'metadata' => 'nullable|array',
            'generated_at' => 'nullable|date',
        ]);

        $rawPdfPayload = trim($validated['pdf_base64']);
        $base64Payload = $rawPdfPayload;

        if (str_contains($rawPdfPayload, ',')) {
            [$meta, $encoded] = explode(',', $rawPdfPayload, 2);
            $normalizedMeta = Str::lower($meta);

            if (
                str_starts_with($normalizedMeta, 'data:application/pdf')
                && str_contains($normalizedMeta, ';base64')
            ) {
                $base64Payload = $encoded;
            }
        }

        $base64Payload = preg_replace('/\s+/', '', $base64Payload ?? '');
        $binaryPdf = base64_decode($base64Payload, true);

        if ($binaryPdf === false) {
            return response()->json([
                'message' => 'El PDF enviado no tiene un formato base64 valido',
            ], 422);
        }

        $rawFileName = trim($validated['file_name'] ?? 'presupuesto-cea-insumos.pdf');
        $fileBaseName = pathinfo($rawFileName, PATHINFO_FILENAME);
        $safeBaseName = Str::slug($fileBaseName);
        if ($safeBaseName === '') {
            $safeBaseName = 'presupuesto-cea-insumos';
        }

        $generatedAt = isset($validated['generated_at'])
            ? Carbon::parse($validated['generated_at'])
            : now();

        $storageFileName = sprintf(
            '%s-%s-%s.pdf',
            $safeBaseName,
            $generatedAt->format('YmdHis'),
            Str::lower(Str::random(6))
        );

        $displayFileName = $safeBaseName . '.pdf';
        $relativePath = sprintf('budgets/%d/%s', $user->id, $storageFileName);
        Storage::disk('local')->put($relativePath, $binaryPdf);

        $budgetDocument = BudgetDocument::create([
            'user_id' => $user->id,
            'file_name' => $displayFileName,
            'file_path' => $relativePath,
            'total_amount' => $validated['total_amount'] ?? null,
            'items_count' => $validated['items_count'] ?? 0,
            'metadata' => $validated['metadata'] ?? null,
            'generated_at' => $generatedAt,
        ]);

        return response()->json([
            'message' => 'Presupuesto guardado correctamente',
            'data' => $budgetDocument,
        ], 201);
    }

    public function download(Request $request, BudgetDocument $budgetDocument)
    {
        $user = $request->user();

        if ((int) $budgetDocument->user_id !== (int) $user->id && ! $user->isAdmin()) {
            return response()->json([
                'message' => 'No autorizado para descargar este presupuesto',
            ], 403);
        }

        if (! Storage::disk('local')->exists($budgetDocument->file_path)) {
            return response()->json([
                'message' => 'El archivo de presupuesto no existe',
            ], 404);
        }

        return Storage::disk('local')->download(
            $budgetDocument->file_path,
            $budgetDocument->file_name
        );
    }
}
