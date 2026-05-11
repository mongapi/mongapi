<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\GameType;
use Illuminate\Http\JsonResponse;

class GameTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $types = GameType::where('is_active', true)->get();
        return response()->json(['data' => $types]);
    }

    public function show(GameType $gameType): JsonResponse
    {
        return response()->json(['data' => $gameType]);
    }
}