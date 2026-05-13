<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_plan_id',
        // 'user_id',  ← NO - Observer lo asigna
        'game_id',
        'current_phase_index',
        'game_content',
        'pin',
        'pin_expires_at',
        'game_mode',
        'status',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'current_phase_index' => 'integer',
        'game_content' => 'array',
        'pin_expires_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    // Relaciones
    public function lessonPlan()
    {
        return $this->belongsTo(LessonPlan::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    public function gameResults()
    {
        return $this->hasMany(GameResult::class);
    }
}