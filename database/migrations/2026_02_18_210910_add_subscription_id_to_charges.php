<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona a coluna subscription_id à tabela charges
     * para vincular cobranças automáticas às assinaturas.
     */
    public function up(): void
    {
        // Descobrir o tipo exato da coluna 'id' em subscriptions
        // para garantir compatibilidade da FK no MariaDB
        $idColumn = DB::selectOne(
            "SELECT COLUMN_TYPE, CHARACTER_SET_NAME, COLLATION_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'subscriptions'
               AND COLUMN_NAME = 'id'"
        );

        Schema::table('charges', function (Blueprint $table) use ($idColumn) {
            // Usar char(36) com mesmo charset/collation da PK de subscriptions
            $table->char('subscription_id', 36)
                  ->nullable()
                  ->after('client_id')
                  ->charset($idColumn->CHARACTER_SET_NAME ?? 'utf8mb4')
                  ->collation($idColumn->COLLATION_NAME ?? 'utf8mb4_unicode_ci');

            $table->index('subscription_id');

            $table->foreign('subscription_id')
                  ->references('id')
                  ->on('subscriptions')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->dropForeign(['subscription_id']);
            $table->dropColumn('subscription_id');
        });
    }
};
