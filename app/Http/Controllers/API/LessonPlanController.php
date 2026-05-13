<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\LessonPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonPlanController extends Controller
{
    public function index(): JsonResponse
    {
        $plans = LessonPlan::all();
        return response()->json(['data' => $plans]);
    }

    public function show(LessonPlan $lessonPlan): JsonResponse
    {
        return response()->json(['data' => $lessonPlan]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'description' => 'nullable|string',
            'game_ids' => 'required|array|min:1',
            'game_ids.*' => 'integer|exists:games,id',
            'is_active' => 'sometimes|boolean',
        ]);

        $plan = LessonPlan::create($validated);
        return response()->json(['data' => $plan, 'message' => 'Plan creado'], 201);
    }

    public function update(Request $request, LessonPlan $lessonPlan): JsonResponse
    {
        $validated = $request->validate([
            'name'  => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'game_ids' => 'sometimes|array|min:1',
            'game_ids.*' => 'integer|exists:games,id',
            'is_active' => 'sometimes|boolean',
        ]);

        $lessonPlan->update($validated);
        return response()->json(['data' => $lessonPlan, 'message' => 'Plan actualizado']);
    }

    public function destroy(LessonPlan $lessonPlan): JsonResponse
    {
        $lessonPlan->delete();
        return response()->json(['message' => 'Plan eliminado']);
    }
}