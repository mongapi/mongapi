<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('type', 20);           // image, audio, video
            $table->string('mime_type', 100)->nullable(); // image/png, audio/mpeg...
            $table->string('category', 100)->nullable();
            $table->string('file_path', 500);
            $table->string('thumbnail_path', 500)->nullable();
            $table->unsignedBigInteger('file_size')->nullable(); // en bytes
            $table->timestamps();

            $table->index('type');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};