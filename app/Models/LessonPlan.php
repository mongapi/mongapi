<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LessonPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        // 'user_id',  ← NO - Observer lo asigna
        'name',
        'description',
        'game_ids',
        'is_active',
    ];

    protected $casts = [
        'game_ids' => 'array',
        'is_active' => 'boolean',
    ];

    // Relaciones
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function gameSessions()
    {
        return $this->hasMany(GameSession::class);
    }
}