<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Descobrir charset/collation da PK de plans para compatibilidade FK
        $idColumn = DB::selectOne(
            "SELECT CHARACTER_SET_NAME, COLLATION_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'plans'
               AND COLUMN_NAME = 'id'"
        );

        Schema::table('payments', function (Blueprint $table) use ($idColumn) {
            $table->char('plan_id', 36)
                  ->nullable()
                  ->after('subscription_id')
                  ->charset($idColumn->CHARACTER_SET_NAME ?? 'utf8mb4')
                  ->collation($idColumn->COLLATION_NAME ?? 'utf8mb4_unicode_ci');

            $table->index('plan_id');

            $table->foreign('plan_id')
                  ->references('id')
                  ->on('plans')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropIndex(['plan_id']);
            $table->dropColumn('plan_id');
        });
    }
};
