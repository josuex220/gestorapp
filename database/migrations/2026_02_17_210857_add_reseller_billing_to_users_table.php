<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('reseller_price', 10, 2)->nullable()->after('reseller_id');
            $table->timestamp('reseller_expires_at')->nullable()->after('reseller_price');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['reseller_price', 'reseller_expires_at']);
        });
    }
};
