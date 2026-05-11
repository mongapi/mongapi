<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('game_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_session_id')
                  ->constrained('game_sessions')
                  ->onDelete('cascade');
            $table->foreignId('device_id')
                  ->nullable()
                  ->constrained('devices')
                  ->onDelete('set null');
            $table->integer('player_number')->nullable();
            $table->string('player_name', 100)->nullable();
            $table->integer('score')->default(0);
            $table->integer('correct_answers')->default(0);
            $table->integer('incorrect_answers')->default(0);
            $table->integer('time_seconds')->nullable();
            $table->boolean('completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('game_session_id');
            $table->index('device_id');
            $table->unique(['game_session_id', 'device_id', 'player_number']);
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_results');
    }
};
