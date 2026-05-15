<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SessionPresenceUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $sessionId,
        public array $participants,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('session.' . $this->sessionId)];
    }

    public function broadcastAs(): string
    {
        return 'session.presence';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->sessionId,
            'participants' => array_values($this->participants),
            'participants_count' => count($this->participants),
            'updated_at' => now()->toISOString(),
        ];
    }
}
