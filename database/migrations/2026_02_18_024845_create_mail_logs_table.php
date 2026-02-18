<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela mail_logs — registra todos os e-mails enviados pela plataforma.
 * Populada automaticamente pelo MailService após cada disparo via Mailgun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('to');
            $table->string('subject');
            $table->string('event')->index();           // template/event name (payment_confirmed, welcome, etc.)
            $table->string('status')->default('accepted')->index(); // accepted, delivered, failed, rejected
            $table->string('mailgun_id')->nullable();    // Mailgun message ID
            $table->text('error')->nullable();           // error message if failed
            $table->unsignedBigInteger('user_id')->nullable()->index(); // optional: tenant user who triggered the email
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_logs');
    }
};
