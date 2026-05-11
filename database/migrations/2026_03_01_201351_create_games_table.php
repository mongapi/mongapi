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
        Schema::create('games', function (Blueprint $table) {
            $table->id();

            // Foreign Keys
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            $table->foreignId('game_type_id')
                ->constrained('game_types')
                ->onDelete('restrict');

            $table->string('name');
            $table->text('description')->nullable();
            $table->jsonb('game_content');

            // Estado
            $table->boolean('is_active')->default(true);
            $table->integer('times_played')->default(0);

            $table->timestamps();

            // Índices
            $table->index('user_id');
            $table->index('game_type_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
