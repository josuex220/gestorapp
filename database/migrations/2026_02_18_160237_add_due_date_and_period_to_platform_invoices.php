<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_invoices', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('status');
            $table->string('period', 20)->nullable()->after('due_date');
        });

        // Preencher registros existentes com valores derivados do created_at
        DB::statement("
            UPDATE `platform_invoices`
            SET `due_date` = DATE(`created_at`),
                `period` = DATE_FORMAT(`created_at`, '%m/%Y')
            WHERE `due_date` IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('platform_invoices', function (Blueprint $table) {
            $table->dropColumn(['due_date', 'period']);
        });
    }
};
