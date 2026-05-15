<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\LessonPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LessonPlanController extends Controller
{
    public function index(): JsonResponse
    {
        $plans = LessonPlan::with(['user:id,name,email'])->get();
        return response()->json(['data' => $plans]);
    }

    public function show(LessonPlan $lessonPlan): JsonResponse
    {
        return response()->json(['data' => $lessonPlan->load(['user:id,name,email'])]);
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

        $plan = LessonPlan::create($validated)->load(['user:id,name,email']);
        return response()->json(['data' => $plan, 'message' => 'Plan creado'], 201);
    }

    public function update(Request $request, LessonPlan $lessonPlan): JsonResponse
    {
        $this->authorizeOwner($request, $lessonPlan);

        $validated = $request->validate([
            'name'  => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'game_ids' => 'sometimes|array|min:1',
            'game_ids.*' => 'integer|exists:games,id',
            'is_active' => 'sometimes|boolean',
        ]);

        $lessonPlan->update($validated);
        $lessonPlan->load(['user:id,name,email']);
        return response()->json(['data' => $lessonPlan, 'message' => 'Plan actualizado']);
    }

    public function destroy(Request $request, LessonPlan $lessonPlan): JsonResponse
    {
        $this->authorizeOwner($request, $lessonPlan);
        $lessonPlan->delete();
        return response()->json(['message' => 'Plan eliminado']);
    }

    private function authorizeOwner(Request $request, LessonPlan $lessonPlan): void
    {
        $user = $request->user();

        if (!$user || (!$user->isAdmin() && $lessonPlan->user_id !== $user->id)) {
            abort(Response::HTTP_FORBIDDEN, 'No puedes modificar un lesson plan creado por otro profesor.');
        }
    }
}