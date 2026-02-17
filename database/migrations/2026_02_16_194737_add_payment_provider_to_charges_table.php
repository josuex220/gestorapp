<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->string('payment_provider')->nullable()->after('payment_method');
        });


        DB::table('charges')
            ->where('payment_method', 'pix')
            ->whereNull('payment_provider')
            ->update(['payment_provider' => 'pix_manual']);

        DB::table('charges')
            ->where('payment_method', '!=', 'pix')
            ->whereNull('payment_provider')
            ->update(['payment_provider' => 'mercado_pago']);
    }

    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->dropColumn('payment_provider');
        });
    }
};

