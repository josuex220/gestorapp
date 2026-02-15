<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mercado_pago_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('access_token')->nullable();
            $table->string('public_key')->nullable();
            $table->boolean('is_connected')->default(false);
            $table->boolean('is_sandbox')->default(true);
            $table->json('accepted_payment_methods')->default(json_encode([
                'credit_card' => true,
                'debit_card' => true,
                'pix' => true,
                'boleto' => true,
            ]));
            $table->json('accepted_brands')->default(json_encode([
                'visa', 'mastercard', 'elo', 'amex', 'hipercard'
            ]));
            $table->integer('max_installments')->default(12);
            $table->string('statement_descriptor', 22)->default('COBGEST MAX');
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mercado_pago_configs');
    }
};
