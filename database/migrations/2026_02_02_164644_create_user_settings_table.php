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
        Schema::create('user_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Categorias (JSONB)
            $table->json('categories')->nullable();

            // Lembretes
            $table->boolean('auto_reminders')->default(true);
            $table->json('reminders')->nullable();
            $table->string('reminder_send_time')->default('09:00');

            // Notificações
            $table->json('notification_channels')->nullable();
            $table->json('notification_preferences')->nullable();

            // Aparência
            $table->string('theme')->default('system');
            $table->string('color_scheme')->default('teal');

            $table->timestamps();

            $table->unique('user_id');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
