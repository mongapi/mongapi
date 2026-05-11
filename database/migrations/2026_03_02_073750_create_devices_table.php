<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('game_session_id')
                ->nullable()
                ->constrained('game_sessions')
                ->onDelete('set null');

            $table->string('device_name', 100);
            $table->string('device_type', 20); // tablet, mobile, desktop
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('game_session_id');
            $table->index('device_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};