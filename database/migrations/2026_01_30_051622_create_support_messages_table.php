<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ticket_id');
            $table->enum('sender_type', ['user', 'support'])->default('user');
            $table->foreignId('sender_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('content');
            $table->json('attachments')->nullable(); // URLs de arquivos anexados
            $table->boolean('is_internal_note')->default(false); // Notas internas do suporte
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('ticket_id')->references('id')->on('support_tickets')->onDelete('cascade');

            // Índices
            $table->index('ticket_id');
            $table->index('sender_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
    }
};
