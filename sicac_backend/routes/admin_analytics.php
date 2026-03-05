<?php

// =========================================================================
// INSTRUCCIONES ESTRICTAS:
// Ya que la regla es NO modificar archivos existentes, debes agregar la
// siguiente línea en tu archivo `sicac_backend/routes/api.php`.
//
// 1. Agrega el 'use' en la parte superior del archivo api.php:
// use App\Http\Controllers\AnalyticsController;
//
// 2. Agrega la siguiente ruta dentro del bloque de `Route::middleware('auth:sanctum')->group(...)`
// preferiblemente cerca de las rutas de admin:
// =========================================================================

Route::get('/analytics/admin/stats', [AnalyticsController::class, 'getStats']);

// =========================================================================
// Fin de las instrucciones
// =========================================================================
