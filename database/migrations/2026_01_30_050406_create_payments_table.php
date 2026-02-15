<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->uuid('client_id');
            $table->uuid('charge_id')->nullable();
            $table->uuid('subscription_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->decimal('fee', 10, 2)->default(0);
            $table->decimal('net_amount', 10, 2);
            $table->enum('payment_method', ['pix', 'boleto', 'credit_card', 'debit_card', 'transfer']);
            $table->enum('status', ['completed', 'pending', 'failed', 'refunded'])->default('pending');
            $table->text('description')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('transaction_id')->nullable(); // ID externo do gateway
            $table->timestamps();

            // Foreign keys
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('charge_id')->references('id')->on('charges')->onDelete('set null');
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->onDelete('set null');

            // Índices
            $table->index(['user_id', 'status']);
            $table->index('client_id');
            $table->index('completed_at');
            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
