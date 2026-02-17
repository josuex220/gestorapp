<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0);
            $table->string('interval')->default('monthly'); // monthly, quarterly, yearly
            $table->json('features')->nullable();
            $table->json('privileges')->nullable(); // max_clients, max_charges, etc.
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_plans');
    }
};
