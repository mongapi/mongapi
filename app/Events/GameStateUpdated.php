<?php

namespace App\Events;

use App\Models\GameSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameStateUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public GameSession $session,
        public string $state
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('session.' . $this->session->id)];
    }

    public function broadcastAs(): string
    {
        return 'session.state';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'state'      => $this->state,
            'game_id' => $this->session->game_id,
            'current_phase_index' => $this->session->current_phase_index,
            'updated_at' => now()->toISOString(),
        ];
    }
}
