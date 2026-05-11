<?php

namespace App\Events;

use App\Models\GameSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameSessionStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public GameSession $session) {}

    public function broadcastOn(): array
    {
        return [new Channel('classroom.' . $this->session->classroom_id)];
    }

    public function broadcastAs(): string
    {
        return 'session.started';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id'   => $this->session->id,
            'game_id'      => $this->session->game_id,
            'classroom_id' => $this->session->classroom_id,
            'started_at'   => $this->session->started_at,
            'status'       => $this->session->status,
        ];
    }
}
