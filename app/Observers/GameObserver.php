<?php

namespace App\Observers;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

use App\Models\Game;

class GameObserver
{
    /**
     * Se ejecuta ANTES de crear un Game
     */
    public function creating(Game $game): void
    {
        // Asignar user_id si no existe y hay usuario autenticado
        if (!$game->user_id && Auth::check()) {
            $game->user_id = Auth::id();
        }
    }

    /**
     * Se ejecuta DESPUÉS de crear un Game
     */
    public function created(Game $game): void
    {
        // Logging básico
        Log::info("Game created", [
            'id' => $game->id,
            'name' => $game->name,
            'user_id' => $game->user_id
        ]);
    }
}