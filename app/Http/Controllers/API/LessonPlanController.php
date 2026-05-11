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
        $request->validate([
            'name'  => 'required|string|max:255',
            'games' => 'nullable|array',
        ]);

        $plan = LessonPlan::create($request->only(['name', 'games']));
        return response()->json(['data' => $plan, 'message' => 'Plan creado'], 201);
    }

    public function update(Request $request, LessonPlan $lessonPlan): JsonResponse
    {
        $request->validate([
            'name'  => 'sometimes|string|max:255',
            'games' => 'nullable|array',
        ]);

        $lessonPlan->update($request->only(['name', 'games']));
        return response()->json(['data' => $lessonPlan, 'message' => 'Plan actualizado']);
    }

    public function destroy(LessonPlan $lessonPlan): JsonResponse
    {
        $lessonPlan->delete();
        return response()->json(['message' => 'Plan eliminado']);
    }
}