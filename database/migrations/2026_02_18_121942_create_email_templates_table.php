<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->char('id', 36)->primary(); // UUID
            $table->string('slug')->unique();  // ex: charge_created, payment_confirmed
            $table->string('name');            // Nome amigável
            $table->string('subject');         // Assunto padrão do e-mail
            $table->longText('html_body');     // HTML do template
            $table->json('variables')->nullable(); // Lista de variáveis disponíveis [{key, description}]
            $table->string('category')->default('geral'); // cobranca, pagamento, assinatura, sistema
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
