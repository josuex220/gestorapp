<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */

    public function up()
    {
        Schema::table('admin_integrations', function (Blueprint $table) {
            // Remove colunas fixas (se existirem)
            $columns = ['key_label', 'key_value', 'secret_label', 'secret_value'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('admin_integrations', $col)) {
                    $table->dropColumn($col);
                }
            }

            // Adiciona coluna JSON para campos dinâmicos
            if (!Schema::hasColumn('admin_integrations', 'fields')) {
                $table->json('fields')->default('[]')->after('description');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
