<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('base_price', 10, 2);
            $table->enum('cycle', ['monthly', 'quarterly', 'semiannual', 'annual', 'custom']);
            $table->integer('custom_days')->nullable(); // Para ciclo personalizado
            $table->enum('category', [
                'consultoria',
                'design',
                'desenvolvimento',
                'marketing',
                'suporte',
                'treinamento',
                'outros'
            ])->default('outros');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Índices
            $table->index(['user_id', 'is_active']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
