<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function index(): JsonResponse
    {
        $games = Game::with(['gameType'])->get();
        return response()->json(['data' => $games]);
    }

    public function show(int $id): JsonResponse
    {
        $game = Game::with(['gameType'])->findOrFail($id);
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

        $game = Game::create($validated);
        return response()->json(['data' => $game, 'message' => 'Juego creado'], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $game = Game::findOrFail($id);

        $validated = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'game_type_id' => 'sometimes|exists:game_types,id',
            'description'  => 'nullable|string',
            'game_content' => 'sometimes|array',
            'is_active'    => 'sometimes|boolean',
        ]);

        $game->update($validated);
        return response()->json(['data' => $game, 'message' => 'Juego actualizado']);
    }

    public function destroy(int $id): JsonResponse
    {
        Game::findOrFail($id)->delete();
        return response()->json(['message' => 'Juego eliminado']);
    }
}
