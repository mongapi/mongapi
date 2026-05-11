<?php

namespace App\Observers;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

use App\Models\LessonPlan;

class LessonPlanObserver
{
    /**
     * Se ejecuta ANTES de crear un LessonPlan
     */
    public function creating(LessonPlan $lessonPlan): void
    {
        // Asignar user_id si no existe y hay usuario autenticado
        if (!$lessonPlan->user_id && Auth::check()) {
            $lessonPlan->user_id = Auth::id();
        }
    }

    /**
     * Se ejecuta DESPUÉS de crear un LessonPlan
     */
    public function created(LessonPlan $lessonPlan): void
    {
        // Logging básico
        Log::info("LessonPlan created", [
            'id' => $lessonPlan->id,
            'name' => $lessonPlan->name,
            'user_id' => $lessonPlan->user_id
        ]);
    }
}