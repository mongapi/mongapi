<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GameController extends Controller
{
    public function index(): JsonResponse
    {
        $games = Game::with(['gameType', 'user:id,name,email'])->get();
        return response()->json(['data' => $games]);
    }

    public function show(int $id): JsonResponse
    {
        $game = Game::with(['gameType', 'user:id,name,email'])->findOrFail($id);
        return response()->json(['data' => $game]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'game_type_id' => 'required|exists:game_types,id',
            'description'  => 'nullable|string',
            'game_content' => 'required|array',
            'is_active'    => 'sometimes|boolean',
        ]);

        $game = Game::create($validated)->load(['gameType', 'user:id,name,email']);
        return response()->json(['data' => $game, 'message' => 'Juego creado'], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $game = Game::findOrFail($id);
        $this->authorizeOwner($request, $game);

        $validated = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'game_type_id' => 'sometimes|exists:game_types,id',
            'description'  => 'nullable|string',
            'game_content' => 'sometimes|array',
            'is_active'    => 'sometimes|boolean',
        ]);

        $game->update($validated);
        $game->load(['gameType', 'user:id,name,email']);
        return response()->json(['data' => $game, 'message' => 'Juego actualizado']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $game = Game::findOrFail($id);
        $this->authorizeOwner($request, $game);
        $game->delete();
        return response()->json(['message' => 'Juego eliminado']);
    }

    private function authorizeOwner(Request $request, Game $game): void
    {
        $user = $request->user();

        if (!$user || (!$user->isAdmin() && $game->user_id !== $user->id)) {
            abort(Response::HTTP_FORBIDDEN, 'No puedes modificar un juego creado por otro profesor.');
        }
    }
}
