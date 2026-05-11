<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\GameSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'game_id'        => 'nullable|exists:games,id',
            'lesson_plan_id' => 'nullable|exists:lesson_plans,id',
            'game_content'   => 'nullable|array',
            'game_mode'      => 'nullable|in:individual,group',
        ]);

        $session = GameSession::create([
            'game_id'        => $request->game_id,
            'lesson_plan_id' => $request->lesson_plan_id,
            'game_content'   => $request->game_content ?? [],
            'game_mode'      => $request->game_mode ?? 'individual',
            'status'         => 'waiting',
        ]);

        return response()->json(['data' => $session], 201);
    }

    public function show(GameSession $session): JsonResponse
    {
        $session->load(['game', 'lessonPlan']);
        return response()->json(['data' => $session]);
    }

    public function start(GameSession $session): JsonResponse
    {
        $session->update(['status' => 'playing', 'started_at' => now()]);
        return response()->json(['data' => $session, 'message' => 'Sesión iniciada']);
    }

    public function pause(GameSession $session): JsonResponse
    {
        $session->update(['status' => 'paused']);
        return response()->json(['data' => $session, 'message' => 'Sesión pausada']);
    }

    public function resume(GameSession $session): JsonResponse
    {
        $session->update(['status' => 'playing']);
        return response()->json(['data' => $session, 'message' => 'Sesión reanudada']);
    }

    public function finish(GameSession $session): JsonResponse
    {
        $session->update(['status' => 'finished', 'ended_at' => now()]);
        return response()->json(['data' => $session, 'message' => 'Sesión finalizada']);
    }
}