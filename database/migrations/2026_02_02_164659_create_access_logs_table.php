<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('action'); // login, logout, password_change, 2fa_enabled, etc.
            $table->string('description')->nullable(); // Descrição legível
            $table->string('device')->nullable();
            $table->string('device_type')->default('desktop');
            $table->string('browser')->nullable();
            $table->string('platform')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('location')->nullable();
            $table->string('status')->default('success'); // success, warning, error
            $table->json('metadata')->nullable(); // Dados adicionais
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'action']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_logs');
    }
};
