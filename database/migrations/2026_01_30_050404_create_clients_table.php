<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Dono do cliente
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('document')->nullable(); // CPF/CNPJ
            $table->string('company')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->json('tags')->default('[]');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Índices
            $table->index(['user_id', 'is_active']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
