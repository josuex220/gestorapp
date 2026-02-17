<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->string('stripe_invoice_id')->nullable()->unique();
            $table->string('invoice_number')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('status')->default('pending'); // pending, paid, overdue, void
            $table->string('currency', 3)->default('brl');
            $table->text('description')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'status']);
            $table->index('stripe_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_invoices');
    }
};
