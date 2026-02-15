<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->enum('brand', ['visa', 'mastercard', 'elo', 'amex', 'hipercard']);
            $table->string('last_four_digits', 4);
            $table->string('holder_name');
            $table->tinyInteger('expiry_month');
            $table->smallInteger('expiry_year');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            // Foreign key
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');

            // Índice
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_cards');
    }
};
