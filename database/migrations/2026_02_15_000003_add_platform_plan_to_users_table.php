<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('platform_plan_id')->nullable()->after('email');
            $table->string('status')->default('active')->after('platform_plan_id'); // active, inactive, suspended
            $table->foreign('platform_plan_id')->references('id')->on('platform_plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['platform_plan_id']);
            $table->dropColumn(['platform_plan_id', 'status']);
        });
    }
};
