<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role', 20)->default('teacher');  // 'admin', 'teacher'
            
            // Seguridad
            $table->integer('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('last_login_at')->nullable();
            
            $table->rememberToken();
            $table->timestamps();
            
            // Índices
            $table->index('email');
            $table->index('role');
            $table->index('locked_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};