<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_tracks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->string('category')->nullable();
            $table->enum('level', ['iniciante', 'intermediario', 'avancado'])->default('iniciante');
            $table->boolean('is_published')->default(false);
            $table->integer('estimated_duration_minutes')->nullable();
            $table->json('tags')->nullable();
            $table->json('prerequisites')->nullable(); // array of track UUIDs
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        Schema::create('learning_lessons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('track_id')->constrained('learning_tracks')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('video_url');
            $table->string('thumbnail_url')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->boolean('is_published')->default(true);
            $table->integer('order')->default(0);
            $table->json('attachments')->nullable(); // [{name, url, type}]
            $table->json('quiz')->nullable();         // [{question, options[], correct_index}]
            $table->timestamps();

            $table->index('track_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_lessons');
        Schema::dropIfExists('learning_tracks');
    }
};
