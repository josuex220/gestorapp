<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->uuid('client_id');
            $table->uuid('plan_id')->nullable();
            $table->string('plan_name');
            $table->string('plan_category')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('cycle')->default('monthly');
            $table->integer('custom_days')->nullable();
            $table->integer('reminder_days')->default(3);
            $table->string('status')->default('active');
            $table->date('start_date');
            $table->date('next_billing_date');
            $table->date('last_payment_date')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('plan_id')->references('id')->on('plans')->onDelete('set null');

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'next_billing_date']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
