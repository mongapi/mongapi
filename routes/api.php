<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\GameController;
use App\Http\Controllers\API\SessionController;
use Illuminate\Support\Facades\Route;

// ─── Públicas ──────────────────────────────────────────────────────────────
Route::post('/auth/login',    [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/auth/register', [AuthController::class, 'register']);

// Estado de sesión sin auth (mesas/pantallas la consultan al conectarse)
Route::get('/sessions/{id}', [SessionController::class, 'show']);

// ─── Protegidas (Sanctum) ──────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);

    // Juegos
    Route::get('/games',        [GameController::class, 'index']);
    Route::get('/games/{id}',   [GameController::class, 'show']);

    // Solo teacher/admin pueden crear/modificar juegos
    Route::middleware('role:teacher,admin')->group(function () {
        Route::post('/games',           [GameController::class, 'store']);
        Route::put('/games/{id}',       [GameController::class, 'update']);
        Route::delete('/games/{id}',    [GameController::class, 'destroy']);

        // Gestión de sesiones (profesor)
        Route::post('/sessions',              [SessionController::class, 'store']);
        Route::post('/sessions/{id}/start',   [SessionController::class, 'start']);
        Route::post('/sessions/{id}/pause',   [SessionController::class, 'pause']);
        Route::post('/sessions/{id}/resume',  [SessionController::class, 'resume']);
        Route::post('/sessions/{id}/finish',  [SessionController::class, 'finish']);
    });
});