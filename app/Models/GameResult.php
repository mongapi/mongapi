<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_session_id',
        'participant_key',
        'device_id',
        'player_number',
        'player_name',
        'score',
        'correct_answers',
        'incorrect_answers',
        'time_seconds',
        'completed',
        'completed_at',
    ];

    protected $casts = [
        'completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    // Relaciones
    public function gameSession()
    {
        return $this->belongsTo(GameSession::class);
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function gameActions()
    {
        return $this->hasMany(GameAction::class);
    }
}