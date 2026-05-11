<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\GameController;
use App\Http\Controllers\API\GameTypeController;
use App\Http\Controllers\API\LessonPlanController;
use App\Http\Controllers\API\SessionController;
use Illuminate\Support\Facades\Route;

// ─── Públicas ────────────────────────────────────────────────────────────────
Route::post('/auth/login',    [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/auth/register', [AuthController::class, 'register']);

Route::get('/sessions/{session}', [SessionController::class, 'show']);
Route::get('/game-types',         [GameTypeController::class, 'index']);
Route::get('/game-types/{gameType}', [GameTypeController::class, 'show']);

// ─── Protegidas (Sanctum) ─────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);

    // Juegos
    Route::get('/games',             [GameController::class, 'index']);
    Route::get('/games/{game}',      [GameController::class, 'show']);
    Route::post('/games',            [GameController::class, 'store']);
    Route::put('/games/{game}',      [GameController::class, 'update']);
    Route::delete('/games/{game}',   [GameController::class, 'destroy']);

    // Lesson plans
    Route::get('/lesson-plans',                  [LessonPlanController::class, 'index']);
    Route::get('/lesson-plans/{lessonPlan}',     [LessonPlanController::class, 'show']);
    Route::post('/lesson-plans',                 [LessonPlanController::class, 'store']);
    Route::put('/lesson-plans/{lessonPlan}',     [LessonPlanController::class, 'update']);
    Route::delete('/lesson-plans/{lessonPlan}',  [LessonPlanController::class, 'destroy']);

    // Sesiones
    Route::post('/sessions',                    [SessionController::class, 'store']);
    Route::post('/sessions/{session}/start',    [SessionController::class, 'start']);
    Route::post('/sessions/{session}/pause',    [SessionController::class, 'pause']);
    Route::post('/sessions/{session}/resume',   [SessionController::class, 'resume']);
    Route::post('/sessions/{session}/finish',   [SessionController::class, 'finish']);
});