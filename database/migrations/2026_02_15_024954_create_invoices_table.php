<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->uuid('platform_plan_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('pending');
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->string('period');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('platform_plan_id')->references('id')->on('platform_plans')->onDelete('set null');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
