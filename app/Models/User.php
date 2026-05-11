<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'locked_until' => 'datetime',
        'password' => 'hashed',
    ];

    // Relaciones
    public function games()
    {
        return $this->hasMany(Game::class);
    }

    public function lessonPlans()
    {
        return $this->hasMany(LessonPlan::class);
    }

    public function gameSessions()
    {
        return $this->hasMany(GameSession::class);
    }

    // Helpers
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isTeacher()
    {
        return $this->role === 'teacher';
    }
}