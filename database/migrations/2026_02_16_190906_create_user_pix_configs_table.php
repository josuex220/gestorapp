<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_pix_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->string('key_type'); // cpf, cnpj, email, phone, random
            $table->string('key_value');
            $table->string('holder_name');
            $table->boolean('require_proof')->default(false);
            $table->boolean('proof_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique('user_id'); // one PIX config per user
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_pix_configs');
    }
};
