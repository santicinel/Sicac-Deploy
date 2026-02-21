<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\ProductFilterController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SubfamilyController;
use App\Http\Controllers\TechnicianController;
use App\Http\Controllers\TechnicianRequestController;
use App\Http\Controllers\ClaimController;
use App\Http\Controllers\BudgetDocumentController;
use App\Http\Controllers\AppSettingController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\RatingSummaryController;
use App\Http\Controllers\ServiceFeedbackController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/login/admin', [AuthController::class, 'loginAdmin']);
Route::post('/login/technician', [AuthController::class, 'loginTechnician']);
Route::post('/login/user', [AuthController::class, 'loginUser']);
Route::post('/create-admin', [AuthController::class, 'createAdmin']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware(middleware: 'auth:sanctum');
Route::get('/user', [AuthController::class, 'user'])->middleware('auth:sanctum');
Route::patch('/user', [AuthController::class, 'updateUser'])->middleware('auth:sanctum');
Route::middleware('auth:sanctum')->group(function () {
    // Usuarios: crear solicitudes técnicas
    Route::post('/technician-requests', [TechnicianRequestController::class, 'store']);
    
    // Usuarios: ver sus solicitudes creadas
    Route::get('/technician-requests/user-requests', [TechnicianRequestController::class, 'userRequests']);
    
    // Admins: obtener todas las solicitudes (con filtros opcionales)
    Route::get('/technician-requests/admin/all', [TechnicianRequestController::class, 'index']);
    
    // Admins: obtener estadísticas
    Route::get('/technician-requests/admin/stats', [TechnicianRequestController::class, 'stats']);
    
    // Technicians: ver solicitudes disponibles (sin técnico asignado)
    Route::get('/technician-requests/unassigned', [TechnicianRequestController::class, 'unassignedRequests']);
    
    // Technicians: asignarse a sí mismo una solicitud sin técnico
    Route::patch('/technician-requests/{technicianRequest}/assign-to-myself', [TechnicianRequestController::class, 'assignToMyself']);
    
    // Technicians: ver sus solicitudes asignadas
    Route::get('/technician-requests/my-requests', [TechnicianRequestController::class, 'myRequests']);
    
    // Technicians: actualizar estado de una solicitud
    Route::patch('/technician-requests/{technicianRequest}/status', [TechnicianRequestController::class, 'updateStatus']);
    
    // Admins: actualizar cualquier campo de una solicitud
    Route::patch('/technician-requests/{technicianRequest}', [TechnicianRequestController::class, 'update']);
    
    // Admins: eliminar una solicitud
    Route::delete('/technician-requests/{technicianRequest}', [TechnicianRequestController::class, 'destroy']);

    // Tecnicos: puntuar cliente de una solicitud completada
    Route::post('/technician-requests/{technicianRequest}/client-rating', [ServiceFeedbackController::class, 'storeClientRating']);

    // Usuarios: crear reclamos
    Route::post('/claims', [ClaimController::class, 'store']);

    // Usuarios: ver sus reclamos
    Route::get('/claims/user-claims', [ClaimController::class, 'userClaims']);

    // Admins: obtener todos los reclamos
    Route::get('/claims/admin/all', [ClaimController::class, 'index']);

    // Admins: obtener estadísticas de reclamos
    Route::get('/claims/admin/stats', [ClaimController::class, 'stats']);

    // Admins: actualizar reclamo (incluye status)
    Route::patch('/claims/{claim}', [ClaimController::class, 'update']);

    // Admins: responder un reclamo
    Route::patch('/claims/{claim}/answer', [ClaimController::class, 'answer']);

    // Admins: eliminar reclamo
    Route::delete('/claims/{claim}', [ClaimController::class, 'destroy']);

    // Presupuestos PDF por usuario
    Route::post('/budgets', [BudgetDocumentController::class, 'store']);
    Route::get('/budgets/my', [BudgetDocumentController::class, 'myBudgets']);
    Route::get('/budgets/{budgetDocument}/download', [BudgetDocumentController::class, 'download']);

    // Parametros globales (mano de obra)
    Route::get('/settings/labor-rate', [AppSettingController::class, 'laborRate']);
    Route::patch('/settings/labor-rate', [AppSettingController::class, 'updateLaborRate']);

    // Resumen de puntajes (admin)
    Route::get('/ratings', [RatingSummaryController::class, 'index']);
});

Route::apiResource('technicians', TechnicianController::class);

Route::post('/technicians/{technician}/reviews', [RatingController::class, 'store'])->middleware('auth:sanctum');

Route::apiResource('products', ProductController::class);
Route::apiResource('brands', BrandController::class)->only(['index']);
Route::apiResource('categories', CategoryController::class)->only(['index']);
Route::apiResource('families', FamilyController::class)->only(['index']);
Route::apiResource('subfamilies', SubfamilyController::class)->only(['index']);

Route::prefix('products/filters')->group(function () {
    Route::get('/brands', [ProductFilterController::class, 'brands']);
    Route::get('/categories', [ProductFilterController::class, 'categories']);
    Route::get('/families', [ProductFilterController::class, 'families']);
    Route::get('/subfamilies', [ProductFilterController::class, 'subfamilies']);
});
