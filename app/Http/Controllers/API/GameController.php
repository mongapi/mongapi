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
        $request->validate([
            'name'         => 'required|string|max:255',
            'game_type_id' => 'required|exists:game_types,id',
            'description'  => 'nullable|string',
        ]);

        $game = Game::create($request->all());
        return response()->json(['data' => $game, 'message' => 'Juego creado'], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $game = Game::findOrFail($id);

        $request->validate([
            'name'         => 'sometimes|string|max:255',
            'game_type_id' => 'sometimes|exists:game_types,id',
            'description'  => 'nullable|string',
        ]);

        $game->update($request->all());
        return response()->json(['data' => $game, 'message' => 'Juego actualizado']);
    }

    public function destroy(int $id): JsonResponse
    {
        Game::findOrFail($id)->delete();
        return response()->json(['message' => 'Juego eliminado']);
    }
}
