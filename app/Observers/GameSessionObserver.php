<?php

namespace App\Observers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

use App\Models\GameSession;
use App\Models\Game;

class GameSessionObserver
{
    /**
     * Se ejecuta ANTES de crear un GameSession
     */
    public function creating(GameSession $session): void
    {
        // Asignar user_id si no existe y hay usuario autenticado
        if (!$session->user_id && Auth::check()) {
            $session->user_id = Auth::id();
        }

        if (!$session->pin) {
            do {
                $pin = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            } while (
                GameSession::where('pin', $pin)
                ->whereIn('status', ['waiting', 'playing'])
                ->exists()
            );

            $session->pin = $pin;
            $session->pin_expires_at = now()->addHours(3);
        }
    }

    /**
     * Se ejecuta DESPUÉS de crear un GameSession
     */
    public function created(GameSession $session): void
    {
        // Incrementar times_played del juego
        if ($session->game_id) {
            Game::where('id', $session->game_id)->increment('times_played');
        }

        // Logging
        Log::info("GameSession created", [
            'id' => $session->id,
            'game_id' => $session->game_id,
            'user_id' => $session->user_id
        ]);
    }

    /**
     * Se ejecuta ANTES de eliminar un GameSession
     */
    public function deleting(GameSession $session): void
    {
        // Eliminar resultados relacionados
        $session->gameResults()->delete();
    }
}
