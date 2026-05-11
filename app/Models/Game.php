<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        // 'user_id',  ← NO - Observer lo asigna
        'game_type_id',
        'name',
        'description',
        'game_content',
        'is_active',
    ];

    protected $casts = [
        'game_content' => 'array',
        'is_active' => 'boolean',
    ];

    // Relaciones
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function gameType()
    {
        return $this->belongsTo(GameType::class);
    }

    public function gameSessions()
    {
        return $this->hasMany(GameSession::class);
    }
}