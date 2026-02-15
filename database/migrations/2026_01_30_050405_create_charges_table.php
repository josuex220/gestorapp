<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->uuid('client_id');
            $table->decimal('amount', 10, 2);
            $table->date('due_date');
            $table->enum('payment_method', ['pix', 'boleto', 'credit_card']);
            $table->enum('status', ['pending', 'paid', 'overdue', 'cancelled'])->default('pending');
            $table->text('description')->nullable();
            $table->json('notification_channels')->default('["email"]'); // whatsapp, email, telegram
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('last_notification_at')->nullable();
            $table->integer('notification_count')->default(0);
            $table->uuid('saved_card_id')->nullable();
            $table->tinyInteger('installments')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('saved_card_id')->references('id')->on('saved_cards')->onDelete('set null');

            // Índices
            $table->index(['user_id', 'status']);
            $table->index('due_date');
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charges');
    }
};
