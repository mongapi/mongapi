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
        Schema::create('lesson_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            $table->string('name');
            $table->text('description')->nullable();
            $table->jsonb('game_ids');

            $table->boolean('is_active')->default(true);
            $table->integer('times_used')->default(0);
            $table->timestamps();

            $table->index('user_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_plans');
    }
};
