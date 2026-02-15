<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('token_id')->nullable(); // ID do token Sanctum
            $table->string('device')->nullable(); // "Chrome em Windows"
            $table->string('device_type')->default('desktop'); // desktop, mobile, tablet
            $table->string('browser')->nullable(); // Chrome, Firefox, Safari
            $table->string('platform')->nullable(); // Windows, macOS, Android, iOS
            $table->string('ip_address', 45)->nullable(); // Suporta IPv6
            $table->string('location')->nullable(); // "São Paulo, BR"
            $table->text('user_agent')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamp('last_active_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_current']);
            $table->index(['user_id', 'last_active_at']);
            $table->index('token_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};
