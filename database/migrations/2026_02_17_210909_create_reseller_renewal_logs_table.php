<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reseller_renewal_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('renewed_by');
            $table->integer('days');
            $table->timestamp('old_expires_at')->nullable();
            $table->timestamp('new_expires_at');
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('renewed_by')->references('id')->on('users')->onDelete('cascade');
            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_renewal_logs');
    }
};
