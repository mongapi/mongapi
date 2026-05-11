<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameAction extends Model
{
    use HasFactory;

    public $timestamps = false;  // Solo usa happened_at

    protected $fillable = [
        'game_result_id',
        'action_type',
        'action_data',
        'happened_at',
    ];

    protected $casts = [
        'action_data' => 'array',
        'happened_at' => 'datetime',
    ];

    // Relaciones
    public function gameResult()
    {
        return $this->belongsTo(GameResult::class);
    }
}