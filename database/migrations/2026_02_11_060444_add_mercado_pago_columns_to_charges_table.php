<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->string('mp_preference_id')->nullable()->after('installments');
            $table->string('mp_payment_id')->nullable()->after('mp_preference_id');
            $table->text('mp_init_point')->nullable()->after('mp_payment_id');
            $table->text('mp_sandbox_init_point')->nullable()->after('mp_init_point');
        });
    }

    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->dropColumn([
                'mp_preference_id',
                'mp_payment_id',
                'mp_init_point',
                'mp_sandbox_init_point',
            ]);
        });
    }
};
