<?php

namespace App\Http\Controllers\API;

use App\Events\GameSessionStarted;
use App\Events\GameStateUpdated;
use App\Http\Controllers\Controller;
use App\Models\GameSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'game_id'      => 'required|exists:games,id',
            'classroom_id' => 'required|integer',
            'game_content' => 'nullable|array',
        ]);

        $session = GameSession::create([
            'game_id'      => $request->game_id,
            'classroom_id' => $request->classroom_id ?? 1,
            'user_id'      => auth()->id(),
            'status'       => 'waiting',
            'game_content' => $request->game_content ?? [],
        ]);

        return response()->json(['data' => $session], 201);
    }

    public function show(int $id): JsonResponse
    {
        $session = GameSession::with(['game'])->findOrFail($id);
        return response()->json(['data' => $session]);
    }

    public function start(int $id): JsonResponse
    {
        $session = GameSession::findOrFail($id);
        $session->update(['status' => 'playing', 'started_at' => now()]);
        broadcast(new GameSessionStarted($session))->toOthers();
        return response()->json(['data' => $session, 'message' => 'Sesión iniciada']);
    }

    public function pause(int $id): JsonResponse
    {
        $session = GameSession::findOrFail($id);
        $session->update(['status' => 'paused']);
        broadcast(new GameStateUpdated($session, 'paused'))->toOthers();
        return response()->json(['data' => $session, 'message' => 'Sesión pausada']);
    }

    public function resume(int $id): JsonResponse
    {
        $session = GameSession::findOrFail($id);
        $session->update(['status' => 'playing']);
        broadcast(new GameStateUpdated($session, 'playing'))->toOthers();
        return response()->json(['data' => $session, 'message' => 'Sesión reanudada']);
    }

    public function finish(int $id): JsonResponse
    {
        $session = GameSession::findOrFail($id);
        $session->update(['status' => 'finished', 'ended_at' => now()]);
        broadcast(new GameStateUpdated($session, 'finished'))->toOthers();
        return response()->json(['data' => $session, 'message' => 'Sesión finalizada']);
    }
}
