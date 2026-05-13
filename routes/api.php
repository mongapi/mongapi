<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\GameController;
use App\Http\Controllers\API\GameTypeController;
use App\Http\Controllers\API\LessonPlanController;
use App\Http\Controllers\API\SessionController;
use Illuminate\Support\Facades\Route;

// ─── Públicas ──────────────────────────────────────────────────────────────
Route::post('/auth/login',    [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/auth/register', [AuthController::class, 'register']);

// Estado de sesión sin auth (mesas/pantallas la consultan al conectarse)
Route::get('/sessions/join/{pin}', [SessionController::class, 'joinByPin']);
Route::get('/sessions/{id}', [SessionController::class, 'show'])->whereNumber('id');
Route::post('/sessions/{id}/answers', [SessionController::class, 'submitAnswer'])->whereNumber('id');

// ─── Protegidas (Sanctum) ──────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);

    Route::get('/game-types',   [GameTypeController::class, 'index']);
    Route::get('/lesson-plans', [LessonPlanController::class, 'index']);
    Route::get('/lesson-plans/{lessonPlan}', [LessonPlanController::class, 'show']);

    // Juegos
    Route::get('/games',        [GameController::class, 'index']);
    Route::get('/games/{id}',   [GameController::class, 'show']);

    // Solo teacher/admin pueden crear/modificar juegos
    Route::middleware('role:teacher,admin')->group(function () {
        Route::post('/games',           [GameController::class, 'store']);
        Route::put('/games/{id}',       [GameController::class, 'update']);
        Route::delete('/games/{id}',    [GameController::class, 'destroy']);
        Route::post('/lesson-plans', [LessonPlanController::class, 'store']);
        Route::put('/lesson-plans/{lessonPlan}', [LessonPlanController::class, 'update']);
        Route::delete('/lesson-plans/{lessonPlan}', [LessonPlanController::class, 'destroy']);

        // Gestión de sesiones (profesor)
        Route::post('/sessions',              [SessionController::class, 'store']);
        Route::post('/sessions/{id}/start',   [SessionController::class, 'start']);
        Route::post('/sessions/{id}/pause',   [SessionController::class, 'pause']);
        Route::post('/sessions/{id}/resume',  [SessionController::class, 'resume']);
        Route::post('/sessions/{id}/next-phase', [SessionController::class, 'nextPhase']);
        Route::post('/sessions/{id}/finish',  [SessionController::class, 'finish']);
    });
});