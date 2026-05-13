<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerAnswered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int    $sessionId,
        public string $deviceId,
        public int    $questionId,
        public mixed  $answer,
        public bool   $isCorrect,
        public int    $score
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('session.' . $this->sessionId)];
    }

    public function broadcastAs(): string
    {
        return 'player.answered';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id'  => $this->sessionId,
            'device_id'   => $this->deviceId,
            'question_id' => $this->questionId,
            'answer'      => $this->answer,
            'is_correct'  => $this->isCorrect,
            'score'       => $this->score,
            'answered_at' => now()->toISOString(),
        ];
    }
}
