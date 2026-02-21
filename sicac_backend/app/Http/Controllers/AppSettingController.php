<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AppSettingController extends Controller
{
    private const LABOR_RATE_KEY = 'labor_rate_ars_per_hour';
    private const DEFAULT_LABOR_RATE = 1500.0;

    public function laborRate(Request $request)
    {
        $user = Auth::user();

        Log::info('AppSetting.laborRate: obtener tarifa mano de obra', [
            'user_id' => $user?->id,
            'user_role' => $user?->role,
        ]);

        return response()->json([
            'data' => [
                'labor_rate' => $this->resolveLaborRate(),
            ],
        ], 200);
    }

    public function updateLaborRate(Request $request)
    {
        $user = Auth::user();

        if (! $user || ! $user->isAdmin()) {
            return response()->json([
                'message' => 'No autorizado',
            ], 403);
        }

        $validatedData = $request->validate([
            'labor_rate' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
        ]);

        $normalizedRate = $this->normalizeLaborRate($validatedData['labor_rate']);

        AppSetting::updateOrCreate(
            ['key' => self::LABOR_RATE_KEY],
            ['value' => (string) $normalizedRate]
        );

        Log::info('AppSetting.updateLaborRate: tarifa actualizada', [
            'user_id' => $user->id,
            'labor_rate' => $normalizedRate,
        ]);

        return response()->json([
            'message' => 'Tarifa actualizada correctamente',
            'data' => [
                'labor_rate' => $normalizedRate,
            ],
        ], 200);
    }

    private function resolveLaborRate(): float
    {
        $setting = AppSetting::where('key', self::LABOR_RATE_KEY)->first();

        if (! $setting) {
            return self::DEFAULT_LABOR_RATE;
        }

        return $this->normalizeLaborRate($setting->value);
    }

    private function normalizeLaborRate(mixed $value): float
    {
        $numeric = (float) $value;
        if (! is_finite($numeric) || $numeric < 0) {
            return self::DEFAULT_LABOR_RATE;
        }

        return round($numeric, 2);
    }
}
